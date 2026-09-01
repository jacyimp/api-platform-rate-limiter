<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php tools/check-coverage.php <clover.xml>\n");

    exit(2);
}

$report = $argv[1];
if (!is_file($report)) {
    fwrite(STDERR, sprintf("Coverage report \"%s\" does not exist.\n", $report));

    exit(2);
}

$coverage = simplexml_load_file($report);
if ($coverage === false || !isset($coverage->project->metrics)) {
    fwrite(STDERR, sprintf("Coverage report \"%s\" is not valid Clover XML.\n", $report));

    exit(2);
}

$metrics = $coverage->project->metrics;
$lines = (int) $metrics['statements'];
$coveredLines = (int) $metrics['coveredstatements'];

if ($lines === 0) {
    fwrite(STDERR, "Coverage report does not contain executable source lines.\n");

    exit(2);
}

$percentage = ($coveredLines / $lines) * 100;
fwrite(STDOUT, sprintf(
    "Source line coverage: %.2f%% (%d/%d)\n",
    $percentage,
    $coveredLines,
    $lines,
));

if ($coveredLines !== $lines) {
    fwrite(STDERR, "Required source line coverage: 100.00%\n");

    exit(1);
}
