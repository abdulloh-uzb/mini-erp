<?php

namespace App\Actions\Stores;

use App\Enums\StoreRole;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateStore
{
    /**
     * Create a store owned by a new user, with its main warehouse.
     *
     * @param  array{name: string, phone: string, password: string, store_name: string}  $input
     */
    public function handle(array $input): User
    {
        return DB::transaction(function () use ($input) {
            $team = Team::create(['name' => $input['store_name']]);

            $owner = User::create([
                'name' => $input['name'],
                'phone' => $input['phone'],
                'password' => $input['password'],
                'team_id' => $team->id,
            ]);

            $owner->assignStoreRole(StoreRole::Owner);

            Location::create([
                'team_id' => $team->id,
                'name' => Location::MAIN_WAREHOUSE_NAME,
            ]);

            return $owner;
        });
    }
}
