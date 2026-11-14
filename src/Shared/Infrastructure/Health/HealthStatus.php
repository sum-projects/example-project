<?php declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Fail = 'fail';
}
