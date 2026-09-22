<?php
// Initialize the sync functionality - include this at the start of your application
session_start();

// Get configuration
$SYNC_CONFIG = include __DIR__ . '/config.php';

// Register autoloader for sync classes
spl_autoload_register(function($class) {
    $prefix = 'Sync\\';
    $base_dir = __DIR__ . '/server/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize client-side sync functionality
function sync_init_client() {
    global $SYNC_CONFIG;
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'anonymous');
    echo '<script>
        window.SYNC_CONFIG = {
            apiEndpoint: "' . htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/sync/server/SyncEndpoint.php') . '",
            syncInterval: ' . $SYNC_CONFIG['sync_interval'] . ',
            userIdentifier: "' . htmlspecialchars($user) . '",
            currentTimestamp: "' . date('d-m-Y H:i:s') . '"
        };
    </script>';
    echo '<script src="/sync/client/db-manager.js"></script>';
    echo '<script src="/sync/client/sync-manager.js"></script>';
    echo '<script>
        if ("serviceWorker" in navigator) {
            window.addEventListener("load", () => {
                navigator.serviceWorker.register("/sync/client/service-worker.js")
                    .then(reg => console.log("Service worker registered: ", reg.scope))
                    .catch(err => console.error("Service worker registration failed: ", err));
            });
        }
    </script>';
}