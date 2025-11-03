<?php

namespace App\Models\Postgres;

use Illuminate\Database\Eloquent\Model;

class PgProduct extends Model
{
    protected $connection = 'pgsql'; // PostgreSQL connection
    protected $table = 'Product';        // PostgreSQL table name

    protected $fillable = [
        'name',
        'description'
    ];
}
