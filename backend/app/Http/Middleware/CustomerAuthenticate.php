<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CustomerAccountService;
use Nemesis\Auth\JWT;
use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;

/** Protects optional customer account pages and profile actions. */
final class CustomerAuthenticate implements MiddlewareInterface
{
    public function __construct(private readonly CustomerAccountService $accounts = new CustomerAccountService())
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $auth = $this->sessionAuth();
        if ($auth === null) {
            $auth = $this->bearerAuth($request);
        }
        if ($auth === null) {
            $accept = strtolower((string) $request->header('Accept', ''));
            if (str_contains($accept, 'application/json')) {
                return Response::json(['error' => 'Unauthenticated.'], 401);
            }
            return Response::redirect('/login?next=/profile');
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
        return $this->accounts->sessionUser((int) $session['sub']);
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
        return $this->accounts->sessionUser((int) $payload['sub']);
    }
}
