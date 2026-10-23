<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Billing;
use App\Support\ErrorReporting;
use App\Support\ReportingExceptionHandler;
use App\Support\SentryLogger;
use App\Services\CarouselStore;
use App\Services\LetterStore;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\XenditGateway;
use App\Services\PhotoAllowance;
use App\Services\PhotoCatalog;
use App\Services\PhotoStorage;
use App\Services\Plans;
use App\Services\Seasons;
use App\Services\Sites;
use App\Services\Subscriptions;
use App\Services\Social\SocialAuth;
use App\Services\SongStore;
use App\Services\SpacesFilesystem;
use App\Services\Subdomains;
use Naluz\Config\Repository;
use Naluz\Database\Connection;
use Naluz\Foundation\ExceptionHandler;
use Naluz\Foundation\ServiceProvider;
use Naluz\Session\Store;
use Naluz\View\Factory;
use Naluz\Log\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Naluz\Security\Hasher;
use Naluz\Storage\StorageManager;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // the framework's handler, plus a report of every server error to Sentry (when SENTRY_DSN is set)
        $this->app->bind(ExceptionHandler::class, fn ($c) => new ReportingExceptionHandler(
            $c->make(Repository::class),
            $c->make(LoggerInterface::class),
            $c->make(Factory::class),
            $c->make(Store::class),
        ));
        $this->app->singleton(Hasher::class, fn ($c) => new Hasher((array) $c->make(Repository::class)->get('security.hashing', [])));
        $this->app->singleton(Plans::class, fn ($c) => new Plans(
            (array) $c->make(Repository::class)->get('saas.plans', []),
            (string) $c->make(Repository::class)->get('saas.currency', 'PHP'),
            (array) $c->make(Repository::class)->get('saas.periods', Plans::DEFAULT_PERIODS),
            fn (): bool => (bool) $c->make(Repository::class)->get('saas.features.subdomains', false),
        ));
        $this->app->singleton(Seasons::class, fn ($c) => new Seasons(
            (array) $c->make(Repository::class)->get('saas.seasons.events', []),
            (int) $c->make(Repository::class)->get('saas.seasons.lead_days', 14),
            (bool) $c->make(Repository::class)->get('saas.seasons.always', false),
            (string) $c->make(Repository::class)->get('app.timezone', 'UTC'),
            null,
            $c->make(Repository::class)->get('app.env') !== 'production' || (bool) $c->make(Repository::class)->get('saas.theme_preview', false),
        ));
        $this->app->singleton(SocialAuth::class, fn ($c) => new SocialAuth(
            (array) $c->make(Repository::class)->get('social', []),
            (string) $c->make(Repository::class)->get('app.url', 'http://localhost'),
        ));
        $this->app->singleton(Sites::class, fn ($c) => new Sites(
            (string) $c->make(Repository::class)->get('saas.domain', ''),
            (string) $c->make(Repository::class)->get('app.url', 'http://localhost'),
        ));
        $this->app->singleton(Subdomains::class, fn ($c) => new Subdomains(
            (string) $c->make(Repository::class)->get('saas.domain', ''),
            array_map('strval', (array) $c->make(Repository::class)->get('saas.reserved_subdomains', [])),
        ));
        $this->app->bind(PhotoAllowance::class, function ($c) {
            $pack = (array) $c->make(Repository::class)->get('saas.photo_pack', []);
            return new PhotoAllowance(
                $c->make(Plans::class),
                (int) ($pack['photos'] ?? 12),
                (int) ($pack['price'] ?? 10000),
                (int) ($pack['max_packs'] ?? 10),
            );
        });
        $this->app->bind(Subscriptions::class, fn ($c) => new Subscriptions($c->make(Connection::class), $c->make(Plans::class), $c->make(Billing::class)));
        $this->app->bind(Billing::class, fn ($c) => new Billing($c->make(Plans::class), $c->make(Connection::class), $c->make(PhotoAllowance::class)));
        $this->app->bind(PhotoStorage::class, fn ($c) => new PhotoStorage(
            $c->make(StorageManager::class),
            $c->make(Connection::class),
            trim((string) $c->make(Repository::class)->get('filesystems.disks.spaces.folder', ''), '/'),
            (int) $c->make(Repository::class)->get('saas.max_upload_bytes', 10 * 1024 * 1024),
        ));
        $this->app->singleton(PayMongoGateway::class, fn ($c) => new PayMongoGateway(
            (string) $c->make(Repository::class)->get('saas.payments.paymongo.secret_key', ''),
            (string) $c->make(Repository::class)->get('saas.payments.paymongo.webhook_secret', ''),
            array_map('strval', (array) $c->make(Repository::class)->get('saas.payments.paymongo.methods', ['gcash', 'paymaya', 'card'])),
            (string) $c->make(Repository::class)->get('app.url', 'http://localhost'),
            null,
            (string) $c->make(Repository::class)->get('saas.payments.paymongo.public_key', ''),
            (bool) $c->make(Repository::class)->get('saas.payments.paymongo.subscriptions', false),
        ));
        $this->app->singleton(XenditGateway::class, fn ($c) => new XenditGateway(
            (string) $c->make(Repository::class)->get('saas.payments.xendit.secret_key', ''),
            (string) $c->make(Repository::class)->get('saas.payments.xendit.callback_token', ''),
            (string) $c->make(Repository::class)->get('app.url', 'http://localhost'),
        ));
        $this->app->bind(PaymentGateway::class, function ($c) {
            $config = $c->make(Repository::class);
            $hosted = static function (\App\Services\Payments\HostedGateway $gateway): \App\Services\Payments\HostedGateway {
                if (!$gateway->configured()) {
                    throw new \LogicException('PAYMENTS_DRIVER=' . $gateway->name() . ' needs its secret key in .env (see .env.example).');
                }
                return $gateway;
            };
            $driver = (string) $config->get('saas.payments.driver', 'auto');
            // `auto` (the default): PayMongo as soon as its secret key is set, manual payments until then
            if ($driver === 'auto') {
                $driver = $c->make(PayMongoGateway::class)->configured() ? 'paymongo' : 'none';
            }
            return match ($driver) {
                'fake' => new FakeGateway((string) $config->get('app.env', 'production')),
                'paymongo' => $hosted($c->make(PayMongoGateway::class)),
                'xendit' => $hosted($c->make(XenditGateway::class)),
                default => new ManualGateway(),
            };
        });

        // The original single-page site, read once by bin/import-legacy.php.
        $this->app->singleton(CarouselStore::class, fn () => new CarouselStore(
            storage_path('app/carousel.json'),
            base_path('database/carousel.seed.json'),
        ));
        $this->app->singleton(PhotoCatalog::class, fn ($c) => new PhotoCatalog($c->make(CarouselStore::class), base_path('public/photos')));
        $this->app->singleton(LetterStore::class, fn () => new LetterStore(
            storage_path('app/letter.json'),
            (array) config('site.letter'),
            (string) config('site.first_name'),
        ));
        $this->app->singleton(SongStore::class, fn () => new SongStore(
            storage_path('app/song.json'),
            (array) config('site.song'),
        ));
    }

    public function boot(): void
    {
        $config = $this->app->make(Repository::class);
        // every date is stored and read as UTC: another zone makes a payment's validity hours off (a new QR Ph payment was found expired at once)
        if (date_default_timezone_get() !== 'UTC') {
            logger()->warning('[config] the app runs in {zone}, not UTC: set APP_TIMEZONE=UTC or stored times are read hours off', ['zone' => date_default_timezone_get()]);
        }
        $sentry = (array) $config->get('sentry', []);
        $started = ErrorReporting::start($sentry);
        // the `sentry_logs` channel always exists: it sends to Sentry when that is on and does nothing otherwise
        $this->app->make(LogManager::class)->extend('sentry_logs', $started && !empty($sentry['enable_logs']) ? new SentryLogger() : new NullLogger());
        $spaces = (array) $config->get('filesystems.disks.spaces', []);

        // The Spaces disk exists only once the credentials are in .env; until then photos go to the local public disk.
        if (($spaces['key'] ?? '') !== '' && ($spaces['secret'] ?? '') !== '' && ($spaces['bucket'] ?? '') !== '') {
            $this->app->make(StorageManager::class)->extend('spaces', new SpacesFilesystem(
                (string) $spaces['endpoint'],
                (string) $spaces['region'],
                (string) $spaces['bucket'],
                (string) $spaces['key'],
                (string) $spaces['secret'],
                (string) ($spaces['cdn_url'] ?? ''),
            ));
        }
    }
}
