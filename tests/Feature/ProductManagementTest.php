<?php

use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function productInput(array $overrides = []): array
{
    return [
        'name' => 'Coca-Cola 1.5 L',
        'barcode' => '4780000000017',
        'pieces_per_block' => 6,
        ...$overrides,
    ];
}

describe('index', function () {
    test('the list shows the store\'s active products by name', function () {
        $owner = User::factory()->create();
        Product::factory()->for($owner->team)->create(['name' => 'Pepsi 1 L', 'barcode' => null, 'pieces_per_block' => 12]);
        Product::factory()->for($owner->team)->create(['name' => 'Fanta 1 L']);
        Product::factory()->for($owner->team)->archived()->create(['name' => 'Eski sharbat']);
        Product::factory()->create(['name' => 'Begona do\'kon mahsuloti']);

        $response = $this->actingAs($owner)->get(route('products.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Fanta 1 L')
            ->where('products.data.1.name', 'Pepsi 1 L')
            ->where('products.data.1.barcode', null)
            ->where('products.data.1.pieces_per_block', 12)
            ->where('products.data.1.archived', false),
        );
    });

    test('the archived filter shows only archived products', function () {
        $owner = User::factory()->create();
        Product::factory()->for($owner->team)->create();
        Product::factory()->for($owner->team)->archived()->create(['name' => 'Eski sharbat']);

        $response = $this->actingAs($owner)->get(route('products.index', ['archived' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Eski sharbat')
            ->where('products.data.0.archived', true)
            ->where('filters.archived', true),
        );
    });

    test('the search matches a product name or barcode', function (string $search) {
        $owner = User::factory()->create();
        Product::factory()->for($owner->team)->create(['name' => 'Coca-Cola 1.5 L', 'barcode' => '4780000000017']);
        Product::factory()->for($owner->team)->create(['name' => 'Fanta 1 L', 'barcode' => '4780000000024']);

        $response = $this->actingAs($owner)->get(route('products.index', ['search' => $search]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Coca-Cola 1.5 L')
            ->where('filters.search', $search),
        );
    })->with([
        'part of the name, any case' => 'coca',
        'part of the barcode' => '00017',
    ]);

    test('an employee sees the product list', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        Product::factory()->for($owner->team)->create();

        $this->actingAs($employee)->get(route('products.index'))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    });
});

describe('permissions', function () {
    test('an employee cannot create, edit or archive products', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        $product = Product::factory()->for($owner->team)->create(['name' => 'Fanta 1 L']);

        $this->actingAs($employee)->post(route('products.store'), productInput())->assertForbidden();
        $this->actingAs($employee)->put(route('products.update', $product), productInput())->assertForbidden();
        $this->actingAs($employee)->post(route('products.archive', $product))->assertForbidden();

        expect(Product::count())->toBe(1)
            ->and($product->fresh())->name->toBe('Fanta 1 L')->archived_at->toBeNull();
    });

    test('a super-admin cannot see the product list', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('products.index'))
            ->assertForbidden();
    });
});

