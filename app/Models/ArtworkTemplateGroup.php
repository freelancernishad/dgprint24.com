<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtworkTemplateGroup extends Model
{
    protected $fillable = [
        'category_id',
        'group_label',
        'group_value',
        'options',
        'sides_count',
        'active',
    ];

    protected $casts = [
        'options' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get the category that owns the group.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the templates for this group.
     */
    public function templates()
    {
        return $this->hasMany(ArtworkTemplate::class);
    }
}
