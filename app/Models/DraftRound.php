<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftRound extends Model
{
    protected $fillable = [
        'league_type',
        'round_number',
        'category_id',
        'start_team_id',
        'current_team_id',
        'pick_order',
        'higher_category_ids',
        'picks_per_team',
        'turn_time_seconds',
        'current_pick_number',
        'total_picks_planned',
        'current_turn_started_at',
        'completed_at',
        'status',
        'is_paused',
        'remaining_turn_seconds',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'pick_order' => 'array',
        'higher_category_ids' => 'array',
        'current_turn_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_paused' => 'boolean',
        'remaining_turn_seconds' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function startTeam()
    {
        return $this->belongsTo(Team::class, 'start_team_id');
    }

    public function currentTeam()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function picks()
    {
        return $this->hasMany(DraftPick::class);
    }
}
