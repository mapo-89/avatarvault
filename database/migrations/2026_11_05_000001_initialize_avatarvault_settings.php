<?php

use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = app(SettingsRepository::class);
        if ($settings->get('i18n', 'languages') === null) {
            $settings->set('i18n', 'languages', ['de', 'en'], 'multiselect', true);
        }
    }

    public function down(): void
    {
        // Preserve runtime settings when rolling back application code.
    }
};
