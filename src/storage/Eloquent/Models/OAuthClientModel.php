<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for OAuth clients.
 *
 * @property string $id
 * @property string $name
 * @property string $client_secret
 * @property array $redirect_uris
 * @property array $allowed_scopes
 * @property string $type
 * @property bool $active
 * @property string $created_at
 * @property string|null $updated_at
 */
class OAuthClientModel extends Model
{
    /** @var string */
    protected $table = 'oauth_clients';

    /** @var string */
    protected $primaryKey = 'id';

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'client_secret',
        'redirect_uris',
        'allowed_scopes',
        'type',
        'active',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'redirect_uris' => 'array',
        'allowed_scopes' => 'array',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
}
