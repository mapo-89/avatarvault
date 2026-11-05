<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_users_can_register_without_uuid_conversion(): void
    {
        $this->post('/register', [
            'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertIsInt($user->id);
        $this->assertSame('Test User', $user->name);
    }

    public function test_registration_screen_is_unavailable_when_disabled(): void
    {
        config(['core-panel.auth.registration_enabled' => false]);
        $this->get('/register')->assertNotFound();
    }
}
