<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for members table.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $role
 * @property \DateTimeInterface $created_at
 */
class MemberModel extends Model
{
    protected $table = 'members';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'user_id',
        'role',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the organization that the member belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    /**
     * Get all team memberships for this member.
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMemberModel::class, 'member_id');
    }
}
