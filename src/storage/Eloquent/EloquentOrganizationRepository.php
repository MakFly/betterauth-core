<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\Organization;
use BetterAuth\Core\Interfaces\OrganizationRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\OrganizationModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of OrganizationRepositoryInterface.
 */
class EloquentOrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = OrganizationModel::class
    ) {
    }

    public function findById(string $id): ?Organization
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $model = $this->getModel()->where('slug', $slug)->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByUserId(string $userId): array
    {
        $models = $this->getModel()
            ->join('members', 'organizations.id', '=', 'members.organization_id')
            ->where('members.user_id', $userId)
            ->orderBy('organizations.created_at', 'desc')
            ->get(['organizations.*']);

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function create(array $data): Organization
    {
        $model = $this->getModel()->create($data);

        return $this->modelToEntity($model);
    }

    public function update(string $id, array $data): Organization
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update($data);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $id): bool
    {
        return $this->getModel()->where('id', $id)->delete() > 0;
    }

    public function isSlugAvailable(string $slug, ?string $excludeId = null): bool
    {
        $query = $this->getModel()->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count() === 0;
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
     * Convert Eloquent model to Organization entity.
     *
     * @param Model $model
     * @return Organization
     */
    private function modelToEntity(Model $model): Organization
    {
        return Organization::fromArray($model->toArray());
    }
}
