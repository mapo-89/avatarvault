<?php

namespace Tests\Feature;

use App\Models\User;
use CorePanel\Domain\ApiToken\Actions\CreateApiTokenAction;
use Tests\TestCase;

class ApiTokenPermissionsTest extends TestCase
{
    public function test_replacing_a_token_preserves_scopes_and_revokes_the_old_token(): void
    {
        $this->configurePassportKeys();
        $user = User::factory()->create();
        $token = app(CreateApiTokenAction::class)->execute($user, 'Test Token', ['read'])['token'];
        $this->actingAs($user)->post('/admin/api-tokens/'.$token->id.'/replace')->assertSessionHasNoErrors()->assertSessionHas('apiToken');
        $this->assertTrue($token->fresh()->revoked);
        $active = $user->tokens()->where('revoked', false)->firstOrFail();
        $this->assertTrue($active->can('read'));
        $this->assertFalse($active->can('delete'));
    }
}
