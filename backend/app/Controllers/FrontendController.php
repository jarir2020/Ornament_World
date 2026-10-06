<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CatalogService;
use App\Services\CatalogAdminService;
use App\Services\CheckoutService;
use App\Services\CustomerAccountService;
use App\Services\OrderAdminService;
use App\Services\ShipmentService;
use App\Services\SeoService;
use Nemesis\Core\Controller;
use Nemesis\Http\Request;
use Nemesis\Http\Session;

class FrontendController extends Controller
{
    public function __construct(private readonly SeoService $seo = new SeoService())
    {
        parent::__construct();
    }

    public function login(Request $request): void
    {
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['authPage'] = 'login';
        $data['pageProps']['authError'] = (string) $request->query('error', '');
        $data['pageProps']['seo'] = $this->seoData(['robots' => 'noindex,nofollow']);
        $this->render('home', $data);
    }

    public function register(Request $request): void
    {
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['authPage'] = 'register';
        $data['pageProps']['authError'] = (string) $request->query('error', '');
        $data['pageProps']['seo'] = $this->seoData(['robots' => 'noindex,nofollow']);
        $this->render('home', $data);
    }

    public function admin(Request $request): void
    {
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['seo'] = $this->seoData(['title' => 'Admin | Ornaments World', 'robots' => 'noindex,nofollow']);
        $data['pageProps']['admin'] = (new CatalogAdminService())->snapshot();
        $data['pageProps']['admin']['csrfToken'] = function_exists('csrf_token') ? csrf_token() : '';
        $data['pageProps']['admin']['orderDashboard'] = (new OrderAdminService())->dashboard(
            trim((string) $request->query('search', '')),
            trim((string) $request->query('status', ''))
        );
        $data['pageProps']['admin']['orderFilters'] = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $this->render('admin', $data);
    }

    public function adminOrder(Request $request, string $reference): void
    {
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['seo'] = $this->seoData(['title' => 'Order admin | Ornaments World', 'robots' => 'noindex,nofollow']);
        $data['pageProps']['adminOrder'] = (new OrderAdminService())->detail($reference);
        $data['pageProps']['adminOrderPage'] = true;
        $data['pageProps']['adminOrderCsrfToken'] = function_exists('csrf_token') ? csrf_token() : '';

        if ($data['pageProps']['adminOrder'] === null) {
            http_response_code(404);
        } else {
            $shipment = (new ShipmentService())->detail($reference);
            $data['pageProps']['adminOrder']['shipment'] = $shipment['shipment'];
            $data['pageProps']['adminOrder']['shipmentEligible'] = $shipment['eligible'];
            $data['pageProps']['adminOrder']['shipmentCanRetry'] = $shipment['canRetry'];
        }

        $this->render('admin-order', $data);
    }

    public function profile(Request $request): void
    {
        $auth = $request->getMeta('auth', []);
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['profilePage'] = true;
        $data['pageProps']['profile'] = is_array($auth) && isset($auth['sub'])
            ? (new CustomerAccountService())->profile((int) $auth['sub'])
            : null;
        $data['pageProps']['seo'] = $this->seoData(['robots' => 'noindex,nofollow']);
        $this->render('home', $data);
    }

    public function settings(Request $request): void
    {
        $data = $this->pageData($request);
        $data['pageProps']['seo'] = $this->seoData(['robots' => 'noindex,nofollow']);
        $this->render('settings', $data);
    }

    public function dashboard(Request $request): void
    {
        $data = $this->pageData($request);
        $data['pageProps']['seo'] = $this->seoData(['robots' => 'noindex,nofollow']);
        $this->render('dashboard', $data);
    }

    public function storefront(Request $request): void
    {
        $category = trim((string) $request->query('category', ''));
        $search = trim((string) $request->query('search', ''));
        $catalog = (new CatalogService())->storefront($category, $search);
        $data = $this->pageData($request, 'svelte');
        $data['pageProps'] = array_merge($data['pageProps'], $catalog);
        $canonical = $category !== '' && $search === ''
            ? '/storefront?category=' . rawurlencode($category)
            : '/storefront';
        $data['pageProps']['seo'] = $this->seoData([
            'title' => $category !== '' ? ucfirst($category) . ' | Ornaments World' : 'Ornaments World | Men\'s jewellery',
            'description' => 'Discover refined men\'s jewellery in Bangladesh, with clear prices and simple guest checkout.',
            'canonical' => $canonical,
            'robots' => $search === '' ? 'index,follow' : 'noindex,follow',
        ]);

        $this->render('home', $data);
    }

    public function product(Request $request, string $slug): void
    {
        $product = (new CatalogService())->productBySlug($slug);
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['product'] = $product;
        $data['pageProps']['notFound'] = $product === null;

        if ($product === null) {
            $data['pageProps']['seo'] = $this->seoData([
                'title' => 'Product not found | Ornaments World',
                'robots' => 'noindex,nofollow',
            ]);
            http_response_code(404);
        } else {
            $canonical = '/storefront/product/' . rawurlencode($slug);
            $data['pageProps']['seo'] = $this->seoData([
                'title' => $product['name'] . ' | Ornaments World',
                'description' => trim((string) ($product['shortDescription'] ?: $product['description'])),
                'canonical' => $canonical,
                'type' => 'product',
                'image' => $product['imageUrl'] ?? null,
                'structuredData' => $this->seo->productStructuredData($product, $this->seo->url($canonical)),
            ]);
        }

        $this->render('home', $data);
    }

    public function checkout(Request $request): void
    {
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['seo'] = $this->seoData([
            'title' => 'Checkout | Ornaments World',
            'robots' => 'noindex,nofollow',
        ]);
        $data['pageProps']['checkout'] = array_merge(
            (new CheckoutService())->options(),
            ['csrfToken' => function_exists('csrf_token') ? csrf_token() : '']
        );

        $this->render('checkout', $data);
    }

