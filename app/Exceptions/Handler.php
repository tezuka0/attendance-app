<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // 存在しない勤怠 ID（ModelNotFoundException）は 404 の JSON にする
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (!$request->is('api/*') || !$e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
        });

        // Policy で拒否された（AuthorizationException）ときは 403 の JSON にする
        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if (!$request->is('api/*') || !$e->getPrevious() instanceof AuthorizationException) {
                return null;
            }

            return response()->json(['error' => 'この操作を実行する権限がありません。'], 403);
        });
    }

    /**
     * api/* へのリクエストは、Accept ヘッダーがなくても常に JSON で返す
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }
}
