<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;

/**
 * Sales returns (Qaytarishlar), created from an executed sale: employees draft them, the
 * owner executes. The refund reduces the customer's debt first, the rest is paid out.
 */
class SalesReturnController extends ReturnDocumentController
{
    /**
     * Get the kind of return the controller handles.
     */
    protected function kind(): DocumentKind
    {
        return DocumentKind::SalesReturn;
    }

    /**
     * Get the route name prefix of the controller's pages.
     */
    protected function routePrefix(): string
    {
        return 'sales-returns';
    }
}
