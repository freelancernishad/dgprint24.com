<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtworkTemplate extends Model
{
    protected $fillable = [
        'artwork_template_group_id',
        'side',
        'label',
        'options',
        'files',
        'active',
    ];

    protected $casts = [
        'options' => 'array',
        'files' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get the group that owns the template.
     */
    public function group()
    {
        return $this->belongsTo(ArtworkTemplateGroup::class, 'artwork_template_group_id');
    }
}
