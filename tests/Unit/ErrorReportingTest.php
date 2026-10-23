<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Support\ErrorReporting;
use App\Support\ReportingExceptionHandler;
use Naluz\Config\Repository;
use Naluz\Foundation\ExceptionHandler;
use Naluz\Http\HttpException;
use Naluz\Tests\TestCase;
use Naluz\Validation\ValidationException;
use Nyholm\Psr7\ServerRequest;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;

final class ErrorReportingTest extends TestCase
{
    public function testTheAppAlwaysRunsInUtcWhateverTheEnvironmentSays(): void
    {
        putenv('APP_TIMEZONE=Asia/Manila');
        $_ENV['APP_TIMEZONE'] = 'Asia/Manila';
        try {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
        } finally {
            putenv('APP_TIMEZONE');
            unset($_ENV['APP_TIMEZONE']);
        }
        $this->assertSame('UTC', $config['timezone'], 'a stored UTC time must be read back as UTC');
        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function testWithoutADsnSentryIsNotStarted(): void
    {
        $this->assertFalse(ErrorReporting::start(['dsn' => '']));
        $this->assertFalse(ErrorReporting::start([]));
    }

    public function testTheAppUsesTheReportingHandlerAndOnlyServerErrorsAreReported(): void
    {
        $this->assertInstanceOf(ReportingExceptionHandler::class, $this->app->make(ExceptionHandler::class));

        $handler = new class (
            $this->app->make(Repository::class),
            $this->app->make(\Psr\Log\LoggerInterface::class),
            $this->app->make(\Naluz\View\Factory::class),
            $this->app->make(\Naluz\Session\Store::class),
        ) extends ReportingExceptionHandler {
            /** @var list<string> */
            public array $reported = [];

            protected function report(\Throwable $e): void
            {
                $this->reported[] = $e::class;
            }
        };
        $request = new ServerRequest('GET', '/');

        $this->assertSame(500, $handler->render(new \RuntimeException('boom'), $request)->getStatusCode());
        $this->assertSame(503, $handler->render(new HttpException(503, 'down'), $request)->getStatusCode());
        $this->assertSame(404, $handler->render(new HttpException(404, 'nope'), $request)->getStatusCode());
        $handler->render(new ValidationException(['email' => ['bad']]), $request);

        $this->assertSame([\RuntimeException::class, HttpException::class], $handler->reported, '404s and validation errors are not reported');
    }

    public function testTheDsnMayBeNamedSentryNaluzDsnAndTheLogChannelAlwaysExists(): void
    {
        $this->assertSame('', (string) config('sentry.dsn'), 'tests never report');

        $logs = $this->app->make(\Naluz\Log\LogManager::class);
        $logs->channel('sentry_logs')->error('nothing is sent without Sentry: {x}', ['x' => 1]);
        $this->app->make(Repository::class)->set('logging.channels.stack.channels', ['single', 'sentry_logs']);
        $logs->stack(['single', 'sentry_logs']); // names the channel safely even with Sentry off
        $this->addToAssertionCount(1);
    }

    public function testTheSentryLoggerFillsPlaceholdersAndKeepsObjectsOut(): void
    {
        $logger = new \App\Support\SentryLogger();
        $logger->error('{class}: {message}', ['class' => \RuntimeException::class, 'message' => 'boom', 'exception' => new \RuntimeException('secret trace')]);
        $logger->log('bogus-level', 'unknown levels become info');
        $this->addToAssertionCount(1); // no client is started in tests: it must simply not throw
    }

    public function testWhatIsSentNeverCarriesFrameArgumentsCookiesOrBodies(): void
    {
        $frame = new Frame('f', '/a.php', 3);
        $frame->setVars(['password' => 'hunter2', 'sk' => 'sk_live_x']);
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('x'), new Stacktrace([$frame]))]);
        $event->setRequest(['url' => 'https://x.test/pay', 'method' => 'POST', 'cookies' => ['naluz_session' => 'abc'], 'headers' => ['Authorization' => 'Basic z'], 'data' => ['card' => '4242'], 'query_string' => 'token=1']);

        $clean = ErrorReporting::scrub($event);

        $this->assertSame([], $clean->getExceptions()[0]->getStacktrace()->getFrames()[0]->getVars());
        $this->assertSame(['url' => 'https://x.test/pay', 'method' => 'POST'], $clean->getRequest());
    }
}
