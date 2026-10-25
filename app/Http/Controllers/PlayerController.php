<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    /**
     * Player profile page (read-only own details).
     */
    public function profile()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user || (!$user->hasRole('player') && !$user->isAdmin())) {
            abort(403, 'Unauthorized. Player access required.');
        }

        $participant = Participant::query()
            ->with(['category', 'team'])
            ->where('email', $user->email)
            ->first();

        if (!$participant) {
            abort(404, 'No participant profile is linked to this account email.');
        }

        return view('player.profile', [
            'participant' => $participant,
        ]);
    }
}
