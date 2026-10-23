<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the fixed roles and permissions, which sign-up and every permission check need.
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }
};
