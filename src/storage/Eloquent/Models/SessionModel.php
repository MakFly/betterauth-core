<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for sessions.
 *
 * @property string $token
 * @property string $user_id
 * @property string $ip_address
 * @property string $user_agent
 * @property string $expires_at
 * @property array|null $metadata
 * @property string|null $active_organization_id
 * @property string|null $active_team_id
 * @property string $created_at
 * @property string $updated_at
 */
class SessionModel extends Model
{
    /** @var string */
    protected $table = 'sessions';

    /** @var string */
    protected $primaryKey = 'token';

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'token',
        'user_id',
        'ip_address',
        'user_agent',
        'expires_at',
        'metadata',
        'active_organization_id',
        'active_team_id',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship with User model.
     *
     * @return BelongsTo<Model, SessionModel>
     */
    public function user(): BelongsTo
    {
        $userModel = '\App\Models\User';
        if (function_exists('config')) {
            $userModel = config('betterauth.user_model', 'App\Models\User');
        }

        return $this->belongsTo($userModel, 'user_id');
    }
}
