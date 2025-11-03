<?php
/**
 * Defect Tracker System Analysis Page
 *
 * @version 1.3
 * @author irlam (Original Structure), Gemini (Enhancements, Comments, Chart Integration)
 * @last-modified 2025-04-12 12:29:57 UTC (Based on user prompt)
 *
 * --- File Description ---
 * This script generates a static analysis and overview page for the Defect Tracker system.
 * It provides insights into the system's architecture, core components, database schema highlights,
 * key features, and basic activity statistics (defect status distribution and a timeline).
 *
 * Key Sections:
 * 1.  Header: Displays logo, title, current UK date/time, and username.
 * 2.  Hero Section: Main title and system description.
 * 3.  System Overview: Shows counts of database components (currently illustrative placeholders). Uses a count-up animation.
 * 4.  System Architecture: Visual diagram of application layers. Uses scroll-triggered fade-in animation.
 * 5.  Core System Components: Describes functional modules (Project Mgt, Defect Workflow, User Mgt, Mobile). Uses scroll-triggered fade-in animation.
 * 6.  Database Schema Highlights: Simplified view of key tables. Uses scroll-triggered fade-in animation.
 * 7.  Key System Features: Highlights important functionalities. Uses scroll-triggered fade-in animation.
 * 8.  System Activity Analytics:
 *     - Fetches real defect status counts from the database and displays a Chart.js doughnut chart.
 *     - Displays a Chart.js line chart showing a defect resolution timeline (using hardcoded data).
 * 9.  Footer: Copyright and creator info.
 *
 * --- Data Handling ---
 * - Component counts are currently hardcoded placeholders.
 * - Defect status counts for the first chart are fetched live from the database.
 * - Defect timeline data for the second chart is currently hardcoded in the JavaScript.
 * - User/Time info is based on provided context.
 * - Time is formatted for UK display ('Europe/London').
 *
 * --- JavaScript Integration ---
 * - The logic previously in 'scripts.js' is now integrated directly into this file.
 * - Uses a custom `requestAnimationFrame` function for stat card count-up animation.
 * - Uses Chart.js for rendering both charts.
 * - Uses IntersectionObserver for scroll-triggered fade-in animations on various sections.
 * - Uses anime.js specifically for the connector animation in the architecture diagram.
 */

// --- PHP Setup and Configuration ---

// Error Reporting (Development settings - adjust for production)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors on screen
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/logs/error.log'); // Log file path

// --- Include Required Files ---
require_once '../config/database.php'; // Ensure this path is correct for DB connection

// Set default timezone to UK for date/time display formatting
date_default_timezone_set('Europe/London');

// Get current UTC time as string for display (falls back if not already set)
if (!isset($utcNowString) || !is_string($utcNowString) || trim($utcNowString) === '') {
    $utcNowString = gmdate('Y-m-d H:i:s');
}

// Set logged-in username (fallback for when auth/session is unavailable)
if (!isset($loggedInUsername) || !is_string($loggedInUsername) || trim($loggedInUsername) === '') {
    $loggedInUsername = 'System Admin'; // Default for demo/analysis page
}

// Create DateTime object from UTC string and convert to UK time for display
try {
    $dateTime = new DateTime($utcNowString, new DateTimeZone('UTC'));
    $dateTime->setTimezone(new DateTimeZone('Europe/London'));
    $displayDateTime = $dateTime->format('d/m/Y H:i:s'); // Format for UK display
} catch (Exception $e) {
    error_log("Error creating/formatting DateTime object: " . $e->getMessage());
    $displayDateTime = "Invalid Date"; // Fallback display
}

// Page Title
$pageTitle = "Defect Tracker System Analysis";

// --- Database Component Counts (Placeholder/Simulated) ---
function getComponentCounts() {
    return ['tables' => 28, 'triggers' => 8, 'views' => 1, 'relations' => 42, 'entities' => 10];
}
$componentCounts = getComponentCounts();

