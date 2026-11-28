<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** The suite drops every table it finds, so it must only ever reach a throw-away database on this machine. */
final class TestDatabaseGuardTest extends TestCase
{
    private const VARIABLES = ['TEST_DB_HOST', 'TEST_DB_DATABASE'];

    /** @var array<string,string|false> */
    private array $before = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->before[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->before as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testTheDefaultIsALocalThrowAwayDatabaseWithNoReadOrWriteHost(): void
    {
        $connection = self::postgresOverrides()['database.connections.pgsql'];

        self::assertSame('127.0.0.1', $connection['host']);
        self::assertSame('lovable_test', $connection['database']);
        self::assertArrayNotHasKey('read', $connection);
        self::assertArrayNotHasKey('write', $connection);
    }

    /** @return array<string,array{string,string}> */
    public static function unsafeTargets(): array
    {
        return [
            'a remote host' => ['TEST_DB_HOST', 'db.example.com'],
            'a list of hosts' => ['TEST_DB_HOST', '127.0.0.1,db.example.com'],
            'a database that is not a test one' => ['TEST_DB_DATABASE', 'lom_local'],
            'a host smuggled in with the name' => ['TEST_DB_DATABASE', 'x;host=db.example.com;dbname=x_test'],
        ];
    }

    #[DataProvider('unsafeTargets')]
    public function testAnythingElseIsRefused(string $variable, string $value): void
    {
        putenv("{$variable}={$value}");

        $this->expectException(\RuntimeException::class);
        self::postgresOverrides();
    }
}
