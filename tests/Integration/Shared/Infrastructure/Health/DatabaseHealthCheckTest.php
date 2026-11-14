<?php declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Health;

use App\Shared\Infrastructure\Health\DatabaseHealthCheck;
use App\Shared\Infrastructure\Health\HealthStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Tools\DsnParser;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseHealthCheckTest extends KernelTestCase
{
    public function test_the_database_is_healthy_when_it_answers_a_query(): void
    {
        $check = new DatabaseHealthCheck($this->applicationConnection(), new NullLogger());

        self::assertSame(HealthStatus::Ok, $check->check());
    }

    public function test_the_database_is_unhealthy_when_the_connection_cannot_be_opened(): void
    {
        $check = new DatabaseHealthCheck($this->connectionToAClosedPort(), new NullLogger());

        self::assertSame(HealthStatus::Fail, $check->check());
    }

    public function test_the_database_is_unhealthy_when_it_refuses_the_credentials(): void
    {
        $url = $_SERVER['DATABASE_URL'] ?? null;
        self::assertIsString($url);
        $params = new DsnParser(['postgresql' => 'pdo_pgsql'])->parse($url);
        $rejected = DriverManager::getConnection([...$params, 'password' => 'not-the-password']);
        $check = new DatabaseHealthCheck($rejected, new NullLogger());

        self::assertSame(HealthStatus::Fail, $check->check());
    }

    public function test_a_failed_check_is_logged_with_its_cause(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'context' => $context];
            }
        };

        new DatabaseHealthCheck($this->connectionToAClosedPort(), $logger)->check();

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertInstanceOf(DbalException::class, $logger->records[0]['context']['exception'] ?? null);
    }

    private function applicationConnection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function connectionToAClosedPort(): Connection
    {
        // Port 1 on the loopback interface has no listener; serverVersion avoids a lookup on connect.
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => '127.0.0.1',
            'port' => 1,
            'user' => 'app',
            'password' => 'app',
            'dbname' => 'app_test',
            'serverVersion' => '17',
        ]);
    }
}
