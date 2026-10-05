<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CatalogService;
use Nemesis\Core\Controller;
use Nemesis\Http\Request;

class FrontendController extends Controller
{
    public function login(Request $request): void
    {
        $this->render('login', $this->pageData($request));
    }

    public function admin(Request $request): void
    {
        $this->render('admin/dashboard', $this->pageData($request));
    }

    public function profile(Request $request): void
    {
        $this->render('profile', $this->pageData($request));
    }

    public function settings(Request $request): void
    {
        $this->render('settings', $this->pageData($request));
    }

    public function dashboard(Request $request): void
    {
        $this->render('dashboard', $this->pageData($request));
    }

    public function storefront(Request $request): void
    {
        $category = trim((string) $request->query('category', ''));
        $search = trim((string) $request->query('search', ''));
        $catalog = (new CatalogService())->storefront($category, $search);
        $data = $this->pageData($request, 'svelte');
        $data['pageProps'] = array_merge($data['pageProps'], $catalog);

        $this->render('home', $data);
    }

    public function product(Request $request, string $slug): void
    {
        $product = (new CatalogService())->productBySlug($slug);
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['product'] = $product;
        $data['pageProps']['notFound'] = $product === null;

        if ($product === null) {
            http_response_code(404);
        }

        $this->render('home', $data);
    }

    public function preview(Request $request, string $framework = 'server'): void
    {
        $this->render('preview', $this->pageData($request, $framework));
    }

    /**
     * Shared render context for auth-aware frontend pages.
     */
    protected function pageData(Request $request, string $fallbackFramework = 'server'): array
    {
        $framework = (string) $request->getMeta('frontend.framework', $fallbackFramework);
        $auth = $request->getMeta('auth', []);

        return [
            'framework' => $framework,
            'layout' => $request->getMeta('frontend.layout', 'layouts.app'),
            'isAuthenticated' => !empty($auth),
            'canSeeAdmin' => ($auth['role'] ?? null) === 'admin',
            'authRole' => $auth['role'] ?? null,
            'authSubject' => $auth['sub'] ?? null,
            'pageProps' => [
                'brand' => 'Ornaments World',
                'eyebrow' => 'Quiet luxury, made for every day',
                'headline' => 'Details that make the moment.',
                'intro' => 'Discover considered men’s jewellery designed to feel personal, lasting, and unmistakably yours.',
                'categories' => [],
                'products' => [],
                'filters' => ['category' => '', 'search' => ''],
                'cartCount' => 0,
                'isAuthenticated' => !empty($auth),
                'authRole' => $auth['role'] ?? null,
            ],
        ];
    }
}
