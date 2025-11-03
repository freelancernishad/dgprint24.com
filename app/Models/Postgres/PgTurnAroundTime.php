<?php

namespace App\Models\Postgres;

use Illuminate\Database\Eloquent\Model;

class PgTurnAroundTime extends Model
{
    protected $connection = 'pgsql'; // PostgreSQL connection
    protected $table = 'TurnAroundTime';        // PostgreSQL table name

    protected $fillable = [
        'name',
        'description'
    ];
}
