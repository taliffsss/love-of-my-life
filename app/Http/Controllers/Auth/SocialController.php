<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Screen;
use App\Services\Social\SocialAuth;
use App\Services\Social\SocialLoginException;
use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\HttpException;
use Naluz\Session\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Sign in or sign up with Google or Facebook (one flow: a returning identity signs in, a new one makes an account).
 * `redirect` sends the person to the provider with a one-time `state` kept in the session; `callback` accepts the
 * answer only for that state, within ten minutes. See App\Services\Social\SocialAuth for the account rules.
 */
final class SocialController
{
    private const MAX_AGE = 600;

    public function redirect(string $provider, SocialAuth $social, Store $session): ResponseInterface
    {
        if (!$social->enabled($provider) && $social->preview()) {
            return Screen::back('/login', $social->missingMessage($provider));
        }
        $this->ensureEnabled($provider, $social);
        $state = bin2hex(random_bytes(24));
        $verifier = bin2hex(random_bytes(32));
        $session->put('social.' . $provider, ['state' => $state, 'verifier' => $verifier, 'at' => time()]);

        return \Naluz\Http\Response::redirect($social->authorizeUrl($provider, $state, $verifier), 302);
    }

    public function callback(string $provider, ServerRequestInterface $request, SocialAuth $social, Store $session, Auth $auth, Connection $db): ResponseInterface
    {
        $this->ensureEnabled($provider, $social);
        $query = $request->getQueryParams();
        $saved = $session->pull('social.' . $provider); // one use, whatever happens next

        if (isset($query['error'])) {
            return Screen::back('/login', 'The ' . ucfirst($provider) . ' sign-in was cancelled.');
        }
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $code = is_string($query['code'] ?? null) ? $query['code'] : '';
        if (
            !is_array($saved) || $state === '' || $code === ''
            || !hash_equals((string) ($saved['state'] ?? ''), $state)
            || time() - (int) ($saved['at'] ?? 0) > self::MAX_AGE
        ) {
            return Screen::back('/login', 'That sign-in link expired. Please try again.');
        }

        try {
            [$user, $created] = $social->resolve($social->profile($provider, $code, (string) $saved['verifier']), $db);
        } catch (SocialLoginException $e) {
            return Screen::back('/login', $e->getMessage());
        }

        $auth->login($user);
        return Screen::back('/dashboard', $created ? 'Welcome, ' . $user->name . '! Create your first page below.' : '');
    }

    private function ensureEnabled(string $provider, SocialAuth $social): void
    {
        if (!$social->enabled($provider)) {
            throw new HttpException(404, 'Not found.');
        }
    }
}
