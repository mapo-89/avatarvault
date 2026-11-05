<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('oidc_provider', 'authentik')->whereNotNull('oidc_sub')
            ->orderBy('id')->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $existing = DB::table('social_accounts')->where('provider', 'oidc')
                        ->where('provider_user_id', $user->oidc_sub)->first();
                    if ($existing !== null) {
                        if ((string) $existing->user_id !== (string) $user->id) {
                            throw new RuntimeException('Conflicting legacy OIDC identity; reconcile the accounts before migrating.');
                        }

                        continue;
                    }

                    DB::table('social_accounts')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'provider' => 'oidc',
                        'provider_user_id' => $user->oidc_sub,
                        'provider_email' => $user->email,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Preserve linked identities on rollback; legacy OIDC fields remain intact.
    }
};
