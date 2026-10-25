<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipantController extends Controller
{
    /**
     * Get all participants with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search = $request->query('search', '');
        $leagueType = $request->query('league_type');

        $query = Participant::query();

        if ($search) {
            $query->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
        }

        if ($leagueType && League::query()->where('slug', (string) $leagueType)->exists()) {
            $query->where('league_type', (string) $leagueType);
        }

        $participants = $query->paginate($perPage);

        return response()->json($participants, Response::HTTP_OK);
    }

    /**
     * Get a single participant by ID
     */
    public function show($id)
    {
        $participant = Participant::find($id);

        if (! $participant) {
            return response()->json(['error' => 'Participant not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($participant, Response::HTTP_OK);
    }

    /**
     * Create a new participant
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'nick_name' => 'required|string|max:255',
                'passport_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'id_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'skill_categories' => 'required|array|min:1',
                'skill_categories.*' => 'string',
                'performance' => 'nullable|string',
                'city' => 'required|string|max:255',
                'address' => 'required|string',
                'mobile' => 'required|string',
                'email' => 'required|email|unique:participants,email',
                'dob' => 'required|date',
                'nationality' => 'required|string',
                'league_type' => ['required', 'string', Rule::exists('leagues', 'slug')],
                'identity' => 'required|string|regex:/^[a-zA-Z0-9]{9,14}$/',
                'kit_size' => 'required|in:small,medium,large,xl,xxl',
                'shirt_number' => 'required|string|max:10',
                'airline' => 'nullable|string|max:255',
                'arrival_date' => 'nullable|date',
                'arrival_time' => 'nullable|date_format:H:i',
                'hotel_name' => 'nullable|string|max:255',
                'checkin' => 'nullable|date',
                'checkout' => 'nullable|date',
            ]);

            // Validate mobile and identity
            if (! Participant::validateMobile($validated['mobile'])) {
                throw ValidationException::withMessages([
                    'mobile' => 'Mobile number must be 10-15 digits',
                ]);
            }

            // Handle file upload
            if ($request->hasFile('passport_picture')) {
                $file = $request->file('passport_picture');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('passports', $filename, 'public');
                $validated['passport_picture'] = $path;
            }

            // Handle file upload
            if ($request->hasFile('id_picture')) {
                $file = $request->file('id_picture');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('ids', $filename, 'public');
                $validated['id_picture'] = $path;
            }

            $participant = Participant::create($validated);

            return response()->json($participant, Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Update a participant
     */
    public function update(Request $request, $id)
    {
        try {
            $participant = Participant::find($id);

            if (! $participant) {
                return response()->json(['error' => 'Participant not found'], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'full_name' => 'nullable|string|max:255',
                'nick_name' => 'nullable|string|max:255',
                'passport_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'id_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'skill_categories' => 'nullable|array',
                'skill_categories.*' => 'string',
                'performance' => 'nullable|string',
                'city' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'mobile' => 'nullable|string',
                'email' => 'nullable|email|unique:participants,email,'.$id,
                'dob' => 'nullable|date',
                'nationality' => 'nullable|string',
                'league_type' => ['nullable', 'string', Rule::exists('leagues', 'slug')],
                'identity' => 'nullable|string|regex:/^[a-zA-Z0-9]{9,14}$/',
                'kit_size' => 'nullable|in:small,medium,large,xl,xxl',
                'shirt_number' => 'nullable|string|max:10',
                'airline' => 'nullable|string|max:255',
                'arrival_date' => 'nullable|date',
                'arrival_time' => 'nullable|date_format:H:i',
                'hotel_name' => 'nullable|string|max:255',
                'checkin' => 'nullable|date',
                'checkout' => 'nullable|date',
            ]);

            // Validate mobile if provided
            if (isset($validated['mobile']) && ! Participant::validateMobile($validated['mobile'])) {
                throw ValidationException::withMessages([
                    'mobile' => 'Mobile number must be 10-15 digits',
                ]);
            }

            // Handle file upload
            if ($request->hasFile('passport_picture')) {
                $file = $request->file('passport_picture');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('passports', $filename, 'public');
                $validated['passport_picture'] = $path;
            }

            // Handle file upload
            if ($request->hasFile('id_picture')) {
                $file = $request->file('id_picture');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('ids', $filename, 'public');
                $validated['id_picture'] = $path;
            }

            // Remove null values
            $validated = array_filter($validated, function ($value) {
                return $value !== null;
            });

            $participant->update($validated);

            return response()->json($participant, Response::HTTP_OK);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Delete a participant
     */
    public function destroy($id)
    {
        $participant = Participant::find($id);

        if (! $participant) {
            return response()->json(['error' => 'Participant not found'], Response::HTTP_NOT_FOUND);
        }

        $participant->delete();

        return response()->json(['message' => 'Participant deleted successfully'], Response::HTTP_OK);
    }
}
