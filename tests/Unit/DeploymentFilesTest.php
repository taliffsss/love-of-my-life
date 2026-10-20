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
        $this->assertStringContainsString('image: ${LOM_IMAGE:-taliffsss/loveofmylife}:${LOM_VERSION:-latest}', $this->file('deploy/docker-stack.yml'));
        $release = $this->file('.github/workflows/release.yml');
        $this->assertStringContainsString("secrets.DOCKER_IMAGE || vars.DOCKER_IMAGE || 'taliffsss/loveofmylife'", $release, 'the image name may be a secret or a variable');
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

    /** The product's domain reaches the app on a normal certificate; own addresses are off, so their wildcard routers are only comments. */
    public function testTheStackRoutesTheProductAndKeepsOwnAddressesOff(): void
    {
        $stack = $this->file('deploy/docker-stack.yml');
        $this->assertSame(1, preg_match('/^SAAS_DOMAIN=(\S+)$/m', $this->file('deploy/.env.production.example'), $m));
        $domain = $m[1];
        $this->assertSame('loveofmylife.love', $domain);
        $this->assertStringContainsString("traefik.http.routers.loveofmylife.rule=Host(`{$domain}`)", $stack);
        $this->assertStringContainsString("traefik.http.routers.loveofmylife-http.rule=Host(`{$domain}`)", $stack);
        $this->assertStringContainsString('traefik.http.routers.loveofmylife.tls.certresolver=letsencrypt"', $stack);
        $this->assertStringContainsString('traefik.http.routers.loveofmylife-http.middlewares=redirect-to-https@file', $stack);

        $live = (string) preg_replace('/^\s*#.*$/m', '', $stack);
        $this->assertStringNotContainsString('HostRegexp', $live, 'no wildcard router while own addresses are off');
        $this->assertStringNotContainsString('letsencrypt-dns', $live);
        $this->assertStringContainsString('# - "traefik.http.routers.loveofmylife-pages.tls.domains[0].sans=*.' . $domain . '"', $stack, 'the wildcard labels are ready to uncomment');
        $this->assertMatchesRegularExpression('/^SUBDOMAINS_ENABLED=false$/m', $this->file('deploy/.env.production.example'));
    }

    public function testNothingNamesTheOldSiteAnyMore(): void
    {
        foreach (['deploy/docker-stack.yml', '.github/workflows/release.yml', 'deploy/.env.production.example', 'docker-compose.yml', 'DEPLOY.md', 'README.md'] as $file) {
            $text = $this->file($file);
            $this->assertStringNotContainsString('jinelin', $text, $file);
            $this->assertStringNotContainsString('markanthonynaluz', $text, $file);
        }
    }

    /** A release of branch main-v2 uses its own image variable and deploy path. */
    public function testReleasesOfMainV2UseTheirOwnImageAndDeployPath(): void
    {
        $release = $this->file('.github/workflows/release.yml');
        $this->assertStringContainsString('vars.SAAS_DOCKER_IMAGE', $release);
        $this->assertStringContainsString('secrets.SAAS_DEPLOY_PATH || vars.SAAS_DEPLOY_PATH', $release);
        $this->assertStringContainsString('"main-v2"', $release);
        $this->assertStringContainsString("secrets.SAAS_DOCKERHUB_TOKEN || secrets.DOCKERHUB_TOKEN", $release, 'main-v2 pushes with its own Docker Hub token');
        $this->assertStringContainsString('v2: ${{ steps.c.outputs.v2 }}', $release);
        $this->assertStringContainsString("secrets.DOCKER_IMAGE || vars.DOCKER_IMAGE || 'taliffsss/loveofmylife'", $release, 'the image name may be a secret or a variable');
        $this->assertSame(3, substr_count($release, 'Image name needs the Docker Hub namespace'), 'every job that uses the image adds the account name when it is missing');
        $this->assertStringContainsString('SAAS_DEPLOY_PATH, which is not set', $release, 'main-v2 never falls back to the old deploy path');
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