describe('create', function () {
    test('the owner creates a product in their store', function () {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('products.store'), productInput());

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'team_id' => $owner->team_id,
            'name' => 'Coca-Cola 1.5 L',
            'barcode' => '4780000000017',
            'pieces_per_block' => 6,
            'archived_at' => null,
        ]);
    });

    test('a product needs a name and pieces per block', function () {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('products.store'), []);

        $response->assertSessionHasErrors([
            'name' => 'Nomi kiritilishi shart.',
            'pieces_per_block' => 'Blokdagi dona soni kiritilishi shart.',
        ]);
        expect(Product::withoutGlobalScopes()->count())->toBe(0);
    });

    test('the owner gives a product a sale price per piece', function () {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('products.store'), productInput(['sale_price' => '13000']));

        expect(Product::query()->sole()->sale_price)->toBe('13000.00');
    });

    test('a sale price cannot be negative or have more than two decimals', function (string $salePrice) {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('products.store'), productInput(['sale_price' => $salePrice]));

        $response->assertSessionHasErrors('sale_price');
    })->with(['negative' => '-1', 'three decimals' => '10.123']);

    test('pieces per block must be at least one', function () {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('products.store'), productInput(['pieces_per_block' => 0]));

        $response->assertSessionHasErrors(['pieces_per_block' => 'Blokdagi dona soni kamida 1 bo\'lishi kerak.']);
    });

    test('products without a barcode are stored with none', function () {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('products.store'), productInput(['name' => 'Suv 0.5 L', 'barcode' => '']));
        $this->actingAs($owner)->post(route('products.store'), productInput(['name' => 'Suv 1 L', 'barcode' => '']));

        expect(Product::whereNull('barcode')->pluck('name')->sort()->values()->all())->toBe(['Suv 0.5 L', 'Suv 1 L']);
    });

    test('a barcode already used in the store is rejected', function () {
        $owner = User::factory()->create();
        Product::factory()->for($owner->team)->create(['barcode' => '4780000000017']);

        $response = $this->actingAs($owner)->post(route('products.store'), productInput());

        $response->assertSessionHasErrors(['barcode' => 'Bu shtrix-kod allaqachon band.']);
    });

    test('a barcode used by another store is accepted', function () {
        $owner = User::factory()->create();
        Product::factory()->create(['barcode' => '4780000000017']);

        $response = $this->actingAs($owner)->post(route('products.store'), productInput());

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['team_id' => $owner->team_id, 'barcode' => '4780000000017']);
    });
});

describe('update', function () {
    test('the owner edits a product, keeping its own barcode', function () {
        $owner = User::factory()->create();
        $product = Product::factory()->for($owner->team)->create(['barcode' => '4780000000017']);

        $response = $this->actingAs($owner)->put(route('products.update', $product), productInput([
            'name' => 'Coca-Cola 2 L',
            'pieces_per_block' => 4,
        ]));

        $response->assertRedirect(route('products.index'));
        expect($product->fresh())
            ->name->toBe('Coca-Cola 2 L')
            ->barcode->toBe('4780000000017')
            ->pieces_per_block->toBe(4);
    });

    test('clearing the sale price removes it', function () {
        $owner = User::factory()->create();
        $product = Product::factory()->for($owner->team)->create(['sale_price' => '13000']);

        $this->actingAs($owner)->put(route('products.update', $product), productInput(['sale_price' => '']));

        expect($product->fresh()->sale_price)->toBeNull();
    });

    test('a product cannot take another product\'s barcode', function () {
        $owner = User::factory()->create();
        Product::factory()->for($owner->team)->create(['barcode' => '4780000000017']);
        $product = Product::factory()->for($owner->team)->create(['barcode' => '4780000000024']);

        $response = $this->actingAs($owner)->put(route('products.update', $product), productInput());

        $response->assertSessionHasErrors(['barcode' => 'Bu shtrix-kod allaqachon band.']);
        expect($product->fresh()->barcode)->toBe('4780000000024');
    });

    test('another store\'s product is not found', function () {
        $owner = User::factory()->create();
        $foreignProduct = Product::factory()->create(['name' => 'Begona']);

        $this->actingAs($owner)->put(route('products.update', $foreignProduct), productInput())->assertNotFound();
        $this->actingAs($owner)->post(route('products.archive', $foreignProduct))->assertNotFound();

        expect(Product::withoutGlobalScopes()->find($foreignProduct->id))
            ->name->toBe('Begona')
            ->archived_at->toBeNull();
    });
});

describe('archive', function () {
    test('the owner archives and unarchives a product', function () {
        $owner = User::factory()->create();
        $product = Product::factory()->for($owner->team)->create();

        $this->actingAs($owner)->post(route('products.archive', $product))->assertRedirect(route('products.index'));
        expect($product->fresh()->isArchived())->toBeTrue();

        $this->actingAs($owner)->post(route('products.unarchive', $product))->assertRedirect(route('products.index'));
        expect($product->fresh()->isArchived())->toBeFalse();
    });
});
