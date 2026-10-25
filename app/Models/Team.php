<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'short_code',
        'captain_name',
        'email',
        'league_type',
        'max_players',
        'logo',
        'color',
    ];

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
}
