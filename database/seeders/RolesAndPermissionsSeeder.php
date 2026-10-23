<?php

namespace Database\Seeders;

use App\Enums\StorePermission;
use App\Enums\StoreRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the fixed roles and permissions. Safe to run repeatedly.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (StorePermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach (StoreRole::cases() as $role) {
            Role::findOrCreate($role->value)->syncPermissions(
                array_map(fn (StorePermission $permission) => $permission->value, $role->permissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
