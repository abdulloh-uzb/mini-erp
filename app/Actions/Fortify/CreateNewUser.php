<?php

namespace App\Actions\Fortify;

use App\Actions\Stores\CreateStore;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateStore $createStore)
    {
        //
    }

    /**
     * Validate and create a newly registered owner together with their store.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['phone'] = Phone::normalize($input['phone'] ?? null);

        /** @var array{name: string, phone: string, password: string, store_name: string} $validated */
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'store_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->createStore->handle($validated);
    }
}
