<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class Participant extends Model
{
    protected $table = 'participants';

    protected $fillable = [
        'first_name',
        'last_name',
        'nick_name',
        'passport_picture',
        'id_picture',
        'skill_categories',
        'performance',
        'city',
        'address',
        'medical_info',
        'mobile',
        'emergency_contact',
        'email',
        'dob',
        'nationality',
        'league_type',
        'identity',
        'kit_size',
        'shirt_number',
        'airline',
        'arrival_date',
        'arrival_time',
        'hotel_name',
        'hotel_reservation',
        'flight_reservation',
        'checkin',
        'checkout',
        'created_by',
        'status',
        'category_id',
        'team_id',
        'drafted_at',
    ];

    protected $casts = [
        'dob' => 'date',
        'arrival_date' => 'datetime',
        'checkin' => 'date',
        'checkout' => 'date',
        'skill_categories' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'drafted_at' => 'datetime',
    ];

    // Encrypted attributes
    protected $encrypted = [
        'mobile',
        'identity',
    ];

    // Hide encrypted fields from API responses by default
    protected $hidden = [];

    /**
     * Get the mobile number (will be automatically decrypted)
     */
    public function getMobileAttribute($value)
    {
        return $value;
    }

    /**
     * Get the identity (will be automatically decrypted)
     */
    public function getIdentityAttribute($value)
    {
        return $value;
    }

    /**
     * Validate mobile format (10-15 digits)
     */
    public static function validateMobile($mobile)
    {
        return preg_match('/^\d{10,15}$/', preg_replace('/[\s\-]/', '', $mobile));
    }

    /**
     * Get the user who created this participant
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the category assigned to this participant
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the team that drafted this participant
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Computed full name used by existing views/controllers.
     */
    public function getFullNameAttribute(): string
    {
        return trim((string) $this->first_name . ' ' . (string) $this->last_name);
    }
}
