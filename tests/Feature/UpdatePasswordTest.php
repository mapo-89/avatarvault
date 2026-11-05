<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/user/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/user/password', ['current_password' => 'wrong-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_passwords_must_match(): void
    {
        $this->actingAs(User::factory()->create())->put('/user/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'different-password'])->assertSessionHasErrors('password');
    }
}
