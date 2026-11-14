<?php declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Health\DatabaseHealthCheck;
use App\Shared\Infrastructure\Health\HealthStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(private DatabaseHealthCheck $databaseHealthCheck)
    {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseHealthCheck->check();

        return new JsonResponse(
            [
                'status' => $database->value,
                'checks' => ['database' => $database->value],
            ],
            HealthStatus::Ok === $database ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
            ['Cache-Control' => 'no-store'],
        );
    }
}
