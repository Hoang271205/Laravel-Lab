<?php

use App\Exceptions\OrderCannotProcessException;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Telescope\IncomingExceptionEntry;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestId::class);
        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->throttle(fn (Throwable $e) => $e instanceof PDOException
            ? Limit::perMinute(10)->by('database-exceptions')
            : null);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Whitelist context and omit arbitrary exception messages, SQL and trace arguments.
        $exceptions->report(function (Throwable $e) {
            $request = app()->bound('request') ? request() : null;
            $context = [
                'request_id' => $request?->attributes->get('request_id'),
                'method' => $request?->method(),
                'route' => $request?->route()?->uri(),
                'exception_type' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_map(fn ($frame) => array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function'])), $e->getTrace()),
            ];
            if ($e instanceof OrderCannotProcessException) {
                $context['order_id'] = $e->orderId;
                Log::warning($e->getMessage(), $context);
            } else {
                Log::error('Unexpected application exception. Use request_id and trace to investigate.', $context);
            }

            if (class_exists(Telescope::class) && Telescope::isRecording()) {
                Telescope::recordException(
                    IncomingExceptionEntry::make($e, [
                        'class' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine(),
                        'message' => $e instanceof OrderCannotProcessException ? $e->getMessage() : 'Unexpected application exception.',
                        'context' => $context, 'trace' => $context['trace'], 'line_preview' => [],
                    ])->tags(['request_id:'.($context['request_id'] ?? 'console')])
                );
            }

            return false;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            $status = match (true) {
                $e instanceof OrderCannotProcessException => 409,
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $code = match ($status) {
                401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED', 409 => 'ORDER_CANNOT_PROCESS',
                419 => 'SESSION_EXPIRED', 422 => 'VALIDATION_FAILED',
                429 => 'TOO_MANY_REQUESTS', 503 => 'SERVICE_UNAVAILABLE',
                default => $status >= 500 ? 'INTERNAL_ERROR' : 'HTTP_ERROR',
            };
            $message = match ($status) {
                401 => 'Vui lòng đăng nhập để tiếp tục.',
                403 => 'Bạn không có quyền thực hiện thao tác này.',
                404 => 'Không tìm thấy tài nguyên. Vui lòng kiểm tra đường dẫn hoặc ID.',
                405 => 'Phương thức HTTP không được hỗ trợ cho đường dẫn này.',
                409 => $e instanceof OrderCannotProcessException ? $e->getMessage() : 'Dữ liệu bị xung đột. Vui lòng kiểm tra và thử lại.',
                419 => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
                422 => 'Dữ liệu chưa hợp lệ. Vui lòng kiểm tra các trường được yêu cầu.',
                429 => 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
                503 => 'Dịch vụ tạm thời không khả dụng. Vui lòng thử lại sau.',
                default => 'Không thể hoàn tất yêu cầu. Vui lòng gửi mã yêu cầu cho bộ phận hỗ trợ.',
            };
            $id = $request->attributes->get('request_id');
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
            $headers['X-Request-ID'] = $id ?? '';

            if ($request->is('api/*') || $request->expectsJson()) {
                $body = ['error' => ['code' => $code, 'message' => $message, 'request_id' => $id]];
                if ($e instanceof ValidationException) {
                    // Field names only: validation messages may include submitted secrets.
                    $body['error']['fields'] = array_keys($e->errors());
                }

                return response()->json($body, $status, $headers);
            }

            // Keep Laravel's browser validation/auth redirects.
            if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }

            return response()->view('errors.safe', compact('status', 'code', 'message', 'id'), $status, $headers);
        });
    })->create();
