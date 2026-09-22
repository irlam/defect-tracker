<?php
// Set page title and other variables
$pageTitle = "Defect Tracker Roles & Permissions";

// Set default timezone to UK for date/time display formatting
date_default_timezone_set('Europe/London');

// Initialize date and time variables
$currentDate = date('d/m/Y');
$currentTime = date('H:i:s');

// Set logged-in username (fallback for when auth/session is unavailable)
$username = 'System Admin'; // Default for presentation page

// Define roles and their permissions
$roles = [
    'admin' => [
        'title' => 'System Administrator',
        'description' => 'Full system access with ability to configure system settings and manage users',
        'color' => '#8b5cf6', // Purple
        'icon' => 'shield',
        'permissions' => [
            'user_management' => [
                'Create and manage all user accounts',
                'Assign user roles and permissions',
                'Reset passwords',
                'Disable/enable user accounts'
            ],
            'system_settings' => [
                'Configure system settings',
                'Manage database settings',
                'View system logs',
                'Perform system maintenance'
            ],
            'data_management' => [
                'Export and import data',
                'Manage data backups',
                'Access to all system data',
                'Purge old records'
            ],
            'access_control' => [
                'Create and modify roles',
                'Define permissions',
                'Set access restrictions',
                'Override locks and restrictions'
            ]
        ],
        'count' => 3 // Mock data - number of users with this role
    ],
    'manager' => [
        'title' => 'Project Manager',
        'description' => 'Responsible for overseeing projects, managing defects, and coordinating with contractors',
        'color' => '#2563eb', // Blue
        'icon' => 'chart-line',
        'permissions' => [
            'project_management' => [
                'View all projects',
                'Create new projects',
                'Edit project details',
                'Assign users to projects'
            ],
            'defect_management' => [
                'Create defects',
                'Edit defect details',
                'Assign defects to contractors',
                'Review completed work',
                'Accept/reject defect resolutions'
            ],
            'floor_plan_management' => [
                'Upload and manage floor plans',
                'View defect locations',
                'Generate reports'
            ],
            'contractor_management' => [
                'View contractor details',
                'Assign contractors to defects',
                'Rate contractor performance'
            ],
            'reporting' => [
                'Generate defect reports',
                'Export data',
                'View analytics dashboard'
            ]
        ],
        'count' => 8 // Mock data
    ],
    'contractor' => [
        'title' => 'Contractor',
        'description' => 'Responsible for resolving assigned defects and updating their status',
        'color' => '#059669', // Green
        'icon' => 'tools',
        'permissions' => [
            'defect_access' => [
                'View assigned defects',
                'Update defect status',
                'Add resolution comments',
                'Upload completion evidence'
            ],
            'communication' => [
                'Add comments to defects',
                'Request clarification',
                'Notify of completion'
            ],
            'profile_management' => [
                'Update company details',
                'Manage contact information',
                'View performance metrics'
            ],
            'mobile_access' => [
                'Use mobile application',
                'Work offline with sync capability',
                'View floor plans'
            ]
        ],
        'count' => 42 // Mock data
    ],
    'inspector' => [
        'title' => 'Quality Inspector',
        'description' => 'Responsible for reporting defects and verifying completed work',
        'color' => '#d97706', // Amber
        'icon' => 'search',
        'permissions' => [
            'defect_reporting' => [
                'Create new defects',
                'Attach photos to defects',
                'Mark locations on floor plans',
                'Set defect priorities'
            ],
            'verification' => [
                'Verify completed defects',
                'Reject inadequate work',
                'Provide rejection reasons',
                'Request rework'
            ],
            'documentation' => [
                'Export defect reports',
                'Generate inspection certificates',
                'Document quality standards'
            ],
            'floor_plan_access' => [
                'View all floor plans',
                'Track defect locations',
                'Generate location reports'
            ]
        ],
        'count' => 15 // Mock data
    ],
    'client' => [
        'title' => 'Client',
        'description' => 'Project stakeholders who can view project status and approve completed work',
        'color' => '#0284c7', // Sky
        'icon' => 'building',
        'permissions' => [
            'project_viewing' => [
                'View project summary',
                'Track project progress',
                'Access project timelines',
                'View project reports'
            ],
            'defect_acceptance' => [
                'View completed defects',
                'Accept resolved defects',
                'Request additional work',
                'Provide feedback'
            ],
            'reporting' => [
                'Access client dashboards',
                'View project statistics',
                'Download project reports'
            ],
            'communication' => [
                'Add comments to defects',
                'Request status updates',
                'Communicate with project managers'
            ]
        ],
        'count' => 23 // Mock data
    ],
    'viewer' => [
        'title' => 'Viewer',
        'description' => 'Limited read-only access to view defect information',
        'color' => '#6b7280', // Gray
        'icon' => 'eye',
        'permissions' => [
            'defect_viewing' => [
                'View defect list',
                'View defect details',
                'View defect images',
                'View defect status'
            ],
            'reports' => [
                'View standard reports',
                'View defect statistics',
                'Export basic reports'
            ],
            'limited_access' => [
                'No editing capabilities',
                'No assignment permissions',
                'No creation privileges',
                'Read-only access to data'
            ]
        ],
        'count' => 17 // Mock data
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --success: #16a34a;
            --background: #f9fafb;
            --card-bg: #ffffff;
            --text: #1f2937;
            --text-light: #6b7280;
            --border: #e5e7eb;
            --border-dark: #d1d5db;
            
            --admin-color: #8b5cf6;
            --manager-color: #2563eb;
            --contractor-color: #059669;
            --inspector-color: #d97706;
            --client-color: #0284c7;
            --viewer-color: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Header styles */
        header {
            background-color: var(--card-bg);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            background-color: var(--primary);
            color: white;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .datetime, .username {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .user-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        /* Hero section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            text-align: center;
            padding: 4rem 1rem;
        }

        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .hero-section p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* Roles section */
        .section {
            margin: 3.5rem 0;
        }

        .section h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }

        .section h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 3rem;
            height: 3px;
            background-color: var(--primary);
        }

        .roles-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .roles-stats {
            display: flex;
            gap: 1.5rem;
        }

        .role-stat {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 1rem;
            min-width: 120px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .role-stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .role-stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        /* Role card styles */
        .roles-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .role-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .role-card.expanded {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .role-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            cursor: pointer;
            border-left: 0px solid transparent;
            transition: border-left-width 0.3s ease;
        }

        .role-header:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .role-icon-wrapper {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .role-info {
            flex: 1;
        }

        .role-title {
            font-size: 1.35rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .role-description {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .role-toggle {
            color: var(--text-light);
            font-size: 1.25rem;
            transition: transform 0.3s ease;
        }

        .role-card.expanded .role-toggle {
            transform: rotate(180deg);
        }

        .role-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }

        .role-card.expanded .role-content {
            max-height: 2000px;
        }

        .permissions-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 0 1.5rem 2rem;
        }

        .permission-group {
            background-color: var(--background);
            border-radius: 8px;
            padding: 1.25rem;
        }

        .permission-group h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            font-weight: 500;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .permission-list {
            list-style-type: none;
        }

        .permission-list li {
            position: relative;
            padding: 0.5rem 0 0.5rem 1.75rem;
            border-bottom: 1px solid var(--border);
        }

        .permission-list li:last-child {
            border-bottom: none;
        }

        .permission-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: 700;
        }

        .roles-footer {
            text-align: center;
            margin-top: 3rem;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .roles-footer p {
            max-width: 700px;
            margin: 0 auto;
        }

        .role-users {
            display: inline-block;
            background-color: rgba(0, 0, 0, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        /* Admin specific styling */
        .role-card[data-role="admin"] .role-header {
            border-left: 6px solid var(--admin-color);
        }

        .role-card[data-role="admin"] .role-icon-wrapper {
            background-color: var(--admin-color);
        }

        .role-card[data-role="admin"] .permission-group h3 {
            color: var(--admin-color);
        }

        /* Manager specific styling */
        .role-card[data-role="manager"] .role-header {
            border-left: 6px solid var(--manager-color);
        }

        .role-card[data-role="manager"] .role-icon-wrapper {
            background-color: var(--manager-color);
        }

        .role-card[data-role="manager"] .permission-group h3 {
            color: var(--manager-color);
        }

        /* Contractor specific styling */
        .role-card[data-role="contractor"] .role-header {
            border-left: 6px solid var(--contractor-color);
        }

        .role-card[data-role="contractor"] .role-icon-wrapper {
            background-color: var(--contractor-color);
        }

        .role-card[data-role="contractor"] .permission-group h3 {
            color: var(--contractor-color);
        }

        /* Inspector specific styling */
        .role-card[data-role="inspector"] .role-header {
            border-left: 6px solid var(--inspector-color);
        }

        .role-card[data-role="inspector"] .role-icon-wrapper {
            background-color: var(--inspector-color);
        }

        .role-card[data-role="inspector"] .permission-group h3 {
            color: var(--inspector-color);
        }

        /* Client specific styling */
        .role-card[data-role="client"] .role-header {
            border-left: 6px solid var(--client-color);
        }

        .role-card[data-role="client"] .role-icon-wrapper {
            background-color: var(--client-color);
        }

        .role-card[data-role="client"] .permission-group h3 {
            color: var(--client-color);
        }

        /* Viewer specific styling */
        .role-card[data-role="viewer"] .role-header {
            border-left: 6px solid var(--viewer-color);
        }

        .role-card[data-role="viewer"] .role-icon-wrapper {
            background-color: var(--viewer-color);
        }

        .role-card[data-role="viewer"] .permission-group h3 {
            color: var(--viewer-color);
        }

        /* Footer */
        footer {
            background-color: var(--primary-dark);
            color: white;
            padding: 1.5rem 0;
            margin-top: 4rem;
            text-align: center;
        }

        footer p {
            opacity: 0.9;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-content {
                padding: 1rem;
            }
            
            .datetime {
                display: none;
            }
            
            .hero-section {
                padding: 3rem 1rem;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .section h2 {
                font-size: 1.5rem;
            }
            
            .roles-stats {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .permissions-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">DT</div>
                <div class="logo-text">Defect Tracker</div>
            </div>
            <div class="user-info">
                <span class="datetime"><?php echo $currentDate; ?> <?php echo $currentTime; ?></span>
                <span class="username"><?php echo $username; ?></span>
                <div class="user-avatar"><?php echo substr($username, 0, 1); ?></div>
            </div>
        </div>
    </header>

    <div class="hero-section">
        <h1>System Roles & Permissions</h1>
        <p>Comprehensive overview of user roles and their access levels in the Defect Tracker system</p>
    </div>

    <main class="container">
        <section class="section" id="roles-section">
            <div class="roles-header">
                <h2>User Roles</h2>
                <div class="roles-stats">
                    <div class="role-stat">
                        <div class="role-stat-value"><?php echo count($roles); ?></div>
                        <div class="role-stat-label">Total Roles</div>
                    </div>
                    <div class="role-stat">
                        <div class="role-stat-value"><?php echo array_sum(array_column($roles, 'count')); ?></div>
                        <div class="role-stat-label">Active Users</div>
                    </div>
                </div>
            </div>

            <div class="roles-grid">
                <?php foreach($roles as $roleKey => $role): ?>
                <div class="role-card" data-role="<?php echo $roleKey; ?>">
                    <div class="role-header" onclick="toggleRole('<?php echo $roleKey; ?>')">
                        <div class="role-icon-wrapper" style="background-color: <?php echo $role['color']; ?>">
                            <i class="fas fa-<?php echo $role['icon']; ?>"></i>
                        </div>
                        <div class="role-info">
                            <div class="role-title"><?php echo $role['title']; ?></div>
                            <div class="role-description"><?php echo $role['description']; ?></div>
                            <div class="role-users"><?php echo $role['count']; ?> users</div>
                        </div>
                        <div class="role-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="role-content">
                        <div class="permissions-container">
                            <?php foreach($role['permissions'] as $groupName => $permissionList): ?>
                                <div class="permission-group">
                                    <h3><?php echo ucwords(str_replace('_', ' ', $groupName)); ?></h3>
                                    <ul class="permission-list">
                                        <?php foreach($permissionList as $permission): ?>
                                            <li><?php echo $permission; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="roles-footer">
                <p>Roles and permissions are managed through the database with a role-based access control system that connects users to roles and roles to specific permissions.</p>
            </div>
        </section>

        <section class="section" id="permissions-map">
            <h2>Permission Structure</h2>
            
            <div class="diagram-note" style="background-color: var(--card-bg); padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); margin-bottom: 2rem;">
                <p style="margin-bottom: 1rem;">The Defect Tracker system implements a comprehensive role-based access control (RBAC) system through database tables that link users to roles and roles to specific permissions:</p>
                <ul style="list-style-type: none; padding-left: 1.5rem;">
                    <li style="position: relative; padding-left: 1.5rem; margin-bottom: 0.5rem;"><span style="position: absolute; left: 0; color: var(--primary);">→</span> <strong>users</strong> table contains the basic user information and links to assigned roles</li>
                    <li style="position: relative; padding-left: 1.5rem; margin-bottom: 0.5rem;"><span style="position: absolute; left: 0; color: var(--primary);">→</span> <strong>user_roles</strong> junction table connects users to their assigned roles</li>
                    <li style="position: relative; padding-left: 1.5rem; margin-bottom: 0.5rem;"><span style="position: absolute; left: 0; color: var(--primary);">→</span> <strong>roles</strong> table defines the available system roles</li>
                    <li style="position: relative; padding-left: 1.5rem; margin-bottom: 0.5rem;"><span style="position: absolute; left: 0; color: var(--primary);">→</span> <strong>role_permissions</strong> junction table connects roles to their permissions</li>
                    <li style="position: relative; padding-left: 1.5rem;"><span style="position: absolute; left: 0; color: var(--primary);">→</span> <strong>permissions</strong> table contains granular system permissions</li>
                </ul>
            </div>
            
            <div id="permission-chart-container" style="width: 100%; height: 450px; background-color: var(--card-bg); border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                <canvas id="permissionsChart"></canvas>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>© 2025 Defect Tracker System | User: <?php echo $username; ?> | Last updated: <?php echo $currentDate; ?> <?php echo $currentTime; ?></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Toggle role expansion
        function toggleRole(roleKey) {
            const roleCard = document.querySelector(`.role-card[data-role="${roleKey}"]`);
            roleCard.classList.toggle('expanded');
            
            // Animate the opening
            if (roleCard.classList.contains('expanded')) {
                const content = roleCard.querySelector('.role-content');
                content.style.maxHeight = `${content.scrollHeight}px`;
            }
        }
        
        // Automatically open the first role card
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const firstRole = document.querySelector('.role-card:first-child');
                if (firstRole) {
                    firstRole.classList.add('expanded');
                    const content = firstRole.querySelector('.role-content');
                    content.style.maxHeight = `${content.scrollHeight}px`;
                }
            }, 500);
            
            // Initialize permissions chart
            const ctx = document.getElementById('permissionsChart').getContext('2d');
            
            // Data for the chart
            const roleNames = <?php echo json_encode(array_map(function($r) { return $r['title']; }, $roles)); ?>;
            const roleColors = <?php echo json_encode(array_column($roles, 'color')); ?>;
            
            // Count permissions per role
            const permissionCounts = <?php 
                echo json_encode(array_map(function($role) {
                    $count = 0;
                    foreach ($role['permissions'] as $group) {
                        $count += count($group);
                    }
                    return $count;
                }, $roles)); 
            ?>;
            
            // Create the chart
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: roleNames,
                    datasets: [{
                        label: 'Number of Permissions',
                        data: permissionCounts,
                        backgroundColor: roleColors,
                        borderColor: roleColors.map(color => color),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Permission Distribution by Role',
                            font: {
                                size: 16
                            }
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Permissions'
                            }
                        }
                    }
                }
            });
            
            // Add scroll animations
            const elements = document.querySelectorAll('.role-card, .diagram-note, #permission-chart-container');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fadeIn');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            elements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(el);
            });
            
            // Add fadeIn class functionality
            document.head.insertAdjacentHTML('beforeend', `
                <style>
                    .fadeIn {
                        opacity: 1 !important;
                        transform: translateY(0) !important;
                    }
                </style>
            `);
        });
    </script>
</body>
</html>