<?php

declare(strict_types=1);

use App\Core\Database;

require dirname(__DIR__) . '/bootstrap.php';

/**
 * مهاجرت معماری حساب‌ها:
 *   ۱) football_players.user_id و password_hash اضافه می‌شود (حساب ورود بازیکن — نقش player؛ رمز روی رکورد بازیکن)
 *   ۲) مشخصات هویتی سرپرست (full_name/mobile/national_code) به football_guardians منتقل و ستون user_id حذف می‌شود
 *   ۳) برای هر بازیکنِ دارایِ کد ملی که حساب ندارد، کاربر با نقش player ساخته می‌شود
 *      (شناسه ورود و رمز اولیه = کد ملی بازیکن؛ must_change_password=1)
 *   ۴) اتاق‌های چت guardian_admin/guardian_coach حذف و پیام‌های سرپرستان پاک می‌شود
 *   ۵) کاربران نقش guardian (و توکن‌هایشان) حذف می‌شوند
 *
 * اجرا:  php scripts/migrate_to_player_login.php
 */

$pdo = Database::connection();

function colExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int) $stmt->fetch()['c'] > 0;
}

function fkName(PDO $pdo, string $table, string $column): ?string
{
    $stmt = $pdo->prepare('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1');
    $stmt->execute(['t' => $table, 'c' => $column]);
    $row = $stmt->fetch();
    return $row ? (string) $row['CONSTRAINT_NAME'] : null;
}

echo "═ مهاجرت «لاگین بازیکن» ═\n";

// ─── ۱) ستون user_id بازیکنان ───
if (!colExists($pdo, 'football_players', 'user_id')) {
    $pdo->exec('ALTER TABLE football_players ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id');
    $pdo->exec('ALTER TABLE football_players ADD UNIQUE KEY uq_football_players_user_id (user_id)');
    $pdo->exec('ALTER TABLE football_players ADD CONSTRAINT fk_football_players_user FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE');
    echo "✓ ستون football_players.user_id اضافه شد\n";
} else {
    echo "• football_players.user_id از قبل موجود است\n";
}

// ─── ۱.۵) ستون رمز بازیکن + رمز اولیه = هش کد ملی ───
if (!colExists($pdo, 'football_players', 'password_hash')) {
    $pdo->exec('ALTER TABLE football_players ADD COLUMN password_hash VARCHAR(255) NULL AFTER national_code');
    echo "✓ ستون football_players.password_hash اضافه شد\n";
} else {
    echo "• football_players.password_hash از قبل موجود است\n";
}

