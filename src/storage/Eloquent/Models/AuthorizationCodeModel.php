<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for authorization codes.
 *
 * @property string $code
 * @property string $client_id
 * @property string $user_id
 * @property string $redirect_uri
 * @property array $scopes
 * @property string $expires_at
 * @property bool $used
 * @property string|null $code_challenge
 * @property string|null $code_challenge_method
 * @property string $created_at
 */
class AuthorizationCodeModel extends Model
{
    /** @var string */
    protected $table = 'authorization_codes';

    /** @var string */
    protected $primaryKey = 'code';

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'client_id',
        'user_id',
        'redirect_uri',
        'scopes',
        'expires_at',
        'used',
        'code_challenge',
        'code_challenge_method',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'scopes' => 'array',
        'used' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship with User model.
     *
     * @return BelongsTo<Model, AuthorizationCodeModel>
     */
    public function user(): BelongsTo
    {
        $userModel = '\App\Models\User';
        if (function_exists('config')) {
            $userModel = config('betterauth.user_model', 'App\Models\User');
        }

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Relationship with OAuth Client model.
     *
     * @return BelongsTo<OAuthClientModel, AuthorizationCodeModel>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClientModel::class, 'client_id');
    }
}
