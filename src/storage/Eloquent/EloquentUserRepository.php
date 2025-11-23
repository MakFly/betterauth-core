<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of UserRepositoryInterface.
 * Requires a User model that extends Illuminate\Database\Eloquent\Model.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass
    ) {
    }

    public function findById(string $id): ?User
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $model = $this->getModel()->where('email', $email)->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByProvider(string $provider, string $providerId): ?User
    {
        $model = $this->getModel()
            ->whereRaw("JSON_EXTRACT(metadata, '$.oauth_providers.{$provider}.provider_id') = ?", [$providerId])
            ->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function create(array $data): User
    {
        $model = $this->getModel()->create($data);

        return $this->modelToEntity($model);
    }

    public function update(string $id, array $data): User
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update($data);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $id): bool
    {
        return $this->getModel()->destroy($id) > 0;
    }

    public function verifyEmail(string $id): bool
    {
        $model = $this->getModel()->findOrFail($id);

        return $model->update([
            'email_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function generateId(): ?string
    {
        $model = $this->getModel();

        // Check if model uses auto-incrementing integer IDs
        if ($model->getKeyType() === 'int' && $model->getIncrementing()) {
            // Let database auto-generate INT IDs
            return null;
        }

        // Generate UUID v7 for string-based IDs (time-ordered, better for database performance)
        return $this->generateUuidV7();
    }

    /**
     * Generate a UUID v7 (time-ordered).
     *
     * Uses Laravel's Str::orderedUuid() if available (Laravel 9.30+),
     * or ramsey/uuid v7 directly, or falls back to manual generation.
     */
    private function generateUuidV7(): string
    {
        // Use Laravel's Str::orderedUuid() if available (preferred - Laravel 9.30+)
        if (class_exists(\Illuminate\Support\Str::class) && method_exists(\Illuminate\Support\Str::class, 'orderedUuid')) {
            return (string) \Illuminate\Support\Str::orderedUuid();
        }

        // Use ramsey/uuid v7 if available (ramsey/uuid 4.6+)
        if (class_exists(\Ramsey\Uuid\Uuid::class) && method_exists(\Ramsey\Uuid\Uuid::class, 'uuid7')) {
            return (string) \Ramsey\Uuid\Uuid::uuid7();
        }

        // Fallback: Manual UUID v7 generation (time-ordered)
        // UUID v7: timestamp (48 bits) + random (74 bits)
        $timestamp = (int) (microtime(true) * 1000); // milliseconds since epoch

        $data = pack('J', $timestamp << 16); // 48-bit timestamp in first 6 bytes
        $data = substr($data, 0, 6) . random_bytes(10); // Add 10 random bytes

        // Set version (7) and variant bits
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x70); // Version 7
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Variant 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get a new model instance.
     *
     * @return Model
     */
    private function getModel(): Model
    {
        return new $this->modelClass();
    }

    /**
     * Convert Eloquent model to User entity.
     *
     * @param Model $model
     * @return User
     */
    private function modelToEntity(Model $model): User
    {
        return User::fromArray($model->toArray());
    }
}
