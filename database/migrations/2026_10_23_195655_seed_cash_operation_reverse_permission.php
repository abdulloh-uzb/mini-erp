<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the new permission to correct a cash operation with its opposite (owner only).
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }
};
