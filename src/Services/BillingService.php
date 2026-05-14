<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class BillingService
{
    private DB $db;
    private array $invoiceColumns = [];

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->invoiceColumns = $this->loadInvoiceColumns();
    }

    private function loadInvoiceColumns(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT column_name
                 FROM information_schema.columns
                 WHERE table_schema = 'public'
                   AND table_name = 'billing_invoices'"
            );
            $cols = [];
            foreach ($rows as $row) {
                $name = (string)($row['column_name'] ?? '');
                if ($name !== '') {
                    $cols[$name] = true;
                }
            }
            return $cols;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasInvoiceColumn(string $column): bool
    {
        return isset($this->invoiceColumns[$column]);
    }

    public function getInvoices(string $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM billing_invoices WHERE organisation_id = :org_id ORDER BY created_at DESC',
            ['org_id' => $orgId]
        );
    }

    public function getInvoice(string $id): array|false
    {
        $invoice = $this->db->fetch(
            'SELECT * FROM billing_invoices WHERE id = :id',
            ['id' => $id]
        );

        if (!$invoice) {
            return false;
        }

        $lineItems = $this->db->fetchAll(
            'SELECT * FROM billing_line_items WHERE invoice_id = :id',
            ['id' => $id]
        );

        $invoice['line_items'] = $lineItems;
        return $invoice;
    }

    public function createInvoice(string $orgId, array $items): string
    {
        $subtotal = 0.0;
        $normalized = [];
        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 1);
            $unit = (float)($item['unit_price'] ?? 0);
            $taxRate = (float)($item['tax_rate'] ?? 0);
            $lineTotal = isset($item['line_total'])
                ? (float) $item['line_total']
                : (float)($item['total'] ?? ($qty * $unit * (1 + $taxRate / 100)));
            $subtotal += $lineTotal;
            $normalized[] = [
                'description' => (string)($item['description'] ?? 'Line item'),
                'quantity'    => $qty,
                'unit_price'  => $unit,
                'tax_rate'    => $taxRate,
                'line_total'  => $lineTotal,
                'product_id'  => $item['product_id'] ?? null,
            ];
        }

        $taxAmount = 0.0;
        $total = $subtotal + $taxAmount;
        $number = 'INV-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)) . '-' . date('Ymd');

        $data = array_filter([
            'organisation_id' => $orgId,
            'invoice_number'  => $number,
            'amount_total'    => $this->hasInvoiceColumn('amount_total') ? $total : null,
            'subtotal'        => $this->hasInvoiceColumn('subtotal') ? $subtotal : null,
            'tax_amount'      => $this->hasInvoiceColumn('tax_amount') ? $taxAmount : null,
            'total'           => $this->hasInvoiceColumn('total') ? $total : null,
            'amount_paid'     => $this->hasInvoiceColumn('amount_paid') ? 0 : null,
            'amount_due'      => $this->hasInvoiceColumn('amount_due') ? $total : null,
            'currency'        => 'ZAR',
            'status'          => $this->hasInvoiceColumn('status') ? ($this->hasInvoiceColumn('total') ? 'issued' : 'pending') : null,
            'due_date'        => $this->hasInvoiceColumn('due_date') ? date('Y-m-d', strtotime('+30 days')) : null,
            'issued_at'       => $this->hasInvoiceColumn('issued_at') ? date('Y-m-d H:i:s') : null,
            'created_at'      => $this->hasInvoiceColumn('created_at') ? date('Y-m-d H:i:s') : null,
            'updated_at'      => $this->hasInvoiceColumn('updated_at') ? date('Y-m-d H:i:s') : null,
            'notes'           => $this->hasInvoiceColumn('notes') ? ($taxAmount > 0 ? ('Includes tax amount: ' . number_format($taxAmount, 2, '.', '')) : null) : null,
        ], static fn($v) => $v !== null);

        $invoiceId = $this->db->insert('billing_invoices', $data);

        foreach ($normalized as $item) {
            $insert = [
                'invoice_id'  => $invoiceId,
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'tax_rate'    => $item['tax_rate'],
                'line_total'  => $item['line_total'],
                'metadata'    => '{}',
            ];
            if (!empty($item['product_id'])) {
                $insert['product_id'] = $item['product_id'];
            }
            $this->db->insert('billing_line_items', $insert);
        }

        return $invoiceId;
    }

    public function markPaid(string $invoiceId): int
    {
        $invoice = $this->db->fetch(
            'SELECT * FROM billing_invoices WHERE id = :id',
            ['id' => $invoiceId]
        ) ?: [];
        $total = (float)($invoice['amount_total'] ?? $invoice['total'] ?? 0);

        return $this->db->update('billing_invoices', array_filter([
            'status'      => $this->hasInvoiceColumn('status') ? 'paid' : null,
            'amount_paid' => $this->hasInvoiceColumn('amount_paid') ? $total : null,
            'amount_due'  => $this->hasInvoiceColumn('amount_due') ? 0 : null,
            'paid_at'     => $this->hasInvoiceColumn('paid_at') ? date('Y-m-d H:i:s') : null,
            'updated_at'  => $this->hasInvoiceColumn('updated_at') ? date('Y-m-d H:i:s') : null,
        ], static fn($v) => $v !== null), ['id' => $invoiceId]);
    }

    /**
     * Marks subscription credits as allocated for this invoice (when columns exist).
     */
    public function markCreditsGrantedForInvoice(string $invoiceId, float $creditsAmount): void
    {
        if (!$this->hasInvoiceColumn('credits_granted')) {
            return;
        }

        $data = [
            'credits_granted' => true,
            'updated_at'     => $this->hasInvoiceColumn('updated_at') ? date('Y-m-d H:i:s') : null,
        ];
        if ($this->hasInvoiceColumn('credits_granted_at')) {
            $data['credits_granted_at'] = date('Y-m-d H:i:s');
        }
        if ($this->hasInvoiceColumn('credits_granted_amount')) {
            $data['credits_granted_amount'] = $creditsAmount;
        }

        $this->db->update('billing_invoices', array_filter($data, static fn($v) => $v !== null), ['id' => $invoiceId]);
    }
}
