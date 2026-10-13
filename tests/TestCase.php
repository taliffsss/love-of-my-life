<?php

declare(strict_types=1);

namespace Naluz\Tests;

use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use Naluz\Database\Connection;
use Naluz\Database\DatabaseManager;
use Naluz\Database\Migrations\Migrator;
use Naluz\Database\Orm\Model;
use Naluz\Foundation\Application;
use Naluz\Http\Request;
use Naluz\Storage\StorageManager;
use Naluz\Tests\Support\MemoryDisk;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    /** @var array<string,string> cookie jar shared by requests in one test */
    protected array $cookies = [];
    /** Spaces stand-in: every test gets an empty one, so nothing ever reaches a real bucket */
    protected MemoryDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = [];
        Model::flushEventListeners();
        $this->app = new Application(dirname(__DIR__), $this->configOverrides());
        $this->app->boot();
        // cheap argon2 parameters keep the suite fast; production uses config/security.php
        $this->app->instance(\Naluz\Security\Hasher::class, new \Naluz\Security\Hasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]));
        if ($this->migrate()) {
            if (self::onPostgres()) {
                $this->db()->schema()->dropAllTables(); // every test starts from an empty *_test database
            }
            (new Migrator($this->db(), dirname(__DIR__) . '/database/migrations'))->run();
        }
        $this->disk = new MemoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $this->disk);
    }

    /**
     * The suite runs on in-memory SQLite. `TEST_DB_CONNECTION=pgsql vendor/bin/phpunit` runs it on PostgreSQL instead,
     * against TEST_DB_DATABASE (default lovable_test) on TEST_DB_HOST/PORT/USERNAME/PASSWORD. Every test drops all its
     * tables, so the database name must end in _test: the suite refuses to touch anything else (e.g. the dev database).
     */
    protected static function onPostgres(): bool
    {
        return getenv('TEST_DB_CONNECTION') === 'pgsql';
    }

    /** @return array<string,mixed> */
    private function postgresOverrides(): array
    {
        $database = (string) (getenv('TEST_DB_DATABASE') ?: 'lovable_test');
        if (!str_ends_with($database, '_test')) {
            throw new \RuntimeException("Refusing to run the tests on [{$database}]: every test drops its tables. Use a database whose name ends in _test.");
        }
        return [
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
            'database.connections.pgsql.port' => (string) (getenv('TEST_DB_PORT') ?: '5432'),
            'database.connections.pgsql.database' => $database,
            'database.connections.pgsql.username' => (string) (getenv('TEST_DB_USERNAME') ?: 'postgres'),
            'database.connections.pgsql.password' => (string) (getenv('TEST_DB_PASSWORD') ?: ''),
        ];
    }

    /** @return array<string,mixed> */
    protected function configOverrides(): array
    {
        return (self::onPostgres() ? $this->postgresOverrides() : []) + [
            'app.debug' => false,
            'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'app.cache' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'logging.default' => 'null', // tests never write to storage/logs; assert via LogManager::extend()
            'security.jwt.secret' => str_repeat('s', 40),
            'security.jwt.issuer' => 'tests',
            'app.url' => 'https://love.test',
            'saas.domain' => 'love.test',
            'saas.payments.driver' => 'none',
            // a developer's .env may hold real Spaces credentials and a folder: tests never use them
            'filesystems.disks.spaces.key' => '',
            'filesystems.disks.spaces.secret' => '',
            'filesystems.disks.spaces.bucket' => '',
            'filesystems.disks.spaces.folder' => '',
        ];
    }

    protected function migrate(): bool
    {
        return true;
    }

    protected function db(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection();
    }

    protected function send(ServerRequestInterface $request): ResponseInterface
    {
        $jar = [];
        foreach ($this->cookies as $k => $v) {
            $jar[$k] = $v;
        }
        $response = $this->app->handle($request->withCookieParams($jar + $request->getCookieParams()));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            [$pair] = explode(';', $cookie, 2);
            [$name, $value] = explode('=', $pair, 2);
            $this->cookies[$name] = $value;
        }
        return $response;
    }

    /** Register extra routes for a test (inside the `web` or `api` middleware group). */
    protected function routes(\Closure $define, array $group = ['middleware' => ['web']]): void
    {
        $this->app->make(\Naluz\Routing\Router::class)->group($group, $define);
    }

    protected function get(string $uri, array $headers = []): ResponseInterface
    {
        return $this->send(Request::create('GET', $uri, [], $headers));
    }

    /** Send a JSON body. */
    protected function json(string $method, string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        $factory = new Psr17Factory();
        $request = (new ServerRequest($method, $uri, $headers + ['Content-Type' => 'application/json', 'Accept' => 'application/json']))
            ->withBody($factory->createStream(json_encode($data)));
        return $this->send($request);
    }

    protected function form(string $method, string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->send(Request::create($method, $uri, $data, $headers));
    }

    protected function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Collects every SQL statement executed while the callback runs. @return list<string> */
    protected function queries(\Closure $callback): array
    {
        $log = [];
        $this->db()->listen(function (string $sql) use (&$log): void {
            $log[] = $sql;
        });
        $callback();
        return $log;
    }

    // ------------------------------------------------------------------ factories and helpers

    protected function makeUser(string $email = 'owner@example.com', string $password = 'correct horse'): User
    {
        $user = new User();
        $user->forceFill(['name' => 'Owner', 'email' => $email, 'password' => $password, 'is_admin' => false])->save();
        return $user;
    }

    /** @param array<string,mixed> $attributes */
    protected function makePage(User $owner, array $attributes = []): Page
    {
        $page = new Page();
        $page->forceFill($attributes + [
            'user_id' => $owner->id,
            'slug' => Page::newSlug(),
            'recipient' => 'Jinelin',
            'letter' => ['First paragraph.', 'Second paragraph.'],
            'gate_enabled' => false,
            'gate_eyebrow' => Page::DEFAULT_EYEBROW,
            'gate_question' => Page::DEFAULT_QUESTION,
            'layout' => 'classic',
            'theme' => 'rose',
            'plan' => 'free',
            'searchable' => false,
        ])->save();
        return $page;
    }

    /** A paid page: Forever by default, or a time-limited plan expiring at $expires. */
    protected function makeLivePage(User $owner, array $attributes = []): Page
    {
        return $this->makePage($owner, $attributes + ['plan' => 'forever', 'published_at' => '2026-01-01 00:00:00']);
    }

    protected function makePhoto(Page $page, string $url, int $position = 1): Photo
    {
        $photo = new Photo();
        $photo->forceFill(['page_id' => $page->id, 'disk' => 'spaces', 'path' => 'pages/' . $page->slug . '/' . basename($url), 'url' => $url, 'position' => $position])->save();
        $this->disk->files['pages/' . $page->slug . '/' . basename($url)] = 'jpeg';
        return $photo;
    }

    /** The CSRF token of the current session, read from a page's <meta name="csrf-token">. */
    protected function csrf(string $from = '/'): string
    {
        $html = (string) $this->get($from)->getBody();
        $this->assertSame(1, preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m), "no CSRF token on {$from}");
        return $m[1];
    }

    protected function signIn(User $user, string $password = 'correct horse'): void
    {
        $response = $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => $user->email, 'password' => $password]);
        $this->assertSame('/dashboard', $response->getHeaderLine('Location'), 'signed in');
    }

    /** A form post carrying the session's CSRF token (method spoofing with _method for PUT/DELETE). */
    protected function submit(string $method, string $uri, array $data = []): ResponseInterface
    {
        $token = $this->csrf('/');
        $verb = strtoupper($method);
        if (in_array($verb, ['PUT', 'PATCH', 'DELETE'], true)) {
            $data['_method'] = $verb;
            $verb = 'POST';
        }
        return $this->form($verb, $uri, $data + ['_token' => $token]);
    }

    /** JSON request carrying the session's CSRF token. */
    protected function ajax(string $method, string $uri, array $data = []): ResponseInterface
    {
        return $this->json($method, $uri, $data, ['X-CSRF-TOKEN' => $this->csrf('/')]);
    }

    protected function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
    }
}
