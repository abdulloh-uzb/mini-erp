<?php

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Location;
use App\Models\User;

/**
 * The shared document-list filters (story 79), used by every document list page.
 */
function listedNumbers(array $filters): array
{
    return Document::query()->ofKind(DocumentKind::Purchase)->filtered($filters)->orderBy('number')->pluck('number')->all();
}

test('the status tab keeps drafts or executed documents', function (DocumentStatus $status, array $numbers) {
    $owner = User::factory()->create();
    $purchases = Document::factory()->by($owner)->kind(DocumentKind::Purchase);
    $purchases->create();
    $purchases->executed()->create();
    $this->actingAs($owner);

    expect(listedNumbers(['status' => $status]))->toBe($numbers);
})->with([
    'Qoralama' => [DocumentStatus::Draft, [1]],
    'Bajarilgan' => [DocumentStatus::Executed, [2]],
]);

test('the date range uses the execution date, or the creation date of a draft', function () {
    $owner = User::factory()->create();
    $purchases = Document::factory()->by($owner)->kind(DocumentKind::Purchase);
    $purchases->create(['created_at' => '2026-10-01 10:00']);
    $purchases->executed()->create(['created_at' => '2026-10-01 10:00', 'executed_at' => '2026-10-05 18:00']);
    $purchases->create(['created_at' => '2026-10-09 10:00']);
    $this->actingAs($owner);

    expect(listedNumbers(['from' => '2026-10-02', 'to' => '2026-10-05']))->toBe([2])
        ->and(listedNumbers(['from' => '2026-10-01', 'to' => '2026-10-01']))->toBe([1]);
});

test('location, counterparty and author filters keep matching documents', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    Document::factory()->by($owner)->kind(DocumentKind::Purchase)->create(['location_id' => $location->id]);
    Document::factory()->by($owner)->kind(DocumentKind::Purchase)->create(['counterparty_id' => $supplier->id]);
    Document::factory()->by($employee)->kind(DocumentKind::Purchase)->create();
    $this->actingAs($owner);

    expect(listedNumbers(['location_id' => $location->id]))->toBe([1])
        ->and(listedNumbers(['counterparty_id' => $supplier->id]))->toBe([2])
        ->and(listedNumbers(['author_id' => $employee->id]))->toBe([3]);
});

test('the search finds a document by number or counterparty name', function (string $search) {
    $owner = User::factory()->create();
    $supplier = Counterparty::factory()->for($owner->team)->create(['name' => 'Anvar Coca-Cola']);
    Document::factory()->by($owner)->kind(DocumentKind::Purchase)->count(2)->create();
    Document::factory()->by($owner)->kind(DocumentKind::Purchase)->create(['counterparty_id' => $supplier->id]);
    $this->actingAs($owner);

    expect(listedNumbers(['search' => $search]))->toBe([3]);
})->with([
    'number' => '3',
    'number with №' => '№3',
    'part of the counterparty name' => 'anvar',
]);
