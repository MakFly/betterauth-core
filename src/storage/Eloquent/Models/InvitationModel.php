<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for invitations table.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property string $role
 * @property string $status
 * @property string|null $invited_by
 * @property \DateTimeInterface|null $expires_at
 * @property \DateTimeInterface $created_at
 */
class InvitationModel extends Model
{
    protected $table = 'invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'email',
        'role',
        'status',
        'invited_by',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the organization that the invitation is for.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'organization_id');
    }
}
