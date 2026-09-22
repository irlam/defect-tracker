<?php
// includes/dashboard-content.php
// Ensure this file is included, not accessed directly
//if (!defined('INCLUDED_FROM_DASHBOARD')) {
//    exit('Direct access to this file is not allowed.');
//}

// Verify required variables are available
$required_vars = ['myDefects', 'stats', 'recentActivity', 'projectProgress', 'systemHealth'];
foreach ($required_vars as $var) {
    if (!isset($$var)) {
        error_log("Dashboard Error: Required variable '$var' is not defined");
        $$var = [];
    }
}

// Ensure stats array has all required keys
$stats = array_merge([
    'openDefects' => [
        'total' => 0,
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0
    ],
    'dueToday' => 0,
    'overdue' => 0,
    'activeProjects' => 0
], $stats ?? []);

// Ensure systemHealth has required keys
$systemHealth = array_merge([
    'system_status' => 'operational',
    'avg_response_time' => 0,
    'failed_jobs' => 0
], $systemHealth ?? []);
?>

<!-- Rest of your dashboard-content.php HTML remains the same -->
if (!defined('INCLUDED_FROM_DASHBOARD')) {
    exit('Direct access to this file is not allowed.');
}
?>

<!-- Statistics Cards Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <h3><?php echo $stats['openDefects']['total']; ?></h3>
                <p class="mb-0">Open Defects</p>
                <div class="mt-2 small">
                    <span class="badge bg-danger"><?php echo $stats['openDefects']['critical']; ?> Critical</span>
                    <span class="badge bg-warning text-dark"><?php echo $stats['openDefects']['high']; ?> High</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body">
                <h3><?php echo $stats['dueToday']; ?></h3>
                <p class="mb-0">Due Today</p>
                <div class="mt-2 small">
                    <span class="badge bg-danger"><?php echo $stats['overdue']; ?> Overdue</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <h3><?php echo $stats['activeProjects']; ?></h3>
                <p class="mb-0">Active Projects</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <h3><?php echo count($myDefects); ?></h3>
                <p class="mb-0">My Assigned Tasks</p>
            </div>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        <!-- Recent Activity -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="defects.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentActivity as $activity): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($activity['id']); ?></td>
                                <td><?php echo htmlspecialchars($activity['project_name']); ?></td>
                                <td>
                                    <a href="defect.php?id=<?php echo $activity['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($activity['status']); ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo getPriorityBadgeClass($activity['priority']); ?>">
                                        <?php echo ucfirst($activity['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($activity['due_date'])); ?></td>
                                <td>
                                    <?php if($activity['comment_count'] > 0): ?>
                                        <span class="badge bg-secondary">
                                            <i class='bx bxs-comment'></i> <?php echo $activity['comment_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if($activity['image_count'] > 0): ?>
                                        <span class="badge bg-secondary">
                                            <i class='bx bxs-image'></i> <?php echo $activity['image_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- System Status -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">System Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>System Status:</span>
                    <span id="systemStatus" class="badge bg-<?php echo $systemHealth['system_status'] === 'operational' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($systemHealth['system_status']); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Response Time:</span>
                    <span id="avgResponseTime"><?php echo round($systemHealth['avg_response_time'], 2); ?> ms</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Failed Jobs (24h):</span>
                    <span class="badge bg-<?php echo $systemHealth['failed_jobs'] > 0 ? 'warning' : 'success'; ?>">
                        <?php echo $systemHealth['failed_jobs']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- My Assigned Defects -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">My Assigned Defects</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($myDefects)): ?>
                    <div class="list-group-item text-muted">
                        No defects currently assigned to you.
                    </div>
                <?php else: ?>
                    <?php foreach($myDefects as $defect): ?>
                    <a href="defect.php?id=<?php echo $defect['id']; ?>" 
                       class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($defect['title']); ?></h6>
                            <small class="text-muted">
                                <?php echo date('M d', strtotime($defect['due_date'])); ?>
                            </small>
                        </div>
                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($defect['project_name']); ?></p>
                        <div>
                            <span class="badge <?php echo getPriorityBadgeClass($defect['priority']); ?>">
                                <?php echo ucfirst($defect['priority']); ?>
                            </span>
                            <span class="badge <?php echo getStatusBadgeClass($defect['status']); ?>">
                                <?php echo ucfirst($defect['status']); ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Project Progress -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Project Progress</h5>
            </div>
            <div class="card-body">
                <?php if (empty($projectProgress)): ?>
                    <p class="text-muted">No active projects found.</p>
                <?php else: ?>
                    <?php foreach($projectProgress as $project): ?>
                        <?php 
                        $total = $project['total_defects'];
                        $completed = $project['completed_defects'];
                        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo htmlspecialchars($project['name']); ?></span>
                                <span><?php echo $percentage; ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%" 
                                     aria-valuenow="<?php echo $percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $completed; ?> of <?php echo $total; ?> defects resolved
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>