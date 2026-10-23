<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    /**
     * Determine whether the user can change or delete the document: only drafts, and only with
     * the kind's draft permission. Executed documents are read-only (ADR-0006). Another store's
     * document is reported as not found.
     */
    public function update(User $user, Document $document): Response
    {
        if ($document->team_id !== $user->team_id) {
            return Response::denyAsNotFound();
        }

        return $document->isDraft() && $user->can($document->kind->draftPermission()->value)
            ? Response::allow()
            : Response::deny();
    }

    /**
     * Determine whether the user can delete the draft.
     */
    public function delete(User $user, Document $document): Response
    {
        return $this->update($user, $document);
    }

    /**
     * Determine whether the user may press Bajarish on the document. The execution module
     * repeats these checks inside its transaction.
     */
    public function execute(User $user, Document $document): Response
    {
        if ($document->team_id !== $user->team_id) {
            return Response::denyAsNotFound();
        }

        return $document->isDraft() && $user->can($document->kind->executePermission()->value)
            ? Response::allow()
            : Response::deny();
    }
}
