<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CatalogAdminService;
use Nemesis\Core\Controller;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class CatalogAdminController extends Controller
{
    public function __construct(private readonly CatalogAdminService $catalog = new CatalogAdminService())
    {
        parent::__construct();
    }

    public function storeCategory(Request $request): Response
    {
        return $this->runAction(fn(): array => $this->catalog->createCategory($request->all()), 201);
    }

    public function storeProduct(Request $request): Response
    {
        return $this->runAction(fn(): array => $this->catalog->createProduct($request->all()), 201);
    }

    public function updateProduct(Request $request, int|string $id): Response
    {
        return $this->runAction(fn(): array => $this->catalog->updateProduct((int) $id, $request->all()));
    }

    public function toggleProduct(Request $request, int|string $id): Response
    {
        $active = filter_var($request->input('active', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $this->runAction(fn(): array => $this->catalog->setActive((int) $id, $active !== false));
    }

    private function runAction(callable $action, int $successStatus = 200): Response
    {
        try {
            return Response::json([
                'success' => true,
                'data' => $action(),
            ], $successStatus);
        } catch (\InvalidArgumentException $error) {
            return Response::json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 422);
        } catch (\RuntimeException $error) {
            $status = $error->getMessage() === 'Product not found.' ? 404 : 422;
            return Response::json([
                'success' => false,
                'message' => $error->getMessage(),
            ], $status);
        }
    }
}
