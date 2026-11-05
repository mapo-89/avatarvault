<?php

namespace Tests\Feature;

use App\Models\EmailAlias;
use App\Models\User;
use App\Notifications\VerifyEmailAlias;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;
use Tests\TestCase;

class EmailAliasTest extends TestCase
{
    private function alias(User $owner, string $email = 'alias@example.com', bool $verified = false): EmailAlias
    {
        $alias = $owner->emailAliases()->create(['email' => $email, 'email_hash' => AvatarManager::hash($email)]);
        if ($verified) {
            $alias->forceFill(['verified_at' => now(), 'verified_email_hash' => $alias->email_hash])->save();
        }

        return $alias;
    }

    public function test_alias_management_requires_authentication(): void
    {
        $this->post('/profile/email-aliases', ['email' => 'alias@example.com'])->assertRedirect('/login');
    }

    public function test_adding_an_alias_normalizes_email_and_notifies_that_address(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $this->actingAs($owner)->from('/dashboard')->post('/profile/email-aliases', ['email' => '  ALIAS@example.com  '])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $alias = $owner->emailAliases()->sole();
        $this->assertSame('alias@example.com', $alias->email);
        $this->assertNull($alias->verified_at);
        $this->assertNull($alias->verified_email_hash);
        Notification::assertSentOnDemand(VerifyEmailAlias::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'alias@example.com');
    }

