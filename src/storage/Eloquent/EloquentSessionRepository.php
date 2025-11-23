<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Interfaces\SessionRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\SessionModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of SessionRepositoryInterface.
 */
class EloquentSessionRepository implements SessionRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = SessionModel::class
    ) {
    }

    public function findByToken(string $token): ?Session
    {
        $model = $this->getModel()->find($token);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByUserId(string $userId): array
    {
        $models = $this->getModel()
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function create(array $data): Session
    {
        $model = $this->getModel()->create($data);

        return $this->modelToEntity($model);
    }

    public function update(string $token, array $data): Session
    {
        $model = $this->getModel()->findOrFail($token);
        $model->update($data);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $token): bool
    {
        return $this->getModel()->where('token', $token)->delete() > 0;
    }

    public function deleteExpired(): int
    {
        return $this->getModel()
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function deleteByUserId(string $userId): int
    {
        return $this->getModel()
            ->where('user_id', $userId)
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
     * Convert Eloquent model to Session entity.
     *
     * @param Model $model
     * @return Session
     */
    private function modelToEntity(Model $model): Session
    {
        return Session::fromArray($model->toArray());
    }
}
