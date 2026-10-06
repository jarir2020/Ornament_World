<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CheckoutService;
use Nemesis\Core\Controller;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

final class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout = new CheckoutService())
    {
        parent::__construct();
    }

    public function store(Request $request): Response
    {
        try {
            return Response::json([
                'success' => true,
                'data' => $this->checkout->createOrder($request->all()),
            ], 201);
        } catch (\InvalidArgumentException $error) {
            return Response::json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 422);
        } catch (\Throwable $error) {
            error_log('Checkout order creation failed: ' . $error->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'We could not place the order right now. Please try again.',
            ], 500);
        }
    }
}
