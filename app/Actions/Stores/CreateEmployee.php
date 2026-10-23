<?php

namespace App\Actions\Stores;

use App\Enums\StoreRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateEmployee
{
    /**
     * Create an active employee in the given store.
     *
     * @param  array{name: string, phone: string, password: string}  $input
     */
    public function handle(Team $team, array $input): User
    {
        return DB::transaction(function () use ($team, $input) {
            $employee = User::create([
                'name' => $input['name'],
                'phone' => $input['phone'],
                'password' => $input['password'],
                'team_id' => $team->id,
            ]);

            $employee->assignStoreRole(StoreRole::Employee);

            return $employee;
        });
    }
}
