<?php

/**
 * Fails unless the code of the extension is covered at 100 %, lines and
 * branches. Reads the report written by `phpunit --path-coverage --coverage-php`.
 *
 *     php bin/check-coverage.php build/coverage.cov
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$file = $argv[1] ?? 'build/coverage.cov';
if (!is_file($file)) {
    fwrite(STDERR, "Coverage report not found: $file\n");
    exit(2);
}

/** @var \SebastianBergmann\CodeCoverage\CodeCoverage $coverage */
$coverage = include $file;
$report   = $coverage->getReport();

$lines    = [$report->numberOfExecutedLines(), $report->numberOfExecutableLines()];
$branches = [$report->numberOfExecutedBranches(), $report->numberOfExecutableBranches()];

$missing = [];
foreach ($report as $node) {
    if (!$node instanceof \SebastianBergmann\CodeCoverage\Node\File) {
        continue;
    }
    if (
        $node->numberOfExecutedLines() < $node->numberOfExecutableLines()
        || $node->numberOfExecutedBranches() < $node->numberOfExecutableBranches()
    ) {
        $uncovered = [];
        foreach ($node->lineCoverageData() as $line => $tests) {
            if ($tests === []) {
                $uncovered[] = $line;
            }
        }
        foreach ($node->functionCoverageData() as $function => $data) {
            foreach ($data['branches'] ?? [] as $branch) {
                if ($branch['hit'] === []) {
                    $uncovered[] = sprintf('branch %s %d-%d', $function, $branch['line_start'], $branch['line_end']);
                }
            }
        }
        $missing[] = sprintf(
            '  %s: lines %d/%d, branches %d/%d%s',
            $node->pathAsString(),
            $node->numberOfExecutedLines(),
            $node->numberOfExecutableLines(),
            $node->numberOfExecutedBranches(),
            $node->numberOfExecutableBranches(),
            $uncovered ? ' (uncovered lines: ' . implode(', ', $uncovered) . ')' : ''
        );
    }
}

printf("Lines: %d/%d, branches: %d/%d\n", $lines[0], $lines[1], $branches[0], $branches[1]);
if ($missing !== [] || $lines[0] < $lines[1] || $branches[0] < $branches[1]) {
    fwrite(STDERR, "Coverage below 100 %:\n" . implode("\n", $missing) . "\n");
    exit(1);
}
echo "Coverage: 100 % of lines and branches.\n";
