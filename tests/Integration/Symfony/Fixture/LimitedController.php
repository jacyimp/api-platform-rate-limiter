<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use Symfony\Component\HttpFoundation\Response;

final class LimitedController
{
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}
