<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class BrowserSessionsTest extends TestCase
{
    public function test_other_browser_sessions_can_be_logged_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/profile/sessions/logout-others', ['password' => 'password'])->assertSessionHasNoErrors();
    }

    public function test_session_logout_requires_correct_password(): void
    {
        $this->actingAs(User::factory()->create())->post('/profile/sessions/logout-others', ['password' => 'wrong'])->assertSessionHasErrors('password');
    }
}
