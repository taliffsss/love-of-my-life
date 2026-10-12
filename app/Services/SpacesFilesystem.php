<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Naluz\Storage\Filesystem;
use Naluz\Storage\StorageException;

/**
 * DigitalOcean Spaces (S3-compatible) disk. Requests are signed with AWS Signature V4 using Guzzle, so no cloud SDK
 * is needed. Only the operations the app uses are implemented (put, get, exists, delete, size, mimeType, url).
 */
final class SpacesFilesystem implements Filesystem
{
    private const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'avif' => 'image/avif',
    ];

    private readonly string $host;
    private readonly string $scheme;
    private readonly ClientInterface $http;
    /** @var \Closure():int */
    private readonly \Closure $clock;

    public function __construct(
        string $endpoint,
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $cdnUrl = '',
        ?ClientInterface $http = null,
        ?\Closure $clock = null,
    ) {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new \InvalidArgumentException('SPACES_ENDPOINT_URL must be a URL such as https://nyc3.digitaloceanspaces.com');
        }
        $this->scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        // virtual-hosted style: https://<bucket>.<region>.digitaloceanspaces.com/<key>
        $this->host = str_starts_with($host, $bucket . '.') ? $host : $bucket . '.' . $host;
        $this->http = $http ?? new Client(['timeout' => 60, 'connect_timeout' => 10, 'http_errors' => false]);
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function put(string $path, mixed $contents): void
    {
        $body = is_resource($contents) ? (string) stream_get_contents($contents) : (string) $contents;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $response = $this->send('PUT', $path, $body, [
            'cache-control' => 'public, max-age=31536000, immutable',
            'content-type' => self::TYPES[$ext] ?? 'application/octet-stream',
            'x-amz-acl' => 'public-read',
        ]);
        if ($response['status'] !== 200) {
            throw new StorageException('Spaces rejected the upload (HTTP ' . $response['status'] . $this->errorCode($response['body']) . ').');
        }
    }

    public function get(string $path): string
    {
        $response = $this->send('GET', $path);
        if ($response['status'] !== 200) {
            throw new StorageException('Spaces could not read the file (HTTP ' . $response['status'] . ').');
        }
        return $response['body'];
    }

    public function exists(string $path): bool
    {
        return $this->send('HEAD', $path)['status'] === 200;
    }

    public function delete(string $path): bool
    {
        return in_array($this->send('DELETE', $path)['status'], [200, 204], true);
    }

    public function size(string $path): int
    {
        return (int) ($this->head($path)['content-length'][0] ?? 0);
    }

    public function lastModified(string $path): int
    {
        return (int) strtotime($this->head($path)['last-modified'][0] ?? '');
    }

    public function mimeType(string $path): string
    {
        return $this->head($path)['content-type'][0] ?? 'application/octet-stream';
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        return $this->cdnUrl !== ''
            ? rtrim($this->cdnUrl, '/') . '/' . $path
            : $this->scheme . '://' . $this->host . '/' . $path;
    }

    public function copy(string $from, string $to): void
    {
        throw new StorageException('copy() is not supported by the Spaces disk.');
    }

    public function move(string $from, string $to): void
    {
        throw new StorageException('move() is not supported by the Spaces disk.');
    }

    public function files(string $directory = ''): array
    {
        throw new StorageException('files() is not supported by the Spaces disk.');
    }

    public function directories(string $directory = ''): array
    {
        throw new StorageException('directories() is not supported by the Spaces disk.');
    }

    public function makeDirectory(string $path): void
    {
        // object stores have no directories
    }

    public function deleteDirectory(string $path): bool
    {
        throw new StorageException('deleteDirectory() is not supported by the Spaces disk.');
    }

    /** @return array<string,list<string>> */
    private function head(string $path): array
    {
        $response = $this->send('HEAD', $path);
        if ($response['status'] !== 200) {
            throw new StorageException('File not found on Spaces.');
        }
        return $response['headers'];
    }

    /**
     * @param array<string,string> $extra lower-case header => value, all of them are signed
     * @return array{status:int,body:string,headers:array<string,list<string>>}
     */
    private function send(string $method, string $path, string $body = '', array $extra = []): array
    {
        $path = $this->guard($path);
        $now = ($this->clock)();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $day = gmdate('Ymd', $now);
        $payloadHash = hash('sha256', $body);

        $headers = $extra + [
            'host' => $this->host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));
        $uri = '/' . implode('/', array_map('rawurlencode', explode('/', $path)));

        $canonicalRequest = implode("\n", [$method, $uri, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
        $scope = "{$day}/{$this->region}/s3/aws4_request";
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

        $key = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $this->region, hash_hmac('sha256', $day, 'AWS4' . $this->secretKey, true), true), true), true);
        $signature = hash_hmac('sha256', $stringToSign, $key);

        $headers['authorization'] = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        unset($headers['host']);

        try {
            $response = $this->http->request($method, $this->scheme . '://' . $this->host . $uri, [
                'headers' => $headers,
                'body' => $body,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new StorageException('Could not reach Spaces.', 0, $e);
        }

        return [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
            'headers' => array_change_key_case($response->getHeaders(), CASE_LOWER),
        ];
    }

    /** Spaces answers errors with XML such as <Code>SignatureDoesNotMatch</Code>; surface the code to help diagnose. */
    private function errorCode(string $body): string
    {
        return preg_match('#<Code>([A-Za-z0-9]+)</Code>#', $body, $m) === 1 ? ', ' . $m[1] : '';
    }

    private function guard(string $path): string
    {
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new StorageException('Invalid storage path.');
        }
        return $path;
    }
}
