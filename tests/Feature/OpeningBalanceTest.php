<?php

use App\Actions\Cash\CashRegister;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{note: string|null, lines: array<int, array<string, mixed>>, payments: array<string, string|null>, loans: array<int, array<string, mixed>>}
 */
function openingBalanceInput(array $lines, ?string $note = null, array $payments = [], array $loans = []): array
{
    return ['note' => $note, 'lines' => $lines, 'payments' => $payments, 'loans' => $loans];
}

describe('show', function () {
    test('the owner opens an empty opening balance with the active locations and products', function () {
        $owner = User::factory()->create();
        $warehouse = Location::factory()->for($owner->team)->create(['name' => 'Asosiy ombor']);
        Location::factory()->inside($warehouse)->create(['name' => 'Muzlatgich']);
        Location::factory()->for($owner->team)->archived()->create();
        Product::factory()->for($owner->team)->create(['name' => 'Fanta 1 L', 'pieces_per_block' => 12]);
        Product::factory()->for($owner->team)->archived()->create();
        Product::factory()->create();

        $this->actingAs($owner)->get(route('opening-balance.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('opening-balance/show')
                ->where('document', null)
                ->where('skipped', false)
                ->has('locations', 2)
                ->where('locations.1.name', 'Asosiy ombor / Muzlatgich')
                ->has('products', 1)
                ->where('products.0.name', 'Fanta 1 L')
                ->where('products.0.pieces_per_block', 12),
            );
    });
});

describe('save', function () {
    test('the owner saves the stock section as a draft, blocks and pieces stored as pieces', function () {
        $owner = User::factory()->create();
        $location = Location::factory()->for($owner->team)->create();
        $product = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);

        $response = $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput([
            ['location_id' => $location->id, 'product_id' => $product->id, 'blocks' => 2, 'pieces' => 3, 'price' => '4500.50'],
        ], 'Dastlabki sanoq'));

        $response->assertRedirect(route('opening-balance.show'));
        $document = Document::query()->sole();
        expect($document)
            ->kind->toBe(DocumentKind::OpeningBalance)
            ->status->toBe(DocumentStatus::Draft)
            ->number->toBe(1)
            ->note->toBe('Dastlabki sanoq')
            ->author_id->toBe($owner->id)
            ->and($document->lines->sole()->only(['location_id', 'product_id', 'quantity', 'pieces_per_block', 'price']))->toBe([
                'location_id' => $location->id,
                'product_id' => $product->id,
                'quantity' => 27,
                'pieces_per_block' => 12,
                'price' => '4500.50',
            ]);
    });

    test('saving again replaces the draft\'s lines', function () {
        $owner = User::factory()->create();
        $location = Location::factory()->for($owner->team)->create();
        $cola = Product::factory()->for($owner->team)->create();
        $water = Product::factory()->for($owner->team)->create();
        $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput([
            ['location_id' => $location->id, 'product_id' => $cola->id, 'blocks' => 0, 'pieces' => 5, 'price' => null],
        ]));

        $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput([
            ['location_id' => $location->id, 'product_id' => $water->id, 'blocks' => 0, 'pieces' => 9, 'price' => '1000'],
        ]));

        expect(Document::query()->count())->toBe(1)
            ->and(DocumentLine::query()->pluck('quantity', 'product_id')->all())->toBe([$water->id => 9]);
    });

    test('a line needs a quantity, an active location and an active product of the store', function () {
        $owner = User::factory()->create();
        $archivedLocation = Location::factory()->for($owner->team)->archived()->create();
        $foreignProduct = Product::factory()->create();

        $response = $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput([
            ['location_id' => $archivedLocation->id, 'product_id' => $foreignProduct->id, 'blocks' => 0, 'pieces' => 0, 'price' => null],
        ]));

        $response->assertSessionHasErrors([
            'lines.0.location_id' => 'Tanlangan joy topilmadi.',
            'lines.0.product_id' => 'Tanlangan mahsulot topilmadi.',
            'lines.0.pieces' => 'Miqdorni kiriting.',
        ]);
        expect(Document::query()->count())->toBe(0);
    });
});

