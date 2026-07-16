<?php
// Migration 054: add shift_from_today_on_pay to subscriptions table
// When enabled, the "Mark as Paid" button shifts next_payment from today instead of from the scheduled date

$columnQuery = $db->query("SELECT * FROM pragma_table_info('subscriptions') WHERE name='shift_from_today_on_pay'");
$columnRequired = $columnQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN shift_from_today_on_pay BOOLEAN DEFAULT 0');
}
