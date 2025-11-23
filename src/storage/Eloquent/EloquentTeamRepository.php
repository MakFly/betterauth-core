<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\Team;
use BetterAuth\Core\Interfaces\TeamRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\TeamModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of TeamRepositoryInterface.
 */
class EloquentTeamRepository implements TeamRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = TeamModel::class
    ) {
    }

    public function findById(string $id): ?Team
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findBySlug(string $slug, string $organizationId): ?Team
    {
        $model = $this->getModel()
            ->where('slug', $slug)
            ->where('organization_id', $organizationId)
            ->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByOrganization(string $organizationId): array
    {
        $models = $this->getModel()
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function create(array $data): Team
    {
        // Map camelCase to snake_case for Eloquent
        $mappedData = [
            'id' => $data['id'],
            'organization_id' => $data['organizationId'] ?? $data['organization_id'],
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'created_at' => $data['created_at'] ?? now(),
        ];

        $model = $this->getModel()->create($mappedData);

        return $this->modelToEntity($model);
    }

    public function update(string $id, array $data): Team
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update($data);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $id): bool
    {
        return $this->getModel()->where('id', $id)->delete() > 0;
    }

    public function deleteByOrganization(string $organizationId): int
    {
        return $this->getModel()->where('organization_id', $organizationId)->delete();
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
     * Convert Eloquent model to Team entity.
     *
     * @param Model $model
     * @return Team
     */
    private function modelToEntity(Model $model): Team
    {
        return Team::fromArray($model->toArray());
    }
}
