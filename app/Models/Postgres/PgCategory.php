<?php

namespace App\Models\Postgres;

use Illuminate\Database\Eloquent\Model;

class PgCategory extends Model
{
    protected $connection = 'pgsql'; // PostgreSQL connection
    protected $table = 'Category';        // PostgreSQL table name

    protected $fillable = [
        'name',
        'description'
    ];
}
