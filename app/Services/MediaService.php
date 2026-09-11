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

        return MediaRepository::paginate([
            'status' => trim((string) ($query['status'] ?? '')) ?: null,
            'visibility' => trim((string) ($query['visibility'] ?? '')) ?: null,
            'file_type' => trim((string) ($query['file_type'] ?? '')) ?: null,
            'related_type' => trim((string) ($query['related_type'] ?? '')) ?: null,
            'related_id' => (int) ($query['related_id'] ?? 0) > 0 ? (int) $query['related_id'] : null,
            'q' => trim((string) ($query['q'] ?? '')) ?: null,
        ], $page, $perPage);
    }

    public static function getDetailed(int $id): array
    {
        $media = self::requireMedia($id);
        $media['audiences'] = MediaRepository::audiences($id);
        return $media;
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

    public static function download(int $id): void
    {
        $media = self::requireMedia($id);

        if ($media['status'] !== 'active') throw new AppException('رسانه در دسترس نیست', 404);

        self::assertCanView($media);

        $fullPath = BASE_PATH . '/storage/' . $media['file_path'];

        if (!is_file($fullPath)) throw new AppException('فایل رسانه یافت نشد', 404);
        if (!is_readable($fullPath)) throw new AppException('فایل رسانه قابل خواندن نیست', 500);

        header('Content-Type: ' . $media['mime_type']);
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) $media['original_name']) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($fullPath);
        exit;
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