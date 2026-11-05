<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase
{
    public function test_name_backfill_preserves_ids_passwords_and_emails(): void
    {
        $migration = require database_path('migrations/2025_08_24_000000_add_core_panel_user_fields.php');
        $migration->down();
        $id = DB::table('users')->insertGetId(['name' => 'Ada van Example', 'email' => 'ada@example.com', 'password' => 'existing-password-hash']);
        $migration->up();
        $user = DB::table('users')->find($id);
        $this->assertSame('Ada', $user->first_name);
        $this->assertSame('van Example', $user->last_name);
        $this->assertSame('existing-password-hash', $user->password);
        $this->assertSame('ada@example.com', $user->email);
    }

    public function test_authentik_subjects_are_migrated_once_without_losing_groups(): void
    {
        $user = User::factory()->create(['oidc_sub' => 'original-subject', 'oidc_provider' => 'authentik', 'oidc_groups' => ['team-a']]);
        $migration = require database_path('migrations/2026_11_05_000000_migrate_authentik_accounts.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'oidc', 'provider_user_id' => 'original-subject']);
        $this->assertSame(['team-a'], $user->fresh()->oidc_groups);
    }

    public function test_conflicting_authentik_subjects_are_rejected(): void
    {
        User::factory()->count(2)->create(['oidc_sub' => 'same-subject', 'oidc_provider' => 'authentik']);
        $migration = require database_path('migrations/2026_11_05_000000_migrate_authentik_accounts.php');
        $this->expectException(\RuntimeException::class);
        $migration->up();
    }

    public function test_migrated_authentik_identity_logs_in_to_the_existing_account(): void
    {
        config([
            'core-panel.auth.socialite.providers.oidc.enabled' => true,
            'services.oidc.client_id' => 'test-client',
            'services.oidc.client_secret' => 'test-secret',
            'services.oidc.issuer' => 'https://auth.example.test/application/o/avatarvault/',
            'services.oidc.redirect' => '/auth/oidc/callback',
        ]);
        $user = User::factory()->create(['oidc_sub' => 'legacy-subject', 'oidc_provider' => 'authentik']);
        $migration = require database_path('migrations/2026_11_05_000000_migrate_authentik_accounts.php');
        $migration->up();
        $socialUser = (new \Laravel\Socialite\Two\User)->map([
            'id' => 'legacy-subject', 'email' => $user->email, 'name' => $user->name, 'avatar' => null,
        ]);
        $driver = \Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('oidc')->once()->andReturn($driver);
        $this->get('/auth/oidc/callback')->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_package_foreign_keys_preserve_numeric_user_ids(): void
    {
        $this->assertContains(Schema::getColumnType('social_accounts', 'user_id'), ['integer', 'bigint', 'int8']);
        $this->assertContains(Schema::getColumnType('user_group_user', 'user_id'), ['integer', 'bigint', 'int8']);
        $this->assertContains(Schema::getColumnType('oauth_access_tokens', 'user_id'), ['integer', 'bigint', 'int8']);
    }
}
