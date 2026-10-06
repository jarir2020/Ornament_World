<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OrderAdminService;
use Nemesis\Core\Controller;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

final class OrderAdminController extends Controller
{
    public function __construct(private readonly OrderAdminService $orders = new OrderAdminService())
    {
        parent::__construct();
    }

    public function transition(Request $request, string $reference): Response
    {
        return $this->runAction(function () use ($request, $reference): array {
            return $this->orders->transition(
                $reference,
                trim((string) $request->input('status', '')),
                (string) $request->input('note', ''),
                $this->actorId($request)
            );
        });
    }

    public function note(Request $request, string $reference): Response
    {
        return $this->runAction(fn (): array => $this->orders->addNote(
            $reference,
            (string) $request->input('note', ''),
            $this->actorId($request)
        ));
    }

    public function reviewFlag(Request $request, int|string $id): Response
    {
        return $this->runAction(fn (): array => $this->orders->reviewFlag(
            (int) $id,
            trim((string) $request->input('resolution', '')),
            (string) $request->input('note', ''),
            $this->actorId($request)
        ));
    }

    private function runAction(callable $action): Response
    {
        try {
            return Response::json(['success' => true, 'data' => $action()]);
        } catch (\InvalidArgumentException $error) {
            return Response::json(['success' => false, 'message' => $error->getMessage()], 422);
        } catch (\RuntimeException $error) {
            $status = in_array($error->getMessage(), ['Order not found.', 'Fraud flag not found.'], true) ? 404 : 422;
            return Response::json(['success' => false, 'message' => $error->getMessage()], $status);
        }
    }

    private function actorId(Request $request): ?int
    {
        $auth = $request->getMeta('auth', []);
        $subject = $auth['sub'] ?? null;
        return is_numeric($subject) ? (int) $subject : null;
    }
}
