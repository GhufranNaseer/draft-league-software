<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'description'];

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
}
