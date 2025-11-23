<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\TeamMember;
use BetterAuth\Core\Interfaces\TeamMemberRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\TeamMemberModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of TeamMemberRepositoryInterface.
 */
class EloquentTeamMemberRepository implements TeamMemberRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = TeamMemberModel::class
    ) {
    }

    public function findById(string $id): ?TeamMember
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByMemberAndTeam(string $memberId, string $teamId): ?TeamMember
    {
        $model = $this->getModel()
            ->where('member_id', $memberId)
            ->where('team_id', $teamId)
            ->first();

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByTeam(string $teamId): array
    {
        $models = $this->getModel()
            ->where('team_id', $teamId)
            ->orderBy('created_at', 'asc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function findByMember(string $memberId): array
    {
        $models = $this->getModel()
            ->where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function create(array $data): TeamMember
    {
        // Map camelCase to snake_case for Eloquent
        $mappedData = [
            'id' => $data['id'],
            'team_id' => $data['teamId'] ?? $data['team_id'],
            'member_id' => $data['memberId'] ?? $data['member_id'],
            'role' => $data['role'] ?? null,
            'created_at' => $data['created_at'] ?? now(),
        ];

        $model = $this->getModel()->create($mappedData);

        return $this->modelToEntity($model);
    }

    public function updateRole(string $id, ?string $role): TeamMember
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update(['role' => $role]);

        return $this->modelToEntity($model->fresh());
    }

    public function delete(string $id): bool
    {
        return $this->getModel()->where('id', $id)->delete() > 0;
    }

    public function deleteByTeam(string $teamId): int
    {
        return $this->getModel()->where('team_id', $teamId)->delete();
    }

    public function deleteByMember(string $memberId): int
    {
        return $this->getModel()->where('member_id', $memberId)->delete();
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
     * Convert Eloquent model to TeamMember entity.
     *
     * @param Model $model
     * @return TeamMember
     */
    private function modelToEntity(Model $model): TeamMember
    {
        return TeamMember::fromArray($model->toArray());
    }
}
