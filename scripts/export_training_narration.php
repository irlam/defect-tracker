<?php
declare(strict_types=1);

require_once __DIR__ . '/../training/TrainingContent.php';

$lessonSlugs = [
    'create-a-defect',
    'floor-plan-location',
    'contractor-manager-lifecycle',
    'reports-exports',
    'projects-setup',
    'mobile-pwa',
    'admin-users',
];

$manifest = [];
foreach ($lessonSlugs as $slug) {
    $content = trainingEnhancedContent($slug);
    $scenes = is_array($content['demo'] ?? null) ? $content['demo'] : [];
    foreach ($scenes as $index => $scene) {
        $text = trim((string)($scene['voice'] ?? $scene['caption'] ?? ''));
        if ($text === '') {
            continue;
        }
        $manifest[] = [
            'lesson' => $slug,
            'scene' => $index + 1,
            'title' => (string)($scene['title'] ?? ''),
            'text' => $text,
            'output' => sprintf('assets/training/audio/%s/scene-%02d.mp3', $slug, $index + 1),
        ];
    }
}

echo json_encode(
    [
        'generator' => 'OpenBMB VoxCPM2',
        'voice' => 'Defect Guardian Academy narrator',
        'items' => $manifest,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
