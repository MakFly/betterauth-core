<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for teams table.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $slug
 * @property array|null $metadata
 * @property \DateTimeInterface $created_at
 */
class TeamModel extends Model
{
    protected $table = 'teams';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'slug',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the organization that the team belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }

    /**
     * Get all team members.
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMemberModel::class, 'team_id');
    }
}
