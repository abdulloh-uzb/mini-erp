<?php

namespace App\Actions\Documents;

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\LoanLedger;
use App\Enums\CashOperationType;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The document execution module: the one way a document takes effect. In one transaction it
 * checks the document may be executed, locks the rows it reads, validates per kind, applies
 * the effects and stamps the executor. A failure throws and changes nothing.
 */
class ExecuteDocument
{
    public function __construct(
        private StockLedger $stock,
        private CashRegister $register,
        private LoanLedger $loans,
    ) {}

    /**
     * Execute the draft document as the given user.
     */
    public function handle(Document $document, User $user): void
    {
        DB::transaction(function () use ($document, $user) {
            $locked = Document::withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->ensureExecutable($locked, $user);

            $lines = $locked->lines()->get()->each(fn (DocumentLine $line) => $line->setRelation('document', $locked));
            $this->stock->lockProducts($locked->team_id, $lines->pluck('product_id')->all());

            match ($locked->kind) {
                DocumentKind::OpeningBalance => $this->executeOpeningBalance($locked, $user, $lines->all()),
                default => throw new LogicException("Documents of kind [{$locked->kind->value}] cannot be executed yet."),
            };

            $locked->update([
                'status' => DocumentStatus::Executed,
                'executor_id' => $user->id,
                'executed_at' => now(),
            ]);

            $document->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Check the user may execute this kind, the document is still a draft and the opening
     * balance gate (D13) is open. The store row is locked so that executing the opening
     * balance and choosing "Boshlang'ich qoldiq yo'q" cannot both happen.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function ensureExecutable(Document $document, User $user): void
    {
        if ($user->team_id !== $document->team_id || ! $user->can($document->kind->executePermission()->value)) {
            throw new AuthorizationException;
        }

        if (! $document->isDraft()) {
            throw ValidationException::withMessages(['document' => __('Hujjat allaqachon bajarilgan.')]);
        }

        $store = Team::query()->whereKey($document->team_id)->lockForUpdate()->firstOrFail();

        if ($document->kind === DocumentKind::OpeningBalance) {
            if ($store->hasSkippedOpeningBalance()) {
                throw ValidationException::withMessages(['document' => __("Boshlang'ich qoldiq yo'q deb belgilangan.")]);
            }

            if ($store->hasExecutedOpeningBalance()) {
                throw ValidationException::withMessages(['document' => __("Boshlang'ich qoldiq allaqachon bajarilgan.")]);
            }

            return;
        }

        if (! $store->hasSettledOpeningBalance()) {
            throw ValidationException::withMessages([
                'document' => __("Avval boshlang'ich qoldiqni kiriting yoki «Boshlang'ich qoldiq yo'q» ni tanlang."),
            ]);
        }
    }

    /**
     * Put the opening stock in place, each line at its own location and at its cost per piece,
     * put the opening cash in the register per method and open the loans already running.
     * Opening loans move no money: the opening cash already reflects them.
     *
     * @param  array<int, DocumentLine>  $lines
     */
    private function executeOpeningBalance(Document $document, User $user, array $lines): void
    {
        $this->validateLines($lines, fn (DocumentLine $line) => $line->price === null ? ['price' => __('Tannarxni kiriting.')] : []);

        foreach ($lines as $line) {
            $this->stock->receive($line, (int) $line->location_id, $line->quantity, $line->price);
        }

        foreach ($document->payments()->get() as $payment) {
            if (bccomp($payment->amount, '0', 2) > 0) {
                $this->register->record($user, CashOperationType::OpeningBalance, $payment->payment_method, $payment->amount, documentId: $document->id);
            }
        }

        foreach ($document->openingLoans()->get() as $openingLoan) {
            $this->loans->openExisting($user, $openingLoan);
        }
    }

    /**
     * Throw one error per failing line field, keyed like the form (`lines.N.field`).
     *
     * @param  array<int, DocumentLine>  $lines
     * @param  Closure(DocumentLine): array<string, string>  $check
     *
     * @throws ValidationException
     */
    private function validateLines(array $lines, Closure $check): void
    {
        $errors = [];

        foreach (array_values($lines) as $index => $line) {
            foreach ($check($line) as $field => $message) {
                $errors["lines.$index.$field"] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
