<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use FilesystemIterator;
use Octfx\ScDataDumper\Services\SchemaSnapshotService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'schema:diff',
    description: 'Build schema snapshots from import directories and compare them',
)]
class SchemaDiffCommand extends Command
{
    protected function configure(): void
    {
        $this->setHelp(<<<'EOF'
Compare two import directories (builds snapshots automatically):
  php cli.php schema:diff import/ /path/to/new-patch/import/

Compare two pre-built snapshot files (faster):
  php cli.php schema:diff import/schema-snapshot.json /path/to/patch/schema-snapshot.json

Filter to specific document types:
  php cli.php schema:diff import/ /new/import/ --type=CraftingBlueprintRecord

Write diff report to file:
  php cli.php schema:diff import/ /new/import/ -o report.md
EOF
        );

        $this->addArgument('old', InputArgument::REQUIRED, 'Old snapshot JSON or import directory');
        $this->addArgument('new', InputArgument::REQUIRED, 'New snapshot JSON or import directory');
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Comma-separated document types to compare (default: all)');
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Write diff report to file instead of console');
        $this->addOption('quiet', 'q', InputOption::VALUE_NONE, 'Only show changes, skip unchanged types');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Schema Diff');

        $oldPath = $input->getArgument('old');
        $newPath = $input->getArgument('new');

        $typeFilter = $input->getOption('type');
        $typeFilter = is_string($typeFilter) && $typeFilter !== ''
            ? array_map('trim', explode(',', $typeFilter))
            : null;

        $quiet = $input->getOption('quiet');
        $outputFile = $input->getOption('output');

        $oldSnapshot = $this->resolveSnapshot($oldPath, 'old', $io, $typeFilter);
        if ($oldSnapshot === null) {
            return Command::FAILURE;
        }

        $newSnapshot = $this->resolveSnapshot($newPath, 'new', $io, $typeFilter);
        if ($newSnapshot === null) {
            return Command::FAILURE;
        }

        $report = $this->diff($oldSnapshot, $newSnapshot, $quiet);

        // High-impact assessment: cross-reference changes against implemented DocumentTypes
        $codeReferences = $this->extractCodeReferences();
        $highImpact = $this->assessHighImpact($report, $codeReferences);

        $formatted = $this->formatReport($report, $highImpact);

        if ($outputFile !== null) {
            file_put_contents($outputFile, $formatted);
            $io->success(sprintf('Diff report written to %s', $outputFile));
        } else {
            $output->write($formatted);
        }

        // Exit code: 1 if there are changes, 0 if clean
        $hasChanges = $report['summary']['new_paths'] > 0
            || $report['summary']['removed_paths'] > 0
            || $report['summary']['cardinality_changes'] > 0
            || $report['summary']['new_attributes'] > 0;

        return $hasChanges ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Resolve a snapshot: load from JSON file, or build+save from directory.
     *
     * When given a directory, checks for an existing schema-snapshot.json inside it.
     * If not found, builds the snapshot and saves it there automatically.
     *
     * @return array<string, mixed>|null
     */
    private function resolveSnapshot(string $path, string $label, SymfonyStyle $io, ?array $typeFilter): ?array
    {
        if (str_ends_with($path, '.json') && file_exists($path)) {
            $io->text(sprintf('Loading %s snapshot: %s', $label, $path));

            return SchemaSnapshotService::loadSnapshot($path);
        }

        if (is_dir($path)) {
            $snapshotFile = rtrim($path, '/').'/schema-snapshot.json';

            if (file_exists($snapshotFile)) {
                $io->text(sprintf('Loading %s snapshot: %s', $label, $snapshotFile));

                return SchemaSnapshotService::loadSnapshot($snapshotFile);
            }

            $io->text(sprintf('Building %s snapshot from: %s', $label, $path));
            $service = new SchemaSnapshotService($path);
            $snapshot = $service->buildSnapshot($typeFilter, $io);
            $service->saveSnapshot($snapshot, $snapshotFile);

            $io->text(sprintf('Saved %s snapshot to: %s', $label, $snapshotFile));

            return $snapshot;
        }

        $io->error(sprintf('%s path is neither a JSON file nor a directory: %s', ucfirst($label), $path));

        return null;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function diff(array $old, array $new, bool $quiet): array
    {
        $report = [
            'summary' => [
                'new_paths' => 0,
                'removed_paths' => 0,
                'cardinality_changes' => 0,
                'new_attributes' => 0,
                'removed_attributes' => 0,
                'new_attribute_values' => 0,
            ],
            'types' => [],
        ];

        $allTypes = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($allTypes);

        foreach ($allTypes as $docType) {
            $oldPaths = $old[$docType] ?? [];
            $newPaths = $new[$docType] ?? [];

            $typeReport = $this->diffDocumentType($oldPaths, $newPaths);

            if ($quiet && ! $typeReport['has_changes']) {
                continue;
            }

            $report['types'][$docType] = $typeReport;

            $report['summary']['new_paths'] += count($typeReport['new_paths']);
            $report['summary']['removed_paths'] += count($typeReport['removed_paths']);
            $report['summary']['cardinality_changes'] += count($typeReport['cardinality_changes']);
            $report['summary']['new_attributes'] += count($typeReport['new_attributes']);
            $report['summary']['removed_attributes'] += count($typeReport['removed_attributes']);
            $report['summary']['new_attribute_values'] += count($typeReport['new_attribute_values']);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $oldPaths
     * @param  array<string, mixed>  $newPaths
     * @return array<string, mixed>
     */
    private function diffDocumentType(array $oldPaths, array $newPaths): array
    {
        $result = [
            'has_changes' => false,
            'old_path_count' => count($oldPaths),
            'new_path_count' => count($newPaths),
            'new_paths' => [],
            'removed_paths' => [],
            'cardinality_changes' => [],
            'new_attributes' => [],
            'removed_attributes' => [],
            'new_attribute_values' => [],
            'unchanged_path_count' => 0,
        ];

        $oldKeys = array_keys($oldPaths);
        $newKeys = array_keys($newPaths);

        $added = array_diff($newKeys, $oldKeys);
        $removed = array_diff($oldKeys, $newKeys);
        $common = array_intersect($oldKeys, $newKeys);

        // New paths
        foreach ($added as $path) {
            $result['new_paths'][$path] = $this->summarizePath($newPaths[$path]);
            $result['has_changes'] = true;
        }

        // Removed paths
        foreach ($removed as $path) {
            $result['removed_paths'][$path] = $this->summarizePath($oldPaths[$path]);
            $result['has_changes'] = true;
        }

        // Changed paths
        foreach ($common as $path) {
            $oldFp = $oldPaths[$path];
            $newFp = $newPaths[$path];
            $changed = false;

            // Cardinality changes in child elements
            $oldChildren = $oldFp['child_elements'] ?? [];
            $newChildren = $newFp['child_elements'] ?? [];

            $allChildTags = array_unique(array_merge(array_keys($oldChildren), array_keys($newChildren)));

            foreach ($allChildTags as $tag) {
                $oldMax = $oldChildren[$tag]['max_count'] ?? 0;
                $newMax = $newChildren[$tag]['max_count'] ?? 0;
                $wasPresent = isset($oldChildren[$tag]);
                $isPresent = isset($newChildren[$tag]);

                if ($newMax > $oldMax) {
                    $result['cardinality_changes'][] = [
                        'path' => $path,
                        'child_tag' => $tag,
                        'old_max' => $oldMax,
                        'new_max' => $newMax,
                        'change' => 'increased',
                    ];
                    $changed = true;
                }

                if ($isPresent && ! $wasPresent) {
                    $result['cardinality_changes'][] = [
                        'path' => $path,
                        'child_tag' => $tag,
                        'old_max' => null,
                        'new_max' => $newMax,
                        'change' => 'new_child',
                    ];
                    $changed = true;
                }

                if ($wasPresent && ! $isPresent) {
                    $result['cardinality_changes'][] = [
                        'path' => $path,
                        'child_tag' => $tag,
                        'old_max' => $oldMax,
                        'new_max' => null,
                        'change' => 'removed_child',
                    ];
                    $changed = true;
                }
            }

            // New/removed attributes
            $oldAttrs = array_keys($oldFp['attributes'] ?? []);
            $newAttrs = array_keys($newFp['attributes'] ?? []);

            $addedAttrs = array_diff($newAttrs, $oldAttrs);
            $removedAttrs = array_diff($oldAttrs, $newAttrs);

            foreach ($addedAttrs as $attr) {
                $result['new_attributes'][] = [
                    'path' => $path,
                    'attribute' => $attr,
                    'sample_values' => $newFp['attributes'][$attr]['sample_values'] ?? [],
                ];
                $changed = true;
            }

            foreach ($removedAttrs as $attr) {
                $result['removed_attributes'][] = [
                    'path' => $path,
                    'attribute' => $attr,
                ];
                $changed = true;
            }

            // New attribute values (for common attributes)
            $commonAttrs = array_intersect($oldAttrs, $newAttrs);
            foreach ($commonAttrs as $attr) {
                $oldValues = $oldFp['attributes'][$attr]['sample_values'] ?? [];
                $newValues = $newFp['attributes'][$attr]['sample_values'] ?? [];
                $addedValues = array_diff($newValues, $oldValues);

                if ($addedValues !== []) {
                    $result['new_attribute_values'][] = [
                        'path' => $path,
                        'attribute' => $attr,
                        'new_values' => array_values($addedValues),
                    ];
                    $changed = true;
                }
            }

            if (! $changed) {
                $result['unchanged_path_count']++;
            }

            $result['has_changes'] = $result['has_changes'] || $changed;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $fp
     * @return array<string, mixed>
     */
    private function summarizePath(array $fp): array
    {
        return [
            'xml_tag' => $fp['xml_tag'] ?? null,
            'attributes' => array_keys($fp['attributes'] ?? []),
            'child_elements' => array_map(
                static fn (array $c) => $c['max_count'] ?? 0,
                $fp['child_elements'] ?? [],
            ),
        ];
    }

    /**
     * Scan src/DocumentTypes/ PHP files and extract XML path references.
     *
     * Builds a map of docType => [path => [[file, line], ...]] by looking at
     * $this->get('path'), $this->getString('path'), etc. and $this->getHydratedDocument('path', ...).
     *
     * @return array<string, array<string, list<array{file: string, line: int}>>>
     */
    private function extractCodeReferences(): array
    {
        $references = [];
        $baseDir = dirname(__DIR__).'/DocumentTypes';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $docType = $fileInfo->getBasename('.php');

            if ($docType === 'RootDocument') {
                continue;
            }

            $content = file_get_contents($fileInfo->getRealPath());
            $lines = explode("\n", $content);
            $relativePath = 'src/DocumentTypes/'.ltrim(substr($fileInfo->getRealPath(), strlen($baseDir)), '/');

            foreach ($lines as $lineNum => $line) {
                // Match ->get('path'), ->getString('path'), ->getInt('path'), etc. and ->getHydratedDocument('path', ...)
                if (! preg_match_all(
                    "/->(?:get|getString|getInt|getFloat|getBool|getNullableBool|getHydratedDocument)\(\s*'([^']+)'/",
                    $line,
                    $matches,
                )) {
                    continue;
                }

                foreach ($matches[1] as $path) {
                    if (str_starts_with($path, '@')) {
                        $path = substr($path, 1);
                    }

                    $elementPath = $path;
                    $attribute = null;

                    if (str_contains($path, '@')) {
                        [$elementPath, $attribute] = explode('@', $path, 2);
                    }

                    $key = $attribute !== null ? $elementPath.'@'.$attribute : $elementPath;

                    if (! isset($references[$docType][$key])) {
                        $references[$docType][$key] = [];
                    }

                    $references[$docType][$key][] = [
                        'file' => $relativePath,
                        'line' => $lineNum + 1,
                    ];
                }
            }
        }

        return $references;
    }

    /**
     * Cross-reference diff report against code references to identify high-impact changes.
     *
     * Returns a sorted list of high-impact types with their matching changes, ordered by impact score (most changes first).
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, array<string, list<array{file: string, line: int}>>>  $codeReferences
     * @return array<string, array{score: int, changes: list<array{type: string, path: string, attribute?: string, references: list<array{file: string, line: int}>}>}>
     */
    private function assessHighImpact(array $report, array $codeReferences): array
    {
        $highImpact = [];

        foreach ($report['types'] as $docType => $typeReport) {
            if (! $typeReport['has_changes']) {
                continue;
            }

            if (! isset($codeReferences[$docType])) {
                continue;
            }

            $typeRefs = $codeReferences[$docType];
            $matchedChanges = [];

            // Check new/removed paths
            foreach (['new_paths' => 'new_path', 'removed_paths' => 'removed_path'] as $field => $type) {
                foreach (array_keys($typeReport[$field]) as $path) {
                    $refs = $this->findMatchingRefs($path, null, $typeRefs);

                    if ($refs !== []) {
                        $matchedChanges[] = [
                            'type' => $type,
                            'path' => $path,
                            'references' => $refs,
                        ];
                    }
                }
            }

            // Check cardinality changes
            foreach ($typeReport['cardinality_changes'] as $change) {
                $refs = $this->findMatchingRefs($change['path'], null, $typeRefs);

                if ($refs !== []) {
                    $matchedChanges[] = [
                        'type' => 'cardinality_change',
                        'path' => $change['path'],
                        'detail' => match ($change['change']) {
                            'increased' => sprintf('<%s> max_count: %d -> %d', $change['child_tag'], $change['old_max'], $change['new_max']),
                            'new_child' => sprintf('<%s> (new child element, max_count: %d)', $change['child_tag'], $change['new_max']),
                            'removed_child' => sprintf('<%s> (removed child element)', $change['child_tag']),
                            default => sprintf('<%s> %s', $change['child_tag'], $change['change']),
                        },
                        'references' => $refs,
                    ];
                }
            }

            // Check new/removed attributes and new attribute values
            foreach (['new_attributes' => 'new_attribute', 'removed_attributes' => 'removed_attribute', 'new_attribute_values' => 'new_attribute_value'] as $field => $type) {
                foreach ($typeReport[$field] as $change) {
                    $refs = $this->findMatchingRefs($change['path'], $change['attribute'], $typeRefs);

                    if ($refs !== []) {
                        $matchedChanges[] = [
                            'type' => $type,
                            'path' => $change['path'],
                            'attribute' => $change['attribute'],
                            'references' => $refs,
                        ];
                    }
                }
            }

            if ($matchedChanges !== []) {
                $highImpact[$docType] = [
                    'score' => count($matchedChanges),
                    'changes' => $matchedChanges,
                ];
            }
        }

        uasort($highImpact, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $highImpact;
    }

    /**
     * Find code references that match a given snapshot path and optional attribute.
     *
     * Matches element paths as prefixes. A code ref for 'Components/SAttachableComponentParams/AttachDef'
     * matches snapshot path 'Components/SAttachableComponentParams/AttachDef'.
     *
     * @param  array<string, list<array{file: string, line: int}>>  $typeRefs
     * @return list<array{file: string, line: int}>
     */
    private function findMatchingRefs(string $path, ?string $attribute, array $typeRefs): array
    {
        $refs = [];
        $matchedKeys = [];

        // Check for exact path@attribute match
        if ($attribute !== null) {
            $key = $path.'@'.$attribute;

            if (isset($typeRefs[$key])) {
                $refs = array_merge($refs, $typeRefs[$key]);
                $matchedKeys[$key] = true;
            }
        }

        // Check for exact element-path match
        if (isset($typeRefs[$path])) {
            $refs = array_merge($refs, $typeRefs[$path]);
            $matchedKeys[$path] = true;
        }

        // Check prefix matches: code reads 'Components/.../AttachDef'
        foreach ($typeRefs as $refKey => $refList) {
            if (isset($matchedKeys[$refKey])) {
                continue;
            }

            $refElementPath = str_contains($refKey, '@') ? explode('@', $refKey, 2)[0] : $refKey;

            if ($path !== $refElementPath && str_starts_with($refElementPath, $path.'/')) {
                $refs = array_merge($refs, $refList);
            }
        }

        $seen = [];
        $deduped = [];
        foreach ($refs as $ref) {
            $key = $ref['file'].':'.$ref['line'];

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $ref;
            }
        }

        return $deduped;
    }

    private function formatReport(array $report, array $highImpact): string
    {
        $lines = [];
        $s = $report['summary'];

        $lines[] = '# Schema Diff Report';
        $lines[] = '';
        $lines[] = sprintf(
            '## Summary: %d new paths, %d removed, %d cardinality changes, %d new attributes, %d removed attributes, %d new attribute values',
            $s['new_paths'],
            $s['removed_paths'],
            $s['cardinality_changes'],
            $s['new_attributes'],
            $s['removed_attributes'],
            $s['new_attribute_values'],
        );
        $lines[] = '';

        if ($s['new_paths'] === 0 && $s['removed_paths'] === 0
            && $s['cardinality_changes'] === 0 && $s['new_attributes'] === 0
            && $s['removed_attributes'] === 0 && $s['new_attribute_values'] === 0) {
            $lines[] = 'No structural changes detected.';

            return implode("\n", $lines)."\n";
        }

        if ($highImpact !== []) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = sprintf('# High Impact (%d implemented types affected)', count($highImpact));
            $lines[] = '';

            foreach ($highImpact as $docType => $impact) {
                $lines[] = sprintf('## %s (%d change%s match code references)', $docType, $impact['score'], $impact['score'] === 1 ? '' : 's');
                $lines[] = '';

                foreach ($impact['changes'] as $change) {
                    $label = match ($change['type']) {
                        'new_path' => '+ NEW PATH',
                        'removed_path' => '- REMOVED PATH',
                        'cardinality_change' => '^ CARDINALITY',
                        'new_attribute' => '+ NEW ATTR',
                        'removed_attribute' => '- REMOVED ATTR',
                        'new_attribute_value' => '~ NEW VALUE',
                        default => '? UNKNOWN',
                    };

                    $path = $change['path'];
                    if (isset($change['attribute'])) {
                        $path .= '@'.$change['attribute'];
                    }

                    $lines[] = sprintf('  ! %s: %s', $label, $path);

                    if (isset($change['detail'])) {
                        $lines[] = sprintf('      %s', $change['detail']);
                    }

                    $refs = $change['references'];
                    $refCount = count($refs);
                    $showRefs = array_slice($refs, 0, 3);

                    foreach ($showRefs as $ref) {
                        $lines[] = sprintf('      -> %s:%d', $ref['file'], $ref['line']);
                    }

                    if ($refCount > 3) {
                        $lines[] = sprintf('      ... and %d more', $refCount - 3);
                    }
                }

                $lines[] = '';
            }
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '# Full Diff';
        $lines[] = '';

        foreach ($report['types'] as $docType => $typeReport) {
            if (! $typeReport['has_changes']) {
                continue;
            }

            $lines[] = sprintf('## %s (%d -> %d paths)', $docType, $typeReport['old_path_count'], $typeReport['new_path_count']);
            $lines[] = '';

            // New paths
            if ($typeReport['new_paths'] !== []) {
                $lines[] = '### New Paths';

                foreach ($typeReport['new_paths'] as $path => $summary) {
                    $attrs = $summary['attributes'] !== [] ? ' @'.implode(', @', $summary['attributes']) : '';
                    $lines[] = sprintf('  + %s%s', $path, $attrs);
                }

                $lines[] = '';
            }

            // Removed paths
            if ($typeReport['removed_paths'] !== []) {
                $lines[] = '### Removed Paths';

                foreach ($typeReport['removed_paths'] as $path => $summary) {
                    $lines[] = sprintf('  - %s', $path);
                }

                $lines[] = '';
            }

            // Cardinality changes
            if ($typeReport['cardinality_changes'] !== []) {
                $lines[] = '### Cardinality Changes';

                foreach ($typeReport['cardinality_changes'] as $change) {
                    $lines[] = match ($change['change']) {
                        'increased' => sprintf(
                            '  ^ %s -> <%s> max_count: %d -> %d',
                            $change['path'],
                            $change['child_tag'],
                            $change['old_max'],
                            $change['new_max'],
                        ),
                        'new_child' => sprintf(
                            '  + %s -> <%s> (new child element, max_count: %d)',
                            $change['path'],
                            $change['child_tag'],
                            $change['new_max'],
                        ),
                        'removed_child' => sprintf(
                            '  - %s -> <%s> (removed child element, was max_count: %d)',
                            $change['path'],
                            $change['child_tag'],
                            $change['old_max'],
                        ),
                    };
                }

                $lines[] = '';
            }

            // New attributes
            if ($typeReport['new_attributes'] !== []) {
                $lines[] = '### New Attributes';

                foreach ($typeReport['new_attributes'] as $change) {
                    $samples = $change['sample_values'] !== []
                        ? ' samples: ['.implode(', ', array_slice($change['sample_values'], 0, 5)).']'
                        : '';
                    $lines[] = sprintf('  + %s @%s%s', $change['path'], $change['attribute'], $samples);
                }

                $lines[] = '';
            }

            if ($typeReport['removed_attributes'] !== []) {
                $lines[] = '### Removed Attributes';

                foreach ($typeReport['removed_attributes'] as $change) {
                    $lines[] = sprintf('  - %s @%s', $change['path'], $change['attribute']);
                }

                $lines[] = '';
            }

            if ($typeReport['new_attribute_values'] !== []) {
                $lines[] = '### New Attribute Values';

                foreach ($typeReport['new_attribute_values'] as $change) {
                    $values = implode(', ', array_slice($change['new_values'], 0, 10));
                    $lines[] = sprintf('  ~ %s @%s = [%s]', $change['path'], $change['attribute'], $values);
                }

                $lines[] = '';
            }
        }

        return implode("\n", $lines)."\n";
    }
}
