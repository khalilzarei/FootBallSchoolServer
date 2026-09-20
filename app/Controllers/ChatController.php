<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\ChatService;

class ChatController
{
    /**
     * هندل کننده عمومی برای تبدیل خطاهای داخلی به پاسخ استاندارد.
     */
    private static function runAndRespond(callable $callback): void
    {
        try {
            $callback();
        } catch (AppException $e) {
            Response::error(
                $e->getMessage(),
                $e->getCode() ?: 400
            );
        } catch (\PDOException $e) {
            error_log(
                '[ChatController PDO] ' .
                $e->getMessage() .
                ' @ ' .
                ($e->getFile() ?? '') .
                ':' .
                ($e->getLine() ?? '')
            );

            if (Config::get('app.debug')) {
                Response::error(
                    'خطای پایگاه داده: ' . $e->getMessage(),
                    500
                );
            } else {
                Response::error(
                    'خطای داخلی سرور در پایگاه داده',
                    500
                );
            }
        } catch (\TypeError | \Error | \RuntimeException $e) {
            error_log(
                '[ChatController Error] ' .
                $e->getMessage() .
                ' @ ' .
                ($e->getFile() ?? '') .
                ':' .
                ($e->getLine() ?? '')
            );

            if (Config::get('app.debug')) {
                Response::error(
                    'خطای داخلی: ' .
                    $e->getMessage() .
                    ' (' .
                    basename($e->getFile() ?? '') .
                    ':' .
                    $e->getLine() .
                    ')',
                    500
                );
            } else {
                Response::error(
                    'خطای داخلی سرور',
                    500
                );
            }
        }
    }

    /**
     * GET /chat/rooms
     */
    public function rooms(
        Request $request,
        array $params = []
    ): void {
        self::runAndRespond(function () {
            $rooms = ChatService::rooms();

            Response::success(
                'لیست اتاق‌های چت',
                [
                    'rooms' => $rooms,
                ]
            );
        });
    }

    /**
     * POST /chat/rooms
     *
     * Private:
     * {
     *   "target_user_id": 25
     * }
     *
     * Group:
     * {
     *   "is_group": true,
     *   "title": "نونهالان",
     *   "image": "/uploads/chat/default-group.webp",
     *   "user_ids": [10, 25, 31]
     * }
     */
    public function createRoom(
        Request $request,
        array $params = []
    ): void {
        $data = $request->input();

        $errors = Validator::make(
            $data,
            [
                'is_group' => 'boolean',

                'target_user_id' => 'integer',

                'user_ids' => 'array',

                'title' => 'string|max:255',

                'image' => 'string|max:500',

                'player_id' => 'integer',

                'class_id' => 'integer',

                'age_group_id' => 'integer',

                'subject' => 'string|max:255',
            ]
        );

        if (!empty($errors)) {
            Response::error(
                'اطلاعات ورودی معتبر نیست',
                422,
                $errors
            );

            return;
        }

        self::runAndRespond(
            function () use ($data) {
                $room = ChatService::createRoom($data);

                Response::success(
                    'اتاق چت ایجاد شد',
                    [
                        'room' => $room,
                    ],
                    201
                );
            }
        );
    }

    /**
     * GET /chat/rooms/{id}
     */
    public function roomDetails(
        Request $request,
        array $params = []
    ): void {
        $roomId = (int) (
            $params['id'] ?? 0
        );

        if ($roomId <= 0) {
            Response::error(
                'شناسه اتاق چت معتبر نیست',
                422
            );

            return;
        }

        self::runAndRespond(
            function () use ($roomId) {
                $room = ChatService::roomDetails(
                    $roomId
                );

                Response::success(
                    'جزئیات اتاق چت',
                    [
                        'room' => $room,
                    ]
                );
            }
        );
    }

