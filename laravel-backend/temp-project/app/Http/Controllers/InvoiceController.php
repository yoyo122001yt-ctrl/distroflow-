<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function download(SalesOrder $order)
    {
        $invoice = $order->invoice ?? $order;
        $items = $order->items;

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'items'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('invoice-' . ($invoice->invoice_number ?? $order->id) . '.pdf');
    }

    public function preview(SalesOrder $order)
    {
        $invoice = $order->invoice ?? $order;
        $items = $order->items;

        return view('pdf.invoice', compact('invoice', 'items'));
    }

    public function stream(SalesOrder $order)
    {
        $invoice = $order->invoice ?? $order;
        $items = $order->items;

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'items'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('invoice-' . ($invoice->invoice_number ?? $order->id) . '.pdf');
    }
}
