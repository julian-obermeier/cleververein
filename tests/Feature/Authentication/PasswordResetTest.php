<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('app/installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed'));
        parent::tearDown();
    }

    public function test_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'SehrSicheresPasswort!2026', 'password_confirmation' => 'SehrSicheresPasswort!2026'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('SehrSicheresPasswort!2026', $user->fresh()->password));
    }
}
