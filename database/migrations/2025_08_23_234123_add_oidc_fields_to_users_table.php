<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oidc_sub')->nullable()->after('password');
            $table->string('oidc_provider')->nullable()->after('oidc_sub');
            $table->json('oidc_groups')->nullable()->after('oidc_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['oidc_sub', 'oidc_provider', 'oidc_groups']);
        });
    }
};
