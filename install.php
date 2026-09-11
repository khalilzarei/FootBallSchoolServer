<!--<?php-->

<!--declare(strict_types=1);-->

// ====================================================================
// نصب‌کننده کاربر ادمین - فقط یک بار استفاده شود
// بعد از اجرا حتماً این فایل را از سرور حذف کنید!
// ====================================================================

// 🔒 کلید امنیتی - این مقدار را تغییر دهید
<!--$INSTALL_KEY = 'FOOTBALL-INSTALL-2026-SECRET';-->

// بررسی کلید
<!--if (($_GET['key'] ?? '') !== $INSTALL_KEY) {-->
<!--    http_response_code(403);-->
<!--    exit('Access Denied');-->
<!--}-->

// بارگذاری سیستم
<!--require __DIR__ . '/bootstrap.php';-->

<!--use App\Core\Database;-->

// پردازش فرم
<!--$error = '';-->
<!--$success = '';-->

<!--if ($_SERVER['REQUEST_METHOD'] === 'POST') {-->
<!--    $fullName = trim($_POST['full_name'] ?? '');-->
<!--    $mobile = trim($_POST['mobile'] ?? '');-->
<!--    $nationalCode = trim($_POST['national_code'] ?? '');-->
<!--    $password = $_POST['password'] ?? '';-->
<!--    $confirm = $_POST['confirm_password'] ?? '';-->

    // اعتبارسنجی
<!--    if ($fullName === '') {-->
<!--        $error = 'نام و نام خانوادگی الزامی است';-->
<!--    } elseif ($mobile === '' && $nationalCode === '') {-->
<!--        $error = 'حداقل یکی از موبایل یا کد ملی باید وارد شود';-->
<!--    } elseif (strlen($password) < 8) {-->
<!--        $error = 'رمز عبور باید حداقل ۸ کاراکتر باشد';-->
<!--    } elseif ($password !== $confirm) {-->
<!--        $error = 'تکرار رمز عبور مطابقت ندارد';-->
<!--    } else {-->
<!--        try {-->
<!--            $pdo = Database::connection();-->
            
            // بررسی تکراری نبودن
<!--            $conditions = [];-->
<!--            $params = [];-->
            
<!--            if ($mobile !== '') {-->
<!--                $conditions[] = 'mobile = :mobile';-->
<!--                $params['mobile'] = $mobile;-->
<!--            }-->
            
<!--            if ($nationalCode !== '') {-->
<!--                $conditions[] = 'national_code = :national_code';-->
<!--                $params['national_code'] = $nationalCode;-->
<!--            }-->
            
<!--            $sql = 'SELECT id FROM football_users WHERE deleted_at IS NULL AND (' -->
<!--                 . implode(' OR ', $conditions) . ') LIMIT 1';-->
            
<!--            $stmt = $pdo->prepare($sql);-->
<!--            $stmt->execute($params);-->
            
<!--            if ($stmt->fetch()) {-->
<!--                $error = 'کاربری با این موبایل یا کد ملی قبلاً ثبت شده است';-->
<!--            } else {-->
                // ایجاد کاربر
<!--                $stmt = $pdo->prepare('-->
<!--                    INSERT INTO football_users (-->
<!--                        full_name, mobile, national_code, password_hash,-->
<!--                        role, status, must_change_password, created_at-->
<!--                    ) VALUES (-->
<!--                        :full_name, :mobile, :national_code, :password_hash,-->
<!--                        :role, :status, :must_change_password, NOW()-->
<!--                    )-->
<!--                ');-->
                
<!--                $stmt->execute([-->
<!--                    'full_name' => $fullName,-->
<!--                    'mobile' => $mobile !== '' ? $mobile : null,-->
<!--                    'national_code' => $nationalCode !== '' ? $nationalCode : null,-->
<!--                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),-->
<!--                    'role' => 'admin',-->
<!--                    'status' => 'active',-->
<!--                    'must_change_password' => 1,-->
<!--                ]);-->
                
<!--                $userId = $pdo->lastInsertId();-->
<!--                $success = "✅ کاربر ادمین با موفقیت ساخته شد! User ID: {$userId}";-->
<!--            }-->
            
<!--        } catch (Exception $e) {-->
<!--            $error = 'خطای دیتابیس: ' . $e->getMessage();-->
<!--        }-->
<!--    }-->
<!--}-->

