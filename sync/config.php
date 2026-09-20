<?php
// Configuration for sync functionality
require_once __DIR__ . '/../config/env.php';

return [
    'db_host' => Environment::get('DB_HOST', 'localhost'),
    'db_name' => Environment::get('DB_NAME', ''),
    'db_user' => Environment::get('DB_USERNAME', ''),
    'db_pass' => Environment::get('DB_PASSWORD', ''),
    'sync_table' => 'sync_queue',
    'sync_interval' => 60,  // seconds between sync attempts
    'sync_batch_size' => 10, // number of items to process in one batch
    'conflict_strategy' => 'server_wins', // Options: server_wins, client_wins, timestamp_wins, prompt_user
    'max_queue_age' => 30, // days to keep sync records
    'debug_mode' => false,
    'upload_dir' => '/uploads/images/', // Directory for uploaded files
    'max_upload_size' => 10 * 1024 * 1024, // 10MB max upload size
    'allowed_image_types' => ['image/jpeg', 'image/png', 'image/gif']
];
