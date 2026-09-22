<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/tool_bootstrap.php';

set_time_limit(60);

/**
 * Normalize a filesystem path for comparison purposes.
 */
function tool_normalize_path(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    return rtrim($normalized, '/');
}

/**
 * Build a path relative to a specific root.
 */
function tool_relative_path(string $root, string $path): string
{
    $normalizedRoot = tool_normalize_path($root);
    $normalizedPath = tool_normalize_path($path);

    if (str_starts_with($normalizedPath, $normalizedRoot)) {
        $relative = ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');
        return $relative === '' ? '.' : $relative;
    }

    return $normalizedPath;
}

/**
 * Highlight a search term within a label.
 */
function tool_highlight_label(string $label, string $term): string
{
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    if ($term === '') {
        return $safeLabel;
    }

    $safeTerm = htmlspecialchars($term, ENT_QUOTES, 'UTF-8');
    $pattern = '/' . preg_quote($safeTerm, '/') . '/i';

    $highlighted = preg_replace($pattern, '<mark>$0</mark>', $safeLabel);

    return $highlighted !== null ? $highlighted : $safeLabel;
}

/**
 * Recursively scan directories respecting depth, search, and visibility options.
 *
 * @param string $rootPath
 * @param string $currentPath
 * @param int $maxDepth
 * @param bool $includeHidden
 * @param string $searchTermLower
 * @param int $currentDepth
 * @param int $entryLimit
 * @param int $entryCount
 * @param array<string, int> $stats
 * @param array<string, mixed> $flags
 * @return array<int, array<string, mixed>>
 */
function tool_scan_file_tree(
    string $rootPath,
    string $currentPath,
    int $maxDepth,
    bool $includeHidden,
    string $searchTermLower,
    int $currentDepth,
    int $entryLimit,
    int &$entryCount,
    array &$stats,
    array &$flags
): array {
    if ($entryCount >= $entryLimit) {
        $flags['limitReached'] = true;
        return [];
    }

    $items = @scandir($currentPath, SCANDIR_SORT_ASCENDING);
    if ($items === false) {
        $flags['unreadable'][] = tool_relative_path($rootPath, $currentPath);
        return [];
    }

    $directories = [];
    $files = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (!$includeHidden && str_starts_with($item, '.')) {
            continue;
        }

        $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;

        if (is_dir($itemPath)) {
            $directories[] = $item;
        } else {
            $files[] = $item;
        }
    }

    sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $nodes = [];

    foreach ($directories as $directoryName) {
        if ($entryCount >= $entryLimit) {
            $flags['limitReached'] = true;
            break;
        }

        $directoryPath = $currentPath . DIRECTORY_SEPARATOR . $directoryName;
        $relativePath = tool_relative_path($rootPath, $directoryPath);
        $matchesSelf = $searchTermLower === '' || str_contains(strtolower($directoryName), $searchTermLower);

        $children = [];
        $descendantDirectories = 0;
        $descendantFiles = 0;
        $aggregateSize = 0;
        $truncatedAtDepth = false;

        if ($currentDepth + 1 < $maxDepth) {
            $children = tool_scan_file_tree(
                $rootPath,
                $directoryPath,
                $maxDepth,
                $includeHidden,
                $searchTermLower,
                $currentDepth + 1,
                $entryLimit,
                $entryCount,
                $stats,
                $flags
            );

            foreach ($children as $childNode) {
                if (($childNode['type'] ?? '') === 'dir') {
                    $descendantDirectories += 1 + (int)($childNode['descendants']['directories'] ?? 0);
                    $descendantFiles += (int)($childNode['descendants']['files'] ?? 0);
                } elseif (($childNode['type'] ?? '') === 'file') {
                    $descendantFiles += 1;
                }

                $aggregateSize += (int)($childNode['size'] ?? 0);
            }
        } else {
            $subItems = @scandir($directoryPath, SCANDIR_SORT_NONE) ?: [];
            foreach ($subItems as $subItem) {
                if ($subItem === '.' || $subItem === '..') {
                    continue;
                }

                if (!$includeHidden && str_starts_with($subItem, '.')) {
                    continue;
                }

                $truncatedAtDepth = true;
                break;
            }

            if ($truncatedAtDepth) {
                $flags['depthTruncated'] = true;
            }
        }

        $shouldInclude = $matchesSelf || $searchTermLower === '' || !empty($children) || $truncatedAtDepth;

        if (!$shouldInclude) {
            continue;
        }

        $entryCount++;

        $nodes[] = [
            'type' => 'dir',
            'name' => $directoryName,
            'path' => $relativePath,
            'children' => $children,
            'modified' => @filemtime($directoryPath) ?: null,
            'descendants' => [
                'directories' => $descendantDirectories,
                'files' => $descendantFiles,
            ],
            'size' => $aggregateSize,
            'pruned' => $truncatedAtDepth,
        ];

        $stats['directories']++;
    }

    foreach ($files as $fileName) {
        if ($entryCount >= $entryLimit) {
            $flags['limitReached'] = true;
            break;
        }

        $filePath = $currentPath . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = tool_relative_path($rootPath, $filePath);
        $matches = $searchTermLower === '' || str_contains(strtolower($fileName), $searchTermLower) || str_contains(strtolower($relativePath), $searchTermLower);

        if (!$matches) {
            continue;
        }

        $size = @filesize($filePath);
        $modified = @filemtime($filePath);

        $entryCount++;

        $nodes[] = [
            'type' => 'file',
            'name' => $fileName,
            'path' => $relativePath,
            'size' => $size === false ? null : (int)$size,
            'modified' => $modified === false ? null : $modified,
            'extension' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
        ];

        $stats['files']++;
        if ($size !== false) {
            $stats['bytes'] += (int)$size;
        }
    }

    return $nodes;
}

