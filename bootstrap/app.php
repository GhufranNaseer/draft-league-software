<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ScopeActivitiesForTeam;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->redirectTo(
            guests: '/login',
            users: function () {
                $user = Auth::user();

                return $user ? route(AuthController::resolveHomeRoute($user)) : route('login');
            }
        );

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'activities.scope' => ScopeActivitiesForTeam::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (BroadcastException $e): void {
            Log::warning('BroadcastException caught: '.$e->getMessage(), [
                'exception' => $e->getMessage(),
            ]);
        });

        $exceptions->renderable(function (BroadcastException $e, $request) {
            Log::warning('BroadcastException handled gracefully: '.$e->getMessage(), [
                'url' => $request->fullUrl(),
            ]);

            if ($request->header('X-Livewire')) {
                return response()->noContent(200);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'broadcast_failed' => true,
                    'message' => 'Action processed successfully. Real-time broadcast notification skipped.',
                ], 200);
            }

            return back()->with('warning', 'Action completed, but real-time notification could not be delivered.');
        });
    })->create();
