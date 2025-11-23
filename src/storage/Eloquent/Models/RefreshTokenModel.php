<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for refresh tokens.
 *
 * @property string $token
 * @property string $user_id
 * @property string $expires_at
 * @property bool $revoked
 * @property string|null $replaced_by
 * @property string $created_at
 * @property string $updated_at
 */
class RefreshTokenModel extends Model
{
    /** @var string */
    protected $table = 'refresh_tokens';

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
        'expires_at',
        'revoked',
        'replaced_by',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'revoked' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship with User model.
     *
     * @return BelongsTo<Model, RefreshTokenModel>
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