describe('kassa and loans', function () {
    test('the owner saves opening cash per method and open loans with the draft', function () {
        $owner = User::factory()->create();
        $lender = Counterparty::factory()->for($owner->team)->create(['name' => 'Akam']);

        $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput(
            [],
            payments: ['cash' => '1250000', 'card' => null, 'bank_transfer' => '40000.50'],
            loans: [['direction' => 'received', 'counterparty_id' => $lender->id, 'principal' => '3000000', 'due_date' => '2027-03-01', 'note' => null]],
        ))->assertSessionHasNoErrors();

        $this->actingAs($owner)->get(route('opening-balance.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.payments', ['cash' => '1250000.00', 'bank_transfer' => '40000.50'])
                ->has('document.loans', 1)
                ->where('document.loans.0.direction', 'received')
                ->where('document.loans.0.counterparty_id', $lender->id)
                ->where('document.loans.0.counterparty_name', 'Akam')
                ->where('document.loans.0.principal', '3000000.00')
                ->where('document.loans.0.due_date', '2027-03-01')
                ->where('counterparties.0.name', 'Akam'),
            );
    });

    test('an opening loan needs a counterparty of the store, a direction and a principal above zero; cash cannot be negative', function () {
        $owner = User::factory()->create();
        $foreign = Counterparty::factory()->create();

        $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput(
            [],
            payments: ['cash' => '-1'],
            loans: [['direction' => 'lent', 'counterparty_id' => $foreign->id, 'principal' => '0']],
        ))->assertSessionHasErrors(['payments.cash', 'loans.0.direction', 'loans.0.counterparty_id', 'loans.0.principal']);

        expect(Document::query()->count())->toBe(0);
    });

    test('executing puts the opening cash in the register and opens the loans', function () {
        $owner = User::factory()->create();
        $borrower = Counterparty::factory()->for($owner->team)->create();

        $this->actingAs($owner)->post(route('opening-balance.execute'), openingBalanceInput(
            [],
            payments: ['cash' => '500000', 'card' => '20000'],
            loans: [['direction' => 'given', 'counterparty_id' => $borrower->id, 'principal' => '100000', 'due_date' => null]],
        ))->assertSessionHasNoErrors();

        expect(app(CashRegister::class)->balances($owner->team))->toBe(['cash' => '500000.00', 'card' => '20000.00', 'bank_transfer' => '0.00'])
            ->and(Loan::query()->sole()->balance())->toBe('100000.00');
    });
});

describe('execute', function () {
    test('executing saves the form, puts the stock in place and makes the page read-only', function () {
        $owner = User::factory()->create(['name' => 'Aziz']);
        $location = Location::factory()->for($owner->team)->create();
        $product = Product::factory()->for($owner->team)->create(['pieces_per_block' => 6]);

        $response = $this->actingAs($owner)->post(route('opening-balance.execute'), openingBalanceInput([
            ['location_id' => $location->id, 'product_id' => $product->id, 'blocks' => 3, 'pieces' => 1, 'price' => '5000'],
        ]));

        $response->assertRedirect(route('opening-balance.show'));
        expect(StockBalance::query()->where('location_id', $location->id)->value('quantity'))->toBe(19);
        $this->actingAs($owner)->get(route('opening-balance.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.status', 'executed')
                ->where('document.author', 'Aziz')
                ->where('document.executor', 'Aziz')
                ->where('document.lines.0.quantity', 19),
            );
    });

    test('a refused execution keeps the saved draft and names the failing line', function () {
        $owner = User::factory()->create();
        $location = Location::factory()->for($owner->team)->create();
        $product = Product::factory()->for($owner->team)->create();

        $response = $this->actingAs($owner)->post(route('opening-balance.execute'), openingBalanceInput([
            ['location_id' => $location->id, 'product_id' => $product->id, 'blocks' => 0, 'pieces' => 4, 'price' => null],
        ]));

        $response->assertSessionHasErrors(['lines.0.price' => 'Tannarxni kiriting.']);
        expect(Document::query()->sole()->status)->toBe(DocumentStatus::Draft)
            ->and(DocumentLine::query()->sole()->quantity)->toBe(4)
            ->and(StockBalance::query()->count())->toBe(0);
    });

    test('an executed opening balance cannot be saved, executed or deleted', function () {
        $owner = User::factory()->create();
        $location = Location::factory()->for($owner->team)->create();
        $product = Product::factory()->for($owner->team)->create();
        $document = Document::factory()->by($owner)->executed()->create();
        $line = DocumentLine::factory()->for($document)->create(['location_id' => $location->id, 'product_id' => $product->id, 'quantity' => 5]);
        $input = openingBalanceInput([['location_id' => $location->id, 'product_id' => $product->id, 'blocks' => 0, 'pieces' => 50, 'price' => '1']]);

        $this->actingAs($owner)->put(route('opening-balance.update'), $input)->assertForbidden();
        $this->actingAs($owner)->post(route('opening-balance.execute'), $input)->assertForbidden();
        $this->actingAs($owner)->delete(route('opening-balance.destroy'))->assertForbidden();

        expect($document->fresh()->status)->toBe(DocumentStatus::Executed)
            ->and($line->fresh()->quantity)->toBe(5);
    });
});

