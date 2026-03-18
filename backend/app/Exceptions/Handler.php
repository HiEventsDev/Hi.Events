<?php

namespace HiEvents\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Facade as Sentry;
use Sentry\State\Scope;
use Symfony\Component\Routing\Exception\ResourceNotFoundException as SymfonyResourceNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        ResourceNotFoundException::class,
        ResourceConflictException::class,
        SymfonyResourceNotFoundException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param Throwable $e
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $e)
    {
        if ($this->shouldReport($e) && app()->bound('sentry')) {
            try {
                $user = auth()->user();
                if ($user) {
                    $ip = request()->ip();
                    $isImpersonating = (bool) auth()->payload()->get('is_impersonating', false);
                    $impersonatorId = $isImpersonating ? auth()->payload()->get('impersonator_id') : null;

                    Sentry::configureScope(function (Scope $scope) use ($user, $ip, $isImpersonating, $impersonatorId): void {
                        $scope->setUser([
                            'id' => $user->id,
                            'email' => $user->email,
                            'username' => trim($user->first_name . ' ' . $user->last_name),
                            'ip_address' => $ip,
                        ]);

                        if ($isImpersonating) {
                            $scope->setTag('is_impersonating', 'true');
                            $scope->setTag('impersonator_id', (string) $impersonatorId);
                        }
                    });
                }
            } catch (Throwable) {
            }
            Sentry::captureException($e);
        }

        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param Throwable $exception
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ResourceNotFoundException || $exception instanceof SymfonyResourceNotFoundException) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Resource not found',
            ], 404);
        }

        return parent::render($request, $exception);
    }
}
