<?php

namespace Database\Seeders;

use CorePanel\Database\Seeders\CorePanelPermissionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CorePanelPermissionSeeder::class);
    }
}
