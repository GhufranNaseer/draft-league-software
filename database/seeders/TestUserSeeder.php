<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Carbon\Carbon;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update test user
        $email = 'test@example.com';
        $password = 'Test@123456';
        
        $user = User::where('email', $email)->first();

        if (!$user) {
            User::create([
                'name' => 'Test User',
                'email' => $email,
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make($password),
                'is_admin' => true,
                'created_by_admin' => true,
            ]);
        } else {
            $user->update([
                'name' => 'Test User',
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => Carbon::now(),
            ]);
        }

        echo "Test user created: $email / $password\n";
    }
}
