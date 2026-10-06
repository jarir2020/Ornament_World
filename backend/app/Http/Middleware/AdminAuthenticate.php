<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AdminAuthService;
use Nemesis\Auth\JWT;
use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;

/**
 * Protects staff pages and actions for both browser sessions and API clients.
 *
 * HTML navigation is redirected to the login page; JSON actions receive an
 * explicit 401/403 response so a failed admin action is recoverable in the UI.
 */
final class AdminAuthenticate implements MiddlewareInterface
{
    public function __construct(private readonly AdminAuthService $auth = new AdminAuthService())
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $auth = $this->sessionAuth();
        if ($auth === null) {
            $auth = $this->bearerAuth($request);
        }

        if ($auth === null) {
            return $this->unauthenticated($request);
        }

        if (($auth['role'] ?? '') !== 'admin') {
            return Response::json(['error' => 'Forbidden. Admin role required.'], 403);
        }

        $request->setMeta('auth', $auth);
        return $next($request);
    }

    private function sessionAuth(): ?array
    {
        $session = Session::get('auth');
        if (!is_array($session) || !isset($session['sub']) || !is_numeric($session['sub'])) {
            return null;
        }

        return $this->auth->sessionUser((int) $session['sub']);
    }

    private function bearerAuth(Request $request): ?array
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return null;
        }

        $payload = JWT::verify($token);
        if (!is_array($payload) || !isset($payload['sub']) || !is_numeric($payload['sub'])) {
            return null;
        }

        $user = $this->auth->sessionUser((int) $payload['sub']);
        return $user !== null ? $user : null;
    }

    private function unauthenticated(Request $request): Response
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'application/json')) {
            return Response::json(['error' => 'Unauthenticated.'], 401);
        }

        return Response::redirect('/login?next=/admin');
    }
}
