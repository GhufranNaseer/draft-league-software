<?php

use App\Http\Controllers\Api\ParticipantController;
use Illuminate\Support\Facades\Route;

// Participant routes (RESTful)
Route::apiResource('participants', ParticipantController::class);
