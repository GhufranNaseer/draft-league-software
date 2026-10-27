<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            return redirect()->route(static::resolveHomeRoute($user));
        }

        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sun, 02 Jan 1990 00:00:00 GMT');
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            $homeRoute = static::resolveHomeRoute($user);
            $intendedUrl = (string) $request->session()->get('url.intended', '');

            if ($this->isValidIntendedUrl($intendedUrl, $user)) {
                return redirect()->intended(route($homeRoute));
            }

            $request->session()->forget('url.intended');

            return redirect()->route($homeRoute);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are invalid.'],
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Show registration form (public)
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle registration (create user account by admin)
     */
    public function register(Request $request)
    {
        // Only admin can create users via form
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'is_admin' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $validated['is_admin'] ?? false,
            'created_by_admin' => true,
        ]);

        return response()->json($user, 201);
    }

    /**
     * Resolve home route by role.
     */
    public static function resolveHomeRoute(?User $user): string
    {
        if (! $user) {
            return 'login';
        }

        if ($user->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user->hasRole('team') || Team::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->exists()) {
            return 'team.dashboard';
        }

        if ($user->hasRole('player') || Participant::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->exists()) {
            return 'player.profile';
        }

        // Safe fallback for authenticated users: never return public registration form
        return 'player.profile';
    }

    /**
     * Determine if intended URL is valid and authorized for the user.
     */
    protected function isValidIntendedUrl(?string $url, User $user): bool
    {
        if (empty($url)) {
            return false;
        }

        $trimmedUrl = rtrim(trim($url), '/');
        $rootUrl = rtrim(url('/'), '/');
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($trimmedUrl === '' || strcasecmp($trimmedUrl, $rootUrl) === 0 || strcasecmp($trimmedUrl, $appUrl) === 0) {
            return false;
        }

        $rawPath = parse_url($url, PHP_URL_PATH);
        $path = ($rawPath === null || $rawPath === '' || $rawPath === '/') ? '/' : ('/'.ltrim($rawPath, '/'));

        // Never redirect authenticated users to public registration, login, logout, or submission endpoints
        if (in_array($path, ['/', '/login', '/logout', '/submit-form']) || Str::startsWith($path, '/status')) {
            return false;
        }

        // Admin-only areas
        if (Str::startsWith($path, '/admin') && ! $user->isAdmin()) {
            return false;
        }

        // Team areas
        if (Str::startsWith($path, '/team') && ! $user->isAdmin() && ! $user->hasRole('team') && ! Team::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->exists()) {
            return false;
        }

        // Player areas
        if (Str::startsWith($path, '/player') && ! $user->isAdmin() && ! $user->hasRole('player') && ! Participant::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->exists()) {
            return false;
        }

        return true;
    }

    // /**
    //  * Create first admin user (only available if no users exist)
    //  */
    // public function createFirstAdmin(Request $request)
    // {
    //     if (User::count() > 0) {
    //         return response()->json(['error' => 'Users already exist'], 403);
    //     }

    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|email|unique:users',
    //         'password' => 'required|min:8|confirmed',
    //     ]);

    //     $admin = User::create([
    //         'name' => $validated['name'],
    //         'email' => $validated['email'],
    //         'password' => Hash::make($validated['password']),
    //         'is_admin' => true,
    //         'created_by_admin' => false,
    //     ]);

    //     Auth::login($admin);
    //     return redirect()->route('admin.dashboard')->with('status', 'Admin account created successfully!');
    // }
}
