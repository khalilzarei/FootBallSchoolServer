<?php

declare(strict_types=1);

use App\Core\Database;

require dirname(__DIR__) . '/bootstrap.php';

// if (PHP_SAPI !== 'cli') {
//     exit('این اسکریپت فقط باید از طریق خط فرمان اجرا شود');
// }

if ($argc < 5) {
    echo "Usage:\n";
    echo "php create_admin.php \"Full Name\" 09120000000 null password123\n";
    echo "php create_admin.php \"Full Name\" null 0012345678 password123\n";
    exit(1);
}

$fullName = $argv[1];
$mobile = $argv[2] ?? null;
$nationalCode = $argv[3] ?? null;
$password = $argv[4];

if ($mobile === 'null' || $mobile === '') {
    $mobile = null;
}

if ($nationalCode === 'null' || $nationalCode === '') {
    $nationalCode = null;
}

if (!$mobile && !$nationalCode) {
    fwrite(STDERR, "حداقل یکی از موبایل یا کد ملی باید وارد شود\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "رمز عبور باید حداقل ۸ کاراکتر باشد\n");
    exit(1);
}

$pdo = Database::connection();

$conditions = [];
$params = [];

if ($mobile) {
    $conditions[] = 'mobile = :mobile';
    $params['mobile'] = $mobile;
}

if ($nationalCode) {
    $conditions[] = 'national_code = :national_code';
    $params['national_code'] = $nationalCode;
}

$sql = '
    SELECT id
    FROM football_users
    WHERE deleted_at IS NULL
      AND (' . implode(' OR ', $conditions) . ')
    LIMIT 1
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if ($stmt->fetch()) {
    fwrite(STDERR, "کاربری با این موبایل یا کد ملی قبلاً ثبت شده است\n");
    exit(1);
}

$stmt = $pdo->prepare('
    INSERT INTO football_users (
        full_name,
        mobile,
        national_code,
        password_hash,
        role,
        status,
        must_change_password,
        created_at
    )
    VALUES (
        :full_name,
        :mobile,
        :national_code,
        :password_hash,
        :role,
        :status,
        :must_change_password,
        NOW()
    )
');

$stmt->execute([
    'full_name' => $fullName,
    'mobile' => $mobile,
    'national_code' => $nationalCode,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
    'status' => 'active',
    'must_change_password' => 1,
]);

echo "کاربر ادمین با موفقیت ساخته شد.\n";
echo 'User ID: ' . $pdo->lastInsertId() . "\n";