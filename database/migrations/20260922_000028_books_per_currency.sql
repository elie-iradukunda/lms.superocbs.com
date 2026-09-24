-- Books kept in the currency the money was actually in.
--
-- The first attempt converted everything into one base currency at a stored
-- rate, so a fuel receipt from Namanga reading KES 12,500 was filed in the
-- ledger as francs. It balanced, but it answered a question nobody asks. A
-- company running in three countries wants to see the Kenyan shillings it took
-- and the Kenyan shillings it spent, in shillings, without a rate standing
-- between the receipt and the report.
--
-- So the conversion goes and the currency comes with the entry instead. Each
-- currency is its own set of books: entries in shillings balance against
-- entries in shillings, francs against francs, and the books are read one
-- currency at a time. Nothing is ever restated, and a rate that moves next
-- month cannot change what last month's page says.
--
-- Entries written before this stay exactly as they are. They were posted in the
-- base currency, which is what they are now labelled as, so the page they
-- appeared on before still shows them.

ALTER TABLE gl_journal_entries
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'RWF' AFTER entry_date;

-- The base currency is whatever the company set, not whatever this file guesses.
UPDATE gl_journal_entries e
  JOIN (SELECT code FROM currencies WHERE is_base = 1 AND deleted_at IS NULL LIMIT 1) b
   SET e.currency = b.code
 WHERE e.currency = 'RWF';

-- An entry written from a document in another currency carries that currency,
-- so the page it belongs on is the one its money was actually counted in.
UPDATE gl_journal_entries e
  JOIN expenses d ON d.id = e.source_id
   SET e.currency = d.currency
 WHERE e.source_type = 'expense' AND d.currency IS NOT NULL AND d.currency <> '';

UPDATE gl_journal_entries e
  JOIN invoices d ON d.id = e.source_id
   SET e.currency = d.currency
 WHERE e.source_type = 'invoice' AND d.currency IS NOT NULL AND d.currency <> '';

UPDATE gl_journal_entries e
  JOIN payments d ON d.id = e.source_id
   SET e.currency = d.currency
 WHERE e.source_type = 'payment' AND d.currency IS NOT NULL AND d.currency <> '';

UPDATE gl_journal_entries e
  JOIN fuel_records d ON d.id = e.source_id
   SET e.currency = d.currency
 WHERE e.source_type = 'fuel' AND d.currency IS NOT NULL AND d.currency <> '';

UPDATE gl_journal_entries e
  JOIN purchase_requests d ON d.id = e.source_id
   SET e.currency = d.currency
 WHERE e.source_type = 'purchase' AND d.currency IS NOT NULL AND d.currency <> '';

UPDATE gl_journal_entries e
  JOIN border_crossings d ON d.id = e.source_id
   SET e.currency = d.currency
 WHERE e.source_type = 'crossing' AND d.currency IS NOT NULL AND d.currency <> '';

CREATE INDEX IF NOT EXISTS idx_journal_currency ON gl_journal_entries (currency, entry_date);

-- The stored rates table stays in place rather than being dropped: it holds
-- what somebody typed, and dropping a table is not undoable. Nothing reads it
-- any more, and the page that edited it is gone.
UPDATE role_permissions SET can_view = 0, can_create = 0, can_edit = 0, can_delete = 0
 WHERE permission_key = 'currency_rates';
