<?php
/**
 * Run this on a schedule (e.g. every 15 minutes via cron) instead of relying
 * on an admin to click "Run attendance payroll sync" by hand:
 *
 *   /15 * * * * php /path/to/app/cron_attendance_sync.php >> /var/log/hr-sync.log 2>&1
 *
 * It's safe to run as often as you like — run_attendance_payroll_sync()
 * only posts one ledger entry per employee per date, ever.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$summary = run_attendance_payroll_sync();
echo date('Y-m-d H:i:s') . " — synced: {$summary['credited']} credited, {$summary['debited']} debited\n";
