<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CreateApiTokenTest extends TestCase
{
    public function test_passport_tokens_can_be_created_for_numeric_user_ids(): void
    {
        $this->configurePassportKeys();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/api-tokens', ['name' => 'Test Token', 'abilities' => ['read']])->assertSessionHasNoErrors()->assertSessionHas('apiToken');
        $token = $user->tokens()->firstOrFail();
        $this->assertSame('Test Token', $token->name);
        $this->assertTrue($token->can('read'));
        $this->assertFalse($token->can('delete'));
    }
}
