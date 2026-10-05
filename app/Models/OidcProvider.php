<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OidcProvider extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'enabled',
        'issuer',
        'client_id',
        'client_secret',
        'redirect_url',
        'scopes',
        'allow_registration',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'client_secret' => 'encrypted',
        'scopes' => 'array',
        'allow_registration' => 'boolean',
    ];
}