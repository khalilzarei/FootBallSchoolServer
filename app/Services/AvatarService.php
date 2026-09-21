<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use finfo;

class AvatarService
{
    private const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

public static function uploadFromBase64(string $base64String, string $subPath): string
{
    if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
        $base64String = substr($base64String, strpos($base64String, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, webp

        if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
            throw new AppException('فرمت عکس نامعتبر است', 422);
        }

        $base64String = str_replace(' ', '+', $base64String);
        $fileData = base64_decode($base64String);

        if ($fileData === false) {
            throw new AppException('دکد کردن عکس با خطا مواجه شد', 422);
        }

        if (strlen($fileData) > 2 * 1024 * 1024) { // 2MB
            throw new AppException('حجم عکس نباید بیشتر از ۲ مگابایت باشد', 422);
        }

        $relativeDir = 'avatars/' . $subPath . '/' . date('Y/m');
        $absoluteDir = BASE_PATH . '/storage/' . $relativeDir;

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . ($type === 'jpg' ? 'jpeg' : $type);
        $absolutePath = $absoluteDir . '/' . $storedName;

        if (file_put_contents($absolutePath, $fileData) === false) {
            throw new AppException('ذخیره فایل انجام نشد', 500);
        }

        return $relativeDir . '/' . $storedName;
    }

    throw new AppException('فرمت داده‌های عکس صحیح نیست', 422);
}

    /**
     * آپلود آواتار
     * @param array $file آرایه $_FILES['avatar']
     * @param string $subPath زیرمسیر (users یا players)
     * @return string مسیر نسبی فایل
     */
    public static function upload(array $file, string $subPath): string
    {
        // بررسی خطای آپلود
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('خطا در آپلود فایل', 422);
        }

        // بررسی حجم
        if ((int) $file['size'] > self::MAX_SIZE) {
            throw new AppException('حجم عکس نباید بیشتر از ۲ مگابایت باشد', 422);
        }

        // بررسی پسوند
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new AppException('فرمت عکس باید JPG، PNG یا WebP باشد', 422);
        }

        // بررسی MIME type واقعی
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            throw new AppException('محتوای فایل با پسوند همخوانی ندارد', 422);
        }

        // ساخت پوشه
        $relativeDir = 'avatars/' . $subPath . '/' . date('Y/m');
        $absoluteDir = BASE_PATH . '/storage/' . $relativeDir;

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0775, true)) {
                throw new AppException('خطا در ساخت پوشه', 500);
            }
        }

        // نام تصادفی فایل
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $storedName;

        // انتقال فایل
        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new AppException('ذخیره فایل انجام نشد', 500);
        }

        return $relativeDir . '/' . $storedName;
    }

    /**
     * حذف آواتار قدیمی
     */
    public static function delete(string $avatarPath): void
    {
        if (empty($avatarPath)) return;

        $fullPath = BASE_PATH . '/storage/' . $avatarPath;

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * دریافت URL کامل آواتار
     */
    public static function getUrl(?string $avatarPath): ?string
    {
        if (empty($avatarPath)) return null;

        $baseUrl = self::getBaseUrl();
        return $baseUrl . '/storage/' . $avatarPath;
    }

    /**
     * دریافت URL عکس پیش‌فرض
     */
    public static function getDefaultUrl(string $type = 'user'): string
    {
        $baseUrl = self::getBaseUrl();
        return $baseUrl . '/storage/default_avatars/' . $type . '_default.png';
    }

    /**
     * دریافت URL آواتار (با fallback به پیش‌فرض)
     */
    public static function getAvatarUrl(?string $avatarPath, string $defaultType = 'user'): string
    {
        if (!empty($avatarPath)) {
            return self::getUrl($avatarPath);
        }
        return self::getDefaultUrl($defaultType);
    }

    /**
     * نوع آواتار پیش‌فرض بر اساس نقش کاربر.
     */
    public static function defaultTypeForRole(?string $role): string
    {
        $role = (string) $role;

        if ($role === 'player') {
            return 'player';
        }

        if ($role === 'coach') {
            return 'coach';
        }

        return 'user';
    }

    private static function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}