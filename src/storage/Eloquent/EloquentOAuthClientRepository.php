<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\OAuthClient;
use BetterAuth\Core\Interfaces\OAuthClientRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\OAuthClientModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of OAuthClientRepositoryInterface.
 */
class EloquentOAuthClientRepository implements OAuthClientRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = OAuthClientModel::class
    ) {
    }

    public function findById(string $clientId): ?OAuthClient
    {
        $model = $this->getModel()->find($clientId);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function create(array $data): OAuthClient
    {
        $model = $this->getModel()->create($data);

        return $this->modelToEntity($model);
    }

    public function update(string $clientId, array $data): OAuthClient
    {
        $model = $this->getModel()->findOrFail($clientId);
        $model->update($data);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $clientId): bool
    {
        return $this->getModel()->where('id', $clientId)->delete() > 0;
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
     * Convert Eloquent model to OAuthClient entity.
     *
     * @param Model $model
     * @return OAuthClient
     */
    private function modelToEntity(Model $model): OAuthClient
    {
        return OAuthClient::fromArray($model->toArray());
    }
}
