<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Lazily applied on every admin voucher page load, since this app has no
// cron/scheduled job runner: a configuration past its end_date flips to
// 'expired', and every still-'active' code under it flips to 'disabled' so
// the code-level status reflects reality too (not just the parent config).
// Both 'active' and 'disabled' configurations expire this way — a disabled
// voucher whose window has passed still needs to reach 'expired' so it can
// eventually be archived/deleted, not sit disabled forever.
function apply_voucher_expiry($_db) {
    $_db->exec("UPDATE voucher_configuration SET status = 'expired' WHERE status IN ('active', 'disabled') AND end_date < NOW()");
    $_db->exec(
        "UPDATE voucher v
         JOIN voucher_configuration vc ON vc.voucher_id = v.voucher_id
         SET v.status = 'disabled'
         WHERE v.status = 'active' AND vc.status = 'expired'"
    );
}

// Called when the Archived Vouchers page is visited: prunes configurations
// that have sat expired for more than 3 days AND were never actually used,
// so the archive doesn't grow forever with dead, never-used campaigns.
// Configurations with at least one 'used' code are kept indefinitely — they
// carry real order history and stay available to delete manually instead.
function cleanup_stale_expired_vouchers($_db) {
    $_db->exec(
        "DELETE FROM voucher_configuration
         WHERE status = 'expired'
           AND end_date < NOW() - INTERVAL 3 DAY
           AND NOT EXISTS (
               SELECT 1 FROM voucher v
               WHERE v.voucher_id = voucher_configuration.voucher_id AND v.status = 'used'
           )"
    );
}
