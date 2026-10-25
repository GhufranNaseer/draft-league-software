<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipantFormController extends Controller
{
    /**
     * Show public registration form
     */
    public function index()
    {
        $leagues = collect([
            (object) ['slug' => 'male', 'name' => 'KTPL (Mens League)'],
            (object) ['slug' => 'female', 'name' => 'WTPL (Women League)'],
        ]);

        if (Schema::hasTable('leagues')) {
            $configuredLeagues = League::query()->active()->get();

            if ($configuredLeagues->isNotEmpty()) {
                $leagues = $configuredLeagues;
            }
        }

        return view('participant_form', [
            'leagues' => $leagues,
        ]);
    }

    /**
     * Handle form submission
     */
    public function submit(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'nick_name' => 'required|string|max:255',
                'passport_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'id_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'skill_categories' => 'required|array|min:1',
                'skill_categories.*' => 'required|string|in:Right Hand Batsman,Left Hand Batsman,Right-Arm Fast,Left-Arm Fast,All Rounder,Right Arm Leg Spin,Left Arm Spinner,Off Spinner,Wicket Keeper',
                'performance' => 'nullable|string',
                'city' => 'required|string|max:255',
                'address' => 'required|string',
                'medical_info' => 'nullable|string',
                'mobile' => 'required|string|min:10|max:15',
                'emergency_contact' => 'required|string|min:10|max:15',
                'email' => 'required|email|unique:participants,email',
                'dob' => 'required|date',
                'nationality' => 'required|string|max:255|not_in:India,Israel',
                'league_type' => ['required', 'string', Rule::exists('leagues', 'slug')->where(fn ($query) => $query->where('is_active', true))],
                'identity' => 'required|string|regex:/^[a-zA-Z0-9]{9,14}$/',
                'kit_size' => 'required|in:small,medium,large,xl,xxl',
                'shirt_number' => 'required|string|max:10',
                // airline & hotel/flight fields required unless nationality is Pakistan
                'airline' => 'required_unless:nationality,Pakistan|string|max:255',
                'arrival_date' => 'nullable|date',
                'arrival_time' => 'nullable|date_format:H:i',
                'hotel_name' => 'required_unless:nationality,Pakistan|string|max:255',
                // 'hotel_reservation' => 'required_unless:nationality,Pakistan|file|mimes:pdf|max:5120',
                // 'flight_reservation' => 'required_unless:nationality,Pakistan|file|mimes:pdf|max:5120',
                'checkin' => 'required_unless:nationality,Pakistan|date',
                'checkout' => 'required_unless:nationality,Pakistan|date',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // Handle passport picture upload
            if ($request->hasFile('passport_picture')) {
                $file = $request->file('passport_picture');
                $filename = time().'_passport_'.uniqid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('passports', $filename, 'public');
                $validated['passport_picture'] = $path;
            }

            // Handle ID picture upload
            if ($request->hasFile('id_picture')) {
                $file = $request->file('id_picture');
                $filename = time().'_id_'.uniqid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('ids', $filename, 'public');
                $validated['id_picture'] = $path;
            }

            // Handle hotel reservation upload
            if ($request->hasFile('hotel_reservation')) {
                $file = $request->file('hotel_reservation');
                $filename = time().'_hotel_'.uniqid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('documents/hotel', $filename, 'public');
                $validated['hotel_reservation'] = $path;
            }

            // Handle flight reservation upload
            if ($request->hasFile('flight_reservation')) {
                $file = $request->file('flight_reservation');
                $filename = time().'_flight_'.uniqid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('documents/flight', $filename, 'public');
                $validated['flight_reservation'] = $path;
            }

            // Set status to pending
            $validated['status'] = 'pending';

            // Create participant
            $participant = Participant::create($validated);

            // Send confirmation email
            try {
                Mail::raw(
                    "Hello {$participant->full_name},\n\n".
                    "Your registration has been submitted successfully.\n".
                    "Status: Pending Admin Approval\n\n".
                    "We will review your submission and send you a confirmation email soon.\n\n".
                    "Best regards,\n".
                    'Participant Management Team',
                    function ($message) use ($participant) {
                        $message->to($participant->email)
                            ->subject('Registration Confirmation - Participant Management System');
                    }
                );
            } catch (\Exception $e) {
                // Log email error but don't fail the request
                \Log::error('Email sending failed: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration submitted successfully! Check your email for confirmation.',
                'participant_id' => $participant->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting form: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get submission status (for tracking)
     */
    public function getStatus($id)
    {
        $participant = Participant::find($id);

        if (! $participant) {
            return response()->json(['error' => 'Participant not found'], 404);
        }

        return response()->json([
            'id' => $participant->id,
            'full_name' => $participant->full_name,
            'email' => $participant->email,
            'status' => $participant->status,
            'created_at' => $participant->created_at->format('Y-m-d H:i:s'),
        ]);
    }
}
