<?php

namespace App\Http\Controllers;

use App\Enums\DocumentKind;

/**
 * Supplier returns (Yetkazib beruvchiga qaytarishlar), created from an executed purchase:
 * owner only (D10), since they are valued at purchase prices. They reduce the supplier debt
 * first, the rest comes back into the register.
 */
class SupplierReturnController extends ReturnDocumentController
{
    /**
     * Get the kind of return the controller handles.
     */
    protected function kind(): DocumentKind
    {
        return DocumentKind::SupplierReturn;
    }

    /**
     * Get the route name prefix of the controller's pages.
     */
    protected function routePrefix(): string
    {
        return 'supplier-returns';
    }
}
