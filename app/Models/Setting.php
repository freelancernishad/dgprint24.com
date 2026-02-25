<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = is_array($value) || is_object($value) ? json_encode($value) : $value;
    }

    public function getValueAttribute($value)
    {
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }
}