// رمز اولیه (هش کد ملی) برای بازیکنان دارای کد ملی که هنوز رمز ندارند؛
// اگر بازیکن حساب دارد و قبلاً رمزش را عوض کرده، همان رمز فعلی حساب کپی می‌شود
$stmt = $pdo->query("
    SELECT p.id, p.national_code, u.password_hash AS user_hash
    FROM football_players p
    LEFT JOIN football_users u ON u.id = p.user_id
    WHERE p.deleted_at IS NULL AND p.national_code IS NOT NULL AND p.national_code <> ''
      AND p.password_hash IS NULL
");
$setPass = $pdo->prepare('UPDATE football_players SET password_hash = :h WHERE id = :id');
$hashed = 0;
foreach ($stmt->fetchAll() as $r) {
    $nc = trim((string) $r['national_code']);
    if (!preg_match('/^[0-9]{10}$/', $nc)) continue;
    $hash = $r['user_hash'] ?? null;
    $setPass->execute(['h' => $hash ?: password_hash($nc, PASSWORD_DEFAULT), 'id' => (int) $r['id']]);
    $hashed++;
}
echo "✓ رمز ورود {$hashed} بازیکن ثبت شد (هش کد ملی)\n";

// ─── ۲) ستون‌های هویتی سرپرست ───
if (!colExists($pdo, 'football_guardians', 'full_name')) {
    $pdo->exec('ALTER TABLE football_guardians ADD COLUMN full_name VARCHAR(150) NULL AFTER id');
    $pdo->exec('ALTER TABLE football_guardians ADD COLUMN mobile VARCHAR(20) NULL AFTER full_name');
    $pdo->exec('ALTER TABLE football_guardians ADD COLUMN national_code CHAR(10) NULL AFTER mobile');
    echo "✓ ستون‌های full_name/mobile/national_code به football_guardians اضافه شد\n";
} else {
    echo "• ستون‌های هویتی سرپرست از قبل موجود است\n";
}

// ─── ۳) انتقال مشخصات از کاربر سرپرست به رکورد سرپرست ───
if (colExists($pdo, 'football_guardians', 'user_id')) {
    $stmt = $pdo->prepare('
        UPDATE football_guardians g
        INNER JOIN football_users u ON u.id = g.user_id
        SET g.full_name = u.full_name, g.mobile = u.mobile, g.national_code = u.national_code
        WHERE g.full_name IS NULL
    ');
    $stmt->execute();
    echo "✓ مشخصات " . $stmt->rowCount() . " سرپرست از حساب کاربری منتقل شد\n";

    // رکوردهای بدون نام (سرپرست حذف‌شده/بی‌نام) — برای NOT NULL شدن بعدی
    $pdo->exec("UPDATE football_guardians SET full_name = 'سرپرست بدون نام', mobile = NULL, national_code = NULL WHERE full_name IS NULL OR full_name = ''");
    $pdo->exec('ALTER TABLE football_guardians MODIFY full_name VARCHAR(150) NOT NULL');
} else {
    echo "• football_guardians.user_id حذف شده است\n";
}

// ─── ۴) ساخت حساب player برای بازیکنان دارای کد ملی ───
$stmt = $pdo->query('
    SELECT p.id, p.first_name, p.last_name, p.national_code
    FROM football_players p
    WHERE p.deleted_at IS NULL AND p.national_code IS NOT NULL AND p.national_code <> \'\'
      AND p.user_id IS NULL
');
$players = $stmt->fetchAll();

$created = 0;
$linked = 0;
$skipped = 0;

$findUser = $pdo->prepare('SELECT id, role FROM football_users WHERE national_code = :nc AND deleted_at IS NULL LIMIT 1');
$insertUser = $pdo->prepare('
    INSERT INTO football_users (full_name, mobile, national_code, avatar_path, password_hash, role, status, must_change_password, created_at)
    VALUES (:full_name, NULL, :nc, \'\', :hash, \'player\', \'active\', 1, NOW())
');
$link = $pdo->prepare('UPDATE football_players SET user_id = :uid WHERE id = :pid');

foreach ($players as $p) {
    $nc = trim((string) $p['national_code']);
    if (!preg_match('/^[0-9]{10}$/', $nc)) {
        echo "  ⚠ بازیکن #{$p['id']}: کد ملی نامعتبر ({$nc}) — حساب ساخته نشد\n";
        $skipped++;
        continue;
    }

    $findUser->execute(['nc' => $nc]);
    $u = $findUser->fetch();

    if ($u) {
        if ((string) $u['role'] !== 'player') {
            echo "  ⚠ بازیکن #{$p['id']}: کد ملی {$nc} متعلق به کاربر نقش {$u['role']} است — حساب ساخته نشد\n";
            $skipped++;
            continue;
        }
        $link->execute(['uid' => (int) $u['id'], 'pid' => (int) $p['id']]);
        $linked++;
        continue;
    }

    $insertUser->execute([
        'full_name' => trim(((string) $p['first_name']) . ' ' . ((string) $p['last_name'])),
        'nc' => $nc,
        // رمز اولیه = کد ملی بازیکن — در اولین ورود اجباری عوض می‌شود
        'hash' => password_hash($nc, PASSWORD_DEFAULT),
    ]);
    $link->execute(['uid' => (int) $pdo->lastInsertId(), 'pid' => (int) $p['id']]);
    $created++;
}

echo "✓ حساب بازیکن: {$created} ساخته شد، {$linked} به حساب موجود وصل شد، {$skipped} رد شد\n";

// ─── ۵) پاکسازی چت سرپرستان ───
// اتاق‌های دوسرپرستی (این نوع‌ها با معماری جدید منسوخ می‌شوند)
$pdo->exec('
    DELETE r FROM football_chat_rooms r
    WHERE r.room_type IN (\'guardian_admin\', \'guardian_coach\')
');
echo "✓ اتاق‌های چت guardian_admin/guardian_coach حذف شدند (پیام‌ها و اعضا به‌صورت آبشاری)\n";

// پیام‌های ارسال‌شده توسط سرپرستان در اتاق‌های باقی‌مانده (مثلاً گروه سنی)
$pdo->exec('
    DELETE m FROM football_chat_messages m
    INNER JOIN football_users u ON u.id = m.sender_id AND u.role = \'guardian\'
');
echo "✓ پیام‌های ارسال‌شده توسط سرپرستان حذف شد\n";

// ─── ۶) حذف ستون user_id سرپرست ───
if (colExists($pdo, 'football_guardians', 'user_id')) {
    $fk = fkName($pdo, 'football_guardians', 'user_id');
    if ($fk !== null) {
        $pdo->exec("ALTER TABLE football_guardians DROP FOREIGN KEY `{$fk}`");
    }
    // حذف کلید یکتا (نام استاندارد اسکیما یا نام خودکار)
    foreach (['uq_football_guardians_user_id'] as $key) {
        try {
            $pdo->exec("ALTER TABLE football_guardians DROP KEY `{$key}`");
        } catch (Throwable $e) {
            // کلید با نام دیگری — از information_schema پیدا می‌کنیم
            $stmt = $pdo->prepare('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'football_guardians\' AND COLUMN_NAME = \'user_id\' LIMIT 1');
            $stmt->execute();
            if ($row = $stmt->fetch()) {
                $pdo->exec('ALTER TABLE football_guardians DROP KEY `' . $row['INDEX_NAME'] . '`');
            }
        }
    }
    $pdo->exec('ALTER TABLE football_guardians DROP COLUMN user_id');
    echo "✓ ستون football_guardians.user_id حذف شد\n";
}

// ─── ۷) حذف کاربران سرپرست ───
// (توکن‌ها و عضویت‌های اتاق به‌صورت آبشاری حذف می‌شوند)
$stmt = $pdo->query('SELECT COUNT(*) AS c FROM football_users WHERE role = \'guardian\'');
$guardianCount = (int) $stmt->fetch()['c'];

if ($guardianCount > 0) {
    $pdo->exec('DELETE FROM football_users WHERE role = \'guardian\'');
    echo "✓ {$guardianCount} کاربر سرپرست حذف شد\n";
} else {
    echo "• کاربر سرپرستی برای حذف نیست\n";
}

// ─── ۸) مخاطبان نقش سرپرست در اخبار → بازیکن ───
if (colExists($pdo, 'football_news_audiences', 'role')) {
    $pdo->exec('UPDATE football_news_audiences SET role = \'player\' WHERE audience_type = \'role\' AND role = \'guardian\'');
    echo "✓ مخاطبان خبرِ نقش سرپرست → بازیکن\n";
}

echo "\n═ مهاجرت کامل شد ═\n";
echo "اطلاعات ورود هر بازیکن: شناسه = کد ملی بازیکن، رمز اولیه = کد ملی بازیکن (در اولین ورود اجباری عوض می‌شود)\n";
