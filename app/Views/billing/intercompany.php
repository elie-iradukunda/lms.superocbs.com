<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
/** @var list<array<string, mixed>> $invoices */
/** @var int $companyId */
use Models\Currency;
?>
<div class="container-fluid lms-list-page">
  <div class="lms-page-head">
    <div>
      <p class="lms-kicker">Accounting · between companies (section 17)</p>
      <h2 class="lms-page-title"><i class="fe fe-repeat mr-2"></i>Inter-company invoices</h2>
      <p class="lms-page-sub">Raised automatically when a job done for a sister company's truck (F11) closes: the company that did the work is owed, the company that asked owes.</p>
    </div>
  </div>

  <div class="card shadow-sm">
    <?php if ($invoices === []): ?>
      <div class="card-body text-center text-muted py-5">No inter-company invoice yet.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Invoice</th><th>Work done by</th><th>Charged to</th><th>Truck</th><th class="text-right">Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($invoices as $invoice): ?>
            <?php $weAreOwed = (int) $invoice['from_company_id'] === $companyId; ?>
            <tr>
              <td><strong><?= e($invoice['invoice_no']) ?></strong></td>
              <td><?= e($invoice['from_name'] ?? '') ?><?= $weAreOwed ? ' <span class="badge badge-success">us — owed</span>' : '' ?></td>
              <td><?= e($invoice['to_name'] ?? '') ?><?= (int) $invoice['to_company_id'] === $companyId ? ' <span class="badge badge-warning">us — to pay</span>' : '' ?></td>
              <td><?= e($invoice['plate_number'] ?? '—') ?></td>
              <td class="text-right"><?= e(Currency::format((float) $invoice['total_amount'], (string) $invoice['currency'])) ?></td>
              <td><span class="badge badge-<?= ['issued' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary'][$invoice['status']] ?? 'light' ?>"><?= e(ucfirst((string) $invoice['status'])) ?></span></td>
              <td><?= e(human_date($invoice['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
