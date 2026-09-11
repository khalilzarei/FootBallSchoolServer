<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\NewsRepository;
use DateTime;

class NewsService
{
    private const STATUSES = ['draft', 'published', 'archived'];
    private const AUDIENCE_TYPES = ['global', 'role', 'class', 'age_group', 'player'];
    private const ROLES = ['admin', 'coach', 'guardian'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت خبر معتبر نیست', 422);

        return NewsRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => trim((string) ($query['q'] ?? '')) ?: null,
        ], $page, $perPage);
    }

    public static function getDetailed(int $id): array
    {
        $news = self::requireNews($id);
        $news['audiences'] = NewsRepository::audiences($id);
        return $news;
    }

    public static function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان خبر الزامی است', 422);

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') throw new AppException('متن خبر الزامی است', 422);

        $status = (string) ($data['status'] ?? 'draft');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $publishAt = self::normalizePublishAt($data['publish_at'] ?? null);
        if ($status === 'published' && $publishAt === null) $publishAt = date('Y-m-d H:i:s');

        $audiences = self::decodeAudiences($data['audiences'] ?? []);

        $newsId = NewsRepository::create([
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'publish_at' => $publishAt,
            'created_by' => Auth::id(),
        ]);

        NewsRepository::replaceAudiences($newsId, $audiences);

        return self::getDetailed($newsId);
    }

    public static function update(int $id, array $data): array
    {
        $news = self::requireNews($id);
        $updateData = [];

        if (array_key_exists('title', $data)) {
            $v = trim((string) $data['title']);
            if ($v === '') throw new AppException('عنوان معتبر نیست', 422);
            $updateData['title'] = $v;
        }

        if (array_key_exists('body', $data)) {
            $v = trim((string) $data['body']);
            if ($v === '') throw new AppException('متن معتبر نیست', 422);
            $updateData['body'] = $v;
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        if (array_key_exists('publish_at', $data)) $updateData['publish_at'] = self::normalizePublishAt($data['publish_at']);

        $finalStatus = $updateData['status'] ?? $news['status'];
        $finalPublishAt = array_key_exists('publish_at', $updateData) ? $updateData['publish_at'] : $news['publish_at'];

        if ($finalStatus === 'published' && $finalPublishAt === null) $updateData['publish_at'] = date('Y-m-d H:i:s');

        if (!empty($updateData)) NewsRepository::update($id, $updateData);

        if (array_key_exists('audiences', $data)) {
            NewsRepository::replaceAudiences($id, self::decodeAudiences($data['audiences']));
        }

        return self::getDetailed($id);
    }

    public static function publish(int $id): array
    {
        $news = self::requireNews($id);
        NewsRepository::setStatus($id, 'published');

        if ($news['publish_at'] === null) {
            NewsRepository::update($id, ['publish_at' => date('Y-m-d H:i:s')]);
        }

        return self::getDetailed($id);
    }

    public static function archive(int $id): array
    {
        self::requireNews($id);
        NewsRepository::setStatus($id, 'archived');
        return self::getDetailed($id);
    }

    public static function delete(int $id): array
    {
        self::requireNews($id);
        NewsRepository::softDelete($id);
        return ['news_id' => $id];
    }

    public static function setAudiences(int $id, array $data): array
    {
        self::requireNews($id);
        NewsRepository::replaceAudiences($id, self::decodeAudiences($data['audiences'] ?? []));
        return self::getDetailed($id);
    }

    private static function requireNews(int $id): array
    {
        $news = NewsRepository::findById($id);
        if (!$news) throw new AppException('خبر یافت نشد', 404);
        return $news;
    }

    private static function normalizePublishAt(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        $value = trim((string) $value);

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) return $dt->format('Y-m-d H:i:s');
        }

        throw new AppException('تاریخ انتشار باید معتبر باشد', 422);
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
            if (!in_array($audienceType, self::AUDIENCE_TYPES, true)) throw new AppException('نوع مخاطب معتبر نیست', 422);

            $role = null;
            $targetId = null;

            if ($audienceType === 'global') {
                // no extra fields
            } elseif ($audienceType === 'role') {
                $role = trim((string) ($item['role'] ?? ''));
                if (!in_array($role, self::ROLES, true)) throw new AppException('نقش مخاطب معتبر نیست', 422);
            } else {
                $targetId = (int) ($item['target_id'] ?? 0);
                if ($targetId <= 0) throw new AppException('شناسه مخاطب معتبر نیست', 422);
            }

            $audiences[] = ['audience_type' => $audienceType, 'role' => $role, 'target_id' => $targetId];
        }

        return $audiences;
    }
}