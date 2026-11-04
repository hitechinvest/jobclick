<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;

class InvoiceController extends Controller
{
    public function __invoke(Invoice $invoice, InvoiceService $invoices)
    {
        abort_unless($invoice->user_id === auth()->id() || auth()->user()?->is_admin, 404);

        return response($invoices->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="schet-'.$invoice->number.'.pdf"',
        ]);
    }
}
