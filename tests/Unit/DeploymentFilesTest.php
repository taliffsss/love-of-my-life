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

    public function testTheStackRunsNoHealthChecks(): void
    {
        $stack = $this->file('deploy/docker-stack.yml');
        $this->assertMatchesRegularExpression('/^\s+healthcheck:\s*\n\s+disable:\s*true\s*$/m', $stack, 'Swarm health check disabled');
        $this->assertStringNotContainsString('loadbalancer.healthcheck', $stack, 'no Traefik health check labels');
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

    public function testNginxPassesTheVisitorsAddressBehindTraefik(): void
    {
        $nginx = $this->file('docker/nginx/default.conf');
        $this->assertStringContainsString('real_ip_header X-Forwarded-For;', $nginx);
        $this->assertStringContainsString('real_ip_recursive on;', $nginx);
        $this->assertStringContainsString('set_real_ip_from 10.0.0.0/8;', $nginx, 'the Swarm overlay network');
    }

    /** The product's domain and every <name>.<domain> page address reach the app, on the wildcard certificate. */
    public function testTheStackRoutesTheProductAndEveryPageAddress(): void
    {
        $stack = $this->file('deploy/docker-stack.yml');
        $this->assertSame(1, preg_match('/^SAAS_DOMAIN=(\S+)$/m', $this->file('deploy/.env.production.example'), $m));
        $domain = $m[1];
        $regex = '^[a-z0-9-]+\\\\.' . str_replace('.', '\\\\.', $domain) . '$$';
        foreach (['lom-saas', 'lom-saas-http'] as $router) {
            $this->assertStringContainsString(
                "traefik.http.routers.{$router}.rule=Host(`{$domain}`) || HostRegexp(`{$regex}`)",
                $stack,
                "{$router} matches {$domain} and <name>.{$domain}",
            );
        }
        $this->assertStringContainsString('traefik.http.routers.lom-saas.tls.certresolver=letsencrypt-dns', $stack, 'only the DNS challenge issues wildcards');
        $this->assertStringContainsString("traefik.http.routers.lom-saas.tls.domains[0].main={$domain}", $stack);
        $this->assertStringContainsString("traefik.http.routers.lom-saas.tls.domains[0].sans=*.{$domain}", $stack);
        $this->assertStringContainsString('traefik.http.routers.lom-saas-http.middlewares=redirect-to-https@file', $stack);
    }

    /** docker stack deploy interpolates `$`: a lone one ("…com$`") is refused with "invalid interpolation format". */
    public function testEveryDollarInTheStackIsAVariableOrEscaped(): void
    {
        $stack = (string) preg_replace('/^\s*#.*$/m', '', $this->file('deploy/docker-stack.yml'));
        $this->assertDoesNotMatchRegularExpression('/(?<!\$)\$(?![${])/', str_replace('$$', '', $stack));
    }

    /** nginx answers files with these extensions itself (try_files … =404), so a PHP route must never end with one. */
    public function testNoRouteEndsWithAnExtensionNginxServesItself(): void
    {
        $nginx = $this->file('docker/nginx/default.conf');
        $this->assertSame(1, preg_match('/location ~\* \\\.\(([^)]+)\)\$/', $nginx, $m), 'static location found');
        $static = '/\.(' . $m[1] . ')$/i';
        preg_match_all("/->(?:get|post|put|delete|patch)\('([^']+)'/", $this->file('routes/web.php'), $routes);
        $this->assertNotEmpty($routes[1]);
        foreach ($routes[1] as $uri) {
            $this->assertDoesNotMatchRegularExpression($static, $uri, "{$uri} would be answered by nginx, not the app");
        }
    }
}
