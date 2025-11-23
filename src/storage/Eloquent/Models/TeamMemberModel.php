<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for team_members table.
 *
 * @property string $id
 * @property string $team_id
 * @property string $member_id
 * @property string|null $role
 * @property \DateTimeInterface $created_at
 */
class TeamMemberModel extends Model
{
    protected $table = 'team_members';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'team_id',
        'member_id',
        'role',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the team that the member belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamModel::class, 'team_id');
    }

    /**
     * Get the member.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }
}
