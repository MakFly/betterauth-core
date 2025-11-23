<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\EmailVerificationToken;
use BetterAuth\Core\Interfaces\EmailVerificationStorageInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of EmailVerificationStorageInterface.
 * Requires an EmailVerificationToken model that extends Illuminate\Database\Eloquent\Model.
 */
class EloquentEmailVerificationStorage implements EmailVerificationStorageInterface
{
    public function __construct(
        private readonly string $modelClass
    ) {
    }

    public function store(string $token, string $email, int $expiresIn): void
    {
        $this->getModel()->create([
            'token' => $token,
            'email' => $email,
            'expires_at' => now()->addSeconds($expiresIn),
            'used_at' => null,
        ]);
    }

    public function findByToken(string $token): ?EmailVerificationToken
    {
        $model = $this->getModel()->where('token', $token)->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function markAsUsed(string $token): bool
    {
        $model = $this->getModel()->where('token', $token)->first();

        if (!$model) {
            return false;
        }

        return $model->update([
            'used_at' => now(),
        ]);
    }

    public function delete(string $token): bool
    {
        return $this->getModel()->where('token', $token)->delete() > 0;
    }

    public function deleteByEmail(string $email): int
    {
        return $this->getModel()->where('email', $email)->delete();
    }

    public function deleteExpired(): int
    {
        return $this->getModel()->where('expires_at', '<', now())->delete();
    }

    private function getModel(): Model
    {
        return new $this->modelClass();
    }

    private function modelToEntity(Model $model): EmailVerificationToken
    {
        return EmailVerificationToken::fromArray($model->toArray());
    }
}
