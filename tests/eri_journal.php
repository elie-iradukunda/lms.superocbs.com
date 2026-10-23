<?php

declare(strict_types=1);

/**
 * ERI specification, section 14: a manual journal is prepared, verified by
 * Finance and authorized by the MD before it reaches the books. Nobody signs
 * their own.
 */

require __DIR__ . '/support.php';

[$pdo] = test_database('eri_journal', false, false);
require __DIR__ . '/../bootstrap.php';

use Models\ManualJournals;

$test = new TestRun('ERI manual journals');
$test->stopOn();
$accounts = (new Models\UserRepository())->activeLoginAccounts();

$account = static fn (string $code): int => (int) $pdo->query("SELECT id FROM gl_accounts WHERE account_code = '{$code}'")->fetchColumn();
$lines = [
    ['account' => $account('6900'), 'debit' => 250000.0, 'credit' => 0.0, 'description' => 'Accrued workshop bill'],
    ['account' => $account('2000'), 'debit' => 0.0, 'credit' => 250000.0, 'description' => null],
];
$entries = static fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM gl_journal_entries WHERE source_type = 'manual'")->fetchColumn();

// Out of balance is refused before it goes anywhere.
test_sign_in($accounts['finance'], 'finance');
$unbalanced = $lines;
$unbalanced[1]['credit'] = 200000.0;
$test->assert(ManualJournals::problems(date('Y-m-d'), 'Accrual', 'RWF', $unbalanced) !== [], 'an entry out of balance is refused');

// Prepared by the Finance Manager: preparing counts as verifying.
test_sign_in($accounts['finance'], 'finance');
$id = ManualJournals::prepare(date('Y-m-d'), 'Accrue September workshop bill', 'ADJ-09', 'RWF', $lines);
$journal = ManualJournals::find($id);
$test->same('verified', $journal['status'], 'prepared by the Finance Manager, it goes straight to the MD');
$test->assert(str_starts_with((string) $journal['draft_no'], 'JV-' . date('Y') . '-'), 'it is numbered from the setting: ' . $journal['draft_no']);
$test->same(0, $entries(), 'nothing is in the books yet');
$test->same(null, ManualJournals::stepFor($journal), 'the preparer cannot authorize it');
$test->throws(static fn () => ManualJournals::authorize($id), 'and is refused if they try');

// The MD authorizes: it posts.
test_sign_in($accounts['management'], 'management');
$test->same('authorize', ManualJournals::stepFor($journal), 'the MD is asked to authorize');
$entryId = ManualJournals::authorize($id);
$test->same(1, $entries(), 'authorized, it posts');
$entry = $pdo->query("SELECT * FROM gl_journal_entries WHERE id = {$entryId}")->fetch();
$test->same('RWF', $entry['currency'], 'in the currency chosen');
$test->same((string) $journal['draft_no'], (string) $entry['source_code'], 'and carries the journal number');
$test->same('posted', ManualJournals::find($id)['status'], 'the journal shows as posted');

// Refused with a reason: nothing posts.
test_sign_in($accounts['finance'], 'finance');
$second = ManualJournals::prepare(date('Y-m-d'), 'Reclassify fuel', null, 'RWF', $lines);
test_sign_in($accounts['management'], 'management');
$test->throws(static fn () => ManualJournals::refuse($second, ''), 'a refusal needs a reason');
ManualJournals::refuse($second, 'Attach the workshop invoice first');
$test->same('refused', ManualJournals::find($second)['status'], 'refused');
$test->same(1, $entries(), 'and nothing more is in the books');
$test->assert((int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE title = 'Journal refused'")->fetchColumn() > 0, 'the preparer is told why');

// Prepared by someone else: Finance verifies first.
test_sign_in($accounts['logistics_manager'], 'logistics_manager');
$third = ManualJournals::prepare(date('Y-m-d'), 'Driver advance correction', null, 'RWF', $lines);
$test->same('sent', ManualJournals::find($third)['status'], 'prepared by someone other than Finance, it waits for Finance');
test_sign_in($accounts['management'], 'management');
$test->same(null, ManualJournals::stepFor(ManualJournals::find($third)), 'the MD cannot authorize before Finance verifies');
test_sign_in($accounts['finance'], 'finance');
ManualJournals::verify($third);
test_sign_in($accounts['management'], 'management');
ManualJournals::authorize($third);
$test->same(2, $entries(), 'verified then authorized, it posts');
$test->assert(Models\Ledger::isBalanced(), 'the books balance');

$test->finish();
