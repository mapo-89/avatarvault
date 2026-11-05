<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    public function test_user_accounts_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete('/profile/account', ['password' => 'password'])->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertNull(User::withTrashed()->find($user->id));
        $this->assertGuest();
    }

    public function test_correct_password_is_required_before_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete('/profile/account', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh());
    }
}
