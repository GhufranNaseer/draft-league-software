<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftSkip extends Model
{
    protected $fillable = [
        'draft_round_id',
        'team_id',
        'round_number',
        'pick_number',
        'skipped_at',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'pick_number' => 'integer',
        'skipped_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(DraftRound::class, 'draft_round_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
