<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;
use App\Services\Social\SocialAuth;
use Naluz\Auth\Auth;
use Naluz\Http\Response;
use Naluz\Session\Store;

/**
 * Renders a product view (landing, sign-in, dashboard) with what every screen needs: the brand, the signed-in user, the
 * validation errors and old input flashed by the previous request, and a one-line status message.
 */
final class Screen
{
    public static function render(string $view, array $data = [], int $status = 200): Response
    {
        $session = app(Store::class);
        $user = app(Auth::class)->user();
        return view($view, $data + [
            'socialProviders' => app(SocialAuth::class)->visibleProviders(),
            'brand' => (string) config('saas.brand'),
            // Google Analytics: not in the admin area (layouts/product.naluz.php prints the tag when this is not empty)
            'analyticsId' => str_starts_with($view, 'admin.') ? '' : (string) config('saas.analytics_id'),
            'user' => $user instanceof User ? $user : null,
            'errors' => (array) ($session->get('errors') ?? []),
            'old' => (array) ($session->get('old') ?? []),
            'flash' => is_string($flash = $session->get('flash')) ? $flash : '',
        ], $status);
    }

    /** Redirect with a status message shown once on the next screen. */
    public static function back(string $to, string $message = ''): Response
    {
        if ($message !== '') {
            app(Store::class)->flash('flash', $message);
        }
        return Response::redirect($to, 303);
    }
}