<!--?>-->
<!--<!DOCTYPE html>-->
<!--<html lang="fa" dir="rtl">-->
<!--<head>-->
<!--    <meta charset="UTF-8">-->
<!--    <meta name="viewport" content="width=device-width, initial-scale=1.0">-->
<!--    <title>نصب کاربر ادمین</title>-->
<!--    <style>-->
<!--        * { box-sizing: border-box; margin: 0; padding: 0; }-->
<!--        body { -->
<!--            font-family: Tahoma, Arial, sans-serif; -->
<!--            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);-->
<!--            min-height: 100vh; -->
<!--            display: flex; -->
<!--            align-items: center; -->
<!--            justify-content: center;-->
<!--            padding: 20px;-->
<!--        }-->
<!--        .container {-->
<!--            background: white;-->
<!--            border-radius: 12px;-->
<!--            box-shadow: 0 20px 60px rgba(0,0,0,0.3);-->
<!--            padding: 40px;-->
<!--            max-width: 500px;-->
<!--            width: 100%;-->
<!--        }-->
<!--        h1 {-->
<!--            color: #333;-->
<!--            margin-bottom: 10px;-->
<!--            font-size: 24px;-->
<!--        }-->
<!--        .warning {-->
<!--            background: #fff3cd;-->
<!--            border: 1px solid #ffc107;-->
<!--            color: #856404;-->
<!--            padding: 12px;-->
<!--            border-radius: 6px;-->
<!--            margin-bottom: 20px;-->
<!--            font-size: 14px;-->
<!--        }-->
<!--        .success {-->
<!--            background: #d4edda;-->
<!--            border: 1px solid #28a745;-->
<!--            color: #155724;-->
<!--            padding: 15px;-->
<!--            border-radius: 6px;-->
<!--            margin-bottom: 20px;-->
<!--            font-size: 14px;-->
<!--        }-->
<!--        .error {-->
<!--            background: #f8d7da;-->
<!--            border: 1px solid #dc3545;-->
<!--            color: #721c24;-->
<!--            padding: 12px;-->
<!--            border-radius: 6px;-->
<!--            margin-bottom: 20px;-->
<!--            font-size: 14px;-->
<!--        }-->
<!--        label {-->
<!--            display: block;-->
<!--            margin-bottom: 5px;-->
<!--            color: #555;-->
<!--            font-weight: bold;-->
<!--            font-size: 14px;-->
<!--        }-->
<!--        input {-->
<!--            width: 100%;-->
<!--            padding: 12px;-->
<!--            border: 2px solid #ddd;-->
<!--            border-radius: 6px;-->
<!--            font-size: 16px;-->
<!--            margin-bottom: 15px;-->
<!--            transition: border-color 0.3s;-->
<!--        }-->
<!--        input:focus {-->
<!--            outline: none;-->
<!--            border-color: #667eea;-->
<!--        }-->
<!--        button {-->
<!--            width: 100%;-->
<!--            padding: 14px;-->
<!--            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);-->
<!--            color: white;-->
<!--            border: none;-->
<!--            border-radius: 6px;-->
<!--            font-size: 16px;-->
<!--            font-weight: bold;-->
<!--            cursor: pointer;-->
<!--            transition: transform 0.2s;-->
<!--        }-->
<!--        button:hover {-->
<!--            transform: translateY(-2px);-->
<!--        }-->
<!--        .hint {-->
<!--            font-size: 12px;-->
<!--            color: #888;-->
<!--            margin-top: -10px;-->
<!--            margin-bottom: 15px;-->
<!--        }-->
<!--    </style>-->
<!--</head>-->
<!--<body>-->
<!--    <div class="container">-->
<!--        <h1>🔐 نصب کاربر ادمین</h1>-->
        
<!--        <div class="warning">-->
<!--            ⚠️ <strong>هشدار:</strong> بعد از ساخت ادمین، حتماً این فایل (<code>install.php</code>) را از سرور حذف کنید!-->
<!--        </div>-->
        
<!--        <?php if ($success): ?>-->
<!--            <div class="success">-->
<!--                <?= $success ?>-->
<!--                <br><br>-->
<!--                <strong>حالا می‌توانید وارد شوید:</strong>-->
<!--                <br>-->
<!--                موبایل: <?= htmlspecialchars($_POST['mobile'] ?? '') ?>-->
<!--                <br>-->
<!--                <br>-->
<!--                <strong style="color:#dc3545;">🗑️ لطفاً الان فایل install.php را از سرور حذف کنید!</strong>-->
<!--            </div>-->
<!--        <?php else: ?>-->
<!--            <?php if ($error): ?>-->
<!--                <div class="error">❌ <?= htmlspecialchars($error) ?></div>-->
<!--            <?php endif; ?>-->
            
<!--            <form method="POST">-->
<!--                <label for="full_name">نام و نام خانوادگی *</label>-->
<!--                <input type="text" id="full_name" name="full_name" required -->
<!--                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">-->
                
<!--                <label for="mobile">شماره موبایل</label>-->
<!--                <input type="text" id="mobile" name="mobile" placeholder="09120000000"-->
<!--                       value="<?= htmlspecialchars($_POST['mobile'] ?? '09120000000') ?>">-->
<!--                <p class="hint">مثال: 09120000000</p>-->
                
<!--                <label for="national_code">کد ملی</label>-->
<!--                <input type="text" id="national_code" name="national_code" placeholder="0012345678"-->
<!--                       value="<?= htmlspecialchars($_POST['national_code'] ?? '') ?>">-->
<!--                <p class="hint">حداقل یکی از موبایل یا کد ملی باید پر شود</p>-->
                
<!--                <label for="password">رمز عبور *</label>-->
<!--                <input type="password" id="password" name="password" required -->
<!--                       placeholder="حداقل ۸ کاراکتر">-->
                
<!--                <label for="confirm_password">تکرار رمز عبور *</label>-->
<!--                <input type="password" id="confirm_password" name="confirm_password" required>-->
                
<!--                <button type="submit">ساخت کاربر ادمین</button>-->
<!--            </form>-->
<!--        <?php endif; ?>-->
<!--    </div>-->
<!--</body>-->
<!--</html>-->