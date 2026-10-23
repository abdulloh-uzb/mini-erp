<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCounterpartyRequest;
use App\Models\Counterparty;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CounterpartyController extends Controller
{
    /**
     * Show the one list of customers, suppliers and loan parties, searchable by name or phone.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $phoneDigits = Phone::digits($search);

        $counterparties = Counterparty::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->when($phoneDigits !== '', fn ($query) => $query->orWhere('phone', 'like', '%'.$phoneDigits.'%'))))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Counterparty $counterparty) => $counterparty->only(['id', 'name', 'phone', 'note']));

        return Inertia::render('counterparties/index', [
            'counterparties' => $counterparties,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Create a counterparty and go back to the screen that asked for it, which picks the new
     * counterparty from the `counterparty` flash (quick-create inside a document).
     */
    public function store(SaveCounterpartyRequest $request): RedirectResponse
    {
        $counterparty = Counterparty::create($request->validated());

        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => __("Kontragent qo'shildi.")],
            'counterparty' => $counterparty->only(['id', 'name', 'phone']),
        ]);

        return back();
    }

    /**
     * Change a counterparty's name, phone or note.
     */
    public function update(SaveCounterpartyRequest $request, Counterparty $counterparty): RedirectResponse
    {
        $counterparty->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kontragent saqlandi.')]);

        return to_route('counterparties.index');
    }
}
