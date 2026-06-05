<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $invoice->invoice_number ?? $invoice->id }}</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #333; line-height: 1.5; direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}; }
        @if(app()->getLocale() === 'ar')
        .header { flex-direction: row-reverse; }
        .details { flex-direction: row-reverse; }
        .invoice-title { text-align: left; }
        .table th { text-align: right; }
        .summary { text-align: left; }
        .qr-code { text-align: left; }
        @endif
        .header { display: flex; justify-content: space-between; align-items: start; padding-bottom: 1.5rem; border-bottom: 2px solid #2563eb; margin-bottom: 1.5rem; }
        .header .company { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
        .header .company-details { font-size: 9pt; color: #666; }
        .invoice-title { text-align: right; }
        .invoice-title h1 { font-size: 1.75rem; color: #2563eb; margin: 0; }
        .invoice-title .tax-info { font-size: 9pt; color: #666; }
        .details { display: flex; justify-content: space-between; margin-bottom: 1.5rem; }
        .details .bill-to h3 { font-size: 10pt; text-transform: uppercase; color: #666; margin-bottom: 0.25rem; }
        .details .bill-to p { margin: 0; }
        .table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .table th { background: #2563eb; color: #fff; padding: 0.5rem 0.75rem; text-align: left; font-size: 9pt; text-transform: uppercase; }
        .table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e5e7eb; }
        .table .total-row td { font-weight: 700; font-size: 12pt; border-top: 2px solid #333; border-bottom: none; }
        .summary { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; text-align: right; }
        .summary .total-label { font-size: 10pt; color: #666; }
        .summary .total-amount { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
        .footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 8pt; color: #999; text-align: center; }
        .qr-code { text-align: right; margin-top: 1rem; }
        .tax-reg { font-size: 9pt; color: #666; margin-bottom: 0.5rem; }
        .table .qty { text-align: center; }
        .table .price, .table .total { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="company">{{ config('app.name', 'DistroFlow WMS') }}</div>
            <div class="company-details">
                {{ config('app.company_address', 'Cairo, Egypt') }}<br>
                {{ __('invoice.taxRegistration', ['number' => config('app.tax_registration_number', '123-456-789')]) }}<br>
                {{ __('invoice.commercialRegister', ['number' => config('app.commercial_register', '12345')]) }}<br>
                {{ __('invoice.phone', ['number' => config('app.company_phone', '+20 2 12345678')]) }}
            </div>
        </div>
        <div class="invoice-title">
            <h1>{{ __('invoice.title') }}</h1>
            <div>#{{ $invoice->invoice_number ?? $invoice->id }}</div>
            <div class="tax-info">{{ __('invoice.date', ['date' => ($invoice->created_at ?? now())->format('d/m/Y')]) }}</div>
            <div class="tax-info">{{ __('invoice.dueDate', ['date' => ($invoice->due_date ?? now()->addDays(30))->format('d/m/Y')]) }}</div>
        </div>
    </div>

    <div class="details">
        <div class="bill-to">
            <h3>{{ __('invoice.billTo') }}</h3>
            <p>
                <strong>{{ $invoice->retailStore->business_name ?? $invoice->customer_name ?? 'N/A' }}</strong><br>
                {{ $invoice->retailStore->address ?? $invoice->customer_address ?? '' }}<br>
                {{ $invoice->retailStore->phone ?? $invoice->customer_phone ?? '' }}<br>
                {{ __('invoice.tax', ['number' => $invoice->retailStore->tax_number ?? 'N/A']) }}
            </p>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 50%;">{{ __('invoice.item') }}</th>
                <th class="qty">{{ __('invoice.qty') }}</th>
                <th class="price">{{ __('invoice.unitPrice') }}</th>
                <th class="total">{{ __('invoice.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $subtotal = 0; @endphp
            @foreach($items ?? $invoice->items ?? [] as $item)
            @php
                $lineTotal = ($item->quantity ?? $item['quantity'] ?? 0) * ($item->unit_price ?? $item['unit_price'] ?? 0);
                $subtotal += $lineTotal;
            @endphp
            <tr>
                <td>{{ $item->product_name ?? $item['name'] ?? 'Product' }}</td>
                <td class="qty">{{ $item->quantity ?? $item['quantity'] ?? 0 }}</td>
                <td class="price">{{ number_format($item->unit_price ?? $item['unit_price'] ?? 0, 2) }} EGP</td>
                <td class="total">{{ number_format($lineTotal, 2) }} EGP</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $taxRate = config('app.tax_rate', 0.14);
        $taxAmount = $subtotal * $taxRate;
        $grandTotal = $subtotal + $taxAmount;
    @endphp

    <div class="summary">
        <div class="tax-reg">{{ __('invoice.subtotal') }}: {{ number_format($subtotal, 2) }} EGP</div>
        <div class="tax-reg">{{ __('invoice.vat', ['rate' => $taxRate * 100]) }}: {{ number_format($taxAmount, 2) }} EGP</div>
        <div class="total-label">{{ __('invoice.totalDue') }}</div>
        <div class="total-amount">{{ number_format($grandTotal, 2) }} EGP</div>
    </div>

    <div class="qr-code">
        {{-- Placeholder for ETA QR code (Egypt Tax Authority) --}}
        <div style="display: inline-block; border: 1px solid #ccc; padding: 0.5rem;">
            <div style="font-size: 8pt; color: #666; text-align: center;">{{ __('invoice.etaQrCode') }}</div>
            <div style="width: 80px; height: 80px; background: #f3f4f6; margin-top: 0.25rem;"></div>
        </div>
    </div>

    <div class="footer">
        <p>{{ __('invoice.footer') }}</p>
        <p>{{ __('invoice.paymentTerms') }}</p>
        <p>{{ __('invoice.computerGenerated') }}</p>
    </div>
</body>
</html>
