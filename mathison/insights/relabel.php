<?php
/**
 * Mathison Insights relabeler
 * ===========================
 *
 * Reads human strategy comments from two sources and converts them into
 * structured rows:
 *
 *   insight_tactics  PRIMARY unit — compound chains like
 *                    "3 hatch · ling bane · all in"
 *   insight_tags     atomic building blocks for secondary filters
 *
 * Sources:
 *   1. Replays.Player_Comments
 *   2. PatternLearning.metadata JSON ($.comment)
 *
 * Usage (CLI only):
 *   php relabel.php             full rebuild
 *   php relabel.php --dry-run   classify + print summary, write nothing
 *
 * Web: use the Insights dashboard "Run relabel" button (same library).
 *
 * Design decisions (read this before changing things)
 * ----------------------------------------------------
 * COMPOUND TACTICS ARE THE POINT. A comment is a plan, not a bag of
 * words. "3 hatch ling bane all in" means one aggressive ZvZ plan —
 * not three unrelated labels. Atomic tags are still written so you can
 * filter ("any all-in"), but dashboards and scouting reports lead with
 * tactic_label chains.
 *
 * FULL REBUILD, NOT INCREMENTAL. Every run wipes insight_tags /
 * insight_tactics and re-classifies everything. Editing rules.php
 * therefore rewrites ALL historical tactics on the next run.
 *
 * SOURCES ARE NEVER WRITTEN TO. Replays and PatternLearning are
 * read-only. Output goes only to insight_* tables.
 *
 * VOCABULARY LIVES IN rules.php. This file only: fetch comments, split
 * "X to Y" phases, detect atomic tags, chain them into ordered tactics.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "relabel.php is a CLI tool. Use the Insights dashboard or relabel_lib.php.\n");
    exit(1);
}

require __DIR__ . '/relabel_lib.php';

$dryRun = in_array('--dry-run', $argv, true);
$pdo = mathison_pdo();

$result = run_relabel($pdo, [
    'dry_run' => $dryRun,
    'on_progress' => static function (int $pct, string $message): void {
        echo "[{$pct}%] {$message}\n";
    },
]);

if (!$result['ok']) {
    fwrite(STDERR, 'FAILED: ' . ($result['error'] ?? 'unknown error') . "\n");
    exit(1);
}

echo "Sources: {$result['replays_scanned']} replay comments, {$result['patterns_scanned']} pattern comments\n";
echo "Atomic tag rows: {$result['tags']}\n";
echo "Compound tactics: {$result['tactics']}\n";
echo "Comments with no match: {$result['unmatched']}\n";
echo "--- top compound tactics ---\n";
foreach ($result['top_tactics'] as $row) {
    echo "  {$row['count']}x  {$row['label']}\n";
}

if ($dryRun) {
    echo "DRY RUN - nothing written.\n";
} else {
    echo "Run #{$result['run_id']} committed: {$result['tags']} tags, {$result['tactics']} tactics.\n";
}

exit(0);