/**
 * Render the file tree recursively.
 *
 * @param array<int, array<string, mixed>> $nodes
 */
function tool_render_file_tree(array $nodes, string $searchTerm, int $level = 0): void
{
    if (empty($nodes)) {
        return;
    }

    foreach ($nodes as $node) {
        $type = $node['type'] ?? '';

        if ($type === 'dir') {
            $modifierClasses = 'file-node';
            if (!empty($node['pruned'])) {
                $modifierClasses .= ' file-node--pruned';
            }

            echo '<details class="' . $modifierClasses . '"' . ($level < 1 ? ' open' : '') . '>';
            echo '<summary>';
            echo "<span class='node-label'><i class='bx bx-folder me-2 text-info'></i>" . tool_highlight_label((string)$node['name'], $searchTerm) . '</span>';

            $descDirectories = (int)($node['descendants']['directories'] ?? 0);
            $descFiles = (int)($node['descendants']['files'] ?? 0);
            $size = $node['size'] ?? null;
            $modified = $node['modified'] ?? null;

            echo '<span class="node-meta">';
            echo '<span class="node-meta__item"><i class="bx bx-folder-open"></i> ' . number_format($descDirectories) . ' dirs</span>';
            echo '<span class="node-meta__item"><i class="bx bx-file"></i> ' . number_format($descFiles) . ' files</span>';
            if (is_int($size) && $size > 0) {
                echo '<span class="node-meta__item"><i class="bx bx-data"></i> ' . htmlspecialchars(tool_format_bytes($size), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if (is_int($modified)) {
                echo '<span class="node-meta__item"><i class="bx bx-time-five"></i> ' . htmlspecialchars(date('d M Y, H:i', $modified), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if (!empty($node['pruned'])) {
                echo '<span class="node-meta__item text-warning"><i class="bx bx-layer"></i> Depth limit</span>';
            }
            echo '</span>';
            echo '</summary>';

            if (!empty($node['children'])) {
                echo '<div class="node-children">';
                tool_render_file_tree($node['children'], $searchTerm, $level + 1);
                echo '</div>';
            }

            echo '</details>';
        } elseif ($type === 'file') {
            $size = $node['size'] ?? null;
            $modified = $node['modified'] ?? null;
            $extension = $node['extension'] ?? '';

            $icon = 'bx-file';
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                $icon = 'bx-image-alt';
            } elseif (in_array($extension, ['php', 'js', 'ts', 'css', 'scss', 'html'], true)) {
                $icon = 'bx-code-alt';
            } elseif (in_array($extension, ['json', 'xml', 'yml', 'yaml'], true)) {
                $icon = 'bx-data';
            } elseif (in_array($extension, ['md', 'txt', 'log'], true)) {
                $icon = 'bx-book';
            } elseif (in_array($extension, ['pdf'], true)) {
                $icon = 'bx-file-pdf';
            }

            echo '<div class="file-entry">';
            echo "<div class='file-entry__label'><i class='bx " . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . " me-2 text-primary'></i>" . tool_highlight_label((string)$node['name'], $searchTerm) . '</div>';
            echo '<div class="file-entry__meta">';
            if (is_int($size)) {
                echo '<span class="file-entry__meta-item"><i class="bx bx-layer"></i> ' . htmlspecialchars(tool_format_bytes($size), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if (is_int($modified)) {
                echo '<span class="file-entry__meta-item"><i class="bx bx-time"></i> ' . htmlspecialchars(date('d M Y, H:i', $modified), ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '<span class="file-entry__meta-item text-muted">' . htmlspecialchars((string)$node['path'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</div>';
            echo '</div>';
        }
    }
}

$workspaceRoot = realpath(dirname(__DIR__)) ?: __DIR__;

$directoryOptions = [
    'workspace' => [
        'label' => 'Project root',
        'description' => 'Entire defect tracking workspace',
        'path' => $workspaceRoot,
    ],
    'system-tools' => [
        'label' => 'System tools',
        'description' => 'Diagnostics and maintenance utilities',
        'path' => __DIR__,
    ],
    'api' => [
        'label' => 'API endpoints',
        'description' => 'REST entrypoints under /api',
        'path' => $workspaceRoot . '/api',
    ],
    'classes' => [
        'label' => 'Classes',
        'description' => 'Business logic classes',
        'path' => $workspaceRoot . '/classes',
    ],
    'uploads' => [
        'label' => 'Uploads',
        'description' => 'User generated uploads',
        'path' => $workspaceRoot . '/uploads',
    ],
    'logs' => [
        'label' => 'Logs',
        'description' => 'Application log files',
        'path' => $workspaceRoot . '/logs',
    ],
];

$availableOptions = [];
foreach ($directoryOptions as $key => $option) {
    $realPath = realpath($option['path']);
    if ($realPath !== false && is_dir($realPath)) {
        $option['path'] = $realPath;
        $availableOptions[$key] = $option;
    }
}

if (empty($availableOptions)) {
    tool_render_header('File Structure Explorer', 'Inspect directories and files in the workspace', [
        ['label' => 'System tools', 'href' => 'system_health.php'],
        ['label' => 'File structure explorer'],
    ]);
    echo '<div class="alert alert-danger">No readable directories were discovered.</div>';
    tool_render_footer();
    exit;
}

$selectedBaseKey = filter_input(INPUT_GET, 'base', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'workspace';
if (!isset($availableOptions[$selectedBaseKey])) {
    $selectedBaseKey = array_key_first($availableOptions);
}

$selectedBase = $availableOptions[$selectedBaseKey];
$basePath = $selectedBase['path'];

$includeHidden = filter_input(INPUT_GET, 'hidden', FILTER_VALIDATE_BOOL);
$includeHidden = $includeHidden === null ? false : $includeHidden;

$searchTerm = trim((string)(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?? ''));
$searchTermLower = strtolower($searchTerm);

$maxDepthInput = filter_input(INPUT_GET, 'depth', FILTER_VALIDATE_INT, ['options' => ['default' => 4]]);
$maxDepth = max(1, min((int)$maxDepthInput, 10));

$entryLimit = 1800;
$entryCount = 0;
$currentDepth = 0;
$stats = [
    'directories' => 0,
    'files' => 0,
    'bytes' => 0,
];
$flags = [
    'limitReached' => false,
    'depthTruncated' => false,
    'unreadable' => [],
];

$tree = tool_scan_file_tree(
    $basePath,
    $basePath,
    $maxDepth,
    $includeHidden,
    $searchTermLower,
    $currentDepth,
    $entryLimit,
    $entryCount,
    $stats,
    $flags
);

tool_render_header(
    'File Structure Explorer',
    'Explore directories, drill into assets, and audit file footprints.',
    [
        ['label' => 'System tools', 'href' => 'system_health.php'],
        ['label' => 'File structure explorer'],
    ]
);
?>
<style>
    .file-hero {
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 64, 175, 0.85));
        border-radius: 1.4rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 2.25rem;
        box-shadow: 0 32px 56px -28px rgba(15, 23, 42, 0.75);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.75rem;
        color: rgba(226, 232, 240, 0.92);
    }

    .file-hero__meta {
        display: grid;
        gap: 0.6rem;
        font-size: 0.9rem;
        color: rgba(191, 219, 254, 0.82);
    }

    .file-controls {
        background: rgba(15, 23, 42, 0.82);
        border-radius: 1.2rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 1.75rem;
        box-shadow: 0 20px 36px -28px rgba(15, 23, 42, 0.75);
    }

    .file-stats .stat-card {
        background: rgba(15, 23, 42, 0.88);
        border-radius: 1.1rem;
        border: 1px solid rgba(148, 163, 184, 0.14);
        padding: 1.5rem;
        height: 100%;
    }

    .stat-card__label {
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.75rem;
        color: rgba(148, 163, 184, 0.78);
    }

    .stat-card__value {
        font-size: 1.85rem;
        font-weight: 600;
        color: rgba(226, 232, 240, 0.96);
    }

    .file-tree {
        background: rgba(15, 23, 42, 0.82);
        border-radius: 1.2rem;
        border: 1px solid rgba(148, 163, 184, 0.16);
        padding: 1.5rem;
        box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.08);
    }

    .file-node {
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 1rem;
        margin-bottom: 0.75rem;
        padding: 0.45rem 0.85rem 0.45rem 1.1rem;
        background: rgba(30, 41, 59, 0.45);
    }

    .file-node summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        list-style: none;
    }

    .file-node summary::-webkit-details-marker {
        display: none;
    }

    .file-node--pruned {
        border-style: dashed;
    }

    .node-label {
        font-weight: 600;
        color: rgba(226, 232, 240, 0.94);
        display: flex;
        align-items: center;
    }

    .node-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        font-size: 0.8rem;
        color: rgba(148, 163, 184, 0.85);
    }

    .node-meta__item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .node-children {
        padding-left: 1.25rem;
        margin-top: 0.75rem;
        border-left: 1px solid rgba(148, 163, 184, 0.12);
    }

    .file-entry {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.75rem 0.95rem;
        border-radius: 0.9rem;
        background: rgba(15, 23, 42, 0.65);
        border: 1px solid rgba(148, 163, 184, 0.08);
        margin-bottom: 0.6rem;
    }

    .file-entry__label {
        font-weight: 500;
        color: rgba(226, 232, 240, 0.92);
        display: flex;
        align-items: center;
    }

    .file-entry__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        font-size: 0.8rem;
        color: rgba(148, 163, 184, 0.85);
    }

    .file-entry__meta-item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    mark {
        background: rgba(58, 134, 255, 0.25);
        color: inherit;
        padding: 0 0.25rem;
        border-radius: 0.35rem;
    }

    @media (max-width: 768px) {
        .file-entry {
            flex-direction: column;
        }

        .node-meta {
            justify-content: flex-start;
        }
    }
</style>

<section class="file-hero mb-4">
    <div>
        <span class="badge bg-primary bg-opacity-25 text-uppercase text-light mb-2"><i class="bx bx-sitemap me-1"></i>Structure explorer</span>
        <h1 class="h4 mb-2">Deep dive into <?php echo htmlspecialchars($selectedBase['label'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="mb-0 text-light text-opacity-75">Browse directories, locate assets fast, and surface depth-limited snapshots without leaving the console.</p>
    </div>
    <div class="file-hero__meta">
        <span><i class="bx bx-folder-open me-1"></i><?php echo htmlspecialchars(tool_relative_path($workspaceRoot, $basePath), ENT_QUOTES, 'UTF-8'); ?></span>
        <span><i class="bx bx-layer me-1"></i>Depth: <?php echo htmlspecialchars((string)$maxDepth, ENT_QUOTES, 'UTF-8'); ?> levels</span>
        <span><i class="bx bx-filter-alt me-1"></i><?php echo $includeHidden ? 'Hidden entries included' : 'Hidden entries filtered'; ?></span>
        <span><i class="bx bx-search me-1"></i><?php echo $searchTerm !== '' ? htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') : 'No search applied'; ?></span>
    </div>
</section>

<section class="file-controls mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label for="base" class="form-label text-uppercase small text-muted">Base directory</label>
            <select name="base" id="base" class="form-select form-select-lg bg-dark text-light border-secondary">
                <?php foreach ($availableOptions as $key => $option): ?>
                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $key === $selectedBaseKey ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text text-muted">
                <?php echo htmlspecialchars($selectedBase['description'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <label for="search" class="form-label text-uppercase small text-muted">Search</label>
            <input type="search" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-lg bg-dark text-light border-secondary" placeholder="Filter by name or path">
        </div>
        <div class="col-6 col-md-2">
            <label for="depth" class="form-label text-uppercase small text-muted">Depth</label>
            <input type="number" min="1" max="10" name="depth" id="depth" value="<?php echo htmlspecialchars((string)$maxDepth, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-lg bg-dark text-light border-secondary">
        </div>
        <div class="col-6 col-md-2 d-flex align-items-center">
            <div class="form-check form-switch mt-4 pt-1">
                <input class="form-check-input" type="checkbox" role="switch" id="hidden" name="hidden" value="1" <?php echo $includeHidden ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hidden">Show hidden</label>
            </div>
        </div>
        <div class="col-12 col-md-1 d-grid">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bx bx-refresh me-1"></i>Run</button>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <a class="btn btn-outline-light btn-sm" href="show_file_structure.php"><i class="bx bx-reset me-1"></i>Reset filters</a>
        </div>
    </form>
</section>

<section class="file-stats row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-card__label">Directories</div>
            <div class="stat-card__value"><?php echo number_format($stats['directories']); ?></div>
            <p class="text-muted small mb-0">Directories surfaced within the current depth window.</p>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-card__label">Files</div>
            <div class="stat-card__value"><?php echo number_format($stats['files']); ?></div>
            <p class="text-muted small mb-0">File entries matching the current filters.</p>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="stat-card__label">Aggregate size</div>
            <div class="stat-card__value"><?php echo htmlspecialchars(tool_format_bytes($stats['bytes']), ENT_QUOTES, 'UTF-8'); ?></div>
            <p class="text-muted small mb-0">Summed file size for the visible results.</p>
        </div>
    </div>
</section>

<?php if (!empty($flags['limitReached'])): ?>
    <div class="alert alert-warning"><i class="bx bx-info-circle me-1"></i>Display limited to <?php echo number_format($entryLimit); ?> entries. Refine your search or reduce the scope for full coverage.</div>
<?php endif; ?>

<?php if (!empty($flags['depthTruncated'])): ?>
    <div class="alert alert-info"><i class="bx bx-layer me-1"></i>Some branches exceed the current depth. Increase the depth slider to reveal further descendants.</div>
<?php endif; ?>

<?php if (!empty($flags['unreadable'])): ?>
    <div class="alert alert-danger"><i class="bx bx-lock me-1"></i>Unable to read: <?php echo htmlspecialchars(implode(', ', $flags['unreadable']), ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="file-tree">
    <?php if (empty($tree)): ?>
        <div class="text-muted text-center py-5">
            <i class="bx bx-search-alt-2 display-6 d-block mb-3"></i>
            <p class="mb-0">No entries matched the current filters. Try broadening the depth or clearing the search term.</p>
        </div>
    <?php else: ?>
        <?php tool_render_file_tree($tree, $searchTerm); ?>
    <?php endif; ?>
</section>

<?php
tool_render_footer();