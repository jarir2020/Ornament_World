<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SeoService;
use Nemesis\Core\Controller;
use Nemesis\Http\Response;

final class SeoController extends Controller
{
    public function __construct(private readonly SeoService $seo = new SeoService())
    {
        parent::__construct();
    }

    public function robots(): Response
    {
        return Response::text($this->seo->robots())->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function sitemap(): Response
    {
        return Response::make($this->seo->sitemap(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
