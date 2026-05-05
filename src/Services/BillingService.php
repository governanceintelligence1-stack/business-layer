<?php
declare(strict_types=1);

namespace GI\Services;

use GI\Core\DB;

class BillingService
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
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
        $total  = array_sum(array_column($items, 'total'));
        $number = 'INV-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)) . '-' . date('Ymd');

        $invoiceId = $this->db->insert('billing_invoices', [
            'organisation_id' => $orgId,
            'invoice_number'  => $number,
            'amount_total'    => $total,
            'status'          => 'pending',
            'due_date'        => date('Y-m-d', strtotime('+30 days')),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        foreach ($items as $item) {
            $this->db->insert('billing_line_items', array_merge($item, ['invoice_id' => $invoiceId]));
        }

        return $invoiceId;
    }

    public function markPaid(string $invoiceId): int
    {
        return $this->db->update('billing_invoices', [
            'status'  => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ], ['id' => $invoiceId]);
    }
}
