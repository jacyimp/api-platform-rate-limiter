<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use JacyImp\ApiPlatformRateLimiter\Tests\Behaviour\FeatureContext;

return (new Config())
    ->withProfile(
        profile: (new Profile('default'))
            ->withSuite(
                suite: (new Suite('package'))
                    ->withPaths('%paths.base%/features')
                    ->withContexts(FeatureContext::class),
            ),
    );
