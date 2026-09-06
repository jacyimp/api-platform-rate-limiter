<?php

declare(strict_types=1);

$projectDirectory = dirname(__DIR__);
$fixtureDirectory = sprintf(
    '%s/api-platform-rate-limiter-no-security-%s',
    sys_get_temp_dir(),
    bin2hex(random_bytes(6)),
);

if (!mkdir($fixtureDirectory, 0777, true) && !is_dir($fixtureDirectory)) {
    throw new RuntimeException(sprintf('Could not create "%s".', $fixtureDirectory));
}

$composer = [
    'name' => 'jacyimp/api-platform-rate-limiter-no-security-test',
    'description' => 'Isolated verification that the Symfony integration works without Security',
    'type' => 'project',
    'license' => 'MIT',
    'repositories' => [[
        'type' => 'path',
        'url' => $projectDirectory,
        'options' => ['symlink' => true],
    ]],
    'require' => [
        'php' => '^8.2',
        'jacyimp/api-platform-rate-limiter' => '@dev',
        'symfony/framework-bundle' => '^6.4 || ^7.0 || ^8.0',
    ],
    'conflict' => [
        'symfony/security-core' => '*',
    ],
    'minimum-stability' => 'dev',
    'prefer-stable' => true,
    'config' => [
        'allow-plugins' => false,
    ],
];
$composerJson = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($fixtureDirectory . '/composer.json', $composerJson . "\n");

try {
    run([
        ...composerCommand(),
        'update',
        '--working-dir=' . $fixtureDirectory,
        '--prefer-dist',
        '--no-interaction',
        '--no-progress',
    ]);
    run([
        PHP_BINARY,
        $projectDirectory . '/tests/NoSecurity/verify.php',
        $fixtureDirectory . '/vendor/autoload.php',
    ]);
} finally {
    removeDirectory($fixtureDirectory);
}

/** @return non-empty-list<string> */
function composerCommand(): array
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return ['composer'];
    }

    $path = getenv('PATH');
    foreach (explode(PATH_SEPARATOR, is_string($path) ? $path : '') as $directory) {
        $composerPhar = $directory . '/composer.phar';
        if (is_file($composerPhar)) {
            return [PHP_BINARY, $composerPhar];
        }
    }

    throw new RuntimeException('Could not locate composer.phar on PATH.');
}

/** @param list<string> $command */
function run(array $command): void
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException(sprintf('Could not run "%s".', implode(' ', $command)));
    }

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException(sprintf('Command failed with exit code %d.', $exitCode));
    }
}

function removeDirectory(string $directory): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if (PHP_OS_FAMILY === 'Windows' && @rmdir($item->getPathname())) {
            continue;
        }

        if ($item->isLink()) {
            unlink($item->getPathname());

            continue;
        }

        if (is_dir($item->getPathname())) {
            rmdir($item->getPathname());

            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($directory);
}
