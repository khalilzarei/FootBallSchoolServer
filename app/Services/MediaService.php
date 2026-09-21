<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Config;
use App\Repositories\ClientRepository;
use App\Repositories\MediaRepository;
use finfo;

class MediaService
{
    private const VISIBILITIES = ['public', 'class', 'age_group', 'players', 'private'];
    private const RELATED_TYPES = ['general', 'news', 'session', 'class', 'match', 'chat', 'player', 'payment', 'evaluation'];
    private const AUDIENCE_TYPES = ['class', 'age_group', 'player', 'user'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $result = MediaRepository::paginate([
            'status' => trim((string) ($query['status'] ?? '')) ?: null,
            'visibility' => trim((string) ($query['visibility'] ?? '')) ?: null,
            'file_type' => trim((string) ($query['file_type'] ?? '')) ?: null,
            'related_type' => trim((string) ($query['related_type'] ?? '')) ?: null,
            'related_id' => (int) ($query['related_id'] ?? 0) > 0 ? (int) $query['related_id'] : null,
            'q' => trim((string) ($query['q'] ?? '')) ?: null,
        ], $page, $perPage);

        // شکل خروجی یکدست: url / file_name / file_size / stream_url
        $result['items'] = self::presentMany($result['items']);

        return $result;
    }

    public static function getDetailed(int $id): array
    {
        $media = self::requireMedia($id);

        // ❗ رفع IDOR (مورد H6 بازبینی):
        // پیش‌تر این بررسی وجود نداشت و هر کاربر احراز هویت‌شده می‌توانست
        // با شمارش id، متادیتای همه‌ی رسانه‌های سیستم را بخواند.
        self::assertCanView($media);

        return self::present($media, true);
    }

    /* ═══════════════════════════════════════════════════════════
     |  ارائه‌ی شکل یکدست برای اپ‌ها
     |  سرور ستون‌های خام دیتابیس را برمی‌گرداند (stored_name,
     |  size_bytes, uploader_id, thumbnail_path) در حالی که اپ‌ها
     |  نام‌های دیگری انتظار داشتند (file_name, file_size, url,
     |  uploaded_by). این تابع فاصله را پر می‌کند و آدرس‌های
     |  قابل استفاده را می‌سازد.
     ═══════════════════════════════════════════════════════════ */

