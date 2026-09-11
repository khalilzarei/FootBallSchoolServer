<?php

declare(strict_types=1);

use App\Core\Response;

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\GuardianController;
use App\Controllers\PlayerController;
use App\Controllers\SeasonController;
use App\Controllers\AgeGroupController;
use App\Controllers\CoachController;
use App\Controllers\ClassController;
use App\Controllers\ClassScheduleController;
use App\Controllers\EnrollmentController;
use App\Controllers\SessionController;
use App\Controllers\AttendanceController;
use App\Controllers\EvaluationController;
use App\Controllers\InvoiceController;
use App\Controllers\DiscountController;
use App\Controllers\PaymentController;
use App\Controllers\MediaController;
use App\Controllers\NewsController;
use App\Controllers\MatchController;
use App\Controllers\NotificationController;
use App\Controllers\SettingController;
use App\Controllers\ChatController;
use App\Controllers\ReportController;
use App\Controllers\ClientController;

/*
|--------------------------------------------------------------------------
| Ping
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/ping', function () {
    Response::success('pong');
});

/*
|--------------------------------------------------------------------------
| Self (خود کاربر)
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/me/avatar', [UserController::class, 'updateOwnAvatar'], ['auth']);
$router->delete('/api/v1/me/avatar', [UserController::class, 'deleteOwnAvatar'], ['auth']);

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/auth/login', [AuthController::class, 'login']);

$router->get('/api/v1/auth/me', [AuthController::class, 'me'], ['auth']);

$router->post('/api/v1/auth/change-password', [AuthController::class, 'changePassword'], ['auth']);

$router->post('/api/v1/auth/logout', [AuthController::class, 'logout'], ['auth']);

/*
|--------------------------------------------------------------------------
| Users
|--------------------------------------------------------------------------
*/
$router->post('/api/v1/users/{id}/avatar', [UserController::class, 'updateAvatar'], ['auth', 'admin']);
$router->delete('/api/v1/users/{id}/avatar', [UserController::class, 'deleteAvatar'], ['auth', 'admin']);