    public function orderSuccess(Request $request, string $reference): void
    {
        $order = (new CheckoutService())->success($reference);
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['seo'] = $this->seoData([
            'title' => 'Order received | Ornaments World',
            'robots' => 'noindex,nofollow',
        ]);
        $data['pageProps']['orderSuccess'] = $order;
        $data['pageProps']['orderSuccessPage'] = true;

        if ($order === null) {
            http_response_code(404);
        }

        $this->render('order-success', $data);
    }

    public function help(Request $request, string $slug): void
    {
        $pages = [
            'delivery' => [
                'eyebrow' => 'Delivery information',
                'title' => 'Simple delivery, clearly explained.',
                'description' => 'Learn about Bangladesh delivery charges and the order-confirmation process at Ornaments World.',
                'intro' => 'We keep delivery details visible before you place an order. Our team calls to confirm each order before shipment.',
                'sections' => [
                    ['heading' => 'Delivery charges', 'body' => 'Inside Dhaka delivery starts at ৳60. Delivery to other Bangladesh districts starts at ৳120. The applicable charge is shown at checkout and stored with the order.'],
                    ['heading' => 'Order confirmation', 'body' => 'After you submit a guest order, our team reviews the details and calls the supplied Bangladesh mobile number. We send only confirmed orders to the courier workflow.'],
                    ['heading' => 'Address details', 'body' => 'Please provide a complete house or building, road or village, district, and area/thana/upazila so the delivery team can reach you.'],
                ],
            ],
            'contact' => [
                'eyebrow' => 'Contact',
                'title' => 'A considered answer is close by.',
                'description' => 'Contact and order-support information for Ornaments World.',
                'intro' => 'For order questions, keep your order reference ready. Customer support details can be replaced with the final business channels before launch.',
                'sections' => [
                    ['heading' => 'Order support', 'body' => 'For an existing order, share the order reference and the phone number used at checkout through the approved business support channel. Do not send payment passwords or account credentials.'],
                    ['heading' => 'Business details', 'body' => 'Final phone, email, social links, and operating hours are pending the approved brand and business-content handoff.'],
                ],
            ],
            'privacy' => [
                'eyebrow' => 'Privacy',
                'title' => 'Your information should stay purposeful.',
                'description' => 'A concise privacy summary for Ornaments World guest checkout.',
                'intro' => 'We use checkout information to review, confirm, deliver, and support an order. The final legal policy should be approved before launch.',
                'sections' => [
                    ['heading' => 'What checkout needs', 'body' => 'Guest checkout asks for a name, Bangladesh mobile number, delivery address, district, area/thana/upazila, and optional email. These details are used for order handling and delivery support.'],
                    ['heading' => 'What we do not publish', 'body' => 'Customer phone numbers, addresses, internal review flags, shipment attempts, and order history are protected from public pages and analytics events.'],
                    ['heading' => 'Final policy review', 'body' => 'Retention periods, support contacts, legal basis, and any marketing consent wording must be confirmed with the business before production launch.'],
                ],
            ],
        ];
        $content = $pages[$slug] ?? null;
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['help'] = $content;
        $data['pageProps']['seo'] = $this->seoData($content === null
            ? ['title' => 'Help page not found | Ornaments World', 'robots' => 'noindex,nofollow']
            : [
                'title' => $content['title'] . ' | Ornaments World',
                'description' => $content['description'],
                'canonical' => '/help/' . rawurlencode($slug),
            ]);
        if ($content === null) {
            $data['pageProps']['notFoundPage'] = true;
            http_response_code(404);
        }

        $this->render('home', $data);
    }

    public function notFound(Request $request): void
    {
        // The fallback route runs outside the frontend middleware group.
        \Nemesis\Core\View::addPath(base_path('resources/views/svelte'));
        $data = $this->pageData($request, 'svelte');
        $data['pageProps']['notFoundPage'] = true;
        $data['pageProps']['seo'] = $this->seoData([
            'title' => 'Page not found | Ornaments World',
            'robots' => 'noindex,nofollow',
        ]);
        http_response_code(404);
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
        if (!is_array($auth) || $auth === []) {
            $sessionAuth = Session::get('auth');
            if (is_array($sessionAuth) && isset($sessionAuth['sub']) && is_numeric($sessionAuth['sub'])) {
                $auth = (new CustomerAccountService())->sessionUser((int) $sessionAuth['sub']) ?? [];
            }
        }

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
                'help' => null,
                'cartCount' => 0,
                'isAuthenticated' => !empty($auth),
                'authRole' => $auth['role'] ?? null,
                'csrfToken' => function_exists('csrf_token') ? csrf_token() : '',
                'authPage' => null,
                'authError' => '',
                'profilePage' => false,
                'profile' => null,
                'seo' => $this->seoData(),
            ],
        ];
    }

    private function seoData(array $overrides = []): array
    {
        $requestPath = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $defaults = [
            'title' => 'Ornaments World | Men\'s jewellery',
            'description' => 'Refined men\'s jewellery for Bangladesh, with simple guest checkout and clear delivery information.',
            'canonical' => $requestPath !== '' ? $requestPath : '/storefront',
            'robots' => 'index,follow',
            'type' => 'website',
            'image' => null,
            'structuredData' => null,
        ];
        $seo = array_merge($defaults, $overrides);
        $seo['canonical'] = $this->seo->url((string) $seo['canonical']);
        if ($seo['image'] !== null && $seo['image'] !== '') {
            $seo['image'] = $this->seo->url((string) $seo['image']);
        }
        return $seo;
    }
}
