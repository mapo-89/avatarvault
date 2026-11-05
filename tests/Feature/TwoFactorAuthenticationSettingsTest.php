<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TwoFactorAuthenticationSettingsTest extends TestCase
{
    public function test_two_factor_authentication_can_be_enabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertCount(8, $user->fresh()->recoveryCodes());
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/user/two-factor-authentication');
        $original = $user->fresh()->recoveryCodes();
        $this->post('/user/two-factor-recovery-codes')->assertSessionHasNoErrors();
        $this->assertCount(8, array_diff($original, $user->fresh()->recoveryCodes()));
    }

    public function test_two_factor_authentication_can_be_disabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/user/two-factor-authentication');
        $this->delete('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
