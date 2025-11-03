<?php

namespace App\Models\Postgres;

use Illuminate\Database\Eloquent\Model;

class PgTax extends Model
{
    protected $connection = 'pgsql'; // PostgreSQL connection
    protected $table = 'Tax';        // PostgreSQL table name

    protected $fillable = [
        'name',
        'description'
    ];
}
