<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Models\Page;
use Naluz\Auth\Auth;
use Naluz\Http\HttpException;

/** Every dashboard lookup is scoped to the signed-in owner: someone else's page answers 404, never 403. */
trait OwnsPages
{
    private function ownedPage(Auth $auth, string $slug): Page
    {
        $page = Page::query()->where('slug', $slug)->where('user_id', (int) $auth->id())->first();
        if (!$page instanceof Page) {
            throw new HttpException(404, 'Page not found.');
        }
        return $page;
    }

    private function editUrl(Page $page, string $anchor = ''): string
    {
        return '/dashboard/pages/' . $page->slug . ($anchor !== '' ? '#' . $anchor : '');
    }
}