    public function test_signed_verification_activates_alias_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $alias = $this->alias($owner);
        $link = (new VerifyEmailAlias($alias))->verificationUrl();
        $this->actingAs($owner)->get($link)->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->get($link)->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertNotNull($alias->fresh()->verified_at);
        $this->assertSame(md5('alias@example.com'), $alias->fresh()->verified_email_hash);
    }

    public function test_pending_alias_does_not_share_the_owners_picture(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $this->alias($owner);
        $owner->addMedia(UploadedFile::fake()->image('owner.jpg'))->toMediaCollection('avatars');
        $this->get('/avatar/'.md5('alias@example.com'))->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_verified_alias_tracks_profile_replacement_and_deletion(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $this->alias($owner, verified: true);
        $file = UploadedFile::fake()->image('owner.jpg');
        $bytes = file_get_contents($file->getRealPath());
        $this->actingAs($owner)->post('/profile/avatar', ['avatar' => $file])->assertSessionHasNoErrors();
        $this->assertSame($bytes, $this->get('/avatar/'.md5('alias@example.com'))->assertOk()->streamedContent());
        $new = UploadedFile::fake()->image('new.png', 50, 50);
        $replacement = file_get_contents($new->getRealPath());
        $this->post('/profile/avatar', ['avatar' => $new])->assertSessionHasNoErrors();
        $this->assertSame($replacement, $this->get('/avatar/'.strtoupper(md5('alias@example.com')))->assertOk()->streamedContent());
        $this->delete('/profile/avatar')->assertSessionHasNoErrors();
        $this->get('/avatar/'.md5('alias@example.com'))->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertSame(0, $owner->fresh()->getMedia('avatars')->count());
    }

    public function test_verification_requires_owner_valid_signature_and_unexpired_link(): void
    {
        $owner = User::factory()->create();
        $alias = $this->alias($owner);
        $link = (new VerifyEmailAlias($alias))->verificationUrl();
        $this->actingAs(User::factory()->create())->get($link)->assertNotFound();
        $this->actingAs($owner)->get(str_replace(sha1($alias->email), str_repeat('a', 40), $link))->assertForbidden();
        $expired = URL::temporarySignedRoute('email-aliases.verify', now()->subMinute(), ['alias' => $alias->id, 'hash' => sha1($alias->email)]);
        $this->get($expired)->assertForbidden();
        $this->assertNull($alias->fresh()->verified_at);
    }

    public function test_another_users_alias_cannot_be_deleted_or_resent(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $alias = $this->alias($owner);
        $this->actingAs(User::factory()->create())->delete('/profile/email-aliases/'.$alias->id)->assertNotFound();
        $this->post('/profile/email-aliases/'.$alias->id.'/resend')->assertNotFound();
        $this->assertModelExists($alias);
        Notification::assertNothingSent();
    }

    public function test_primary_and_verified_addresses_cannot_be_claimed(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create(['email' => 'PRIMARY@example.com']);
        $this->alias($other, 'reserved@example.com', true);
        $this->actingAs($owner);
        foreach (['primary@example.com', 'reserved@example.com', 'owner@example.com'] as $email) {
            $this->post('/profile/email-aliases', ['email' => $email])->assertSessionHasErrors('email');
        }
        $this->assertSame(0, $owner->emailAliases()->count());
        Notification::assertNothingSent();
    }

    public function test_pending_claims_do_not_reserve_an_address_for_other_users(): void
    {
        Notification::fake();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->alias($first);
        $this->actingAs($second)->post('/profile/email-aliases', ['email' => 'alias@example.com'])->assertSessionHasNoErrors();
        $winner = $second->emailAliases()->sole();
        $this->get((new VerifyEmailAlias($winner))->verificationUrl())->assertSessionHasNoErrors();
        $loser = $first->emailAliases()->sole();
        $this->actingAs($first)->get((new VerifyEmailAlias($loser))->verificationUrl())->assertSessionHasErrors('email');
        $this->assertNull($loser->fresh()->verified_at);
        $this->assertSame(1, EmailAlias::whereNotNull('verified_at')->count());
    }

    public function test_primary_addresses_are_rechecked_at_verification_time(): void
    {
        $owner = User::factory()->create();
        $alias = $this->alias($owner);
        User::factory()->create(['email' => $alias->email]);
        $this->actingAs($owner)->get((new VerifyEmailAlias($alias))->verificationUrl())->assertSessionHasErrors('email');
        $this->assertNull($alias->fresh()->verified_at);
    }

    public function test_alias_limit_and_resend_throttle_are_enforced(): void
    {
        Notification::fake();
        config(['avatarvault.email_aliases.max_per_user' => 1]);
        $owner = User::factory()->create();
        $alias = $this->alias($owner);
        $this->actingAs($owner)->post('/profile/email-aliases', ['email' => 'another@example.com'])->assertSessionHasErrors('email');
        for ($i = 0; $i < 3; $i++) {
            $this->post('/profile/email-aliases/'.$alias->id.'/resend')->assertSessionHasNoErrors();
        }
        $this->post('/profile/email-aliases/'.$alias->id.'/resend')->assertStatus(429);
        Notification::assertSentOnDemandTimes(VerifyEmailAlias::class, 3);
    }

    public function test_removing_an_alias_leaves_the_primary_avatar_intact(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $alias = $this->alias($owner, verified: true);
        $owner->addMedia(UploadedFile::fake()->image('owner.jpg'))->toMediaCollection('avatars');
        $this->actingAs($owner)->delete('/profile/email-aliases/'.$alias->id)->assertSessionHasNoErrors();
        $this->get('/avatar/'.md5($owner->email))->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get('/avatar/'.md5($alias->email))->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertModelMissing($alias);
    }

    public function test_api_upload_to_alias_updates_the_primary_avatar(): void
    {
        Storage::fake('public');
        config(['avatar-manager.api_keys' => ['test-key']]);
        $owner = User::factory()->create();
        $this->alias($owner, verified: true);
        $this->withHeader('X-API-KEY', 'test-key')->postJson('/api/avatars/upload', [
            'email' => 'alias@example.com', 'avatar' => UploadedFile::fake()->image('api.jpg'),
        ])->assertOk();
        Storage::disk('public')->assertExists('avatars/'.md5($owner->email).'.jpg');
        Storage::disk('public')->assertMissing('avatars/'.md5('alias@example.com').'.jpg');
        $main = $this->get('/avatar/'.md5($owner->email))->assertOk()->streamedContent();
        $this->assertSame($main, $this->get('/avatar/'.md5('alias@example.com'))->assertOk()->streamedContent());
    }

    public function test_api_cannot_override_managed_photo_through_an_alias(): void
    {
        Storage::fake('public');
        config(['avatar-manager.api_keys' => ['test-key']]);
        $owner = User::factory()->create();
        $this->alias($owner, verified: true);
        $owner->addMedia(UploadedFile::fake()->image('owner.jpg'))->toMediaCollection('avatars');
        $this->withHeader('X-API-KEY', 'test-key')->postJson('/api/avatars/upload', [
            'email' => 'alias@example.com', 'avatar' => UploadedFile::fake()->image('api.jpg'),
        ])->assertStatus(409);
    }

    public function test_dashboard_exposes_only_owned_aliases_and_verified_urls(): void
    {
        $owner = User::factory()->create();
        $this->alias($owner, verified: true);
        $this->alias($owner, 'pending@example.com');
        $this->alias(User::factory()->create(), 'private@example.com', true);
        $this->actingAs($owner)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('emailAliases', 2)->where('emailAliases.0.verified', true)
            ->where('emailAliases.1.verified', false)->where('emailAliases.1.url', null));
    }

    public function test_verified_alias_cannot_be_registered_as_someone_elses_primary_email(): void
    {
        $this->alias(User::factory()->create(), verified: true);
        $this->expectException(ValidationException::class);
        User::factory()->create(['email' => 'alias@example.com']);
    }

    public function test_deleted_accounts_lose_aliases_and_cannot_serve_images(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $alias = $this->alias($owner, verified: true);
        $owner->addMedia(UploadedFile::fake()->image('owner.jpg'))->toMediaCollection('avatars');
        $owner->delete();
        $this->get('/avatar/'.md5($alias->email))->assertOk()->assertHeader('content-type', 'image/png');
        $owner->forceDelete();
        $this->assertModelMissing($alias);
    }
}
