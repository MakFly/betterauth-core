<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for organizations table.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property array|null $metadata
 * @property \DateTimeInterface $created_at
 */
class OrganizationModel extends Model
{
    protected $table = 'organizations';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'logo',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get all members of the organization.
     */
    public function members(): HasMany
    {
        return $this->hasMany(MemberModel::class, 'organization_id');
    }

    /**
     * Get all invitations for the organization.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(InvitationModel::class, 'organization_id');
    }

    /**
     * Get all teams in the organization.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(TeamModel::class, 'organization_id');
    }
}
