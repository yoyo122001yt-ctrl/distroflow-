<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\SalesOrder;
use App\Models\RetailStore;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateInvoice(int $salesOrderId): Invoice
    {
        $order = SalesOrder::with(['items', 'retailStore'])->findOrFail($salesOrderId);

        return DB::transaction(function () use ($order) {
            $invoiceNumber = 'INV-' . strtoupper(uniqid());

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'sales_order_id' => $order->id,
                'retail_store_id' => $order->retail_store_id,
                'invoice_date' => now(),
                'due_date' => $this->calculateDueDate($order->retailStore),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'tax' => $order->tax,
                'total' => $order->total,
                'amount_paid' => 0,
                'balance_due' => $order->total,
                'status' => 'pending',
            ]);

            return $invoice;
        });
    }

    private function calculateDueDate(RetailStore $store): \Carbon\Carbon
    {
        $terms = $store->payment_terms;
        return match ($terms) {
            'net_15' => now()->addDays(15),
            'net_30' => now()->addDays(30),
            'net_45' => now()->addDays(45),
            'net_60' => now()->addDays(60),
            default => now()->addDays(7),
        };
    }

    public function recordPayment(int $invoiceId, float $amount, string $method, array $extra = []): InvoicePayment
    {
        return DB::transaction(function () use ($invoiceId, $amount, $method, $extra) {
            $invoice = Invoice::findOrFail($invoiceId);

            $payment = InvoicePayment::create([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $extra['reference_number'] ?? null,
                'payment_date' => $extra['payment_date'] ?? now(),
                'notes' => $extra['notes'] ?? null,
            ]);

            $invoice->increment('amount_paid', $amount);
            $invoice->decrement('balance_due', $amount);

            if ($invoice->balance_due <= 0) {
                $invoice->update(['status' => 'paid']);
            } elseif ($invoice->amount_paid > 0) {
                $invoice->update(['status' => 'partial']);
            }

            if ($store = $invoice->retailStore) {
                $store->decrement('current_balance', $amount);
            }

            return $payment;
        });
    }

    public function getAgingReport(): array
    {
        $invoices = Invoice::with('retailStore')
            ->where('balance_due', '>', 0)
            ->get()
            ->groupBy(function ($invoice) {
                $days = $invoice->daysOverdue();
                if ($days <= 0) return 'current';
                if ($days <= 30) return '1-30';
                if ($days <= 60) return '31-60';
                if ($days <= 90) return '61-90';
                return '90+';
            });

        $totals = [];
        foreach (['current', '1-30', '31-60', '61-90', '90+'] as $bucket) {
            $totals[$bucket] = ($invoices[$bucket] ?? collect())->sum('balance_due');
        }

        return [
            'buckets' => $totals,
            'total_outstanding' => array_sum($totals),
            'details' => $invoices,
        ];
    }
}
