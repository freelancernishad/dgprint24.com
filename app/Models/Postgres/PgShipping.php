<?php

namespace App\Models\Postgres;

use Illuminate\Database\Eloquent\Model;

class PgShipping extends Model
{
    protected $connection = 'pgsql'; // PostgreSQL connection
    protected $table = 'Shipping';        // PostgreSQL table name

    protected $fillable = [
        'name',
        'description'
    ];
}
