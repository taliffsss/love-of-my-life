<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the lessons of the 2.1.0 deploy: a Docker health check stopped a working container on the production Swarm. */
final class DeploymentFilesTest extends TestCase
{
    private function file(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testTheImageHasNoDockerHealthcheck(): void
    {
        $dockerfile = $this->file('Dockerfile');
        $this->assertDoesNotMatchRegularExpression('/^\s*HEALTHCHECK\b/mi', $dockerfile);
    }

    public function testTheStackKeepsSwarmHealthchecksOff(): void
    {
        $stack = $this->file('deploy/docker-stack.yml');
        $this->assertMatchesRegularExpression('/^\s+healthcheck:\s*\n\s+disable:\s*true\s*$/m', $stack);
        $this->assertStringContainsString('traefik.http.services.jinelin-basela.loadbalancer.healthcheck.path=/api/ping', $stack);
    }

    public function testTheStackDeploysTheImageTheReleasePushes(): void
    {
        $this->assertStringContainsString('image: ${LOM_IMAGE:-taliffsss/jinelin-basela}:${LOM_VERSION:-latest}', $this->file('deploy/docker-stack.yml'));
        $release = $this->file('.github/workflows/release.yml');
        $this->assertStringContainsString("vars.DOCKER_IMAGE || 'taliffsss/jinelin-basela'", $release);
        $this->assertStringContainsString("LOM_IMAGE='\${IMAGE}'", $release);
    }

    public function testEveryDeployChecksTheLiveSite(): void
    {
        $release = $this->file('.github/workflows/release.yml');
        $this->assertStringContainsString('Wait until the new version is running', $release);
        $this->assertStringContainsString('Check the live site answers', $release);
        $this->assertStringContainsString('/api/ping', $release);
    }

    public function testNginxLogsToTheContainerLog(): void
    {
        $nginx = $this->file('docker/nginx/default.conf');
        $this->assertStringContainsString('access_log /dev/stdout;', $nginx);
        $this->assertStringContainsString('error_log /dev/stderr warn;', $nginx);
    }
}
