<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comb extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'game',
        'source',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get a value from the stored Comb manifest.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Comb category.
     */
    public function category(): string
    {
        return (string) $this->get('category', 'application');
    }

    /**
     * Comb group.
     */
    public function group(): string
    {
        return (string) $this->get(
            'group',
            $this->game ?: 'other'
        );
    }

    /**
     * Comb tags.
     */
    public function tags(): array
    {
        $tags = $this->get('tags', []);

        return is_array($tags) ? $tags : [];
    }

    /**
     * Comb capabilities.
     */
    public function capabilities(): array
    {
        $capabilities = $this->get('capabilities', []);

        return is_array($capabilities) ? $capabilities : [];
    }

    /**
     * Determine if this Comb has an install section.
     */
    public function hasInstaller(): bool
    {
        return ! empty($this->get('install'));
    }

    /**
     * Determine if this Comb defines variables.
     */
    public function hasVariables(): bool
    {
        return ! empty(
            $this->get(
                'variables_schema',
                $this->get('variables', [])
            )
        );
    }

    /**
     * Startup command from the Comb definition.
     */
    public function startup(): ?string
    {
        return $this->get('startup');
    }

    /**
     * Docker image from the Comb definition.
     */
    public function image(): ?string
    {
        return $this->get('image');
    }
}