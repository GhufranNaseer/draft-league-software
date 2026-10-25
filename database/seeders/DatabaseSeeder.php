<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = ['create-user', 'edit-user', 'delete-user'];
        $permissions = collect($permissionNames)->map(function (string $name) use ($guard) {
            return Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        });

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        Role::firstOrCreate(['name' => 'team', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'player', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);

        $admin->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Run AdminUserSeeder when running DatabaseSeeder
        $this->call(AdminUserSeeder::class);
    }
}
