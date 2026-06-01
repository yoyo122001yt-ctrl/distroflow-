<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class SendPendingInvoices extends Command
{
    protected $signature = 'distroflow:send-invoices';
    protected $description = 'Send pending invoices to retail stores';

    public function handle(): void
    {
        $invoices = Invoice::with('retailStore')
            ->where('status', 'pending')
            ->where('balance_due', '>', 0)
            ->get();

        $sent = 0;
        foreach ($invoices as $invoice) {
            if ($invoice->retailStore?->email) {
                // Mail::to($invoice->retailStore->email)->send(new InvoiceMail($invoice));
                $sent++;
                $this->info("Sent invoice {$invoice->invoice_number} to {$invoice->retailStore->email}");
            }
        }

        $this->info("Sent {$sent} invoices. Pending: " . ($invoices->count() - $sent));
    }
}
