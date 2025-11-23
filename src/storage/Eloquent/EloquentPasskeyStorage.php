<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Interfaces\PasskeyStorageInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of PasskeyStorageInterface.
 * Stores passkey data as JSON in the metadata column.
 * Returns arrays instead of entities.
 */
class EloquentPasskeyStorage implements PasskeyStorageInterface
{
    public function __construct(
        private readonly string $modelClass
    ) {
    }

    public function store(array $passkey): void
    {
        $this->getModel()->create([
            'credential_id' => $passkey['credential_id'] ?? null,
            'user_id' => $passkey['user_id'] ?? null,
            'metadata' => $passkey,
        ]);
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        $model = $this->getModel()
            ->where('credential_id', $credentialId)
            ->first();

        return $model ? $this->modelToArray($model) : null;
    }

    public function findByUserId(string $userId): array
    {
        return $this->getModel()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Model $model) => $this->modelToArray($model))
            ->toArray();
    }

    public function update(string $credentialId, array $data): bool
    {
        $model = $this->getModel()
            ->where('credential_id', $credentialId)
            ->first();

        if (!$model) {
            return false;
        }

        $metadata = $model->metadata ?? [];
        $metadata = array_merge($metadata, $data);

        return $model->update([
            'metadata' => $metadata,
        ]);
    }

    public function delete(string $credentialId): bool
    {
        return $this->getModel()
            ->where('credential_id', $credentialId)
            ->delete() > 0;
    }

    private function getModel(): Model
    {
        return new $this->modelClass();
    }

    private function modelToArray(Model $model): array
    {
        return $model->toArray();
    }
}
