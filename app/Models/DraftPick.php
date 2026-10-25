<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftPick extends Model
{
    protected $fillable = [
        'draft_round_id',
        'team_id',
        'participant_id',
        'pick_number',
        'picked_at',
    ];

    protected $casts = [
        'picked_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(DraftRound::class, 'draft_round_id');
    }

    public function getRoundNumberAttribute(): int
    {
        return (int) ($this->round?->round_number ?? $this->draft_round_id);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
