<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\PlayerRepository;
use App\Repositories\UserRepository;
use App\Services\PlayerService;

class PlayerRegistrationController
{
    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        // Honeypot ضد ربات
        if (trim((string) ($data['website'] ?? '')) !== '') {
            Response::success('اطلاعات دریافت شد', [], 201);
        }

        $errors = Validator::make($data, [
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'birth_date' => 'required|date',
            'gender' => 'required|string|in:male,female',
            'national_code' => 'required|digits:10',
            'mobile' => 'required|string|min:11|max:15',
            'medical_notes' => 'string|max:1000',
            'notes' => 'string|max:1000',

            'guardian_full_name' => 'string|min:2|max:150',
            'guardian_mobile' => 'string|min:11|max:15',
            'guardian_national_code' => 'digits:10',
            'guardian_relation' => 'string|in:father,mother,grandfather,grandmother,uncle,aunt,guardian,other',
            'emergency_phone' => 'string|min:11|max:15',
        ]);

        $nationalCode = self::englishDigits(
            trim((string) ($data['national_code'] ?? ''))
        );

        $mobile = self::englishDigits(
            trim((string) ($data['mobile'] ?? ''))
        );

        if (
            $nationalCode !== ''
            && !self::isValidNationalCode($nationalCode)
        ) {
            $errors['national_code'][] = 'کد ملی معتبر نیست';
        }

        if (
            $mobile !== ''
            && !preg_match('/^09[0-9]{9}$/', $mobile)
        ) {
            $errors['mobile'][] =
                'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد';
        }

        $guardianName = trim(
            (string) ($data['guardian_full_name'] ?? '')
        );

        $guardianMobile = self::englishDigits(
            trim((string) ($data['guardian_mobile'] ?? ''))
        );

        $guardianNationalCode = self::englishDigits(
            trim((string) ($data['guardian_national_code'] ?? ''))
        );

        $emergencyPhone = self::englishDigits(
            trim((string) ($data['emergency_phone'] ?? ''))
        );

        $hasGuardian =
            $guardianName !== ''
            || $guardianMobile !== '';

        if ($hasGuardian) {
            if ($guardianName === '') {
                $errors['guardian_full_name'][] =
                    'نام سرپرست الزامی است';
            }

            if (!preg_match('/^09[0-9]{9}$/', $guardianMobile)) {
                $errors['guardian_mobile'][] =
                    'شماره موبایل سرپرست معتبر نیست';
            }

            if (
                $guardianNationalCode !== ''
                && !self::isValidNationalCode($guardianNationalCode)
            ) {
                $errors['guardian_national_code'][] =
                    'کد ملی سرپرست معتبر نیست';
            }

            if (
                $emergencyPhone !== ''
                && !preg_match('/^09[0-9]{9}$/', $emergencyPhone)
            ) {
                $errors['emergency_phone'][] =
                    'شماره تماس اضطراری معتبر نیست';
            }
        }

        if (!empty($errors)) {
            Response::error(
                'اطلاعات واردشده معتبر نیست',
                422,
                $errors
            );
        }

        try {
            if (
                PlayerRepository::existsByNationalCode($nationalCode)
                || UserRepository::existsByNationalCode($nationalCode)
            ) {
                throw new AppException(
                    'این کد ملی قبلاً ثبت شده است',
                    422
                );
            }

            if (
                PlayerRepository::existsByMobile($mobile)
                || UserRepository::existsByMobile($mobile)
            ) {
                throw new AppException(
                    'این شماره موبایل قبلاً ثبت شده است',
                    422
                );
            }

            $pdo = Database::connection();
            $pdo->beginTransaction();

            try {
                $player = PlayerService::create([
                    'first_name' => trim(
                        (string) $data['first_name']
                    ),
                    'last_name' => trim(
                        (string) $data['last_name']
                    ),
                    'national_code' => $nationalCode,
                    'mobile' => $mobile,
                    'birth_date' => trim(
                        (string) $data['birth_date']
                    ),
                    'gender' => trim(
                        (string) $data['gender']
                    ),
                    'medical_notes' => trim(
                        (string) ($data['medical_notes'] ?? '')
                    ) ?: null,
                    'notes' => trim(
                        (string) ($data['notes'] ?? '')
                    ) ?: 'ثبت‌نام عمومی از فرم وب',
                    'status' => 'active',
                ], $_FILES['avatar'] ?? null);

                $guardian = null;

                if ($hasGuardian) {
                    $guardian = PlayerService::attachNewGuardian(
                        (int) $player['id'],
                        [
                            'full_name' => $guardianName,
                            'mobile' => $guardianMobile,
                            'national_code' =>
                                $guardianNationalCode !== ''
                                    ? $guardianNationalCode
                                    : null,
                            'relation' => (string) (
                                $data['guardian_relation'] ?? 'other'
                            ),
                            'emergency_phone' =>
                                $emergencyPhone !== ''
                                    ? $emergencyPhone
                                    : null,
                        ]
                    );
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }

            Response::success(
                'ثبت‌نام بازیکن با موفقیت انجام شد',
                [
                    'player' => $player,
                    'guardian' => $guardian,
                    'login' => [
                        'identifier' => $nationalCode,
                        'initial_password' => $nationalCode,
                        'must_change_password' => true,
                    ],
                ],
                201
            );
        } catch (AppException $e) {
            Response::error(
                $e->getMessage(),
                $e->getCode() ?: 400
            );
        }
    }

    private static function englishDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',

            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    private static function isValidNationalCode(string $code): bool
    {
        if (!preg_match('/^[0-9]{10}$/', $code)) {
            return false;
        }

        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2
            ? $check === $remainder
            : $check === 11 - $remainder;
    }
}