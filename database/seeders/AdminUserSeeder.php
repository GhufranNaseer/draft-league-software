<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create or update admin user
        $email = 'admin@badarexpo.com';
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make("Master$#$321"),
            'is_admin' => true,
            'created_by_admin' => true,
            ]);
            $user->assignRole($adminRole);
        } else {
            $user->update([
            'name' => 'Admin',
            'password' => Hash::make("Master$#$321"),
            'is_admin' => true,
            'created_by_admin' => true,
            'email_verified_at' => Carbon::now(),
            ]);
            $user->syncRoles([$adminRole]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
