<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Naluz\Database\Connection;

/**
 * Google and Facebook sign-in by the server-side OAuth authorization-code flow, with the rules Dink Play settled on:
 *
 *  - The provider's own account id identifies the person, never the e-mail. A returning identity signs in to the account
 *    it is linked to.
 *  - A new identity makes a new account, but only with an address the provider vouches for (Google: email_verified;
 *    Facebook: the address it returns, which it withholds until confirmed). No address, no account.
 *  - A provider sign-in is never linked to an existing account by e-mail. Accounts made by password are not e-mail
 *    verified here, so linking would let whoever registered an address first take over its owner's Google sign-in.
 *  - An administrator never signs in through a provider.
 *  - The account gets an unusable random password, so the password form cannot open it.
 */
final class SocialAuth
{
    public const PROVIDERS = ['google', 'facebook'];

    private const GOOGLE_AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO = 'https://openidconnect.googleapis.com/v1/userinfo';

    /** @param array<string,array<string,string>> $config config/social.php */
    public function __construct(
        private readonly array $config,
        private readonly string $appUrl,
        private readonly ?ClientInterface $http = null,
    ) {
    }

    /** Whether the buttons show before the keys are set (see config/social.php). */
    public function preview(): bool
    {
        return (bool) ($this->config['preview'] ?? false);
    }

    /** @return list<string> the providers to show buttons for: the enabled ones, or all of them in preview */
    public function visibleProviders(): array
    {
        return $this->preview() ? self::PROVIDERS : $this->enabledProviders();
    }

    /** What to tell a developer who clicks a button whose keys are not set. */
    public function missingMessage(string $provider): string
    {
        $keys = $provider === 'google' ? 'GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET' : 'FACEBOOK_APP_ID and FACEBOOK_APP_SECRET';
        return ucfirst($provider) . ' sign-in is not set up yet. Add ' . $keys . ' to .env (see .env.example), then try again.';
    }

