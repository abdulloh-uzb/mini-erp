<?php

use App\Models\Counterparty;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    test('the list shows the store\'s counterparties by name', function () {
        $owner = User::factory()->create();
        Counterparty::factory()->for($owner->team)->create(['name' => 'Zafar aka', 'phone' => null, 'note' => 'Bozordagi do\'kon']);
        Counterparty::factory()->for($owner->team)->create(['name' => 'Anvar Coca-Cola']);
        Counterparty::factory()->create(['name' => 'Begona mijoz']);

        $this->actingAs($owner)->get(route('counterparties.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('counterparties/index')
                ->has('counterparties.data', 2)
                ->where('counterparties.data.0.name', 'Anvar Coca-Cola')
                ->where('counterparties.data.1.name', 'Zafar aka')
                ->where('counterparties.data.1.phone', null)
                ->where('counterparties.data.1.note', 'Bozordagi do\'kon'),
            );
    });

    test('the search matches a name or phone digits', function (string $search) {
        $owner = User::factory()->create();
        Counterparty::factory()->for($owner->team)->create(['name' => 'Zafar aka', 'phone' => '+998901234567']);
        Counterparty::factory()->for($owner->team)->create(['name' => 'Anvar', 'phone' => '+998935554433']);

        $this->actingAs($owner)->get(route('counterparties.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('counterparties.data', 1)
                ->where('counterparties.data.0.name', 'Zafar aka')
                ->where('filters.search', $search),
            );
    })->with([
        'part of the name, any case' => 'zafar',
        'phone as typed with spaces' => '90 123',
    ]);

    test('an employee sees the counterparty list', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        Counterparty::factory()->for($owner->team)->create();

        $this->actingAs($employee)->get(route('counterparties.index'))
            ->assertInertia(fn (Assert $page) => $page->has('counterparties.data', 1));
    });

    test('a super-admin cannot see the counterparty list', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('counterparties.index'))
            ->assertForbidden();
    });
});

describe('create', function () {
    test('an employee quick-creates a counterparty and gets it back to pick', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();

        $response = $this->actingAs($employee)
            ->from(route('dashboard'))
            ->post(route('counterparties.store'), ['name' => 'Zafar aka', 'phone' => '90 123 45 67']);

        $counterparty = Counterparty::sole();
        expect($counterparty)
            ->team_id->toBe($owner->team_id)
            ->name->toBe('Zafar aka')
            ->phone->toBe('+998901234567')
            ->note->toBeNull();

        $response->assertRedirect(route('dashboard'))
            ->assertInertiaFlash('counterparty.id', $counterparty->id)
            ->assertInertiaFlash('counterparty.name', 'Zafar aka');
    });

    test('the owner creates a counterparty with a note and no phone', function (string $phone) {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->from(route('counterparties.index'))
            ->post(route('counterparties.store'), ['name' => 'Bozor do\'koni', 'phone' => $phone, 'note' => 'Har juma oladi'])
            ->assertRedirect(route('counterparties.index'));

        $this->assertDatabaseHas('counterparties', [
            'team_id' => $owner->team_id,
            'name' => 'Bozor do\'koni',
            'phone' => null,
            'note' => 'Har juma oladi',
        ]);
    })->with([
        'phone left empty' => '',
        'phone left at the mask prefix' => '+998',
    ]);

    test('a counterparty needs a name', function () {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('counterparties.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Nomi kiritilishi shart.']);

        expect(Counterparty::count())->toBe(0);
    });

    test('a phone that is not an Uzbek number is rejected', function () {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('counterparties.store'), ['name' => 'Zafar aka', 'phone' => '12345'])
            ->assertSessionHasErrors(['phone' => "Telefon raqami +998 XX XXX XX XX ko'rinishida bo'lishi kerak."]);
    });
});

describe('update', function () {
    test('the owner edits a counterparty', function () {
        $owner = User::factory()->create();
        $counterparty = Counterparty::factory()->for($owner->team)->create(['name' => 'Zafar']);

        $this->actingAs($owner)
            ->put(route('counterparties.update', $counterparty), ['name' => 'Zafar aka', 'phone' => '+998 93 555 44 33', 'note' => 'Ulgurji'])
            ->assertRedirect(route('counterparties.index'));

        expect($counterparty->fresh())
            ->name->toBe('Zafar aka')
            ->phone->toBe('+998935554433')
            ->note->toBe('Ulgurji');
    });

    test('an employee cannot edit a counterparty', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        $counterparty = Counterparty::factory()->for($owner->team)->create(['name' => 'Zafar']);

        $this->actingAs($employee)
            ->put(route('counterparties.update', $counterparty), ['name' => 'Boshqa'])
            ->assertForbidden();

        expect($counterparty->fresh()->name)->toBe('Zafar');
    });

    test('another store\'s counterparty is not found', function () {
        $owner = User::factory()->create();
        $foreignCounterparty = Counterparty::factory()->create(['name' => 'Begona']);

        $this->actingAs($owner)
            ->put(route('counterparties.update', $foreignCounterparty), ['name' => 'Meniki'])
            ->assertNotFound();

        expect(Counterparty::withoutGlobalScopes()->find($foreignCounterparty->id)->name)->toBe('Begona');
    });
});