    /**
     * GET /chat/rooms/{id}/messages
     */
    public function messages(
        Request $request,
        array $params = []
    ): void {
        $roomId = (int) (
            $params['id'] ?? 0
        );

        if ($roomId <= 0) {
            Response::error(
                'شناسه اتاق چت معتبر نیست',
                422
            );

            return;
        }

        $data = $request->input();

        $errors = Validator::make(
            $data,
            [
                'limit' => 'integer',
                'before' => 'integer',
            ]
        );

        if (!empty($errors)) {
            Response::error(
                'اطلاعات ورودی معتبر نیست',
                422,
                $errors
            );

            return;
        }

        self::runAndRespond(
            function () use ($roomId, $data) {
                $messages = ChatService::messages(
                    $roomId,
                    $data
                );

                Response::success(
                    'لیست پیام‌ها',
                    [
                        'messages' => $messages,
                    ]
                );
            }
        );
    }

    /**
     * POST /chat/rooms/{id}/messages
     */
    public function sendMessage(
        Request $request,
        array $params = []
    ): void {
        $roomId = (int) (
            $params['id'] ?? 0
        );

        if ($roomId <= 0) {
            Response::error(
                'شناسه اتاق چت معتبر نیست',
                422
            );

            return;
        }

        $data = $request->input();

        $errors = Validator::make(
            $data,
            [
                'message_type' =>
                    'string|in:text,image,video,file,system',

                'body' =>
                    'string|max:5000',

                'media_id' =>
                    'integer',
            ]
        );

        if (!empty($errors)) {
            Response::error(
                'اطلاعات ورودی معتبر نیست',
                422,
                $errors
            );

            return;
        }

        self::runAndRespond(
            function () use ($roomId, $data) {
                $message = ChatService::sendMessage(
                    $roomId,
                    $data
                );

                Response::success(
                    'پیام ارسال شد',
                    [
                        'message' => $message,
                    ],
                    201
                );
            }
        );
    }

    /**
     * POST /chat/rooms/{id}/read
     */
    public function read(
        Request $request,
        array $params = []
    ): void {
        $roomId = (int) (
            $params['id'] ?? 0
        );

        if ($roomId <= 0) {
            Response::error(
                'شناسه اتاق چت معتبر نیست',
                422
            );

            return;
        }

        $data = $request->input();

        $errors = Validator::make(
            $data,
            [
                'last_read_message_id' =>
                    'required|integer',
            ]
        );

        if (!empty($errors)) {
            Response::error(
                'اطلاعات ورودی معتبر نیست',
                422,
                $errors
            );

            return;
        }

        self::runAndRespond(
            function () use ($roomId, $data) {
                $result = ChatService::read(
                    $roomId,
                    $data
                );

                Response::success(
                    'پیام‌ها خوانده شدند',
                    $result
                );
            }
        );
    }

    /**
     * POST /chat/rooms/{id}/lock
     *
     * فقط ادمین
     */
    public function lockRoom(
        Request $request,
        array $params = []
    ): void {
        $roomId = (int) (
            $params['id'] ?? 0
        );

        if ($roomId <= 0) {
            Response::error(
                'شناسه اتاق چت معتبر نیست',
                422
            );

            return;
        }

        self::runAndRespond(
            function () use ($roomId) {
                $room = ChatService::setRoomLocked(
                    $roomId,
                    true
                );

                Response::success(
                    'گفتگو قفل شد',
                    [
                        'room' => $room,
                    ]
                );
            }
        );
    }

    /**
     * POST /chat/rooms/{id}/unlock
     *
     * فقط ادمین
     */
    public function unlockRoom(
        Request $request,
        array $params = []
    ): void {
        $roomId = (int) (
            $params['id'] ?? 0
        );

        if ($roomId <= 0) {
            Response::error(
                'شناسه اتاق چت معتبر نیست',
                422
            );

            return;
        }

        self::runAndRespond(
            function () use ($roomId) {
                $room = ChatService::setRoomLocked(
                    $roomId,
                    false
                );

                Response::success(
                    'گفتگو باز شد',
                    [
                        'room' => $room,
                    ]
                );
            }
        );
    }
}