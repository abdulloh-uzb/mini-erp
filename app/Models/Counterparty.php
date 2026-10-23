<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use Database\Factories\CounterpartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A customer, supplier or loan party (UI: kontragent); its roles are implied by usage.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'name', 'phone', 'note'])]
class Counterparty extends Model
{
    /** @use HasFactory<CounterpartyFactory> */
    use BelongsToStore, HasFactory;
}
