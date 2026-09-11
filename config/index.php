<?php

declare(strict_types=1);

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

require dirname(__DIR__) . '/bootstrap.php';

$request = new Request();

if ($request->method() === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Platform, X-Device-Name');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    exit;
}

$router = new Router();

require BASE_PATH . '/routes/api.php';

try {
    $router->dispatch($request);
} catch (AppException $e) {
    Response::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Response::error('خطای داخلی سرور', 500);
}