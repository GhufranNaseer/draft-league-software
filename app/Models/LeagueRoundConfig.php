<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeagueRoundConfig extends Model
{
    protected $fillable = [
        'league_type',
        'round_number',
        'team_pick_order',
        'is_manually_set',
    ];

    protected $casts = [
        'team_pick_order' => 'array',
        'is_manually_set' => 'boolean',
    ];
}
