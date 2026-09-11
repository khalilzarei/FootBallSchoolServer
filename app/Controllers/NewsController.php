<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\NewsService;

class NewsController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = NewsService::list($request->input());
            Response::success('لیست اخبار', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'body' => 'required|string',
            'status' => 'string|in:draft,published,archived',
            'publish_at' => 'string|max:30',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $news = NewsService::create($data);
            Response::success('خبر ایجاد شد', ['news' => $news], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $news = NewsService::getDetailed($id);
            Response::success('جزئیات خبر', ['news' => $news]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'string|min:3|max:255',
            'body' => 'string',
            'status' => 'string|in:draft,published,archived',
            'publish_at' => 'string|max:30',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $news = NewsService::update($id, $data);
            Response::success('خبر به‌روزرسانی شد', ['news' => $news]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function publish(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $news = NewsService::publish($id);
            Response::success('خبر منتشر شد', ['news' => $news]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function archive(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $news = NewsService::archive($id);
            Response::success('خبر آرشیو شد', ['news' => $news]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function delete(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $result = NewsService::delete($id);
            Response::success('خبر حذف شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function setAudiences(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $news = NewsService::setAudiences($id, $request->input());
            Response::success('مخاطبان خبر به‌روزرسانی شدند', ['news' => $news]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}