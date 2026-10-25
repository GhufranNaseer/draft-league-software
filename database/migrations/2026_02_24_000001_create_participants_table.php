<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            // Basic Information
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nick_name');
            $table->string('passport_picture'); // file path
            $table->string('id_picture'); // file path
            
            // Skills (stored as JSON)
            $table->json('skill_categories')->default('[]');
            
            // Performance & Location
            $table->text('performance')->nullable();
            $table->string('city');
            $table->text('address');
            $table->text('medical_info')->nullable();
            $table->string('mobile')->encrypted(); // encrypted
            $table->string('emergency_contact')->encrypted(); // encrypted
            $table->string('email')->unique();
            $table->date('dob');
            $table->string('nationality');
            $table->string('identity')->encrypted(); // encrypted, 9-14 alphanumeric
            
            // Kit Details
            $table->enum('kit_size', ['small', 'medium', 'large', 'xl', 'xxl']);
            $table->string('shirt_number');
            
            // Travel Details
            $table->string('airline')->nullable();
            $table->datetime('arrival_date')->nullable();
            $table->time('arrival_time')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('hotel_reservation')->nullable();
            $table->string('flight_reservation')->nullable();
            $table->date('checkin')->nullable();
            $table->date('checkout')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
