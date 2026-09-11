<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\ChatService;

class ChatController
{
    public function rooms(Request $request, array $params = []): void
    {
        try {
            $rooms = ChatService::rooms();
            Response::success('لیست اتاق‌های چت', ['rooms' => $rooms]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function createRoom(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'room_type' => 'required|string|in:guardian_admin,coach_admin,guardian_coach',
            'target_user_id' => 'required|integer',
            'player_id' => 'integer',
            'class_id' => 'integer',
            'subject' => 'string|max:255',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $room = ChatService::createRoom($data);
            Response::success('اتاق چت ایجاد شد', ['room' => $room], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function roomDetails(Request $request, array $params = []): void
    {
        $roomId = (int) ($params['id'] ?? 0);

        try {
            $room = ChatService::roomDetails($roomId);
            Response::success('جزئیات اتاق چت', ['room' => $room]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function messages(Request $request, array $params = []): void
    {
        $roomId = (int) ($params['id'] ?? 0);

        try {
            $messages = ChatService::messages($roomId, $request->input());
            Response::success('لیست پیام‌ها', ['messages' => $messages]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function sendMessage(Request $request, array $params = []): void
    {
        $roomId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'message_type' => 'string|in:text,image,video,file,system',
            'body' => 'string|max:5000',
            'media_id' => 'integer',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $message = ChatService::sendMessage($roomId, $data);
            Response::success('پیام ارسال شد', ['message' => $message], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function read(Request $request, array $params = []): void
    {
        $roomId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'last_read_message_id' => 'required|integer',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $result = ChatService::read($roomId, $data);
            Response::success('پیام‌ها خوانده شدند', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}