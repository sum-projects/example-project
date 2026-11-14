<?php declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    private string|false $originalGetenv = false;

    private ?string $originalEnv = null;

    private ?string $originalServer = null;

    protected function setUp(): void
    {
        $this->originalGetenv = getenv('DATABASE_URL');
        $env = $_ENV['DATABASE_URL'] ?? null;
        $this->originalEnv = \is_string($env) ? $env : null;
        $server = $_SERVER['DATABASE_URL'] ?? null;
        $this->originalServer = \is_string($server) ? $server : null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restore('DATABASE_URL', $this->originalGetenv, $this->originalEnv, $this->originalServer);
    }

    public function test_the_health_endpoint_reports_ok_when_the_database_answers(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        $response = $client->getResponse();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('{"status":"ok","checks":{"database":"ok"}}', $response->getContent());
    }

    public function test_the_health_endpoint_answers_503_when_the_database_cannot_be_reached(): void
    {
        $this->pointDatabaseAtClosedPort();
        $client = self::createClient();

        $client->request('GET', '/health');

        $response = $client->getResponse();
        self::assertResponseStatusCodeSame(503);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('{"status":"fail","checks":{"database":"fail"}}', $response->getContent());
    }

    private function pointDatabaseAtClosedPort(): void
    {
        $url = \is_string($this->originalGetenv) ? $this->originalGetenv : ($this->originalEnv ?? $this->originalServer);
        self::assertIsString($url, 'DATABASE_URL must be defined for the test environment.');

        $parts = parse_url($url);
        self::assertIsArray($parts);
        // Port 1 on the loopback interface of the PHP container has no listener.
        $parts['host'] = '127.0.0.1';
        $parts['port'] = 1;

        $unreachable = sprintf(
            '%s://%s:%s@%s:%d%s%s',
            $parts['scheme'] ?? 'postgresql',
            $parts['user'] ?? '',
            $parts['pass'] ?? '',
            $parts['host'],
            $parts['port'],
            $parts['path'] ?? '',
            isset($parts['query']) ? '?'.$parts['query'] : '',
        );

        putenv('DATABASE_URL='.$unreachable);
        $_ENV['DATABASE_URL'] = $unreachable;
        $_SERVER['DATABASE_URL'] = $unreachable;
    }

    private function restore(string $name, string|false $getenv, ?string $env, ?string $server): void
    {
        false === $getenv ? putenv($name) : putenv($name.'='.$getenv);

        if (null === $env) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $env;
        }

        if (null === $server) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $server;
        }
    }
}
