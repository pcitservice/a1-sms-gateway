<?php

namespace App\Domain\Billing;

use App\Models\Invoice;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Produces an internal `invoices` row (plus a stored PDF) from Stripe's
 * invoice payload. The internal record exists so customers can re-download
 * their VAT invoices without needing to log into Stripe.
 */
class InvoiceGenerator
{
    public function fromStripe(Team $team, array $stripeInvoice): Invoice
    {
        $subtotal = (int) ($stripeInvoice['subtotal'] ?? 0);
        $tax      = (int) ($stripeInvoice['tax'] ?? 0);
        $total    = (int) ($stripeInvoice['total'] ?? $subtotal + $tax);

        $lines = [];
        foreach (($stripeInvoice['lines']['data'] ?? []) as $l) {
            $lines[] = [
                'description' => $l['description'] ?? '',
                'quantity'    => $l['quantity']    ?? 1,
                'unit_amount' => $l['price']['unit_amount'] ?? 0,
                'amount'      => $l['amount']      ?? 0,
            ];
        }

        $number = Invoice::query()->withoutGlobalScopes()
            ->where('team_id', $team->id)->max('id') + 1;
        $number = sprintf('a1sms-%s-%05d', now()->year, $number);

        $invoice = Invoice::create([
            'team_id'           => $team->id,
            'number'            => $number,
            'stripe_invoice_id' => $stripeInvoice['id'] ?? null,
            'status'            => $stripeInvoice['status'] ?? 'open',
            'currency'          => strtoupper($stripeInvoice['currency'] ?? 'DKK'),
            'subtotal_ore'      => $subtotal,
            'vat_ore'           => $tax,
            'total_ore'         => $total,
            'line_items'        => $lines,
            'issued_at'         => isset($stripeInvoice['created']) ? now()->createFromTimestamp($stripeInvoice['created']) : now(),
            'paid_at'           => isset($stripeInvoice['status_transitions']['paid_at'])
                ? now()->createFromTimestamp($stripeInvoice['status_transitions']['paid_at']) : null,
        ]);

        try {
            $pdf = Pdf::loadView('invoices.template', [
                'invoice' => $invoice,
                'team'    => $team,
            ]);
            $path = "invoices/{$team->id}/{$invoice->number}.pdf";
            Storage::put($path, $pdf->output());
            $invoice->update(['pdf_path' => $path]);
        } catch (\Throwable) {
            // PDF generation is non-fatal; the invoice row still exists.
        }

        return $invoice;
    }
}
