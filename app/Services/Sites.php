<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;

/**
 * Maps hosts to pages and pages to their public address.
 *
 *  - <name>.<saas domain>  → the Sweetheart or Forever page that claimed <name> (nothing to configure per page: a wildcard DNS record
 *    and certificate cover every name)
 *  - a custom domain set by an admin (e.g. the original single-page site) → that page
 *  - the saas domain itself, www, localhost, an IP address or any other host → the product (landing page, dashboard)
 */
final class Sites
{
    public function __construct(private readonly string $domain, private readonly string $appUrl)
    {
    }

    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * @return Page|false|null the page served on this host; false when the host is a subdomain without a page;
     *                         null when the host belongs to the product itself
     */
    public function pageForHost(string $host): Page|false|null
    {
        $host = strtolower(rtrim(trim($host), '.'));
        $product = ['', 'localhost', $this->domain, 'www.' . $this->domain];
        if (in_array($host, $product, true) || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        if ($this->domain !== '' && str_ends_with($host, '.' . $this->domain)) {
            $label = substr($host, 0, -strlen($this->domain) - 1);
            if ($label === '' || str_contains($label, '.')) {
                return false;
            }
            $page = Page::query()->where('subdomain', $label)->first();
            return $page instanceof Page && $page->allowsSubdomain() ? $page : false;
        }

        $page = Page::query()->where('custom_domain', $host)->first();
        return $page instanceof Page ? $page : null;
    }

    /** Where visitors open the page: its subdomain, else its custom domain, else /p/{slug} on the product domain. */
    public function url(Page $page): string
    {
        if ($page->subdomain !== null && $page->subdomain !== '' && $page->allowsSubdomain()) {
            return $this->subdomainUrl($page->subdomain);
        }
        if ($page->custom_domain !== null && $page->custom_domain !== '') {
            return 'https://' . $page->custom_domain . '/';
        }
        return $this->pathUrl($page);
    }

    public function pathUrl(Page $page): string
    {
        return rtrim($this->appUrl, '/') . '/p/' . $page->slug;
    }

    /** Same scheme and port as APP_URL, e.g. http://jinelin.localhost:8000/ in development. */
    public function subdomainUrl(string $name): string
    {
        $scheme = (string) (parse_url($this->appUrl, PHP_URL_SCHEME) ?: 'https');
        $port = parse_url($this->appUrl, PHP_URL_PORT);
        return $scheme . '://' . $name . '.' . $this->domain . ($port !== null && $port !== false ? ':' . $port : '') . '/';
    }
}
