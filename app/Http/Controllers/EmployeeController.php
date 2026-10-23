<?php

namespace App\Http\Controllers;

use App\Actions\Stores\CreateEmployee;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Show the store's employees.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('employees/index', [
            'employees' => $request->user()->team->employees()
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'is_active']),
        ]);
    }

    /**
     * Create an employee in the owner's store.
     */
    public function store(StoreEmployeeRequest $request, CreateEmployee $createEmployee): RedirectResponse
    {
        /** @var array{name: string, phone: string, password: string} $input */
        $input = $request->validated();

        $createEmployee->handle($request->user()->team, $input);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Xodim qo'shildi.")]);

        return to_route('employees.index');
    }

    /**
     * Set a new password for an employee.
     */
    public function updatePassword(ResetPasswordRequest $request, User $employee): RedirectResponse
    {
        Gate::authorize('manageEmployee', $employee);

        $employee->update(['password' => $request->validated('password')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Parol yangilandi.')]);

        return to_route('employees.index');
    }

    /**
     * Stop an employee from logging in, keeping their history.
     */
    public function deactivate(User $employee): RedirectResponse
    {
        Gate::authorize('manageEmployee', $employee);

        $employee->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Xodim o'chirildi.")]);

        return to_route('employees.index');
    }

    /**
     * Let a deactivated employee log in again.
     */
    public function activate(User $employee): RedirectResponse
    {
        Gate::authorize('manageEmployee', $employee);

        $employee->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Xodim faollashtirildi.')]);

        return to_route('employees.index');
    }
}
