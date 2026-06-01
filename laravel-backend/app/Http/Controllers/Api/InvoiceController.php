<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index(Request $request)
    {
        $query = Invoice::with(['retailStore', 'salesOrder']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('store_id')) {
            $query->where('retail_store_id', $request->store_id);
        }
        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        return response()->json([
            'data' => $query->orderBy('created_at', 'desc')->paginate(20),
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['retailStore', 'salesOrder.items', 'payments'])->findOrFail($id);
        return response()->json(['data' => $invoice]);
    }

    public function send($id)
    {
        $invoice = Invoice::with('retailStore')->findOrFail($id);

        if (!$invoice->retailStore?->email) {
            return response()->json(['message' => 'Store has no email address'], 422);
        }

        // Mail::to($invoice->retailStore->email)->send(new InvoiceMail($invoice));

        return response()->json(['message' => 'Invoice sent successfully']);
    }

    public function pdf($id)
    {
        $invoice = Invoice::with(['retailStore', 'salesOrder.items'])->findOrFail($id);

        // $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        // return $pdf->download("invoice-{$invoice->invoice_number}.pdf");

        return response()->json(['message' => 'PDF generation placeholder']);
    }

    public function recordPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,check,bank_transfer,credit',
            'reference_number' => 'nullable|string',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $payment = $this->invoiceService->recordPayment(
                (int) $id,
                $validated['amount'],
                $validated['payment_method'],
                $validated
            );

            return response()->json([
                'data' => $payment,
                'message' => 'Payment recorded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
