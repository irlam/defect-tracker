<?php
// includes/profile-form.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-18 15:47:29
// Current User's Login: irlam

// Prevent direct access to this file
if (!defined('INCLUDED')) {
    header("HTTP/1.0 403 Forbidden");
    exit('Direct access forbidden.');
}
?>

<ul class="nav nav-tabs" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" 
                data-bs-target="#edit" type="button" role="tab" aria-selected="true">
            <i class="bi bi-pencil me-2"></i>Edit Profile
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" 
                data-bs-target="#security" type="button" role="tab">
            <i class="bi bi-shield-lock me-2"></i>Security
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" 
                data-bs-target="#activity" type="button" role="tab">
            <i class="bi bi-clock-history me-2"></i>Activity
        </button>
    </li>
</ul>

<div class="tab-content mt-4" id="profileTabsContent">
    <!-- Edit Profile Tab -->
    <div class="tab-pane fade show active" id="edit" role="tabpanel" tabindex="0">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" 
              method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="update_profile">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="first_name">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="<?php echo htmlspecialchars($user_data['first_name']); ?>"
                           maxlength="50">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($user_data['last_name']); ?>"
                           maxlength="50">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required
                       value="<?php echo htmlspecialchars($user_data['email']); ?>">
                <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="department">Department</label>
                <input type="text" class="form-control" id="department" name="department"
                       value="<?php echo htmlspecialchars($user_data['department']); ?>"
                       maxlength="50">
            </div>

            <div class="mb-4">
                <label class="form-label" for="theme_preference">Theme Preference</label>
                <select class="form-select" id="theme_preference" name="theme_preference">
                    <option value="light" <?php echo $user_data['theme_preference'] === 'light' ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo $user_data['theme_preference'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    <option value="system" <?php echo $user_data['theme_preference'] === 'system' ? 'selected' : ''; ?>>System</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i>Update Profile
            </button>
        </form>
    </div>

    <!-- Security Tab -->
    <div class="tab-pane fade" id="security" role="tabpanel" tabindex="0">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" 
              method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="change_password">
            
            <div class="mb-3">
                <label class="form-label" for="current_password">Current Password</label>
                <input type="password" class="form-control" id="current_password" 
                       name="current_password" required minlength="8">
                <div class="invalid-feedback">Please enter your current password.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" 
                       name="new_password" required minlength="8"
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                <div class="invalid-feedback">
                    Password must be at least 8 characters long and include uppercase, lowercase, and numbers.
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" 
                       name="confirm_password" required minlength="8">
                <div class="invalid-feedback">Passwords do not match.</div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-key me-2"></i>Change Password
            </button>
        </form>
    </div>

    <!-- Activity Tab -->
    <div class="tab-pane fade" id="activity" role="tabpanel" tabindex="0">
        <div class="activity-timeline">
            <?php if (empty($activities)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-clock-history display-4"></i>
                    <p class="mt-2">No recent activity</p>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h6 class="mb-1">
                                <?php if ($activity['type'] === 'defect'): ?>
                                    <i class="bi bi-bug me-2"></i>
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                <?php else: ?>
                                    <i class="bi bi-chat-dots me-2"></i>
                                    Commented on defect #<?php echo (int)$activity['id']; ?>
                                <?php endif; ?>
                            </h6>
                            <small class="text-muted">
                                <?php echo formatDate($activity['activity_date']); ?>
                            </small>
                        </div>
                        <?php if ($activity['type'] === 'defect' && !empty($activity['status'])): ?>
                            <p class="mb-1">
                                <span class="badge bg-<?php echo getStatusBadgeClass($activity['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($activity['status'])); ?>
                                </span>
                                <?php if (!empty($activity['project_name'])): ?>
                                    <span class="ms-2">in <?php echo htmlspecialchars($activity['project_name']); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    if (newPassword && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== newPassword.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        newPassword.addEventListener('input', function() {
            confirmPassword.value = '';
            confirmPassword.setCustomValidity('');
        });
    }

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});
</script>