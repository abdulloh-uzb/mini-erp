<?php

use App\Models\Location;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    test('a new store lists its main warehouse', function () {
        $this->post(route('register.store'), [
            'name' => 'Ali Valiyev',
            'phone' => '+998 90 123 45 67',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'store_name' => 'Ichimliklar',
        ]);

        $this->get(route('locations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('locations/index')
                ->has('warehouses', 1)
                ->where('warehouses.0.name', 'Asosiy ombor')
                ->where('warehouses.0.archived', false)
                ->where('warehouses.0.sub_warehouses', []),
            );
    });

    test('the list shows each warehouse with its sub-warehouses, archived ones included', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->create(['name' => 'Asosiy ombor']);
        Location::factory()->inside($warehouse)->create(['name' => 'Sovutgich']);
        Location::factory()->inside($warehouse)->archived()->create(['name' => 'Eski javon']);
        Location::factory()->for($owner->team)->create(['name' => 'Bozor ombori']);
        Location::factory()->create(['name' => 'Begona ombor']);

        $this->actingAs($owner)->get(route('locations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('warehouses', 2)
                ->where('warehouses.0.name', 'Asosiy ombor')
                ->has('warehouses.0.sub_warehouses', 2)
                ->where('warehouses.0.sub_warehouses.0.name', 'Eski javon')
                ->where('warehouses.0.sub_warehouses.0.archived', true)
                ->where('warehouses.0.sub_warehouses.1.name', 'Sovutgich')
                ->where('warehouses.1.name', 'Bozor ombori'),
            );
    });
});

describe('create', function () {
    test('the owner creates a warehouse', function () {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('locations.store'), ['name' => 'Bozor ombori']);

        $response->assertRedirect(route('locations.index'));
        $this->assertDatabaseHas('locations', [
            'team_id' => $owner->team_id,
            'name' => 'Bozor ombori',
            'parent_id' => null,
        ]);
    });

    test('the owner creates a sub-warehouse inside a warehouse', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->create();

        $response = $this->actingAs($owner)->post(route('locations.store'), [
            'name' => 'Sovutgich',
            'parent_id' => $warehouse->id,
        ]);

        $response->assertRedirect(route('locations.index'));
        $this->assertDatabaseHas('locations', [
            'team_id' => $owner->team_id,
            'name' => 'Sovutgich',
            'parent_id' => $warehouse->id,
        ]);
    });

    test('a location needs a name', function () {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('locations.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Nomi kiritilishi shart.']);
    });

    test('a sub-warehouse cannot be placed inside another sub-warehouse', function () {
        $owner = User::factory()->create();
        $subWarehouse = Location::factory()->inside(Location::factory()->for($owner->team)->create())->create();

        $response = $this->actingAs($owner)->post(route('locations.store'), [
            'name' => 'Javon',
            'parent_id' => $subWarehouse->id,
        ]);

        $response->assertSessionHasErrors(['parent_id' => "Ichki ombor faqat omborning ichida bo'lishi mumkin."]);
        $this->assertDatabaseMissing('locations', ['name' => 'Javon']);
    });

    test('a sub-warehouse cannot be placed inside an archived warehouse', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->archived()->create();

        $response = $this->actingAs($owner)->post(route('locations.store'), [
            'name' => 'Sovutgich',
            'parent_id' => $warehouse->id,
        ]);

        $response->assertSessionHasErrors(['parent_id' => "Ichki ombor faqat omborning ichida bo'lishi mumkin."]);
    });

    test('a sub-warehouse cannot be placed inside another store\'s warehouse', function () {
        $owner = User::factory()->create();
        $foreignWarehouse = Location::factory()->create();

        $response = $this->actingAs($owner)->post(route('locations.store'), [
            'name' => 'Sovutgich',
            'parent_id' => $foreignWarehouse->id,
        ]);

        $response->assertSessionHasErrors(['parent_id' => "Ichki ombor faqat omborning ichida bo'lishi mumkin."]);
        expect(Location::withoutGlobalScopes()->where('parent_id', $foreignWarehouse->id)->exists())->toBeFalse();
    });
});

describe('rename', function () {
    test('the owner renames a location, keeping where it sits', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->create();
        $subWarehouse = Location::factory()->inside($warehouse)->create(['name' => 'Sovutgich']);

        $response = $this->actingAs($owner)->put(route('locations.update', $subWarehouse), [
            'name' => 'Katta sovutgich',
            'parent_id' => null,
        ]);

        $response->assertRedirect(route('locations.index'));
        expect($subWarehouse->fresh())
            ->name->toBe('Katta sovutgich')
            ->parent_id->toBe($warehouse->id);
    });

    test('another store\'s location is not found', function () {
        $owner = User::factory()->create();
        $foreignLocation = Location::factory()->create(['name' => 'Begona ombor']);

        $this->actingAs($owner)->put(route('locations.update', $foreignLocation), ['name' => 'Meniki'])->assertNotFound();
        $this->actingAs($owner)->post(route('locations.archive', $foreignLocation))->assertNotFound();

        expect(Location::withoutGlobalScopes()->find($foreignLocation->id))
            ->name->toBe('Begona ombor')
            ->archived_at->toBeNull();
    });
});

describe('archive', function () {
    test('the owner archives and unarchives a location', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->create();

        $this->actingAs($owner)->post(route('locations.archive', $warehouse))->assertRedirect(route('locations.index'));
        expect($warehouse->fresh()->isArchived())->toBeTrue();

        $this->actingAs($owner)->post(route('locations.unarchive', $warehouse))->assertRedirect(route('locations.index'));
        expect($warehouse->fresh()->isArchived())->toBeFalse();
    });

    test('a warehouse with active sub-warehouses cannot be archived', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->create();
        Location::factory()->inside($warehouse)->create();

        $response = $this->actingAs($owner)->post(route('locations.archive', $warehouse));

        $response->assertSessionHasErrors(['location' => 'Avval ichki omborlarni arxivlang.']);
        expect($warehouse->fresh()->isArchived())->toBeFalse();
    });

    test('a sub-warehouse of an archived warehouse cannot be unarchived', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->archived()->create();
        $subWarehouse = Location::factory()->inside($warehouse)->archived()->create();

        $response = $this->actingAs($owner)->post(route('locations.unarchive', $subWarehouse));

        $response->assertSessionHasErrors(['location' => 'Avval omborni arxivdan chiqaring.']);
        expect($subWarehouse->fresh()->isArchived())->toBeTrue();
    });
});

describe('permissions', function () {
    test('an employee cannot see or manage locations', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        $warehouse = Location::factory()->for($owner->team)->create(['name' => 'Asosiy ombor']);

        $this->actingAs($employee)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('locations.store'), ['name' => 'Yangi'])->assertForbidden();
        $this->actingAs($employee)->put(route('locations.update', $warehouse), ['name' => 'Yangi'])->assertForbidden();
        $this->actingAs($employee)->post(route('locations.archive', $warehouse))->assertForbidden();

        expect(Location::count())->toBe(1)
            ->and($warehouse->fresh())->name->toBe('Asosiy ombor')->archived_at->toBeNull();
    });
});
