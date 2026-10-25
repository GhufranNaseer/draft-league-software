<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ParticipantController;

// Participant routes (RESTful)
Route::apiResource('participants', ParticipantController::class);
