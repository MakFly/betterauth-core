<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Interfaces\TotpStorageInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of TotpStorageInterface.
 * Stores TOTP data as JSON in the metadata column.
 * Returns arrays instead of entities.
 */
class EloquentTotpStorage implements TotpStorageInterface
{
    public function __construct(
        private readonly string $modelClass
    ) {
    }

    public function store(string $userId, array $totpData): void
    {
        $this->getModel()->create([
            'user_id' => $userId,
            'enabled' => $totpData['enabled'] ?? false,
            'metadata' => $totpData,
        ]);
    }

    public function findByUserId(string $userId): ?array
    {
        $model = $this->getModel()
            ->where('user_id', $userId)
            ->first();

        return $model ? $this->modelToArray($model) : null;
    }

    public function isEnabled(string $userId): bool
    {
        $model = $this->getModel()
            ->where('user_id', $userId)
            ->first();

        return $model?->enabled ?? false;
    }

    public function enable(string $userId): bool
    {
        $model = $this->getModel()
            ->where('user_id', $userId)
            ->first();

        if (!$model) {
            return false;
        }

        return $model->update([
            'enabled' => true,
        ]);
    }

    public function disable(string $userId): bool
    {
        $model = $this->getModel()
            ->where('user_id', $userId)
            ->first();

        if (!$model) {
            return false;
        }

        return $model->update([
            'enabled' => false,
        ]);
    }

    public function delete(string $userId): bool
    {
        return $this->getModel()
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function useBackupCode(string $userId, string $backupCode): bool
    {
        $model = $this->getModel()
            ->where('user_id', $userId)
            ->first();

        if (!$model) {
            return false;
        }

        $metadata = $model->metadata ?? [];
        $backupCodes = $metadata['backup_codes'] ?? [];

        if (!in_array($backupCode, $backupCodes, true)) {
            return false;
        }

        // Remove used backup code
        $backupCodes = array_filter(
            $backupCodes,
            fn (string $code) => $code !== $backupCode
        );

        $metadata['backup_codes'] = array_values($backupCodes);

        return $model->update([
            'metadata' => $metadata,
        ]);
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
