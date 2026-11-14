<?php declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;

final readonly class DatabaseHealthCheck
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function check(): HealthStatus
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (DbalException $exception) {
            // The response never says why, so the log is where an operator finds the cause.
            $this->logger->warning('The database health check failed.', ['exception' => $exception]);

            return HealthStatus::Fail;
        }

        return HealthStatus::Ok;
    }
}