    public static function present(array $media, bool $withAudiences = false): array
    {
        $id = (int) $media['id'];

        $presented = [
            'id'               => $id,
            'file_name'        => (string) ($media['stored_name'] ?? ''),
            'original_name'    => (string) ($media['original_name'] ?? ''),
            'file_type'        => (string) ($media['file_type'] ?? 'other'),
            'mime_type'        => (string) ($media['mime_type'] ?? 'application/octet-stream'),
            'file_size'        => (int) ($media['size_bytes'] ?? 0),
            'duration_seconds' => isset($media['duration_seconds']) && $media['duration_seconds'] !== null
                                    ? (int) $media['duration_seconds']
                                    : null,
            // دانلود (Content-Disposition: attachment)
            'url'              => self::mediaUrl($id, 'download'),
            // پخش/نمایش درون‌خطی (Content-Disposition: inline + پشتیبانی Range)
            'stream_url'       => self::mediaUrl($id, 'stream'),
            'thumbnail_url'    => !empty($media['thumbnail_path'])
                                    ? self::mediaUrl($id, 'thumbnail')
                                    : null,
            'visibility'       => (string) ($media['visibility'] ?? 'private'),
            'related_type'     => $media['related_type'] ?? null,
            'related_id'       => isset($media['related_id']) ? (int) $media['related_id'] : null,
            'description'      => $media['description'] ?? null,
            'status'           => (string) ($media['status'] ?? 'active'),
            'uploaded_by'      => isset($media['uploader_id']) ? (int) $media['uploader_id'] : null,
            'uploader_name'    => $media['uploader_name'] ?? null,
            'created_at'       => $media['created_at'] ?? null,
            'updated_at'       => $media['updated_at'] ?? null,
        ];

        if ($withAudiences) {
            $presented['audiences'] = MediaRepository::audiences($id);
        }

        return $presented;
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    public static function presentMany(array $rows): array
    {
        return array_map(
            static fn (array $row): array => self::present($row),
            array_values($rows)
        );
    }

    private static function mediaUrl(int $id, string $action): string
    {
        return self::baseUrl() . '/media/' . $id . '/' . $action;
    }

    /**
     * ریشه‌ی API (شامل /api/v1).
     * اگر 'app.public_base_url' تنظیم شده باشد از آن استفاده می‌شود،
     * وگرنه از روی Host درخواست ساخته می‌شود.
     * مثال مقدار تنظیمات: https://football.madahinote.ir/api/v1
     */
    private static function baseUrl(): string
    {
        $configured = trim((string) Config::get('app.public_base_url', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return self::origin() . '/api/v1';
    }

    private static function origin(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            ? 'https'
            : 'http';

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    public static function upload(array $data, array $files): array
    {
        if (empty($files['file'])) throw new AppException('فایلی آپلود نشده است', 422);

        $file = $files['file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new AppException('خطا در آپلود فایل', 422);

        $maxMb = (int) Config::get('app.media_max_upload_size_mb', 200);
        $maxBytes = $maxMb * 1024 * 1024;

        if ((int) $file['size'] <= 0) throw new AppException('فایل خالی است', 422);
        if ((int) $file['size'] > $maxBytes) throw new AppException("حجم فایل نمی‌تواند بیشتر از {$maxMb} مگابایت باشد", 422);

        $originalName = basename((string) $file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowed = self::allowedMimeTypes();

        if (!isset($allowed[$extension])) throw new AppException('نوع فایل مجاز نیست', 422);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowed[$extension], true)) throw new AppException('محتوای فایل با پسوند همخوانی ندارد', 422);

        $fileType = self::detectFileType($extension);

        $relativeDir = 'media/' . date('Y/m');
        $absoluteDir = BASE_PATH . '/storage/' . $relativeDir;

        if (!is_dir($absoluteDir)) mkdir($absoluteDir, 0775, true);

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) throw new AppException('ذخیره فایل انجام نشد', 500);

        $visibility = (string) ($data['visibility'] ?? 'private');
        if (!in_array($visibility, self::VISIBILITIES, true)) throw new AppException('سطح دسترسی معتبر نیست', 422);

        $relatedType = trim((string) ($data['related_type'] ?? ''));
        if ($relatedType !== '' && !in_array($relatedType, self::RELATED_TYPES, true)) throw new AppException('نوع مرتبط معتبر نیست', 422);

        $relatedId = self::normalizeOptionalInt($data['related_id'] ?? null);
        $audiences = self::decodeAudiences($data['audiences'] ?? []);

        if (in_array($visibility, ['class', 'age_group', 'players'], true) && empty($audiences)) {
            throw new AppException('برای این سطح دسترسی باید مخاطبان مشخص شوند', 422);
        }

        $mediaId = MediaRepository::create([
            'uploader_id' => Auth::id(),
            'file_type' => $fileType,
            'mime_type' => $mimeType,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_path' => $relativeDir . '/' . $storedName,
            'thumbnail_path' => null,
            'size_bytes' => (int) $file['size'],
            'duration_seconds' => null,
            'visibility' => $visibility,
            'related_type' => $relatedType !== '' ? $relatedType : null,
            'related_id' => $relatedId,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'status' => 'active',
        ]);

        MediaRepository::replaceAudiences($mediaId, $audiences);

        return self::getDetailed($mediaId);
    }

    public static function setAudiences(int $mediaId, array $data): array
    {
        $media = self::requireMedia($mediaId);
        $audiences = self::decodeAudiences($data['audiences'] ?? []);

        if (in_array($media['visibility'], ['class', 'age_group', 'players'], true) && empty($audiences)) {
            throw new AppException('برای این سطح دسترسی باید مخاطبان مشخص شوند', 422);
        }

        MediaRepository::replaceAudiences($mediaId, $audiences);

        return self::getDetailed($mediaId);
    }

    /** دانلود فایل (مرورگر/اپ آن را ذخیره می‌کند) */
    public static function download(int $id): void
    {
        self::serve($id, 'attachment');
    }

    /** پخش درون‌خطی — برای نمایش ویدیو در player بدون اجبار به دانلود */
    public static function stream(int $id): void
    {
        self::serve($id, 'inline');
    }

    /** نمایش thumbnail با همان کنترل دسترسی رسانه‌ی اصلی */
    public static function thumbnail(int $id): void
    {
        $media = self::requireMedia($id);

        if (($media['status'] ?? '') !== 'active') {
            throw new AppException('رسانه در دسترس نیست', 404);
        }

        self::assertCanView($media);

        $relativePath = trim((string) ($media['thumbnail_path'] ?? ''));
        if ($relativePath === '') {
            throw new AppException('thumbnail برای این رسانه موجود نیست', 404);
        }

        $fullPath = BASE_PATH . '/storage/' . ltrim($relativePath, '/');

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new AppException('thumbnail یافت نشد', 404);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $mime = function_exists('mime_content_type')
            ? (string) (mime_content_type($fullPath) ?: 'image/jpeg')
            : 'image/jpeg';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: inline');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        readfile($fullPath);
        exit;
    }

    /**
     * ارسال فایل با پشتیبانی از هدر Range.
     *
     * ⚠ چرا Range لازم است:
     * پیاده‌سازی قبلی کل فایل را با readfile() می‌فرستاد و هدر Range را
     * نادیده می‌گرفت. نتیجه: player نمی‌توانست در ویدیو seek کند و هر
     * قطعی شبکه، دانلود را از صفر شروع می‌کرد.
     */
    private static function serve(int $id, string $disposition): void
    {
        $media = self::requireMedia($id);

        if (($media['status'] ?? '') !== 'active') {
            throw new AppException('رسانه در دسترس نیست', 404);
        }

        self::assertCanView($media);

        $fullPath = BASE_PATH . '/storage/' . $media['file_path'];

        if (!is_file($fullPath)) throw new AppException('فایل رسانه یافت نشد', 404);
        if (!is_readable($fullPath)) throw new AppException('فایل رسانه قابل خواندن نیست', 500);

        $size = (int) filesize($fullPath);
        $mime = (string) (($media['mime_type'] ?? '') !== '' ? $media['mime_type'] : 'application/octet-stream');

        // خروجی باینری باید دقیقاً همان بایت‌ها باشد؛ هر بافر فعال را می‌بندیم.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');

        if ($disposition === 'attachment') {
            $name = rawurlencode((string) (($media['original_name'] ?? '') !== '' ? $media['original_name'] : $media['stored_name']));
            header("Content-Disposition: attachment; filename=\"{$name}\"; filename*=UTF-8''{$name}");
        } else {
            header('Content-Disposition: inline');
        }

        $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');

        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $range, $m) === 1) {
            $rawStart = $m[1];
            $rawEnd = $m[2];

            if ($rawStart === '' && $rawEnd !== '') {
                // bytes=-N  → N بایت آخر
                $length = min((int) $rawEnd, $size);
                $start = max(0, $size - $length);
                $end = $size - 1;
            } else {
                $start = $rawStart === '' ? 0 : (int) $rawStart;
                $end = $rawEnd === '' ? ($size - 1) : (int) $rawEnd;
            }

            if ($start > $end || $start >= $size) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                exit;
            }

            $end = min($end, $size - 1);
            $length = $end - $start + 1;

            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . $length);

            self::outputRange($fullPath, $start, $length);
            exit;
        }

