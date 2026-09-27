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

    public function newsDetail(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            $news = ClientService::newsDetail($id);
            Response::success('جزئیات خبر', ['news' => $news]);
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

    public function profile(Request $request, array $params = []): void
    {
        try {
            $data = ClientService::profile();
            Response::success('پروفایل', $data);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updateProfile(Request $request, array $params = []): void
    {
        try {
            $data = ClientService::updateOwnProfile($request->input());
            Response::success('پروفایل به‌روزرسانی شد', $data);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updateChild(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            $data = ClientService::updateOwnChild($id, $request->input());
            Response::success('اطلاعات بازیکن به‌روزرسانی شد', $data);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function guardians(Request $request, array $params = []): void
    {
        try {
            $data = ClientService::guardians();
            Response::success('سرپرستان', ['guardians' => $data]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updateGuardian(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            $data = ClientService::updateOwnGuardian($id, $request->input());
            Response::success('سرپرست به‌روزرسانی شد', $data);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function evaluations(Request $request, array $params = []): void
    {
        try {
            $data = ClientService::evaluations();
            Response::success('ارزیابی‌ها', ['evaluations' => $data]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}