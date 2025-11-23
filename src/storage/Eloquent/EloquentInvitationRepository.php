<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Entities\Invitation;
use BetterAuth\Core\Interfaces\InvitationRepositoryInterface;
use BetterAuth\Storage\Eloquent\Models\InvitationModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of InvitationRepositoryInterface.
 */
class EloquentInvitationRepository implements InvitationRepositoryInterface
{
    public function __construct(
        private readonly string $modelClass = InvitationModel::class
    ) {
    }

    public function findById(string $id): ?Invitation
    {
        $model = $this->getModel()->find($id);

        return $model ? $this->modelToEntity($model) : null;
    }

    public function findByEmailAndOrganization(string $email, string $organizationId): ?Invitation
    {
        $model = $this->getModel()
            ->where('email', $email)
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
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

    public function findPendingByEmail(string $email): array
    {
        $models = $this->getModel()
            ->where('email', $email)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->modelToEntity($model))->all();
    }

    public function create(array $data): Invitation
    {
        // Map camelCase to snake_case for Eloquent
        $mappedData = [
            'id' => $data['id'],
            'organization_id' => $data['organizationId'] ?? $data['organization_id'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'member',
            'status' => $data['status'] ?? 'pending',
            'invited_by' => $data['invitedBy'] ?? $data['invited_by'] ?? null,
            'expires_at' => $data['expiresAt'] ?? $data['expires_at'] ?? null,
            'created_at' => $data['created_at'] ?? now(),
        ];

        $model = $this->getModel()->create($mappedData);

        return $this->modelToEntity($model);
    }

    public function updateStatus(string $id, string $status): Invitation
    {
        $model = $this->getModel()->findOrFail($id);
        $model->update(['status' => $status]);

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

    public function deleteExpired(): int
    {
        return $this->getModel()
            ->whereNotNull('expires_at')
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
     * Convert Eloquent model to Invitation entity.
     *
     * @param Model $model
     * @return Invitation
     */
    private function modelToEntity(Model $model): Invitation
    {
        return Invitation::fromArray($model->toArray());
    }
}
