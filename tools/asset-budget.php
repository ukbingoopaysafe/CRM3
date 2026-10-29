<?php

declare(strict_types=1);

/**
 * Fails when the built assets grow past the ceilings recorded in
 * CONSTRAINTS.md. Run after `npm run build`.
 *
 *   php tools/asset-budget.php
 */
// Mirrors the numbers in CONSTRAINTS.md. The CSS ceiling moved once, when
// Filament 5.11 shipped a heavier bundle; the file records why.
const CEILINGS = [
    'css' => 790_000,
    'js' => 62_000,
];

$directory = dirname(__DIR__).'/public/build/assets';

if (! is_dir($directory)) {
    fwrite(STDERR, "asset-budget: {$directory} is missing. Run npm run build first.\n");
    exit(2);
}

$totals = array_fill_keys(array_keys(CEILINGS), 0);

foreach (scandir($directory) ?: [] as $name) {
    $extension = pathinfo($name, PATHINFO_EXTENSION);

    if (isset($totals[$extension])) {
        $totals[$extension] += filesize($directory.'/'.$name);
    }
}

$failed = false;

foreach (CEILINGS as $extension => $ceiling) {
    $used = $totals[$extension];
    $percent = number_format($used / $ceiling * 100, 1);
    $verdict = $used <= $ceiling ? 'ok' : 'over';

    if ($used > $ceiling) {
        $failed = true;
    }

    printf("%-4s %9s bytes of %9s (%s%%)  %s\n", strtoupper($extension), number_format($used), number_format($ceiling), $percent, $verdict);
}

if ($failed) {
    fwrite(STDERR, "asset-budget: the bundle grew past its ceiling. Remove weight, or justify a new number in CONSTRAINTS.md.\n");
    exit(1);
}
