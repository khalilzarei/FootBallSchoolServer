<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Services\MediaService;

class MediaController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = MediaService::list($request->input());
            Response::success('لیست رسانه‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function upload(Request $request, array $params = []): void
    {
        try {
            $media = MediaService::upload($request->input(), $_FILES);
            Response::success('رسانه آپلود شد', ['media' => $media], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $media = MediaService::getDetailed($id);
            Response::success('جزئیات رسانه', ['media' => $media]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function download(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            MediaService::download($id);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * پخش درون‌خطی (بدون Content-Disposition: attachment).
     * برای نمایش عکس در اسلایدر اخبار و پخش ویدیو در player.
     * با پشتیبانی از هدر Range برای seek کردن در ویدیو.
     */
    public function stream(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            MediaService::stream($id);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /** نمایش thumbnail با کنترل دسترسی API */
    public function thumbnail(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            MediaService::thumbnail($id);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function setAudiences(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $media = MediaService::setAudiences($id, $request->input());
            Response::success('مخاطبان رسانه به‌روزرسانی شدند', ['media' => $media]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function delete(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $media = MediaService::delete($id);
            Response::success('رسانه حذف شد', ['media' => $media]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}