// --- Fetch Defect Status Data for Chart ---
$statusLabels = [];
$statusCounts = [];
$activityError = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    $statusQuery = "SELECT status, COUNT(*) as count
                    FROM defects
                    WHERE deleted_at IS NULL AND status IS NOT NULL AND status != ''
                    GROUP BY status
                    ORDER BY FIELD(status, 'open', 'pending', 'in_progress', 'completed', 'verified', 'accepted', 'rejected')";
    $stmt = $db->prepare($statusQuery);
    $stmt->execute();
    $statusCountsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($statusCountsData) {
        foreach ($statusCountsData as $row) {
            $statusLabels[] = ucwords(str_replace('_', ' ', $row['status']));
            $statusCounts[] = (int)$row['count'];
        }
    } else {
        $statusLabels = ['No Defects Found'];
        $statusCounts = [0];
    }
} catch (PDOException $e) {
    $activityError = "Database error fetching activity data.";
    error_log("System Analysis DB Error: " . $e->getMessage());
    $statusLabels = ['DB Error']; $statusCounts = [0];
} catch (Exception $e) {
    $activityError = "Error fetching activity data.";
    error_log("System Analysis General Error: " . $e->getMessage());
    $statusLabels = ['App Error']; $statusCounts = [0];
}

// Prepare data for JavaScript
$statusLabelsJSON = json_encode($statusLabels);
$statusCountsJSON = json_encode($statusCounts);

