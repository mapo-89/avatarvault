<?php

namespace Tests\Feature;

use App\Models\User;
use CorePanel\Domain\ApiToken\Actions\CreateApiTokenAction;
use Tests\TestCase;

class DeleteApiTokenTest extends TestCase
{
    public function test_api_tokens_can_be_revoked(): void
    {
        $this->configurePassportKeys();
        $user = User::factory()->create();
        $token = app(CreateApiTokenAction::class)->execute($user, 'Test Token', ['read'])['token'];
        $this->actingAs($user)->delete('/admin/api-tokens/'.$token->id)->assertSessionHasNoErrors();
        $this->assertTrue($token->fresh()->revoked);
    }

    public function test_other_users_cannot_revoke_another_users_token(): void
    {
        $this->configurePassportKeys();
        $owner = User::factory()->create();
        $token = app(CreateApiTokenAction::class)->execute($owner, 'Test Token', ['read'])['token'];
        $this->actingAs(User::factory()->create())->delete('/admin/api-tokens/'.$token->id)->assertNotFound();
        $this->assertFalse($token->fresh()->revoked);
    }
}
