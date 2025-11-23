<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\RefreshTokenModel;
use DateTimeImmutable;

/**
 * Eloquent-based refresh token repository for Laravel.
 */
class EloquentRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function findByToken(string $token): ?RefreshToken
    {
        /** @var RefreshTokenModel|null $model */
        $model = RefreshTokenModel::where('token', $token)->first();

        if (!$model) {
            return null;
        }

        return new RefreshToken(
            token: $model->token,
            userId: $model->user_id,
            expiresAt: new DateTimeImmutable($model->expires_at),
            createdAt: new DateTimeImmutable($model->created_at),
            revoked: (bool) $model->revoked,
            replacedBy: $model->replaced_by
        );
    }

    /**
     * @return RefreshToken[]
     */
    public function findByUserId(string $userId): array
    {
        $models = RefreshTokenModel::where('user_id', $userId)->get();
        $tokens = [];

        foreach ($models as $model) {
            $tokens[] = new RefreshToken(
                token: $model->token,
                userId: $model->user_id,
                expiresAt: new DateTimeImmutable($model->expires_at),
                createdAt: new DateTimeImmutable($model->created_at),
                revoked: (bool) $model->revoked,
                replacedBy: $model->replaced_by
            );
        }

        return $tokens;
    }

    public function create(array $data): RefreshToken
    {
        $model = RefreshTokenModel::create([
            'token' => $data['token'],
            'user_id' => $data['userId'],
            'expires_at' => $data['expiresAt'],
            'revoked' => $data['revoked'] ?? false,
            'replaced_by' => $data['replacedBy'] ?? null,
        ]);

        return new RefreshToken(
            token: $model->token,
            userId: $model->user_id,
            expiresAt: new DateTimeImmutable($model->expires_at),
            createdAt: new DateTimeImmutable($model->created_at),
            revoked: (bool) $model->revoked,
            replacedBy: $model->replaced_by
        );
    }

    public function revoke(string $token, ?string $replacedBy = null): bool
    {
        $updated = RefreshTokenModel::where('token', $token)->update([
            'revoked' => true,
            'replaced_by' => $replacedBy,
        ]);

        return $updated > 0;
    }

    public function revokeAllForUser(string $userId): int
    {
        return RefreshTokenModel::where('user_id', $userId)
            ->where('revoked', false)
            ->update([
                'revoked' => true,
            ]);
    }

    public function deleteExpired(): int
    {
        return RefreshTokenModel::where('expires_at', '<', now())
            ->delete();
    }
}
