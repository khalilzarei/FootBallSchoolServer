<?php

declare(strict_types=1);

/**
 * اجرای وبیِ مهاجرت «لاگین بازیکن» — برای هاست بدون SSH
 * (همان scripts/migrate_to_player_login.php را اجرا می‌کند)
 *
 * ⚠ بعد از اجرا این فایل را از هاست حذف کنید!
 *
 * آدرس اجرا:  https://football.madahinote.ir/km-run.php?key=km-7f4d9e21a8
 */

const MIGRATION_KEY = 'km-7f4d9e21a8';

header('Content-Type: text/html; charset=utf-8');

echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>مهاجرت</title></head>'
    . '<body style="background:#12032A;color:#fff;font-family:Tahoma,sans-serif;direction:rtl;padding:24px;line-height:2">';

$key = (string) ($_GET['key'] ?? '');
if (!hash_equals(MIGRATION_KEY, $key)) {
    http_response_code(403);
    echo '<h3 style="color:#FFD700">⛔ کلید اجرا نادرست است</h3></body></html>';
    exit;
}

$migrationFile = __DIR__ . '/scripts/migrate_to_player_login.php';

echo '<h2 style="color:#FFD700">مهاجرت «لاگین بازیکن»</h2>';

if (!is_file($migrationFile)) {
    echo '<p style="color:#FF8A80">❌ فایل scripts/migrate_to_player_login.php روی هاست پیدا نشد — ابتدا server-deploy.zip جدید را کامل آپلود کنید.</p></body></html>';
    exit;
}

echo '<pre style="direction:ltr;text-align:left;background:#1A0533;color:#81C784;padding:16px;border-radius:12px;overflow-x:auto">';

try {
    set_time_limit(180);
    require $migrationFile;
    echo "\n\n✅ اجرا تمام شد — خروجی بالا را بررسی کنید، سپس فایل km-run.php را از هاست حذف کنید!";
} catch (Throwable $e) {
    echo "\n\n❌ خطا: " . $e->getMessage() . "\n"
        . $e->getFile() . ':' . $e->getLine() . "\n\n"
        . "متن کامل این خطا را برایم بفرستید. (اجرای دوباره معمولاً بی‌خطر است — مراحل تکرارپذیر نوشته شده‌اند)";
}

echo '</pre></body></html>';
