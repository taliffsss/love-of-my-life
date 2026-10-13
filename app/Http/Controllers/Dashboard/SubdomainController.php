<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Screen;
use App\Services\Sites;
use App\Services\Subdomains;
use Naluz\Auth\Auth;
use Naluz\Database\QueryException;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The dedicated address of a Forever page: <name>.<saas domain>. Claiming a name is all it takes; the wildcard DNS record
 * and certificate already cover it, and App\Http\Middleware\ResolveSiteHost routes it to the page.
 */
final class SubdomainController
{
    use OwnsPages;

    /** GET ?name= — live availability for the form. */
    public function check(ServerRequestInterface $request, Subdomains $subdomains, Sites $sites): array
    {
        $name = $subdomains->normalise((string) ($request->getQueryParams()['name'] ?? ''));
        $problem = $subdomains->problem($name);
        if ($problem === null && $subdomains->isTaken($name)) {
            $problem = 'That address is already taken.';
        }
        return [
            'name' => $name,
            'available' => $problem === null,
            'message' => $problem ?? 'Available: ' . $sites->subdomainUrl($name),
        ];
    }

    public function update(string $slug, ServerRequestInterface $request, Auth $auth, Subdomains $subdomains, Sites $sites): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        if (!$page->allowsSubdomain()) {
            throw new HttpException(403, 'A dedicated address comes with the Forever plan.');
        }
        $body = Request::body($request);
        $name = $subdomains->normalise((string) ($body['subdomain'] ?? ''));
        $problem = $subdomains->problem($name) ?? ($subdomains->isTaken($name, $page->id) ? 'That address is already taken.' : null);
        if ($problem !== null) {
            throw new ValidationException(['subdomain' => [$problem]], $body);
        }

        try {
            $page->forceFill(['subdomain' => $name])->save();
        } catch (QueryException) {
            // claimed by someone else between the check and the save (unique index)
            throw new ValidationException(['subdomain' => ['That address was just taken. Try another one.']], $body);
        }
        return Screen::back($this->editUrl($page, 'share'), 'Your page now lives at ' . $sites->subdomainUrl($name));
    }

    public function destroy(string $slug, Auth $auth): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $page->forceFill(['subdomain' => null])->save();
        return Screen::back($this->editUrl($page, 'share'), 'The dedicated address was released.');
    }
}
