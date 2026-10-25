<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function admin_can_reset_a_user_password_from_user_management(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $targetUser = User::factory()->create([
            'password' => Hash::make('OldPassword123'),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.user.password.reset', $targetUser->id), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect();

        $targetUser->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $targetUser->password));
        $this->assertFalse(Hash::check('OldPassword123', $targetUser->password));
    }

    /**
     * @test
     */
    public function non_admin_user_cannot_reset_another_users_password(): void
    {
        $regularUser = User::factory()->create([
            'is_admin' => false,
        ]);
        $targetUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->patch(route('admin.user.password.reset', $targetUser->id), [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertForbidden();
    }
}
