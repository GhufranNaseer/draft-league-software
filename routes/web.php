<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ParticipantFormController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [ParticipantFormController::class, 'index'])->name('form.index');
Route::post('/submit-form', [ParticipantFormController::class, 'submit'])->name('form.submit');
Route::get('/status/{id}', [ParticipantFormController::class, 'getStatus'])->name('form.status');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    // Route::get('/setup-admin', [AuthController::class, 'createFirstAdmin'])->name('setup.admin');
    // Route::post('/setup-admin', [AuthController::class, 'createFirstAdmin'])->name('setup.admin.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Team and player self-service routes
Route::middleware('auth')->group(function () {
    Route::get('/team/dashboard', [TeamController::class, 'teamDashboard'])->name('team.dashboard');
    Route::post('/team/draft/rounds/{round}/pick/{participant}', [TeamController::class, 'pickInRound'])->name('team.draft.round.pick');
    Route::post('/team/draft/rounds/{round}/tick', [TeamController::class, 'tickRound'])->name('team.draft.round.tick');
    Route::get('/activities', [TeamController::class, 'activities'])->middleware('activities.scope')->name('activities.index');
    Route::get('/player/profile', [PlayerController::class, 'profile'])->name('player.profile');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Participants management
    Route::get('/participants', [AdminController::class, 'participants'])->name('participants');
    Route::get('/participants/{id}', [AdminController::class, 'viewParticipant'])->name('participant.view');
    Route::get('/participants/{id}/edit', [AdminController::class, 'editParticipant'])->name('participant.edit');
    Route::patch('/participants/{id}', [AdminController::class, 'updateParticipant'])->name('participant.update');
    Route::post('/participants/{id}/approve', [AdminController::class, 'approveParticipant'])->name('participant.approve');
    Route::post('/participants/{id}/reject', [AdminController::class, 'rejectParticipant'])->name('participant.reject');
    Route::delete('/participants/{id}', [AdminController::class, 'deleteParticipant'])->name('participant.delete');
    Route::get('/participants/{participantId}/preview/{fileType}', [AdminController::class, 'previewFile'])->name('participant.preview');
    Route::get('/participants/{participantId}/download/{fileType}', [AdminController::class, 'downloadFile'])->name('participant.download');
    Route::get('/export', [AdminController::class, 'exportParticipants'])->name('export');

    // Categories management
    Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
    Route::post('/categories', [AdminController::class, 'createCategory'])->name('category.create');
    Route::patch('/categories/{id}', [AdminController::class, 'updateCategory'])->name('category.update');
    Route::delete('/categories/{id}', [AdminController::class, 'deleteCategory'])->name('category.delete');

    // Teams and draft management
    Route::get('/teams', [TeamController::class, 'index'])->name('teams');
    Route::post('/teams', [TeamController::class, 'store'])->name('team.create');
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('team.delete');
    Route::post('/teams/{team}/draft/{participant}', [TeamController::class, 'draftPlayer'])->name('team.draft');
    Route::delete('/teams/{team}/draft/{participant}', [TeamController::class, 'releasePlayer'])->name('team.release');
    Route::post('/draft/rounds', [TeamController::class, 'startRound'])->name('draft.round.start');
    Route::post('/draft/rounds/{round}/pick/{participant}', [TeamController::class, 'pickInRound'])->name('draft.round.pick');
    Route::post('/draft/rounds/{round}/tick', [TeamController::class, 'tickRound'])->name('draft.round.tick');
    Route::post('/draft/rounds/{round}/close', [TeamController::class, 'closeRound'])->name('draft.round.close');
    Route::post('/draft/rounds/{round}/pause', [TeamController::class, 'pauseRound'])->name('draft.round.pause');
    Route::post('/draft/rounds/{round}/resume', [TeamController::class, 'resumeRound'])->name('draft.round.resume');

    // Drafting Screen (PTPL draft card full screen)
    Route::get('/drafting-screen', [AdminController::class, 'draftingScreen'])->name('drafting-screen');
    Route::get('/drafting-screen/latest', [AdminController::class, 'latestDraftingPick'])->name('drafting-screen.latest');

    // League setup
    Route::post('/league-setup', [TeamController::class, 'saveLeagueSetup'])->name('league.setup.save');
    Route::patch('/league-setup/{roundNumber}', [TeamController::class, 'updateLeagueRound'])->name('league.round.update');
    Route::delete('/league-setup', [TeamController::class, 'clearLeagueSetup'])->name('league.setup.clear');
    Route::post('/leagues', [TeamController::class, 'storeLeague'])->name('leagues.store');
    Route::patch('/leagues/{league}/toggle-status', [TeamController::class, 'toggleLeagueStatus'])->name('leagues.toggle');

    // Users management
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users', [AdminController::class, 'createUser'])->name('user.create');
    Route::patch('/users/{id}', [AdminController::class, 'editUser'])->name('user.edit');
    Route::patch('/users/{id}/password', [AdminController::class, 'resetUserPassword'])->name('user.password.reset');
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('user.delete');
});
