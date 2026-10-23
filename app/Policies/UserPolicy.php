<?php

namespace App\Policies;

use App\Enums\StorePermission;
use App\Enums\StoreRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can reset the password of, deactivate or reactivate the employee.
     * Another store's user is reported as not found, so its existence is not revealed.
     */
    public function manageEmployee(User $user, User $employee): Response
    {
        if ($employee->team_id !== $user->team_id) {
            return Response::denyAsNotFound();
        }

        return $user->can(StorePermission::EmployeesManage->value) && $employee->storeRole() === StoreRole::Employee
            ? Response::allow()
            : Response::deny();
    }
}
