<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use JacyImp\ApiPlatformRateLimiter\Tests\NoSecurity\NoSecurityKernel;
use Symfony\Component\HttpFoundation\Request;

$autoload = $argv[1] ?? null;
if (!is_string($autoload) || !is_file($autoload)) {
    throw new RuntimeException('Pass the isolated Composer autoload path.');
}

require $autoload;
require __DIR__ . '/NoSecurityController.php';
require __DIR__ . '/NoSecurityKernel.php';

if (InstalledVersions::isInstalled('symfony/security-core')) {
    throw new RuntimeException('symfony/security-core must not be installed in this fixture.');
}

$kernel = new NoSecurityKernel('test', false);
$response = $kernel->handle(Request::create(
    '/identity',
    server: ['REMOTE_ADDR' => '203.0.113.10'],
));
$kernel->terminate(Request::create('/identity'), $response);

if ($response->getStatusCode() !== 200 || $response->getContent() !== 'ip:203.0.113.10') {
    throw new RuntimeException(sprintf(
        'Expected an IP identity response, got HTTP %d with %s.',
        $response->getStatusCode(),
        var_export($response->getContent(), true),
    ));
}

fwrite(STDOUT, "No-Security Symfony container and IP identity verification passed.\n");
