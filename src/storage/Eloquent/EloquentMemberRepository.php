<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\Member;
use BetterAuth\Core\Interfaces\MemberRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\MemberModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of MemberRepositoryInterface.
 */
class EloquentMemberRepository implements MemberRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = MemberModel::class
    ) {
    }

    public function findById(string $id): ?Member
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByUserAndOrganization(string $userId, string $organizationId): ?Member
    {
        $model = $this->getModel()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByOrganization(string $organizationId): array
    {
        $models = $this->getModel()
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'asc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function findByUser(string $userId): array
    {
        $models = $this->getModel()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function create(array $data): Member
    {
        // Map camelCase to snake_case for Eloquent
        $mappedData = [
            'id' => $data['id'],
            'organization_id' => $data['organizationId'] ?? $data['organization_id'],
            'user_id' => $data['userId'] ?? $data['user_id'],
            'role' => $data['role'] ?? 'member',
            'created_at' => $data['created_at'] ?? now(),
        ];

        $model = $this->getModel()->create($mappedData);

        return $this->modelToEntity($model);
    }

    public function updateRole(string $id, string $role): Member
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update(['role' => $role]);

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
     * Convert Eloquent model to Member entity.
     *
     * @param Model $model
     * @return Member
     */
    private function modelToEntity(Model $model): Member
    {
        return Member::fromArray($model->toArray());
    }
}
