<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\AuthorizationCode;
use BetterAuth\Core\Interfaces\AuthorizationCodeRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\AuthorizationCodeModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of AuthorizationCodeRepositoryInterface.
 */
class EloquentAuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = AuthorizationCodeModel::class
    ) {
    }

    public function findByCode(string $code): ?AuthorizationCode
    {
        $model = $this->getModel()->find($code);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function create(array $data): AuthorizationCode
    {
        // Map camelCase to snake_case for Eloquent
        $mappedData = [
            'code' => $data['code'],
            'client_id' => $data['clientId'],
            'user_id' => $data['userId'],
            'redirect_uri' => $data['redirectUri'],
            'scopes' => $data['scopes'],
            'expires_at' => $data['expiresAt'],
            'code_challenge' => $data['codeChallenge'] ?? null,
            'code_challenge_method' => $data['codeChallengeMethod'] ?? null,
            'used' => $data['used'] ?? false,
            'created_at' => $data['created_at'] ?? now(),
        ];

        $model = $this->getModel()->create($mappedData);

        return $this->modelToEntity($model);
    }

    public function markAsUsed(string $code): void
    {
        $this->getModel()
            ->where('code', $code)
            ->update(['used' => true]);
    }

    public function deleteExpired(): int
    {
        return $this->getModel()
            ->where('expires_at', '<', now())
            ->delete();
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
     * Convert Eloquent model to AuthorizationCode entity.
     *
     * @param Model $model
     * @return AuthorizationCode
     */
    private function modelToEntity(Model $model): AuthorizationCode
    {
        return AuthorizationCode::fromArray($model->toArray());
    }
}
