<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ShipmentService;
use Nemesis\Core\Controller;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

final class ShipmentAdminController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments = new ShipmentService())
    {
        parent::__construct();
    }

    public function create(Request $request, string $reference): Response
    {
        return $this->runAction(fn (): array => $this->shipments->createForOrder(
            $reference,
            $this->actorId($request)
        ));
    }

    public function manual(Request $request, string $reference): Response
    {
        return $this->runAction(fn (): array => $this->shipments->recordManual(
            $reference,
            (string) $request->input('manual_reference', ''),
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
            $status = in_array($error->getMessage(), ['Order not found.', 'Shipment not found.'], true) ? 404 : 422;
            return Response::json(['success' => false, 'message' => $error->getMessage()], $status);
        } catch (\Throwable $error) {
            error_log('Shipment admin action failed: ' . $error->getMessage());
            return Response::json(['success' => false, 'message' => 'The shipment action could not be completed right now.'], 500);
        }
    }

    private function actorId(Request $request): ?int
    {
        $auth = $request->getMeta('auth', []);
        $subject = $auth['sub'] ?? null;
        return is_numeric($subject) ? (int) $subject : null;
    }
}
