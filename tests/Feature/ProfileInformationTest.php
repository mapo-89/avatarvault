<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    public function test_profile_page_uses_core_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/user/profile-information', [
            'first_name' => 'Updated', 'last_name' => 'User', 'email' => 'new@example.com', 'locale' => 'de',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Updated User', $user->fresh()->name);
        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertSame('de', $user->fresh()->locale);
    }
}