        http_response_code(200);
        header('Content-Length: ' . $size);

        self::outputRange($fullPath, 0, $size);
        exit;
    }

    /** ارسال تکه‌ای فایل تا حافظه اشغال نشود (مهم برای ویدیوهای چند ده مگابایتی) */
    private static function outputRange(string $path, int $offset, int $length): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $chunkSize = 256 * 1024; // ۲۵۶ کیلوبایت
        $remaining = $length;

        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, (int) min($chunkSize, $remaining));

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }

            flush();
        }

        fclose($handle);
    }

    public static function delete(int $id): array
    {
        $media = self::requireMedia($id);
        MediaRepository::setStatus($id, 'deleted');
        return self::getDetailed($id);
    }

    private static function assertCanView(array $media): void
    {
        if (Auth::hasRole('admin')) return;

        if ($media['visibility'] === 'public') return;

        if ($media['visibility'] === 'private' && (int) $media['uploader_id'] === (int) Auth::id()) return;

        $context = ClientRepository::getUserContext((int) Auth::id(), (string) Auth::role());
        $audiences = MediaRepository::audiences((int) $media['id']);

        foreach ($audiences as $audience) {
            if ($media['visibility'] === 'class'
                && $audience['audience_type'] === 'class'
                && in_array((int) $audience['target_id'], $context['class_ids'], true)) {
                return;
            }

            if ($media['visibility'] === 'age_group'
                && $audience['audience_type'] === 'age_group'
                && in_array((int) $audience['target_id'], $context['age_group_ids'], true)) {
                return;
            }

            if ($media['visibility'] === 'players'
                && $audience['audience_type'] === 'player'
                && in_array((int) $audience['target_id'], $context['player_ids'], true)) {
                return;
            }
        }

        throw new AppException('شما به این رسانه دسترسی ندارید', 403);
    }

    private static function requireMedia(int $id): array
    {
        $media = MediaRepository::findById($id);
        if (!$media) throw new AppException('رسانه یافت نشد', 404);
        return $media;
    }

    private static function allowedMimeTypes(): array
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'mp4' => ['video/mp4'],
            'm4v' => ['video/x-m4v', 'video/mp4'],
            'webm' => ['video/webm'],
            'mov' => ['video/quicktime'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav', 'audio/x-wav'],
            'aac' => ['audio/aac'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'application/csv'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    private static function detectFileType(string $extension): string
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) return 'image';
        if (in_array($extension, ['mp4', 'm4v', 'webm', 'mov'], true)) return 'video';
        if (in_array($extension, ['mp3', 'wav', 'aac', 'm4a'], true)) return 'audio';
        if (in_array($extension, ['pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'], true)) return 'document';
        return 'other';
    }

    private static function decodeAudiences(mixed $input): array
    {
        if ($input === null || $input === '') return [];

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (!is_array($decoded)) throw new AppException('ساختار مخاطبان معتبر نیست', 422);
            $input = $decoded;
        }

        if (!is_array($input)) throw new AppException('ساختار مخاطبان معتبر نیست', 422);

        $audiences = [];

        foreach ($input as $item) {
            $audienceType = trim((string) ($item['audience_type'] ?? ''));
            $targetId = (int) ($item['target_id'] ?? 0);

            if (!in_array($audienceType, self::AUDIENCE_TYPES, true)) throw new AppException('نوع مخاطب معتبر نیست', 422);
            if ($targetId <= 0) throw new AppException('شناسه مخاطب معتبر نیست', 422);

            $audiences[] = ['audience_type' => $audienceType, 'target_id' => $targetId];
        }

        return $audiences;
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('شناسه معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }
}