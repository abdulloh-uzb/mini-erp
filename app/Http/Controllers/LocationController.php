<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    /**
     * Show the store's warehouses, each with its sub-warehouses, archived ones included.
     */
    public function index(): Response
    {
        $warehouses = Location::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('name')])
            ->orderBy('id')
            ->get()
            ->map(fn (Location $warehouse) => [
                ...$this->locationData($warehouse),
                'sub_warehouses' => $warehouse->children->map($this->locationData(...))->all(),
            ]);

        return Inertia::render('locations/index', [
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Create a warehouse, or a sub-warehouse when a parent warehouse is given.
     */
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Ombor qo'shildi.")]);

        return to_route('locations.index');
    }

    /**
     * Rename a location.
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ombor saqlandi.')]);

        return to_route('locations.index');
    }

    /**
     * Hide a location from new documents, keeping it in history. A warehouse goes only once
     * its sub-warehouses are archived, so no active location sits inside an archived one.
     */
    public function archive(Location $location): RedirectResponse
    {
        if ($location->children()->active()->exists()) {
            throw ValidationException::withMessages([
                'location' => __('Avval ichki omborlarni arxivlang.'),
            ]);
        }

        $location->archive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ombor arxivlandi.')]);

        return to_route('locations.index');
    }

    /**
     * Make an archived location available to new documents again.
     */
    public function unarchive(Location $location): RedirectResponse
    {
        if ($location->parent?->isArchived()) {
            throw ValidationException::withMessages([
                'location' => __('Avval omborni arxivdan chiqaring.'),
            ]);
        }

        $location->unarchive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ombor arxivdan chiqarildi.')]);

        return to_route('locations.index');
    }

    /**
     * @return array{id: int, name: string, archived: bool}
     */
    private function locationData(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'archived' => $location->isArchived(),
        ];
    }
}