describe('delete', function () {
    test('the owner deletes the draft opening balance', function () {
        $owner = User::factory()->create();
        $document = Document::factory()->by($owner)->create();
        DocumentLine::factory()->for($document)->create();

        $this->actingAs($owner)->delete(route('opening-balance.destroy'))->assertRedirect(route('opening-balance.show'));

        expect(Document::query()->count())->toBe(0)
            ->and(DocumentLine::query()->count())->toBe(0);
    });
});

describe('skip', function () {
    test('"Boshlang\'ich qoldiq yo\'q" opens execution, drops the draft and hides the home card', function () {
        $owner = User::factory()->create();
        Document::factory()->by($owner)->create();
        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('openingBalancePending', true));

        $this->actingAs($owner)->post(route('opening-balance.skip'))->assertRedirect(route('dashboard'));

        $owner = $owner->fresh();
        expect($owner->team->hasSettledOpeningBalance())->toBeTrue()
            ->and(Document::query()->count())->toBe(0);
        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('openingBalancePending', false));
        $this->actingAs($owner)->put(route('opening-balance.update'), openingBalanceInput([]))
            ->assertSessionHasErrors(['document' => "Boshlang'ich qoldiq yo'q deb belgilangan."]);
    });

    test('the store cannot say it has none after executing its opening balance', function () {
        $owner = User::factory()->create();
        Document::factory()->by($owner)->executed()->create();

        $this->actingAs($owner)->post(route('opening-balance.skip'))
            ->assertSessionHasErrors(['document' => "Boshlang'ich qoldiq allaqachon bajarilgan."]);

        expect($owner->team->fresh()->opening_balance_skipped_at)->toBeNull();
    });

    test('the home card disappears once the opening balance is executed', function () {
        $owner = User::factory()->create();
        Document::factory()->by($owner)->executed()->create();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('openingBalancePending', false));
    });
});

describe('permissions', function () {
    test('an employee cannot open, save, execute, delete or skip the opening balance', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        Document::factory()->by($owner)->create();

        $this->actingAs($employee)->get(route('opening-balance.show'))->assertForbidden();
        $this->actingAs($employee)->put(route('opening-balance.update'), openingBalanceInput([]))->assertForbidden();
        $this->actingAs($employee)->post(route('opening-balance.execute'), openingBalanceInput([]))->assertForbidden();
        $this->actingAs($employee)->delete(route('opening-balance.destroy'))->assertForbidden();
        $this->actingAs($employee)->post(route('opening-balance.skip'))->assertForbidden();

        expect(Document::query()->sole()->status)->toBe(DocumentStatus::Draft)
            ->and($owner->team->fresh()->opening_balance_skipped_at)->toBeNull();
    });

    test('an employee\'s home page has no opening-balance card', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();

        $this->actingAs($employee)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('openingBalancePending', false));
    });

    test('each store sees only its own opening balance', function () {
        $owner = User::factory()->create();
        Document::factory()->create(['note' => 'Begona']);

        $this->actingAs($owner)->get(route('opening-balance.show'))
            ->assertInertia(fn (Assert $page) => $page->where('document', null));
    });
});
