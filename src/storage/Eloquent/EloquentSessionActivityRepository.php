<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\SessionActivity;
use BetterAuth\Core\Interfaces\SessionActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of SessionActivityRepositoryInterface.
 * Requires a SessionActivity model that extends Illuminate\Database\Eloquent\Model.
 */
class EloquentSessionActivityRepository implements SessionActivityRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass
    ) {
    }

    public function generateId(): ?string
    {
        $model = $this->getModel();

        // Check if model uses auto-incrementing integer IDs
        if ($model->getKeyType() === 'int' && $model->getIncrementing()) {
            return null;
        }

        // Generate UUID v7 for string-based IDs
        return $this->generateUuidV7();
    }

    public function create(array $data): SessionActivity
    {
        $model = $this->getModel()->create($data);

        return $this->modelToEntity($model);
    }

    public function findById(string $id): ?SessionActivity
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findBySessionId(string $sessionId): array
    {
        return $this->getModel()
            ->where('session_id', $sessionId)
            ->get()
            ->map(fn (Model $model) => $this->modelToEntity($model))
            ->toArray();
    }

    public function findByUserId(string $userId): array
    {
        return $this->getModel()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Model $model) => $this->modelToEntity($model))
            ->toArray();
    }

    public function delete(string $id): bool
    {
        return $this->getModel()->destroy($id) > 0;
    }

    public function deleteBySessionId(string $sessionId): int
    {
        return $this->getModel()->where('session_id', $sessionId)->delete();
    }

    /**
     * Generate a UUID v7 (time-ordered).
     */
    private function generateUuidV7(): string
    {
        if (class_exists(\Illuminate\Support\Str::class) && method_exists(\Illuminate\Support\Str::class, 'orderedUuid')) {
            return (string) \Illuminate\Support\Str::orderedUuid();
        }

        if (class_exists(\Ramsey\Uuid\Uuid::class) && method_exists(\Ramsey\Uuid\Uuid::class, 'uuid7')) {
            return (string) \Ramsey\Uuid\Uuid::uuid7();
        }

        $timestamp = (int) (microtime(true) * 1000);
        $data = pack('J', $timestamp << 16);
        $data = substr($data, 0, 6) . random_bytes(10);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x70);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function getModel(): Model
    {
        return new $this->modelClass();
    }

    private function modelToEntity(Model $model): SessionActivity
    {
        return SessionActivity::fromArray($model->toArray());
    }
}
