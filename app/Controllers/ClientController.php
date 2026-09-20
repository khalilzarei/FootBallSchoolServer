<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ClientService;

class ClientController
{
    public function children(Request $request, array $params = []): void
    {
        try {
            $children = ClientService::children();
            Response::success('لیست فرزندان', ['children' => $children]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function schedule(Request $request, array $params = []): void
    {
        try {
            $schedule = ClientService::schedule();
            Response::success('برنامه جلسات آینده', ['sessions' => $schedule]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function news(Request $request, array $params = []): void
    {
        try {
            $news = ClientService::news();
            Response::success('اخبار قابل مشاهده', ['news' => $news]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function media(Request $request, array $params = []): void
    {
        try {
            $media = ClientService::media();
            Response::success('رسانه‌های قابل مشاهده', ['media' => $media]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function finance(Request $request, array $params = []): void
    {
        try {
            $finance = ClientService::finance();
            Response::success('وضعیت مالی فرزندان', ['finance' => $finance]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /** کلاس‌های فعال فرزندان (با مربی و برنامه هفتگی) */
    public function classes(Request $request, array $params = []): void
    {
        try {
            $classes = ClientService::classes();
            Response::success('کلاس‌های فرزندان', ['classes' => $classes]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /** مسابقات مرتبط با فرزندان */
    public function matches(Request $request, array $params = []): void
    {
        try {
            $matches = ClientService::matches();
            Response::success('مسابقات فرزندان', ['matches' => $matches]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /** مخاطبین قابل گفتگو (ادمین‌ها + مربیان کلاس‌های فرزندان) */
    public function chatContacts(Request $request, array $params = []): void
    {
        try {
            $contacts = ClientService::chatContacts();
            Response::success('مخاطبین گفتگو', ['contacts' => $contacts]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}