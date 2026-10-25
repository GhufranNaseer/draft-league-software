<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DraftPick;
use App\Models\DraftRound;
use App\Models\League;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use App\Services\DraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /**
     * Admin dashboard
     */
    public function dashboard()
    {
        $stats = [
            'total_participants' => Participant::count(),
            'pending' => Participant::where('status', 'pending')->count(),
            'approved' => Participant::where('status', 'approved')->count(),
            'rejected' => Participant::where('status', 'rejected')->count(),
            'total_users' => User::count(),
            'total_teams' => Team::count(),
            'drafted_players' => Participant::whereNotNull('team_id')->count(),
        ];

        return view('admin.dashboard', $stats);
    }

    /**
     * View all participants
     */
    public function participants(Request $request)
    {
        $query = Participant::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('league_type')) {
            $query->where('league_type', (string) $request->league_type);
        }

        $participants = $query->with('creator')->paginate(15);

        return view('admin.participants', [
            'participants' => $participants,
            'leagues' => League::query()->active()->get(),
        ]);
    }

    /**
     * View participant details
     */
    public function viewParticipant($id)
    {
        $participant = Participant::with('creator')->findOrFail($id);

        return view('admin.participant-detail', ['participant' => $participant]);
    }

    /**
     * Approve participant
     */
    public function approveParticipant($id)
    {
        $participant = Participant::findOrFail($id);
        $participant->update(['status' => 'approved']);

        $accountResult = $this->ensureParticipantUserAccount($participant);

        if (! empty($accountResult['blocked'])) {
            return back()->with('success', 'Participant approved, but no player login was created because this email belongs to an admin account.');
        }

        if ($accountResult['created']) {
            return back()
                ->with('success', 'Participant approved and player login created successfully.')
                ->with('account_credentials', [
                    'label' => 'Player Account',
                    'email' => $accountResult['email'],
                    'password' => $accountResult['password'],
                ]);
        }

        return back()->with('success', 'Participant approved successfully. Existing user was linked with player access.');
    }

    /**
     * Reject participant
     */
    public function rejectParticipant($id)
    {
        $participant = Participant::findOrFail($id);
        $participant->update(['status' => 'rejected']);

        return back()->with('success', 'Participant rejected successfully');
    }

    /**
     * Delete participant
     */
    public function deleteParticipant($id)
    {
        $participant = Participant::findOrFail($id);
        $participant->delete();

        return back()->with('success', 'Participant deleted successfully');
    }

    /**
     * Manage users
     */
    public function users(Request $request)
    {
        $admins = User::with('roles')->whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->paginate(15);
        $teams = User::with('roles')->whereHas('roles', function ($query) {
            $query->where('name', 'team');
        })->paginate(15);
        $players = User::with('roles')->whereHas('roles', function ($query) {
            $query->where('name', 'player');
        })->paginate(15);
        $users = User::with('roles')->whereHas('roles', function ($query) {
            $query->where('name', 'user');
        })->paginate(15);

        $roles = Role::query()->orderBy('name')->pluck('name');

        return view('admin.users', [
            'users' => [
                'admin' => $admins,
                'team' => $teams,
                'player' => $players,
                'user' => $users,
            ],
            'roles' => $roles,
        ]);
        // return $roles;
    }

    /**
     * Create user
     */
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $selectedRoles = $validated['roles'] ?? [];
        $isAdmin = in_array('admin', $selectedRoles, true);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'is_admin' => $isAdmin,
            'created_by_admin' => true,
        ]);

        if (! empty($selectedRoles)) {
            $user->syncRoles($selectedRoles);
        }

        return back()->with('success', 'User created successfully');
    }

    /**
     * Edit user
     */
    public function editUser($id, Request $request)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $selectedRoles = $validated['roles'] ?? [];
        $isAdmin = in_array('admin', $selectedRoles, true);

        if ($user->id === Auth::id() && ! $isAdmin) {
            return back()->with('error', 'You cannot remove your own admin role');
        }

        $validated['is_admin'] = $isAdmin;

        $user->update($validated);
        $user->syncRoles($selectedRoles);

        return back()->with('success', 'User updated successfully');
    }

    /**
     * Reset user password
     */
    public function resetUserPassword($id, Request $request)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'User password reset successfully');
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        if ($id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account');
        }

        $user = User::findOrFail($id);
        $user->delete();

        return back()->with('success', 'User deleted successfully');
    }

    /**
     * Export participants (CSV)
     */
    public function exportParticipants(Request $request)
    {
        $query = Participant::query();

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

        if ($request->filled('league_type')) {
            $query->where('league_type', (string) $request->league_type);
        }

        $participants = $query->orderByDesc('id')->get();

        $filename = 'participants_'.date('Y-m-d_H-i-s').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($participants) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'ID',
                'First Name',
                'Last Name',
                'Email',
                'Mobile',
                'City',
                'Nationality',
                'League',
                'Kit Size',
                'Status',
                'Passport Photo URL',
                'Created At',
            ]);

            // Data
            foreach ($participants as $p) {
                fputcsv($file, [
                    $p->id,
                    $p->first_name,
                    $p->last_name,
                    $p->email,
                    $p->mobile,
                    $p->city,
                    $p->nationality,
                    ucfirst((string) ($p->league_type ?? 'male')),
                    $p->kit_size,
                    $p->status,
                    $p->passport_picture ? asset('storage/'.ltrim((string) $p->passport_picture, '/')) : '',
                    $p->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show edit form for participant
     */
    public function editParticipant($id)
    {
        $participant = Participant::findOrFail($id);
        $categories = Category::all();
        $leagues = League::query()->active()->get();

        return view('admin.participant-edit', [
            'participant' => $participant,
            'categories' => $categories,
            'leagues' => $leagues,
        ]);
    }

    /**
     * Update participant details
     */
    public function updateParticipant(Request $request, $id)
    {
        $participant = Participant::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nick_name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string',
            'email' => 'required|email|unique:participants,email,'.$id,
            'dob' => 'required|date',
            'nationality' => 'required|string|max:255',
            'league_type' => ['required', 'string', Rule::exists('leagues', 'slug')],
            'kit_size' => 'required|in:small,medium,large,xl,xxl',
            'shirt_number' => 'required|string|max:10',
            'performance' => 'nullable|string',
            'airline' => 'nullable|string|max:255',
            'arrival_date' => 'nullable|date',
            'arrival_time' => 'nullable|date_format:H:i',
            'hotel_name' => 'nullable|string|max:255',
            'checkin' => 'nullable|date',
            'checkout' => 'nullable|date',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $participant->update($validated);

        return redirect()->route('admin.participant.view', $participant->id)->with('success', 'Participant updated successfully');
    }

    /**
     * View all categories
     */
    public function categories()
    {
        return redirect()->route('admin.teams', ['tab' => 'categories']);
    }

    /**
     * Create category
     */
    public function createCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories|max:255',
            'description' => 'nullable|string',
        ]);

        Category::create($validated);

        return back()->with('success', 'Category created successfully');
    }

    /**
     * Update category
     */
    public function updateCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,'.$id,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated successfully');
    }

    /**
     * Delete category
     */
    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return back()->with('success', 'Category deleted successfully. Linked players are now uncategorized.');
    }

    /**
     * Download participant file
     */
    public function downloadFile($participantId, $fileType)
    {
        $participant = Participant::findOrFail($participantId);

        $fileMap = [
            'passport' => 'passport_picture',
            'id' => 'id_picture',
            'hotel' => 'hotel_reservation',
            'flight' => 'flight_reservation',
        ];

        if (! isset($fileMap[$fileType])) {
            abort(404, 'File type not found');
        }

        $field = $fileMap[$fileType];
        $filePath = $participant->$field;

        if (! $filePath || ! Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        // Generate meaningful filename
        $fileNameMap = [
            'passport_picture' => 'Passport_Photo_'.$participant->id.'_'.$participant->full_name,
            'id_picture' => 'ID_Picture_'.$participant->id.'_'.$participant->full_name,
            'hotel_reservation' => 'Hotel_Reservation_'.$participant->id.'_'.$participant->full_name,
            'flight_reservation' => 'Flight_Reservation_'.$participant->id.'_'.$participant->full_name,
        ];

        $fileName = $fileNameMap[$field] ?? basename($filePath);
        $originalFileName = basename($filePath);
        $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);

        return Storage::disk('public')->download($filePath, $fileName.'.'.$fileExtension);
    }

    /**
     * Preview participant file
     */
    public function previewFile($participantId, $fileType)
    {
        $participant = Participant::findOrFail($participantId);

        $fileMap = [
            'passport' => 'passport_picture',
            'id' => 'id_picture',
            'hotel' => 'hotel_reservation',
            'flight' => 'flight_reservation',
        ];

        if (! isset($fileMap[$fileType])) {
            abort(404, 'File type not found');
        }

        $field = $fileMap[$fileType];
        $filePath = $participant->$field;

        if (! $filePath || ! Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->response($filePath);
    }

    /**
     * Ensure a player-role user exists for approved participant email.
     */
    private function ensureParticipantUserAccount(Participant $participant): array
    {
        $email = (string) $participant->email;
        $displayName = trim($participant->first_name.' '.$participant->last_name);
        $displayName = $displayName !== '' ? $displayName : ($participant->nick_name ?: 'Player');

        $user = User::query()->where('email', $email)->first();

        if ($user && $user->isAdmin()) {
            return [
                'blocked' => true,
                'created' => false,
                'email' => $email,
                'password' => null,
            ];
        }

        if ($user) {
            $user->update([
                'name' => $displayName,
                'created_by_admin' => true,
            ]);
            $user->syncRoles(['player']);

            return [
                'created' => false,
                'email' => $user->email,
                'password' => null,
            ];
        }

        $plainPassword = 'Player@'.Str::upper(Str::random(8)).random_int(10, 99);

        $user = User::create([
            'name' => $displayName,
            'email' => $email,
            'password' => $plainPassword,
            'is_admin' => false,
            'created_by_admin' => true,
        ]);

        $user->syncRoles(['player']);

        return [
            'created' => true,
            'email' => $user->email,
            'password' => $plainPassword,
        ];
    }

    /**
     * Drafting Screen display (PTPL card full screen)
     */
    public function draftingScreen(Request $request)
    {
        $draftService = app(DraftService::class);
        $leagueType = $draftService->normalizeLeagueType($request->query('league'));

        $availableLeagues = League::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activeRound = DraftRound::query()
            ->where('league_type', $leagueType)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        $latestPick = null;
        if ($activeRound) {
            $latestPick = DraftPick::query()
                ->with(['participant.category', 'team', 'round.category'])
                ->where('draft_round_id', $activeRound->id)
                ->latest('id')
                ->first();
        }

        if (! $latestPick) {
            $latestPick = DraftPick::query()
                ->with(['participant.category', 'team', 'round.category'])
                ->whereHas('round', fn ($q) => $q->where('league_type', $leagueType))
                ->latest('id')
                ->first();
        }

        $formattedPick = $this->formatDraftPickData($latestPick);

        return view('admin.drafting-screen', [
            'leagueType' => $leagueType,
            'availableLeagues' => $availableLeagues,
            'activeRound' => $activeRound,
            'initialPick' => $formattedPick,
        ]);
    }

    /**
     * Get latest draft pick as JSON (for reconnection/sync)
     */
    public function latestDraftingPick(Request $request)
    {
        $draftService = app(DraftService::class);
        $leagueType = $draftService->normalizeLeagueType($request->query('league'));

        $activeRound = DraftRound::query()
            ->where('league_type', $leagueType)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        $latestPick = null;
        if ($activeRound) {
            $latestPick = DraftPick::query()
                ->with(['participant.category', 'team', 'round.category'])
                ->where('draft_round_id', $activeRound->id)
                ->latest('id')
                ->first();
        }

        if (! $latestPick) {
            $latestPick = DraftPick::query()
                ->with(['participant.category', 'team', 'round.category'])
                ->whereHas('round', fn ($q) => $q->where('league_type', $leagueType))
                ->latest('id')
                ->first();
        }

        $formattedPick = $this->formatDraftPickData($latestPick);

        return response()->json($formattedPick ?: [
            'pickId' => 0,
            'roundId' => $activeRound?->id ?? 0,
            'leagueType' => $leagueType,
            'pickNumber' => 0,
            'pickedAt' => null,
            'teamName' => null,
            'player' => null,
        ]);
    }

    private function formatDraftPickData(?DraftPick $pick): ?array
    {
        if (! $pick || ! $pick->participant) {
            return null;
        }

        $player = $pick->participant;
        $skills = collect($player->skill_categories ?? [])->filter()->values();
        $role = $skills->isNotEmpty() ? $skills->implode(' / ') : '—';

        $passportUrl = null;
        if ($player->passport_picture) {
            $passportUrl = asset('storage/'.ltrim($player->passport_picture, '/'));
        }

        return [
            'pickId' => (int) $pick->id,
            'roundId' => (int) $pick->draft_round_id,
            'roundNumber' => (int) ($pick->round?->round_number ?: $pick->draft_round_id),
            'leagueType' => (string) ($pick->round?->league_type ?? 'male'),
            'pickNumber' => (int) $pick->pick_number,
            'pickedAt' => $pick->picked_at?->toIso8601String(),
            'teamName' => $pick->team?->name ?? '—',
            'player' => [
                'id' => (int) $player->id,
                'fullName' => $player->full_name ?: trim($player->first_name.' '.$player->last_name),
                'nickName' => $player->nick_name ?: '—',
                'category' => $player->category?->name ?: ($pick->round?->category?->name ?: '—'),
                'role' => $role,
                'city' => $player->city ?: '—',
                'passportPictureUrl' => $passportUrl,
            ],
        ];
    }
}
