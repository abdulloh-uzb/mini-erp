<?php

use App\Models\Location;
use App\Models\User;

test('a store user reads only their own store\'s data', function () {
    $owner = User::factory()->create();
    $ownLocation = Location::factory()->for($owner->team)->create();
    $foreignLocation = Location::factory()->create();

    $this->actingAs($owner);

    expect(Location::pluck('id')->all())->toBe([$ownLocation->id])
        ->and(Location::find($foreignLocation->id))->toBeNull();
});

test('a store user cannot change another store\'s data', function () {
    $owner = User::factory()->create();
    $foreignLocation = Location::factory()->create(['name' => 'Begona ombor']);

    $this->actingAs($owner);

    $updated = Location::whereKey($foreignLocation->id)->update(['name' => 'O\'zgartirildi']);
    $deleted = Location::whereKey($foreignLocation->id)->delete();

    expect($updated)->toBe(0)
        ->and($deleted)->toBe(0)
        ->and(Location::withoutGlobalScopes()->find($foreignLocation->id)->name)->toBe('Begona ombor');
});

test('rows created by a store user always belong to their store', function () {
    $owner = User::factory()->create();
    $otherStoreOwner = User::factory()->create();

    $this->actingAs($owner);

    $location = Location::create(['name' => 'Ichki ombor', 'team_id' => $otherStoreOwner->team_id]);

    expect($location->team_id)->toBe($owner->team_id);
});

test('a super-admin reads no store\'s data', function () {
    Location::factory()->count(2)->create();

    $this->actingAs(User::factory()->superAdmin()->create());

    expect(Location::count())->toBe(0);
});
