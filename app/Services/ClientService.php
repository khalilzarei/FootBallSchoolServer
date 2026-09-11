<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\ClientRepository;

class ClientService
{
    public static function children(): array
    {
        if (!Auth::hasRole('guardian', 'admin')) {
            throw new AppException('این بخش فقط برای سرپرست در دسترس است', 403);
        }

        if (Auth::hasRole('admin')) return [];

        return ClientRepository::childrenForGuardianUserId((int) Auth::id());
    }

    public static function schedule(): array
    {
        return ClientRepository::scheduleForUser((int) Auth::id(), (string) Auth::role());
    }

    public static function news(): array
    {
        return ClientRepository::publishedNewsForUser((int) Auth::id(), (string) Auth::role());
    }

    public static function media(): array
    {
        return ClientRepository::visibleMediaForUser((int) Auth::id(), (string) Auth::role());
    }

    public static function finance(): array
    {
        if (!Auth::hasRole('guardian')) {
            throw new AppException('این بخش فقط برای سرپرست در دسترس است', 403);
        }

        return ClientRepository::financeForGuardianUserId((int) Auth::id());
    }
}