$router->get('/api/v1/users', [UserController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/users', [UserController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/users/{id}', [UserController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/users/{id}', [UserController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['auth', 'admin']);

$router->post('/api/v1/users/{id}/activate', [UserController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/users/{id}/deactivate', [UserController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Guardians
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/guardians', [GuardianController::class, 'index'], ['auth', 'admin']);

$router->get('/api/v1/guardians/{id}', [GuardianController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/guardians/{id}', [GuardianController::class, 'update'], ['auth', 'admin']);

$router->get('/api/v1/guardians/{id}/players', [GuardianController::class, 'players'], ['auth', 'admin']);

$router->post('/api/v1/guardians/{id}/players', [GuardianController::class, 'attachPlayer'], ['auth', 'admin']);

$router->delete('/api/v1/guardians/{id}/players/{player_id}', [GuardianController::class, 'detachPlayer'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Players
|--------------------------------------------------------------------------
*/

$router->post('/api/v1/players/{id}/avatar', [PlayerController::class, 'updateAvatar'], ['auth', 'admin']);
$router->delete('/api/v1/players/{id}/avatar', [PlayerController::class, 'deleteAvatar'], ['auth', 'admin']);

$router->get('/api/v1/players', [PlayerController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/players', [PlayerController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}', [PlayerController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/players/{id}', [PlayerController::class, 'update'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}/guardians', [PlayerController::class, 'guardians'], ['auth', 'admin']);

$router->post('/api/v1/players/{id}/guardians', [PlayerController::class, 'attachGuardian'], ['auth', 'admin']);

$router->delete('/api/v1/players/{id}/guardians/{guardian_id}', [PlayerController::class, 'detachGuardian'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}/attendances', [AttendanceController::class, 'playerAttendances'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}/evaluations', [EvaluationController::class, 'playerEvaluations'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}/invoices', [InvoiceController::class, 'playerInvoices'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}/payments', [PaymentController::class, 'playerPayments'], ['auth', 'admin']);

$router->get('/api/v1/players/{id}/balance', [PaymentController::class, 'playerBalance'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Seasons
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/seasons', [SeasonController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/seasons', [SeasonController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/seasons/{id}', [SeasonController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/seasons/{id}', [SeasonController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/seasons/{id}/activate', [SeasonController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/seasons/{id}/deactivate', [SeasonController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Age Groups
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/age-groups', [AgeGroupController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/age-groups', [AgeGroupController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/age-groups/{id}', [AgeGroupController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/age-groups/{id}', [AgeGroupController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/age-groups/{id}/activate', [AgeGroupController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/age-groups/{id}/deactivate', [AgeGroupController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Coaches
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/coaches', [CoachController::class, 'index'], ['auth', 'admin']);

$router->get('/api/v1/coaches/{id}', [CoachController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/coaches/{id}', [CoachController::class, 'update'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Classes
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/classes', [ClassController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/classes', [ClassController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/classes/{id}', [ClassController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/classes/{id}', [ClassController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/classes/{id}/activate', [ClassController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/classes/{id}/deactivate', [ClassController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Class Schedules
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/classes/{id}/schedules', [ClassScheduleController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/classes/{id}/schedules', [ClassScheduleController::class, 'store'], ['auth', 'admin']);

$router->put('/api/v1/schedules/{id}', [ClassScheduleController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/schedules/{id}/activate', [ClassScheduleController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/schedules/{id}/deactivate', [ClassScheduleController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Enrollments
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/classes/{id}/players', [EnrollmentController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/classes/{id}/players', [EnrollmentController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/enrollments/{id}', [EnrollmentController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/enrollments/{id}', [EnrollmentController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/enrollments/{id}/activate', [EnrollmentController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/enrollments/{id}/deactivate', [EnrollmentController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Sessions
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/sessions', [SessionController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/sessions', [SessionController::class, 'store'], ['auth', 'admin']);

$router->post('/api/v1/sessions/generate', [SessionController::class, 'generate'], ['auth', 'admin']);

$router->get('/api/v1/sessions/{id}', [SessionController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/sessions/{id}', [SessionController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/sessions/{id}/cancel', [SessionController::class, 'cancel'], ['auth', 'admin']);

$router->post('/api/v1/sessions/{id}/complete', [SessionController::class, 'complete'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/sessions/{id}/attendance', [AttendanceController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/sessions/{id}/attendance', [AttendanceController::class, 'saveBulk'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Evaluations
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/evaluations', [EvaluationController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/evaluations', [EvaluationController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/evaluations/{id}', [EvaluationController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/evaluations/{id}', [EvaluationController::class, 'update'], ['auth', 'admin']);

$router->get('/api/v1/sessions/{id}/evaluations', [EvaluationController::class, 'sessionEvaluations'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Invoices
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/invoices', [InvoiceController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/invoices', [InvoiceController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/invoices/{id}', [InvoiceController::class, 'show'], ['auth', 'admin']);

$router->post('/api/v1/invoices/{id}/cancel', [InvoiceController::class, 'cancel'], ['auth', 'admin']);

$router->post('/api/v1/invoices/{id}/items', [InvoiceController::class, 'addItem'], ['auth', 'admin']);

$router->put('/api/v1/invoice-items/{id}', [InvoiceController::class, 'updateItem'], ['auth', 'admin']);

$router->delete('/api/v1/invoice-items/{id}', [InvoiceController::class, 'deleteItem'], ['auth', 'admin']);

$router->post('/api/v1/invoices/{id}/discounts', [InvoiceController::class, 'applyDiscount'], ['auth', 'admin']);

$router->post('/api/v1/invoices/{id}/installments', [InvoiceController::class, 'addInstallment'], ['auth', 'admin']);

$router->get('/api/v1/invoices/{id}/installments', [InvoiceController::class, 'listInstallments'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Discounts
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/discounts', [DiscountController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/discounts', [DiscountController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/discounts/{id}', [DiscountController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/discounts/{id}', [DiscountController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/discounts/{id}/activate', [DiscountController::class, 'activate'], ['auth', 'admin']);

$router->post('/api/v1/discounts/{id}/deactivate', [DiscountController::class, 'deactivate'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/payments', [PaymentController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/payments', [PaymentController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/payments/{id}', [PaymentController::class, 'show'], ['auth', 'admin']);

$router->post('/api/v1/payments/{id}/approve', [PaymentController::class, 'approve'], ['auth', 'admin']);

$router->post('/api/v1/payments/{id}/reject', [PaymentController::class, 'reject'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Media
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/media', [MediaController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/media/upload', [MediaController::class, 'upload'], ['auth', 'admin']);

$router->get('/api/v1/media/{id}', [MediaController::class, 'show'], ['auth']);

$router->get('/api/v1/media/{id}/download', [MediaController::class, 'download'], ['auth']);

$router->post('/api/v1/media/{id}/audiences', [MediaController::class, 'setAudiences'], ['auth', 'admin']);

$router->delete('/api/v1/media/{id}', [MediaController::class, 'delete'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| News
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/news', [NewsController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/news', [NewsController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/news/{id}', [NewsController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/news/{id}', [NewsController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/news/{id}/publish', [NewsController::class, 'publish'], ['auth', 'admin']);

$router->post('/api/v1/news/{id}/archive', [NewsController::class, 'archive'], ['auth', 'admin']);

$router->post('/api/v1/news/{id}/audiences', [NewsController::class, 'setAudiences'], ['auth', 'admin']);

$router->delete('/api/v1/news/{id}', [NewsController::class, 'delete'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Matches
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/matches', [MatchController::class, 'index'], ['auth', 'admin']);

$router->post('/api/v1/matches', [MatchController::class, 'store'], ['auth', 'admin']);

$router->get('/api/v1/matches/{id}', [MatchController::class, 'show'], ['auth', 'admin']);

$router->put('/api/v1/matches/{id}', [MatchController::class, 'update'], ['auth', 'admin']);

$router->post('/api/v1/matches/{id}/cancel', [MatchController::class, 'cancel'], ['auth', 'admin']);

$router->post('/api/v1/matches/{id}/result', [MatchController::class, 'setResult'], ['auth', 'admin']);

$router->post('/api/v1/matches/{id}/players', [MatchController::class, 'addPlayer'], ['auth', 'admin']);

$router->put('/api/v1/match-players/{id}', [MatchController::class, 'updatePlayer'], ['auth', 'admin']);

$router->delete('/api/v1/match-players/{id}', [MatchController::class, 'removePlayer'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/notifications', [NotificationController::class, 'index'], ['auth']);

$router->get('/api/v1/notifications/unread-count', [NotificationController::class, 'unreadCount'], ['auth']);

$router->post('/api/v1/notifications/{id}/read', [NotificationController::class, 'read'], ['auth']);

$router->post('/api/v1/notifications/read-all', [NotificationController::class, 'readAll'], ['auth']);

$router->post('/api/v1/notifications/send', [NotificationController::class, 'send'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/settings', [SettingController::class, 'index'], ['auth', 'admin']);

$router->put('/api/v1/settings', [SettingController::class, 'update'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Chat
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/chat/rooms', [ChatController::class, 'rooms'], ['auth']);

$router->post('/api/v1/chat/rooms', [ChatController::class, 'createRoom'], ['auth']);

$router->get('/api/v1/chat/rooms/{id}', [ChatController::class, 'roomDetails'], ['auth']);

$router->get('/api/v1/chat/rooms/{id}/messages', [ChatController::class, 'messages'], ['auth']);

$router->post('/api/v1/chat/rooms/{id}/messages', [ChatController::class, 'sendMessage'], ['auth']);

$router->post('/api/v1/chat/rooms/{id}/read', [ChatController::class, 'read'], ['auth']);

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/reports/dashboard', [ReportController::class, 'dashboard'], ['auth', 'admin']);

$router->get('/api/v1/reports/finance', [ReportController::class, 'finance'], ['auth', 'admin']);

$router->get('/api/v1/reports/debts', [ReportController::class, 'debts'], ['auth', 'admin']);

$router->get('/api/v1/reports/attendance', [ReportController::class, 'attendance'], ['auth', 'admin']);

$router->get('/api/v1/reports/classes', [ReportController::class, 'classes'], ['auth', 'admin']);

/*
|--------------------------------------------------------------------------
| Client / Me
|--------------------------------------------------------------------------
*/

$router->get('/api/v1/me/children', [ClientController::class, 'children'], ['auth']);

$router->get('/api/v1/me/schedule', [ClientController::class, 'schedule'], ['auth']);

$router->get('/api/v1/me/news', [ClientController::class, 'news'], ['auth']);

$router->get('/api/v1/me/media', [ClientController::class, 'media'], ['auth']);

$router->get('/api/v1/me/finance', [ClientController::class, 'finance'], ['auth']);