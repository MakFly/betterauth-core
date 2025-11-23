<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\SuspiciousActivity;
use BetterAuth\Core\Interfaces\SuspiciousActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of SuspiciousActivityRepositoryInterface.
 * Requires a SuspiciousActivity model that extends Illuminate\Database\Eloquent\Model.
 */
class EloquentSuspiciousActivityRepository implements SuspiciousActivityRepositoryInterface
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

    public function create(array $data): SuspiciousActivity
    {
        $model = $this->getModel()->create($data);

        return $this->modelToEntity($model);
    }

    public function findById(string $id): ?SuspiciousActivity
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByUserId(string $userId): array
    {
        return $this->getModel()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Model $model) => $this->modelToEntity($model))
            ->toArray();
    }

    public function findByStatus(string $status): array
    {
        return $this->getModel()
            ->where('status', $status)
            ->get()
            ->map(fn (Model $model) => $this->modelToEntity($model))
            ->toArray();
    }

    public function update(string $id, array $data): SuspiciousActivity
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update($data);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $id): bool
    {
        return $this->getModel()->destroy($id) > 0;
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

    private function modelToEntity(Model $model): SuspiciousActivity
    {
        return SuspiciousActivity::fromArray($model->toArray());
    }
}
