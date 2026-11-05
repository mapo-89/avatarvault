<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    public function test_pages_expose_the_host_application_version(): void
    {
        $version = json_decode(file_get_contents(config_path('app-version.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('corePanel.version', $version['release_version'])
            ->where('corePanel.shortName', 'AvatarVault'));
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/profile/avatar')->assertRedirect('/login');
    }

    public function test_dashboard_exposes_normalized_avatar_link(): void
    {
        $user = User::factory()->create(['email' => 'USER@example.com']);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Avatar/Dashboard')->where('avatar.hash', md5('user@example.com')));
    }

    public function test_profile_upload_is_publicly_accessible_by_hash(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->from('/dashboard')->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->getFirstMedia('avatars'));
        $this->get('/avatar/'.md5(strtolower($user->email)))->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_remove_does_not_resurrect_legacy_or_api_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['profile_photo_path' => 'old.jpg']);
        Storage::disk('public')->put('old.jpg', 'legacy');
        $hash = md5(strtolower($user->email));
        Storage::disk('public')->put('avatars/'.$hash.'.jpg', 'api');
        $this->actingAs($user)->delete('/profile/avatar')->assertRedirect();
        Storage::disk('public')->assertMissing('old.jpg');
        Storage::disk('public')->assertMissing('avatars/'.$hash.'.jpg');
        $this->assertNull($user->fresh()->profile_photo_path);
        $this->get('/avatar/'.$hash)->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_invalid_and_oversized_uploads_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('large.jpg')->size(2049),
        ])->assertSessionHasErrors('avatar');
        $this->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('avatar');
    }

    public function test_legacy_import_is_idempotent_and_keeps_original_files(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('old.png');
        Storage::disk('public')->put('old.png', file_get_contents($file->getRealPath()));
        $user = User::factory()->create(['profile_photo_path' => 'old.png']);
        $id = $user->id;
        $this->artisan('avatarvault:import-legacy-avatars')->assertSuccessful();
        $this->artisan('avatarvault:import-legacy-avatars')->assertSuccessful();
        $this->assertSame($id, $user->fresh()->id);
        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame(1, $user->fresh()->getMedia('avatars')->count());
        Storage::disk('public')->assertExists('old.png');
    }

    public function test_missing_legacy_image_is_reported_without_losing_reference(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['profile_photo_path' => 'missing.png']);
        $this->artisan('avatarvault:import-legacy-avatars')->assertFailed();
        $this->assertSame('missing.png', $user->fresh()->profile_photo_path);
    }

    public function test_api_upload_requires_key_and_respects_managed_avatar(): void
    {
        Storage::fake('public');
        config(['avatar-manager.api_keys' => ['test-key']]);
        $this->postJson('/api/avatars/upload', [])->assertUnauthorized();
        $user = User::factory()->create();
        $user->addMedia(UploadedFile::fake()->image('profile.jpg'))->toMediaCollection('avatars');
        $this->withHeader('X-API-KEY', 'test-key')->postJson('/api/avatars/upload', [
            'email' => $user->email, 'avatar' => UploadedFile::fake()->image('other.jpg'),
        ])->assertStatus(409);
    }
}