    public function enabled(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true)
            && ($this->config[$provider]['client_id'] ?? '') !== ''
            && ($this->config[$provider]['client_secret'] ?? '') !== '';
    }

    /** @return list<string> the providers that are switched on */
    public function enabledProviders(): array
    {
        return array_values(array_filter(self::PROVIDERS, fn (string $p): bool => $this->enabled($p)));
    }

    public function redirectUri(string $provider): string
    {
        return rtrim($this->appUrl, '/') . '/auth/' . $provider . '/callback';
    }

    /** The provider page to send the person to. $verifier is the PKCE secret kept in the session (Google). */
    public function authorizeUrl(string $provider, string $state, string $verifier): string
    {
        $id = $this->config[$provider]['client_id'];
        if ($provider === 'google') {
            return self::GOOGLE_AUTH . '?' . http_build_query([
                'client_id' => $id,
                'redirect_uri' => $this->redirectUri($provider),
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account',
            ]);
        }

        return 'https://www.facebook.com/' . $this->config['facebook']['graph_version'] . '/dialog/oauth?' . http_build_query([
            'client_id' => $id,
            'redirect_uri' => $this->redirectUri($provider),
            'response_type' => 'code',
            'scope' => 'email,public_profile',
            'state' => $state,
        ]);
    }

    /** Trade the code for the person's profile. @throws SocialLoginException */
    public function profile(string $provider, string $code, string $verifier): SocialProfile
    {
        try {
            return $provider === 'google' ? $this->googleProfile($code, $verifier) : $this->facebookProfile($code);
        } catch (SocialLoginException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // class only: a transport error's message can carry the request URL, and Facebook's holds the app secret
            logger()->warning('[social] {provider} sign-in failed: {class}', ['provider' => $provider, 'class' => $e::class]);
            throw new SocialLoginException('We could not reach ' . ucfirst($provider) . '. Please try again.');
        }
    }

    /**
     * The account this profile signs in to, made if it is new.
     *
     * @return array{0:User,1:bool} the user and whether the account was just created
     * @throws SocialLoginException
     */
    public function resolve(SocialProfile $profile, Connection $db): array
    {
        $identity = SocialAccount::query()->where('provider', $profile->provider)->where('provider_uid', $profile->uid)->first();
        if ($identity !== null) {
            $user = User::query()->find($identity->user_id);
            if ($user instanceof User) {
                $this->refuseAdmin($user);
                return [$user, false];
            }
            if (User::query()->withTrashed()->where('id', $identity->user_id)->exists()) {
                throw new SocialLoginException(User::DELETED_MESSAGE); // deleted, not gone: it is not made again
            }
        }

        $email = User::normaliseEmail($profile->email);
        if ($email === '' || !$profile->emailVerified || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            throw new SocialLoginException(ucfirst($profile->provider) . ' did not share a confirmed e-mail address, so we cannot make an account from it. '
                . 'Allow the e-mail permission and try again, or create an account with your e-mail instead.');
        }
        if (User::query()->onlyTrashed()->where('email', $email)->exists()) {
            throw new SocialLoginException(User::DELETED_MESSAGE);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new SocialLoginException('There is already an account with this e-mail. Sign in with its password instead.');
        }

        $name = trim(preg_replace('/\s+/u', ' ', $profile->name) ?? '');
        $name = mb_substr($name !== '' ? $name : (string) strstr($email, '@', true), 0, 80);

        $user = $db->transaction(function () use ($profile, $email, $name): User {
            $user = new User();
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => bin2hex(random_bytes(32)), // never shown or used: provider accounts sign in through the provider
                'is_admin' => false,
                'login_type' => $profile->provider,
            ])->save();
            $link = new SocialAccount();
            $link->forceFill(['user_id' => $user->id, 'provider' => $profile->provider, 'provider_uid' => $profile->uid])->save();
            return $user;
        });

        return [$user, true];
    }

    private function refuseAdmin(User $user): void
    {
        if (!$user->isActive()) {
            throw new SocialLoginException($user->inactiveMessage());
        }
        if ($user->is_admin) {
            throw new SocialLoginException('Administrators sign in with their e-mail and password.');
        }
    }

    private function client(): ClientInterface
    {
        return $this->http ?? new Client(['timeout' => 8, 'connect_timeout' => 4, 'http_errors' => false]);
    }

    /** @return array<string,mixed> */
    private function json(string $method, string $url, array $options): array
    {
        $response = $this->client()->request($method, $url, $options);
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 400 || !is_array($data)) {
            throw new SocialLoginException('The sign-in could not be completed. Please try again.');
        }
        return $data;
    }

    private function googleProfile(string $code, string $verifier): SocialProfile
    {
        $token = $this->json('POST', self::GOOGLE_TOKEN, ['form_params' => [
            'code' => $code,
            'client_id' => $this->config['google']['client_id'],
            'client_secret' => $this->config['google']['client_secret'],
            'redirect_uri' => $this->redirectUri('google'),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]]);
        $access = (string) ($token['access_token'] ?? '');
        if ($access === '') {
            throw new SocialLoginException('The sign-in could not be completed. Please try again.');
        }
        $info = $this->json('GET', self::GOOGLE_USERINFO, ['headers' => ['Authorization' => 'Bearer ' . $access]]);
        $uid = (string) ($info['sub'] ?? '');
        if ($uid === '') {
            throw new SocialLoginException('The sign-in could not be completed. Please try again.');
        }
        // a JSON boolean, or the string "true" some older responses used
        $verified = ($info['email_verified'] ?? false);
        return new SocialProfile('google', $uid, (string) ($info['name'] ?? ''), (string) ($info['email'] ?? ''), $verified === true || $verified === 'true');
    }

    private function facebookProfile(string $code): SocialProfile
    {
        $version = $this->config['facebook']['graph_version'];
        $token = $this->json('GET', 'https://graph.facebook.com/' . $version . '/oauth/access_token', ['query' => [
            'client_id' => $this->config['facebook']['client_id'],
            'client_secret' => $this->config['facebook']['client_secret'],
            'redirect_uri' => $this->redirectUri('facebook'),
            'code' => $code,
        ]]);
        $access = (string) ($token['access_token'] ?? '');
        if ($access === '') {
            throw new SocialLoginException('The sign-in could not be completed. Please try again.');
        }
        $me = $this->json('GET', 'https://graph.facebook.com/' . $version . '/me', ['query' => ['fields' => 'id,name,email', 'access_token' => $access]]);
        $uid = (string) ($me['id'] ?? '');
        if ($uid === '') {
            throw new SocialLoginException('The sign-in could not be completed. Please try again.');
        }
        $email = (string) ($me['email'] ?? '');
        // Facebook returns only an address its owner has confirmed; that is the rule Dink Play relies on (documented there)
        return new SocialProfile('facebook', $uid, (string) ($me['name'] ?? ''), $email, $email !== '');
    }
}
