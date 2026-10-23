<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\StoreRole;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-super-admin {--name= : Full name} {--phone= : Phone, e.g. +998901234567} {--password= : Password}')]
#[Description('Create a platform super-admin, who manages stores and belongs to none')]
class CreateSuperAdmin extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'phone' => Phone::normalize($this->option('phone') ?? text('Phone', placeholder: '+998 90 123 45 67', required: true)),
            'password' => $password = $this->option('password') ?? password('Password', required: true),
            'password_confirmation' => $password,
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($input) {
            $superAdmin = User::create([
                'name' => $input['name'],
                'phone' => $input['phone'],
                'password' => $input['password'],
                'team_id' => null,
            ]);

            $superAdmin->assignStoreRole(StoreRole::SuperAdmin);
        });

        $this->components->info("Super-admin {$input['phone']} created.");

        return self::SUCCESS;
    }
}
