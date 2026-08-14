<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\RobotsGenerator;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __construct(private readonly RobotsGenerator $robots) {}

    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach ($this->robots->disallowedPaths() as $path) {
            $lines[] = "Disallow: {$path}";
        }

        $lines[] = '';
        $lines[] = "Sitemap: {$this->robots->sitemapUrl()}";

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