// --- HTML Output Starts ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <?php // --- CSS Includes --- ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css"> <?php // Main application theme ?>
    <link rel="stylesheet" href="styles.css"> <?php // IMPORTANT: Ensure this file exists and contains necessary styles for diagrams, cards etc. ?>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <?php // --- JavaScript Includes (Libraries) --- ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js" integrity="sha256-ErZ09KkZnzjpqcane4SCSU5cXODQqAfnIlca1cfUfbo=" crossorigin="anonymous"></script> <?php // Chart.js library ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js" integrity="sha256-XL2inqUJaslATFnHdJOi9GfQ60on8Wx1C2H8DYiN1xY=" crossorigin="anonymous"></script> <?php // Anime.js (Used for connector animation only now) ?>

    <?php // --- Internal Styles (Fallback & Scroll Animation CSS) --- ?>
    <style>
        body { font-family: 'Inter', sans-serif; line-height: 1.6; margin: 0; background-color: var(--background-color, #0b1220); color: var(--text-color, #e2e8f0); }
        header { background-color: var(--surface-color, #16213d); color: var(--text-color, white); padding: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.3); position: sticky; top: 0; z-index: 100; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .logo { display: flex; align-items: center; }
        .logo-icon { background-color: var(--primary-color, #2563eb); padding: 5px 10px; border-radius: 4px; margin-right: 10px; font-weight: bold; }
        .user-info { display: flex; align-items: center; gap: 1rem; font-size: 0.85em; }
        .user-info .bx { margin-right: 0.25rem; vertical-align: middle; }
        .user-avatar { background-color: var(--primary-color, #2563eb); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; text-transform: uppercase; }
        .hero-section { background: linear-gradient(135deg, var(--primary-color, #2563eb), var(--primary-light, #3b82f6)); color: white; text-align: center; padding: 3rem 1rem; }
        .hero-section h1 { margin-bottom: 0.5rem; font-weight: 700; font-size: 2rem; }
        .container { max-width: 1200px; margin: 1.5rem auto; padding: 0 1rem; }
        .section { background-color: var(--surface-color, #16213d); border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 5px rgba(0,0,0,0.2); }
        h2 { border-bottom: 2px solid var(--primary-color, #2563eb); padding-bottom: 0.5rem; margin-bottom: 1.5rem; color: var(--text-color, #e2e8f0); font-weight: 600; font-size: 1.5rem; }
        h3 { color: var(--text-color, #e2e8f0); margin-top: 1.5rem; margin-bottom: 1rem; font-weight: 500; font-size: 1.2rem; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
        .card { background-color: var(--surface-muted, #1a2742); padding: 1rem; border-radius: 6px; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 3px 6px rgba(37, 99, 235, 0.3); }
        .stat-card .card-value { font-size: 2rem; font-weight: 700; color: var(--primary-light, #3b82f6); margin-bottom: 0.3rem; min-height: 2.4rem; /* Prevent jump during countup */ }
        .card-label { font-size: 0.8rem; color: var(--text-muted-color, #94a3b8); font-weight: 500; }
        .diagram-container { overflow-x: auto; padding-bottom: 1rem; }
        .architecture-diagram, .workflow-diagram, .roles-diagram, .sync-diagram { text-align: center; margin-bottom: 1rem; /* Assumes styles.css */ }
        .component-section { margin-bottom: 2rem; }
        .feature-list { list-style: none; padding-left: 0; }
        .feature-list li { margin-bottom: 0.6rem; padding-left: 1.8rem; position: relative; }
        .feature-list li::before { content: '✓'; color: var(--success-color, #22c55e); position: absolute; left: 0; font-weight: bold; }
        .schema-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; }
        .schema-table { border: 1px solid var(--border-color, rgba(148, 163, 184, 0.25)); border-radius: 4px; overflow: hidden; margin-bottom: 1rem; font-size: 0.8em; background-color: var(--surface-muted, #1a2742); }
        .table-header { background-color: var(--surface-hover, #1f2a44); padding: 6px 10px; font-weight: bold; border-bottom: 1px solid var(--border-color, rgba(148, 163, 184, 0.25)); }
        .table-row { padding: 5px 10px; border-bottom: 1px solid var(--border-color, rgba(148, 163, 184, 0.15)); }
        .table-row:last-child { border-bottom: none; }
        .pk, .fk, .field { font-family: monospace; }
        .pk { color: var(--danger-color, #f87171); font-weight: bold; } .fk { color: var(--primary-light, #3b82f6); }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        .feature-card { background-color: var(--surface-muted, #1a2742); border: 1px solid var(--border-color, rgba(148, 163, 184, 0.25)); border-radius: 8px; padding: 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; }
        .feature-icon { margin-bottom: 1rem; color: var(--primary-color, #2563eb); }
        .feature-icon .bx { font-size: 2.5rem; }
        .feature-card h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-color, #e2e8f0); }
        .feature-card p { font-size: 0.85rem; color: var(--text-muted-color, #94a3b8); flex-grow: 1; }
        .charts-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .chart-wrapper { background-color: var(--surface-muted, #1a2742); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-color, rgba(148, 163, 184, 0.25)); min-height: 350px; display: flex; flex-direction: column; }
        .chart-wrapper h3 { text-align: center; margin-bottom: 1rem; font-size: 1.1rem; color: var(--text-color, #e2e8f0); }
        #defectStatusChart, #defectTimelineChart { max-height: 300px; width: 100% !important; }
        footer { background-color: var(--surface-color, #16213d); color: var(--text-muted-color, #94a3b8); text-align: center; padding: 1.5rem 0; margin-top: 2rem; font-size: 0.9em; }
        .text-danger { color: var(--danger-color, #f87171) !important; }

        /* CSS for Scroll Animations (moved from JS) */
        .fadeIn {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        /* Initial state for elements to be animated */
        .diagram-component, .feature-card, .schema-table, .workflow-diagram, .role-card {
             opacity: 0;
             transform: translateY(20px);
             transition: opacity 0.5s ease, transform 0.5s ease;
        }
        /* Basic connector style if styles.css is missing */
        .connector { height: 0; width: 2px; background-color: #ccc; margin: 0 auto 1rem; /* Height animated by anime.js */ }

    </style>
</head>
<body>
    <?php // --- Header Section --- ?>
    <header>
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">DT</div>
                <div class="logo-text">Defect Tracker</div>
            </div>
            <div class="user-info">
                <span class="datetime" title="Current UK Time (Source UTC: <?php echo $utcNowString; ?>)">
                    <i class='bx bx-calendar'></i> <?php echo $displayDateTime; ?> (UK)
                </span>
                <span class="username">
                    <i class='bx bx-user'></i> <?php echo htmlspecialchars($loggedInUsername); ?>
                </span>
                <div class="user-avatar" title="<?php echo htmlspecialchars($loggedInUsername); ?>">
                    <?php echo strtoupper(substr($loggedInUsername, 0, 1)); ?>
                </div>
            </div>
        </div>
    </header>

    <?php // --- Hero Section --- ?>
    <div class="hero-section">
        <h1>Defect Tracker System Analysis</h1>
        <p>An overview of the system architecture, database structure, and key functionalities.</p>
    </div>

    <?php // --- Main Content Container --- ?>
    <main class="container">

        <?php // --- System Overview Section --- ?>
        <section class="section" id="overview">
            <h2>System Overview</h2>
            <p>Illustrative counts of core database schema components.</p>
            <div class="card-grid">
                <div class="card stat-card" data-value="<?php echo $componentCounts['tables']; ?>"><div class="card-value">0</div><div class="card-label">DB Tables</div></div>
                <div class="card stat-card" data-value="<?php echo $componentCounts['triggers']; ?>"><div class="card-value">0</div><div class="card-label">Triggers</div></div>
                <div class="card stat-card" data-value="<?php echo $componentCounts['views']; ?>"><div class="card-value">0</div><div class="card-label">Views</div></div>
                <div class="card stat-card" data-value="<?php echo $componentCounts['relations']; ?>"><div class="card-value">0</div><div class="card-label">Relations (FKs)</div></div>
                <div class="card stat-card" data-value="<?php echo $componentCounts['entities']; ?>"><div class="card-value">0</div><div class="card-label">Core Entities</div></div>
            </div>
            <small class="text-muted d-block mt-3">*Note: Component counts are illustrative placeholders.</small>
        </section>

        <?php // --- System Architecture Section --- ?>
        <section class="section" id="architecture">
            <h2>System Architecture</h2>
            <p>The system utilizes a multi-tier architecture for separation of concerns.</p>
            <div class="diagram-container">
                <div class="architecture-diagram">
                     <div class="diagram-component level-1"><h3>UI Layer</h3><small>Web Interface, Mobile App, Reporting</small></div>
                     <div class="connector"></div> <?php // Connector line (height animated by JS) ?>
                     <div class="diagram-component level-2"><h3>Business Logic Layer</h3><small>APIs, Auth, Notifications, Sync Logic</small></div>
                     <div class="connector"></div> <?php // Connector line ?>
                     <div class="diagram-component level-3"><h3>Database Layer</h3><small>MySQL/MariaDB: Entities, Logs, Users, Sync Tables</small></div>
                </div>
            </div>
        </section>

        <?php // --- Core System Components Section --- ?>
        <section class="section" id="core-components">
            <h2>Core System Components</h2>
            <p>Main functional modules of the Defect Tracker.</p>
            <div class="component-section">
                <h3><i class='bx bx-briefcase-alt-2' ></i> Project Management</h3>
                <ul class="feature-list">
                    <li>Organize work into distinct projects.</li>
                    <li>Associate versioned floor plans with projects.</li>
                    <li>Track building levels and link floor plan images.</li>
                    <li>Manage floor plan statuses (Draft, Active, Archived).</li>
                    <li>Pinpoint defects accurately using X/Y coordinates.</li>
                </ul>
            </div>
            <div class="component-section">
                <h3><i class='bx bx-bug' ></i> Defect Tracking Workflow</h3>
                <div class="workflow-diagram" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 5px; font-size: 0.8em; margin: 1rem 0;">
                    <span class="badge bg-warning text-dark">Open</span> →
                    <span class="badge bg-primary">In Progress</span> →
                    <span class="badge bg-info text-dark">Pending</span> →
                    <span class="badge bg-secondary">Completed</span> →
                    <span class="badge bg-primary">Verified</span> →
                    (<span class="badge bg-success">Accepted</span> / <span class="badge bg-danger">Rejected</span>)
                </div>
                <ul class="feature-list">
                    <li>Attach multiple images to document defects visually.</li>
                    <li>Facilitate communication via defect-specific comments.</li>
                    <li>Assign defects to responsible contractors or users.</li>
                    <li>Maintain a complete history of status changes and actions.</li>
                    <li>Define defect priorities (Critical, High, Medium, Low).</li>
                </ul>
            </div>
            <div class="component-section">
                 <h3><i class='bx bx-group' ></i> User Management & Roles</h3>
                <div class="roles-diagram" style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center;">
                    <div class="role-card" style="border: 1px solid #ddd; padding: 1rem; border-radius: 4px; text-align: center; min-width: 150px;">Admin</div>
                    <div class="role-card" style="border: 1px solid #ddd; padding: 1rem; border-radius: 4px; text-align: center; min-width: 150px;">Manager</div>
                    <div class="role-card" style="border: 1px solid #ddd; padding: 1rem; border-radius: 4px; text-align: center; min-width: 150px;">Contractor</div>
                    <div class="role-card" style="border: 1px solid #ddd; padding: 1rem; border-radius: 4px; text-align: center; min-width: 150px;">Inspector/Client</div>
                </div>
                 <ul class="feature-list mt-3">
                     <li>Define distinct user roles with specific permissions.</li>
                     <li>Manage user accounts (activation/deactivation).</li>
                     <li>Associate users with contractors.</li>
                     <li>Secure login and session handling.</li>
                 </ul>
            </div>
            <div class="component-section">
                <h3><i class='bx bx-mobile-alt' ></i> Mobile/Offline Capabilities</h3>
                <div class="sync-diagram" style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin: 1rem 0;">
                    <div style="border: 1px solid #ccc; padding: 0.5rem;">Mobile Device</div>
                    <div style="text-align: center;">↑ Sync ↓</div>
                    <div style="border: 1px solid #ccc; padding: 0.5rem;">Server Database</div>
                </div>
                <ul class="feature-list">
                    <li>Enable field use without constant internet access.</li>
                    <li>Robust synchronization mechanism for data consistency.</li>
                    <li>Device tracking and management features.</li>
                    <li>Conflict resolution logic for sync operations.</li>
                </ul>
            </div>
        </section>

        <?php // --- Database Schema Highlights Section --- ?>
        <section class="section" id="database-schema">
            <h2>Database Schema Highlights</h2>
            <p>Simplified view of key tables and relationships.</p>
            <div class="schema-container">
                <div class="schema-section"><h3>Core Tables</h3><!-- Table examples --></div>
                <div class="schema-section"><h3>Management Tables</h3><!-- Table examples --></div>
                <div class="schema-section"><h3>Supporting Tables</h3><!-- Table examples --></div>
                <?php // Simplified table display from previous version would go here ?>
                 <div class="schema-section">
                    <h3>Core Tables</h3>
                    <div class="schema-table"><div class="table-header"><div class="table-name">projects</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="field">name</span></div></div></div>
                    <div class="schema-table"><div class="table-header"><div class="table-name">floor_plans</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="fk">project_id</span> (FK)</div></div></div>
                    <div class="schema-table"><div class="table-header"><div class="table-name">defects</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="fk">project_id</span> (FK)</div><div class="table-row"><span class="fk">floor_plan_id</span> (FK)</div><div class="table-row"><span class="field">status</span></div></div></div>
                </div>
                <div class="schema-section">
                    <h3>Management Tables</h3>
                    <div class="schema-table"><div class="table-header"><div class="table-name">users</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="field">username</span></div><div class="table-row"><span class="fk">role_id</span> (FK)</div></div></div>
                    <div class="schema-table"><div class="table-header"><div class="table-name">contractors</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="field">company_name</span></div></div></div>
                    <div class="schema-table"><div class="table-header"><div class="table-name">roles</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="field">name</span></div></div></div>
                </div>
                <div class="schema-section">
                    <h3>Supporting Tables</h3>
                    <div class="schema-table"><div class="table-header"><div class="table-name">defect_images</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="fk">defect_id</span> (FK)</div></div></div>
                    <div class="schema-table"><div class="table-header"><div class="table-name">defect_comments</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="fk">defect_id</span> (FK)</div><div class="table-row"><span class="fk">user_id</span> (FK)</div></div></div>
                    <div class="schema-table"><div class="table-header"><div class="table-name">activity_logs</div></div><div class="table-rows"><div class="table-row"><span class="pk">id</span> (PK)</div><div class="table-row"><span class="fk">user_id</span> (FK)</div><div class="table-row"><span class="field">action</span></div></div></div>
                </div>
            </div>
        </section>

        <?php // --- Key System Features Section --- ?>
        <section class="section" id="key-features">
            <h2>Key System Features</h2>
             <div class="features-grid">
                <div class="feature-card"> <div class="feature-icon"><i class='bx bx-check-shield bx-lg'></i></div> <h3>Formal Acceptance</h3> <p>Structured defect acceptance workflow with tracking and verification.</p> </div>
                <div class="feature-card"> <div class="feature-icon"><i class='bx bx-history bx-lg'></i></div> <h3>Audit Trail</h3> <p>Comprehensive logging of actions, activities, and changes with user attribution.</p> </div>
                <div class="feature-card"> <div class="feature-icon"><i class='bx bxs-hard-hat bx-lg'></i></div> <h3>Contractor Management</h3> <p>Detailed contractor profiles including compliance tracking.</p> </div>
                <div class="feature-card"> <div class="feature-icon"><i class='bx bxs-bell-ring bx-lg'></i></div> <h3>Notification System</h3> <p>Real-time alerts for status changes, assignments, and comments.</p> </div>
                <div class="feature-card"> <div class="feature-icon"><i class='bx bx-sync bx-lg'></i></div> <h3>Offline Functionality</h3> <p>Robust synchronization for field use with conflict resolution.</p> </div>
                <div class="feature-card"> <div class="feature-icon"><i class='bx bx-data bx-lg'></i></div> <h3>Data Integrity</h3> <p>Use of triggers and constraints to maintain data consistency.</p> </div>
            </div>
        </section>

        <?php // --- System Activity Analytics Section --- ?>
        <section class="section" id="activity-stats">
            <h2>System Activity Analytics</h2>
            <?php if ($activityError): ?>
                <div class="alert alert-danger" role="alert">
                   <i class='bx bx-error-circle'></i> Could not load activity data: <?php echo htmlspecialchars($activityError); ?>
                </div>
            <?php else: ?>
                <p>Overview of current defect distribution and resolution trends.</p>
                <div class="charts-container">
                    <?php // Wrapper for the Status Distribution Chart ?>
                    <div class="chart-wrapper">
                        <h3>Defect Status Distribution</h3>
                        <canvas id="defectStatusChart"></canvas> <?php // Canvas for the status chart ?>
                    </div>
                    <?php // Wrapper for the Timeline Chart ?>
                    <div class="chart-wrapper">
                        <h3>Defect Resolution Timeline (Demo)</h3>
                        <canvas id="defectTimelineChart"></canvas> <?php // Canvas for the timeline chart ?>
                        <small class="text-muted text-center mt-2">*Timeline data is currently illustrative.</small>
                    </div>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <?php // --- Footer Section --- ?>
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Defect Tracker System | Created by <?php echo htmlspecialchars($loggedInUsername); ?></p>
        </div>
    </footer>

    <?php // --- Integrated JavaScript --- ?>
    <script>
        /**
         * Integrated JavaScript for System Analysis Page
         * Originally from 'scripts.js', now embedded here.
         * Handles:
         * - Count-up animation for statistic cards.
         * - Initialization of the Defect Resolution Timeline chart (using HARDCODED data).
         * - Scroll-triggered fade-in animations for various sections.
         * - Animation for architecture diagram connectors using anime.js.
         */
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Analysis page DOM loaded, initializing integrated JS...");

            // --- 1. Animate Stat Cards ---
            // Find all cards with the 'stat-card' class and 'data-value' attribute
            const statCards = document.querySelectorAll('.stat-card[data-value]');
            // Call the function to animate them with a staggered delay
            animateStatCards(statCards);

            // --- 2. Initialize Charts (Timeline Chart ONLY) ---
            // The Status chart is initialized in a separate script block using PHP data
            initializeTimelineChart(); // Call function to set up the timeline chart

            // --- 3. Add Scroll Animations ---
            // Set up IntersectionObserver to fade in elements as they scroll into view
            addScrollAnimations();

        }); // End DOMContentLoaded

        /**
         * Animates the count-up effect for statistic cards.
         * @param {NodeListOf<Element>} cards - A list of card elements to animate.
         */
        function animateStatCards(cards) {
            console.log(`Animating ${cards.length} stat cards...`);
            cards.forEach((card, index) => {
                const targetValue = parseInt(card.getAttribute('data-value'), 10);
                const valueDisplay = card.querySelector('.card-value');

                if (!isNaN(targetValue) && valueDisplay) {
                    // Stagger the start of each animation using setTimeout
                    setTimeout(() => {
                        animateCountUp(valueDisplay, targetValue);
                    }, index * 150); // 150ms delay between each card animation
                } else if (valueDisplay) {
                    // If value is not a number, display it directly
                     valueDisplay.textContent = card.getAttribute('data-value') || 'N/A';
                     console.warn("Stat card found without a valid number in data-value:", card);
                }
            });
        }

        /**
         * Performs the count-up animation using requestAnimationFrame.
         * @param {Element} element - The HTML element displaying the number.
         * @param {number} target - The final number to count up to.
         */
        function animateCountUp(element, target) {
            let start = 0;
            const duration = 1500; // Animation duration in ms
            const startTime = performance.now(); // Get start time

            function updateCount(currentTime) {
                const elapsedTime = currentTime - startTime;
                // Stop animation if duration is exceeded
                if (elapsedTime > duration) {
                    element.textContent = target; // Ensure final value is exact
                    return;
                }

                // Calculate progress and current value
                const progress = elapsedTime / duration;
                // Ease-out effect can be added here if desired, e.g., progress = 1 - Math.pow(1 - progress, 3);
                const currentValue = Math.floor(progress * target);
                element.textContent = currentValue; // Update display

                // Continue the animation on the next frame
                requestAnimationFrame(updateCount);
            }

            // Start the animation loop
            requestAnimationFrame(updateCount);
        }

        /**
         * Initializes ONLY the Defect Resolution Timeline chart.
         * Uses HARDCODED data for demonstration.
         * The Defect Status chart is initialized elsewhere using PHP data.
         */
        function initializeTimelineChart() {
            const timelineCtx = document.getElementById('defectTimelineChart');
            if (!timelineCtx) {
                console.warn("Canvas element '#defectTimelineChart' not found. Timeline chart not rendered.");
                return; // Exit if canvas is missing
            }

            console.log("Initializing Defect Timeline Chart (with hardcoded data)...");
            try {
                const timelineChart = new Chart(timelineCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        // Hardcoded labels and data for demonstration
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [
                            {
                                label: 'Reported',
                                data: [42, 55, 48, 58, 60, 53], // Example data
                                borderColor: '#3b82f6', // Blue line
                                backgroundColor: 'rgba(59, 130, 246, 0.1)', // Light blue fill
                                fill: true,
                                tension: 0.4 // Smooth curves
                            },
                            {
                                label: 'Resolved',
                                data: [30, 45, 40, 50, 58, 48], // Example data
                                borderColor: '#10b981', // Green line
                                backgroundColor: 'rgba(16, 185, 129, 0.1)', // Light green fill
                                fill: true,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true } // Start Y-axis at 0
                        },
                        plugins: {
                            legend: { position: 'top' } // Legend position
                        }
                    }
                });
                 console.log("Defect Timeline Chart Initialized Successfully.");
            } catch (error) {
                 console.error("Error initializing Timeline Chart:", error);
                 const chartWrapper = timelineCtx.closest('.chart-wrapper');
                 if (chartWrapper) {
                     chartWrapper.innerHTML += '<p class="text-danger">Could not render timeline chart.</p>';
                 }
            }

            // Adjust canvas height (optional, could be done via CSS)
            // timelineCtx.height = 300; // Set fixed height
        }

        /**
         * Sets up IntersectionObserver to trigger fade-in animations on scroll.
         * Also animates the architecture diagram connectors using anime.js.
         */
        function addScrollAnimations() {
            console.log("Setting up scroll animations...");
            // Select all elements intended for scroll animation
            const elementsToAnimate = [
                ...document.querySelectorAll('.diagram-component'),
                ...document.querySelectorAll('.feature-card'),
                ...document.querySelectorAll('.schema-table'),
                ...document.querySelectorAll('.workflow-diagram'),
                ...document.querySelectorAll('.role-card')
                // Add other selectors if needed
            ];

            if (elementsToAnimate.length === 0) {
                console.warn("No elements found for scroll animation.");
                return;
            }

            // Create an Intersection Observer instance
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    // If the element is intersecting (visible in viewport)
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fadeIn'); // Add the fadeIn class
                        observer.unobserve(entry.target); // Stop observing once animated
                    }
                });
            }, { threshold: 0.1 }); // Trigger when 10% of the element is visible

            // Observe each selected element
            elementsToAnimate.forEach(el => {
                // Initial styles are now set in CSS (opacity: 0, transform: translateY(20px))
                observer.observe(el);
            });

            // Animate architecture connectors using anime.js (kept from original JS)
            try {
                anime({
                    targets: '.connector', // Select elements with class 'connector'
                    height: [0, '2rem'], // Animate height from 0 to 2rem (adjust value as needed)
                    duration: 1000,      // Animation duration
                    delay: 700,          // Delay before starting
                    easing: 'easeOutQuad' // Easing function
                });
                console.log("Connector animation initiated.");
            } catch (error) {
                 console.error("Error initiating connector animation (anime.js):", error);
            }
        }

    </script>

    <?php // Separate script block JUST for initializing the status chart with PHP data ?>
    <script>
        /**
         * Initializes the Defect Status Distribution chart using data fetched by PHP.
         * This is kept separate from the integrated script to clearly use the dynamic data
         * and avoid conflicts with the hardcoded chart data in the other functions.
         */
        document.addEventListener('DOMContentLoaded', function() {
            const statusCtx = document.getElementById('defectStatusChart');
            const activityErrorPHP = <?php echo json_encode($activityError); ?>;

            // Only initialize if canvas exists and PHP didn't report an error
            if (statusCtx && !activityErrorPHP) {
                try {
                    const statusChartLabels = <?php echo $statusLabelsJSON; ?>;
                    const statusChartData = <?php echo $statusCountsJSON; ?>;

                    console.log("Initializing Defect Status Chart (with PHP data)...");
                    console.log("Status Chart Labels:", statusChartLabels);
                    console.log("Status Chart Data:", statusChartData);

                    const statusChartColors = [
                        '#e74c3c', '#f39c12', '#3498db', '#bdc3c7', '#9b59b6', '#2ecc71', '#7f8c8d', '#1abc9c', '#f1c40f'
                    ];

                    new Chart(statusCtx.getContext('2d'), {
                        type: 'doughnut', // Changed to doughnut as it looked better in previous example
                        data: {
                            labels: statusChartLabels,
                            datasets: [{
                                label: ' Defect Count',
                                data: statusChartData,
                                backgroundColor: statusChartColors.slice(0, statusChartLabels.length),
                                borderColor: '#ffffff',
                                borderWidth: 2,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'right', // Legend on the right
                                    labels: { padding: 15, boxWidth: 12 }
                                },
                                title: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) { label += ': '; }
                                            const value = context.parsed;
                                            const total = context.dataset.data.reduce((a, b) => a + (Number.isFinite(b) ? b : 0), 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                            label += value + ' (' + percentage + ')';
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                     console.log("Defect Status Chart (PHP data) Initialized Successfully.");

                } catch (error) {
                    console.error("Error initializing Status Chart (PHP data):", error);
                    const chartWrapper = statusCtx.closest('.chart-wrapper');
                    if (chartWrapper) {
                         chartWrapper.innerHTML += '<p class="text-danger">Could not render status chart.</p>';
                    }
                }
            } else if (statusCtx && activityErrorPHP) {
                 console.warn("Status Chart not rendered due to PHP data fetching error.");
                 const chartWrapper = statusCtx.closest('.chart-wrapper');
                 if (chartWrapper) {
                      chartWrapper.innerHTML += `<p class="text-danger">Chart data error: ${activityErrorPHP}</p>`;
                 }
            } else if (!statusCtx) {
                 console.warn("Canvas element '#defectStatusChart' not found.");
            }
        }); // End DOMContentLoaded for Status Chart
    </script>

    <?php // REMOVED: <script src="scripts.js"></script> as the content is integrated above ?>
</body>
</html>