<?php

namespace App\Http\Controllers;

use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\SaveDraft;
use App\Enums\DocumentKind;
use App\Http\Requests\SaveOpeningBalanceRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's one opening balance (owner only): saved as often as needed, executed once.
 * Until it is executed, or the owner says there is none, no document can be executed (D13).
 */
class OpeningBalanceController extends Controller
{
    /**
     * Show the opening balance: editable while a draft, read-only once executed.
     */
    public function show(Request $request): Response
    {
        $document = $this->openingBalance()?->load(['lines.product', 'lines.location.parent', 'author', 'executor']);

        return Inertia::render('opening-balance/show', [
            'document' => $document ? DocumentResource::make($document)->resolve($request) : null,
            'skipped' => $request->user()->team->hasSkippedOpeningBalance(),
            'locations' => Location::pickerOptions(Location::query()->active()),
            'products' => Product::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'barcode', 'pieces_per_block']),
        ]);
    }

    /**
     * Save the opening balance as a draft, creating it on the first save.
     */
    public function update(SaveOpeningBalanceRequest $request, SaveDraft $saveDraft): RedirectResponse
    {
        $this->save($request, $saveDraft);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saqlandi.')]);

        return to_route('opening-balance.show');
    }

    /**
     * Save the form and execute the opening balance. If execution is refused the draft stays
     * saved and the errors come back per line.
     */
    public function execute(SaveOpeningBalanceRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $document = $this->save($request, $saveDraft);
        Gate::authorize('execute', $document);

        $executeDocument->handle($document, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Boshlang'ich qoldiq bajarildi.")]);

        return to_route('opening-balance.show');
    }

    /**
     * Delete the draft opening balance.
     */
    public function destroy(): RedirectResponse
    {
        $document = $this->openingBalance() ?? abort(404);
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Qoralama o'chirildi.")]);

        return to_route('opening-balance.show');
    }

    /**
     * Record that the store starts with no opening balance ("Boshlang'ich qoldiq yo'q"), which
     * opens document execution. A draft opening balance is deleted.
     */
    public function skip(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $store = $request->user()->team()->lockForUpdate()->firstOrFail();
            $document = $this->openingBalance();

            if ($document !== null && ! $document->isDraft()) {
                throw ValidationException::withMessages(['document' => __("Boshlang'ich qoldiq allaqachon bajarilgan.")]);
            }

            $document?->delete();
            $store->update(['opening_balance_skipped_at' => $store->opening_balance_skipped_at ?? now()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Boshlang'ich qoldiqsiz ishlash boshlandi.")]);

        return to_route('dashboard');
    }

    /**
     * Get the store's opening balance, if one has been saved.
     */
    private function openingBalance(): ?Document
    {
        return Document::query()->ofKind(DocumentKind::OpeningBalance)->first();
    }

    /**
     * Create or update the draft from the form.
     */
    private function save(SaveOpeningBalanceRequest $request, SaveDraft $saveDraft): Document
    {
        $this->ensureNotSkipped($request);

        $document = $this->openingBalance();

        if ($document !== null) {
            Gate::authorize('update', $document);
        }

        return $saveDraft->handle(
            $document,
            DocumentKind::OpeningBalance,
            $request->user(),
            ['note' => $request->validated('note')],
            $request->documentLines(),
        );
    }

    /**
     * Refuse to change the opening balance once the owner said there is none.
     */
    private function ensureNotSkipped(Request $request): void
    {
        if ($request->user()->team->hasSkippedOpeningBalance()) {
            throw ValidationException::withMessages(['document' => __("Boshlang'ich qoldiq yo'q deb belgilangan.")]);
        }
    }
}
