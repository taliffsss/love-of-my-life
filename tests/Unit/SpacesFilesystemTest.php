<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\SpacesFilesystem;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Naluz\Storage\StorageException;
use PHPUnit\Framework\TestCase;

final class SpacesFilesystemTest extends TestCase
{
    /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    /** @param list<Response> $responses */
    private function disk(array $responses, string $endpoint = 'https://nyc3.digitaloceanspaces.com', string $cdn = '', string $bucket = 'bkt', string $region = 'nyc3', string $access = 'KEY', string $secret = 'SECRET', int $now = 1_767_225_600): SpacesFilesystem
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new SpacesFilesystem($endpoint, $region, $bucket, $access, $secret, $cdn, new Client(['handler' => $stack, 'http_errors' => false]), static fn (): int => $now);
    }

    public function testSignatureMatchesTheAwsReferenceVector(): void
    {
        // "GET Object" example from the AWS Signature Version 4 documentation (Range header included).
        $disk = $this->disk([new Response(200, [], 'x')], 'https://s3.amazonaws.com', '', 'examplebucket', 'us-east-1', 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 1_369_353_600);
        $send = new \ReflectionMethod($disk, 'send');
        $send->invoke($disk, 'GET', 'test.txt', '', ['range' => 'bytes=0-9']);

        $auth = $this->history[0]['request']->getHeaderLine('Authorization');
        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, '
            . 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, '
            . 'Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41',
            $auth,
        );
    }

    public function testPutSendsAPublicReadImageToTheVirtualHostedBucket(): void
    {
        $disk = $this->disk([new Response(200)]);
        $disk->put('yam/a b.jpg', 'JPEGDATA');

        $request = $this->history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('https://bkt.nyc3.digitaloceanspaces.com/yam/a%20b.jpg', (string) $request->getUri());
        $this->assertSame('public-read', $request->getHeaderLine('x-amz-acl'));
        $this->assertSame('image/jpeg', $request->getHeaderLine('Content-Type'));
        $this->assertSame(hash('sha256', 'JPEGDATA'), $request->getHeaderLine('x-amz-content-sha256'));
        $this->assertStringContainsString('SignedHeaders=cache-control;content-type;host;x-amz-acl;x-amz-content-sha256;x-amz-date', $request->getHeaderLine('Authorization'));
        $this->assertSame('JPEGDATA', (string) $request->getBody());
    }

    public function testFailedUploadThrows(): void
    {
        $this->expectException(StorageException::class);
        $this->disk([new Response(403)])->put('yam/a.jpg', 'x');
    }

    public function testUrlUsesTheCdnWhenConfigured(): void
    {
        $this->assertSame('https://cdn.example.com/yam/a.jpg', $this->disk([], cdn: 'https://cdn.example.com/')->url('yam/a.jpg'));
        $this->assertSame('https://bkt.nyc3.digitaloceanspaces.com/yam/a.jpg', $this->disk([])->url('/yam/a.jpg'));
    }

    public function testEndpointAlreadyContainingTheBucketIsNotDoubled(): void
    {
        $this->assertSame('https://bkt.nyc3.digitaloceanspaces.com/a.jpg', $this->disk([], 'https://bkt.nyc3.digitaloceanspaces.com')->url('a.jpg'));
    }

    public function testTraversalPathsAreRejected(): void
    {
        $this->expectException(StorageException::class);
        $this->disk([new Response(200)])->put('../etc/passwd', 'x');
    }
}
