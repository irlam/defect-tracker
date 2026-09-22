// User Management Demo JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeUserManagement();
    initializeInteractiveElements();
    initializeRoleSimulation();
});

function initializeUserManagement() {
    // Initialize user management components
    updateUserStats();
    initializeUserTable();
    initializeRoleCards();
}

function updateUserStats() {
    // Update role statistics
    const roleCards = document.querySelectorAll('.role-card');
    roleCards.forEach(card => {
        const role = card.dataset.role;
        // Simulate dynamic user counts
        const userCounts = {
            admin: 2,
            manager: 15,
            contractor: 85,
            inspector: 12
        };
        const countElement = card.querySelector('.user-count');
        countElement.textContent = userCounts[role] + ' users';
    });
}

function initializeUserTable() {
    // Add hover effects and click handlers
    const userRows = document.querySelectorAll('.user-row');
    userRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
        });

        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.05)';
        });
    });
}

function filterUsers(filterType) {
    const userRows = document.querySelectorAll('.user-row');
    const filterButtons = document.querySelectorAll('.widget-actions .btn');

    // Update button states
    filterButtons.forEach(btn => {
        if (btn.onclick.toString().includes(filterType)) {
            btn.classList.add('active');
        } else if (!btn.onclick.toString().includes('addNewUser')) {
            btn.classList.remove('active');
        }
    });

    userRows.forEach(row => {
        const status = row.dataset.status;
        let show = true;

        switch(filterType) {
            case 'active':
                show = status === 'active';
                break;
            case 'inactive':
                show = status === 'inactive';
                break;
            case 'all':
            default:
                show = true;
                break;
        }

        row.style.display = show ? 'flex' : 'none';

        // Add fade animation
        if (show) {
            row.style.opacity = '0';
            setTimeout(() => {
                row.style.opacity = '1';
                row.style.transition = 'opacity 0.3s ease';
            }, 50);
        }
    });
}

function addNewUser() {
    showUserModal('add');
}

function editUser(userId) {
    showUserModal('edit', userId);
}

function deactivateUser(userId) {
    if (confirm('Are you sure you want to deactivate this user?')) {
        updateUserStatus(userId, 'inactive');
        showToast('User deactivated successfully', 'warning');
    }
}

function activateUser(userId) {
    updateUserStatus(userId, 'active');
    showToast('User activated successfully', 'success');
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')) {
        const userRow = document.querySelector(`[data-user-id="${userId}"]`) ||
                       document.querySelector('.user-row'); // Fallback
        if (userRow) {
            userRow.remove();
            showToast('User deleted successfully', 'danger');
        }
    }
}

function updateUserStatus(userId, newStatus) {
    const userRow = document.querySelector(`[data-user-id="${userId}"]`) ||
                   document.querySelector('.user-row'); // Fallback

    if (userRow) {
        userRow.dataset.status = newStatus;
        const statusIndicator = userRow.querySelector('.status-indicator');
        const statusText = userRow.querySelector('.user-status span:last-child');

        statusIndicator.className = `status-indicator ${newStatus}`;
        statusText.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);

        // Update action buttons based on status
        const actionsDiv = userRow.querySelector('.user-actions');
        if (newStatus === 'active') {
            actionsDiv.innerHTML = `
                <button class="btn btn-sm btn-outline-primary" onclick="editUser('${userId}')">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deactivateUser('${userId}')">
                    <i class="fas fa-ban"></i>
                </button>
            `;
        } else {
            actionsDiv.innerHTML = `
                <button class="btn btn-sm btn-outline-success" onclick="activateUser('${userId}')">
                    <i class="fas fa-play"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('${userId}')">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        }
    }
}

function showUserModal(mode, userId = null) {
    const modal = document.createElement('div');
    modal.className = 'user-modal-overlay';
    modal.innerHTML = `
        <div class="user-modal">
            <div class="modal-header">
                <h3>${mode === 'add' ? 'Add New User' : 'Edit User'}</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="user-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" id="userName" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" id="userEmail" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" id="userPhone">
                        </div>
                        <div class="form-group">
                            <label>User Role *</label>
                            <select id="userRole" required>
                                <option value="">Select Role</option>
                                <option value="admin">System Administrator</option>
                                <option value="manager">Project Manager</option>
                                <option value="contractor">Contractor</option>
                                <option value="inspector">Quality Inspector</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Company/Organization</label>
                            <input type="text" id="userCompany">
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" id="userDepartment">
                        </div>
                    </div>
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" id="sendWelcomeEmail" checked>
                            <span>Send welcome email with login instructions</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="requirePasswordChange">
                            <span>Require password change on first login</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="enableTwoFactor">
                            <span>Enable two-factor authentication</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal(this)">Cancel</button>
                <button class="btn btn-primary" onclick="${mode === 'add' ? 'createUser()' : 'updateUser(\'' + userId + '\')'}">
                    ${mode === 'add' ? 'Create User' : 'Update User'}
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    // Pre-fill form if editing
    if (mode === 'edit' && userId) {
        setTimeout(() => {
            prefillUserForm(userId);
        }, 100);
    }
}

function prefillUserForm(userId) {
    // Simulate loading user data
    const mockUserData = {
        'sarah-admin': {
            name: 'Sarah Admin',
            email: 'sarah.admin@company.com',
            phone: '+1 (555) 123-4567',
            role: 'admin',
            company: 'Construction Corp',
            department: 'IT'
        },
        'john-manager': {
            name: 'John Manager',
            email: 'john.manager@company.com',
            phone: '+1 (555) 234-5678',
            role: 'manager',
            company: 'Construction Corp',
            department: 'Project Management'
        }
    };

    const userData = mockUserData[userId];
    if (userData) {
        document.getElementById('userName').value = userData.name;
        document.getElementById('userEmail').value = userData.email;
        document.getElementById('userPhone').value = userData.phone;
        document.getElementById('userRole').value = userData.role;
        document.getElementById('userCompany').value = userData.company;
        document.getElementById('userDepartment').value = userData.department;
    }
}

function createUser() {
    const formData = getFormData();
    if (validateUserForm(formData)) {
        // Simulate user creation
        addUserToTable(formData);
        closeModal();
        showToast('User created successfully! Welcome email sent.', 'success');
    }
}

function updateUser(userId) {
    const formData = getFormData();
    if (validateUserForm(formData)) {
        // Simulate user update
        updateUserInTable(userId, formData);
        closeModal();
        showToast('User updated successfully!', 'success');
    }
}

function getFormData() {
    return {
        name: document.getElementById('userName').value,
        email: document.getElementById('userEmail').value,
        phone: document.getElementById('userPhone').value,
        role: document.getElementById('userRole').value,
        company: document.getElementById('userCompany').value,
        department: document.getElementById('userDepartment').value,
        sendWelcomeEmail: document.getElementById('sendWelcomeEmail').checked,
        requirePasswordChange: document.getElementById('requirePasswordChange').checked,
        enableTwoFactor: document.getElementById('enableTwoFactor').checked
    };
}

function validateUserForm(data) {
    if (!data.name || !data.email || !data.role) {
        showToast('Please fill in all required fields', 'danger');
        return false;
    }

    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
        showToast('Please enter a valid email address', 'danger');
        return false;
    }

    return true;
}

function addUserToTable(userData) {
    const usersTable = document.querySelector('.users-table');
    const userRow = document.createElement('div');
    userRow.className = 'user-row';
    userRow.dataset.role = userData.role;
    userRow.dataset.status = 'active';
    userRow.dataset.userId = 'new-user-' + Date.now();

    const roleLabels = {
        admin: 'Administrator',
        manager: 'Project Manager',
        contractor: 'Contractor',
        inspector: 'Quality Inspector'
    };

    userRow.innerHTML = `
        <div class="user-avatar">
            <img src="https://via.placeholder.com/40x40/2563eb/white?text=${userData.name.split(' ').map(n => n[0]).join('')}" alt="${userData.name}">
        </div>
        <div class="user-info">
            <div class="user-name">${userData.name}</div>
            <div class="user-email">${userData.email}</div>
        </div>
        <div class="user-role">
            <span class="role-badge ${userData.role}">${roleLabels[userData.role]}</span>
        </div>
        <div class="user-status">
            <span class="status-indicator active"></span>
            <span>Active</span>
        </div>
        <div class="user-last-login">Never</div>
        <div class="user-actions">
            <button class="btn btn-sm btn-outline-primary" onclick="editUser('${userRow.dataset.userId}')">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="deactivateUser('${userRow.dataset.userId}')">
                <i class="fas fa-ban"></i>
            </button>
        </div>
    `;

    usersTable.appendChild(userRow);
    updateUserStats();
}

function updateUserInTable(userId, userData) {
    const userRow = document.querySelector(`[data-user-id="${userId}"]`) ||
                   document.querySelector('.user-row'); // Fallback

    if (userRow) {
        const roleLabels = {
            admin: 'Administrator',
            manager: 'Project Manager',
            contractor: 'Contractor',
            inspector: 'Quality Inspector'
        };

        userRow.querySelector('.user-name').textContent = userData.name;
        userRow.querySelector('.user-email').textContent = userData.email;
        userRow.querySelector('.role-badge').textContent = roleLabels[userData.role];
        userRow.querySelector('.role-badge').className = `role-badge ${userData.role}`;
        userRow.dataset.role = userData.role;
    }
}

function initializeRoleCards() {
    // Add click handlers for role cards
    const roleCards = document.querySelectorAll('.role-card');
    roleCards.forEach(card => {
        card.addEventListener('click', function() {
            // Highlight selected role
            roleCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            // Show role details
            showRoleDetails(this.dataset.role);
        });
    });
}

function showRoleDetails(role) {
    const roleDetails = {
        admin: {
            title: 'System Administrator',
            description: 'Full system access with user management and configuration capabilities',
            permissions: ['All System Features', 'User Creation & Management', 'System Settings', 'Backup & Restore', 'Audit Reports']
        },
        manager: {
            title: 'Project Manager',
            description: 'Project oversight with defect management and contractor coordination',
            permissions: ['Create & Edit Defects', 'Assign Contractors', 'Approve Resolutions', 'View Reports', 'Project Analytics']
        },
        contractor: {
            title: 'Contractor',
            description: 'Field operations with defect resolution and progress updates',
            permissions: ['View Assigned Defects', 'Update Status', 'Upload Photos', 'Submit Work', 'View Schedule']
        },
        inspector: {
            title: 'Quality Inspector',
            description: 'Quality assurance with read-only access and reporting capabilities',
            permissions: ['View All Defects', 'Generate Reports', 'Quality Checks', 'Read-Only Access', 'Compliance Monitoring']
        }
    };

    const detail = roleDetails[role];
    if (detail) {
        showToast(`Selected: ${detail.title}`, 'info');
    }
}

function initializeRoleSimulation() {
    // Initialize access control matrix
    simulateAccess('admin');
}

function simulateAccess(selectedRole) {
    const permissionCells = document.querySelectorAll('.permission-cell');

    permissionCells.forEach(cell => {
        const hasPermission = cell.dataset[selectedRole] === 'yes';
        cell.innerHTML = hasPermission ?
            '<i class="fas fa-check" style="color: #10b981;"></i>' :
            '<i class="fas fa-times" style="color: #ef4444;"></i>';

        cell.style.background = hasPermission ? '#d1fae5' : '#fef2f2';
    });

    // Update role selector
    const roleSelector = document.getElementById('roleSelector');
    roleSelector.value = selectedRole;
}

function initializeInteractiveElements() {
    // Add click handlers for security cards
    const securityCards = document.querySelectorAll('.security-card');
    securityCards.forEach(card => {
        card.addEventListener('click', function() {
            // Add click animation
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);

            // Show demo of the feature
            showSecurityDemo(this.querySelector('h4').textContent);
        });
    });

    // Add click handlers for onboarding steps
    const flowSteps = document.querySelectorAll('.flow-step');
    flowSteps.forEach(step => {
        step.addEventListener('click', function() {
            // Highlight selected step
            flowSteps.forEach(s => s.classList.remove('active'));
            this.classList.add('active');

            // Show step details
            showOnboardingStep(this.querySelector('.step-number').textContent);
        });
    });
}

function showSecurityDemo(featureTitle) {
    let demoContent = '';

    switch(featureTitle) {
        case 'Multi-Factor Auth':
            demoContent = `
                <div class="security-demo">
                    <div class="mfa-setup">
                        <h5>Two-Factor Authentication Setup</h5>
                        <div class="mfa-step">
                            <span class="step-number">1</span>
                            <span>Scan QR code with authenticator app</span>
                        </div>
                        <div class="mfa-step">
                            <span class="step-number">2</span>
                            <span>Enter verification code</span>
                            <input type="text" placeholder="000000" maxlength="6">
                        </div>
                        <button class="btn btn-success">Enable 2FA</button>
                    </div>
                </div>
            `;
            break;
        case 'Role-Based Access':
            demoContent = `
                <div class="security-demo">
                    <div class="permission-matrix">
                        <h5>Permission Matrix</h5>
                        <div class="matrix-item">
                            <span>Admin</span>
                            <div class="permissions">
                                <i class="fas fa-check text-success"></i>
                                <i class="fas fa-check text-success"></i>
                                <i class="fas fa-check text-success"></i>
                                <i class="fas fa-check text-success"></i>
                            </div>
                        </div>
                        <div class="matrix-item">
                            <span>Manager</span>
                            <div class="permissions">
                                <i class="fas fa-check text-success"></i>
                                <i class="fas fa-check text-success"></i>
                                <i class="fas fa-times text-danger"></i>
                                <i class="fas fa-times text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'Session Management':
            demoContent = `
                <div class="security-demo">
                    <div class="session-info">
                        <h5>Active Sessions</h5>
                        <div class="session-item">
                            <div class="session-device">
                                <i class="fas fa-desktop"></i>
                                <span>Chrome on Windows • Current Session</span>
                            </div>
                            <button class="btn btn-sm btn-outline-danger">Logout</button>
                        </div>
                        <div class="session-item">
                            <div class="session-device">
                                <i class="fas fa-mobile-alt"></i>
                                <span>Safari on iPhone • 2 hours ago</span>
                            </div>
                            <button class="btn btn-sm btn-outline-danger">Logout</button>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'Audit Trails':
            demoContent = `
                <div class="security-demo">
                    <div class="audit-log">
                        <h5>Recent Activity</h5>
                        <div class="audit-entry">
                            <span class="timestamp">2025-01-15 14:30</span>
                            <span class="action">User login</span>
                            <span class="user">john.manager</span>
                        </div>
                        <div class="audit-entry">
                            <span class="timestamp">2025-01-15 14:25</span>
                            <span class="action">Defect created</span>
                            <span class="user">john.manager</span>
                        </div>
                        <div class="audit-entry">
                            <span class="timestamp">2025-01-15 14:20</span>
                            <span class="action">User created</span>
                            <span class="user">sarah.admin</span>
                        </div>
                    </div>
                </div>
            `;
            break;
    }

    const demoModal = document.createElement('div');
    demoModal.className = 'demo-modal-overlay';
    demoModal.innerHTML = `
        <div class="demo-modal">
            <div class="modal-header">
                <h3>${featureTitle} Demo</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                ${demoContent}
            </div>
        </div>
    `;

    document.body.appendChild(demoModal);

    setTimeout(() => {
        demoModal.classList.add('show');
    }, 10);
}

function showOnboardingStep(stepNumber) {
    const stepDetails = {
        '1': 'User receives secure email invitation with role assignment and temporary access link.',
        '2': 'Guided account setup with password creation, profile completion, and security settings.',
        '3': 'Interactive tutorial walks users through key features based on their assigned role.',
        '4': 'User is fully onboarded with appropriate permissions and ready to use the system.'
    };

    showToast(`Step ${stepNumber}: ${stepDetails[stepNumber]}`, 'info');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `notification-toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
            <div class="toast-message">${message}</div>
        </div>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 100);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function closeModal(button = null) {
    const modal = button ? button.closest('.modal-overlay, .user-modal-overlay, .demo-modal-overlay') :
                          document.querySelector('.modal-overlay, .user-modal-overlay, .demo-modal-overlay');

    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Add CSS for user management demo styles
const userManagementStyles = `
<style>
/* User Management Dashboard */
.user-management-dashboard {
    display: grid;
    gap: 2rem;
}

.dashboard-widget {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.widget-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.widget-header h4 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
}

.widget-header h4 i {
    color: #2563eb;
    margin-right: 0.5rem;
}

.widget-actions {
    display: flex;
    gap: 0.5rem;
}

.widget-actions .btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
}

.widget-content {
    padding: 2rem;
}

/* Roles Grid */
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.role-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.role-card:hover, .role-card.selected {
    border-color: #2563eb;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
    transform: translateY(-2px);
}

.role-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.role-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.role-header h5 {
    margin: 0;
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 600;
}

.role-permissions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: #374151;
}

.permission-item i {
    color: #10b981;
    width: 16px;
}

.role-stats {
    text-align: center;
}

.user-count {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    color: #2563eb;
    font-size: 0.9rem;
}

/* Users Table */
.users-table {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.user-row {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    cursor: pointer;
}

.user-avatar {
    margin-right: 1rem;
}

.user-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.user-info {
    flex: 1;
    min-width: 150px;
}

.user-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.user-email {
    font-size: 0.875rem;
    color: #6b7280;
}

.user-role {
    margin-right: 1rem;
    min-width: 120px;
}

.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.role-badge.admin {
    background: #fef2f2;
    color: #dc2626;
}

.role-badge.manager {
    background: #fef3c7;
    color: #d97706;
}

.role-badge.contractor {
    background: #d1fae5;
    color: #059669;
}

.role-badge.inspector {
    background: #e0e7ff;
    color: #7c3aed;
}

.user-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-right: 1rem;
    min-width: 80px;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-indicator.active {
    background: #10b981;
}

.status-indicator.inactive {
    background: #6b7280;
}

.user-last-login {
    margin-right: 1rem;
    font-size: 0.875rem;
    color: #6b7280;
    min-width: 80px;
}

.user-actions {
    display: flex;
    gap: 0.5rem;
}

/* Access Control Demo */
.access-demo {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.demo-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.demo-controls label {
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.demo-controls select {
    padding: 0.5rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 0.9rem;
}

.access-matrix {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
}

.matrix-row {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.matrix-row:last-child {
    border-bottom: none;
}

.action-label {
    flex: 1;
    font-weight: 500;
    color: #374151;
}

.permission-cell {
    width: 60px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.permission-cell i {
    font-size: 1.1rem;
}

/* Security Features */
.security-features .row {
    --bs-gutter-x: 2rem;
}

.security-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
}

.security-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.security-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    margin: 0 auto 1.5rem;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.security-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.security-card p {
    color: #64748b;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.security-metric {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    color: #2563eb;
    font-size: 0.9rem;
}

/* Onboarding Flow */
.onboarding-flow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.flow-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    max-width: 250px;
}

.flow-step:hover, .flow-step.active {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
    border: 2px solid #2563eb;
}

.step-number {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.step-content h4 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 600;
}

.step-content p {
    margin: 0;
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.4;
}

.step-demo {
    margin-top: 1rem;
    width: 100%;
}

.flow-arrow {
    color: #cbd5e1;
    font-size: 1.5rem;
}

/* Modal Styles */
.modal-overlay, .user-modal-overlay, .demo-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.show, .user-modal-overlay.show, .demo-modal-overlay.show {
    opacity: 1;
}

.user-modal, .demo-modal {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* User Form */
.user-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: flex;
    gap: 1rem;
}

.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-group input, .form-group select {
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.form-group input:focus, .form-group select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: #374151;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

/* Demo Content Styles */
.email-preview {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.email-header {
    background: #f8fafc;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #374151;
    font-weight: 600;
}

.email-body {
    padding: 1rem;
}

.setup-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tutorial-card {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #0ea5e9;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.tutorial-icon {
    font-size: 3rem;
    color: #0ea5e9;
    margin-bottom: 1rem;
}

.tutorial-card h5 {
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.tutorial-card p {
    color: #64748b;
    margin-bottom: 1rem;
}

.tutorial-progress {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: center;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    max-width: 100px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.success-card {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #10b981;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.success-card .success-icon {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.success-card h5 {
    color: #065f46;
    margin-bottom: 0.5rem;
}

.success-card p {
    color: #047857;
    margin-bottom: 1rem;
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 100px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    z-index: 9999;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    border-left: 4px solid #2563eb;
}

.notification-toast.toast-success {
    border-left-color: #10b981;
}

.notification-toast.toast-warning {
    border-left-color: #f59e0b;
}

.notification-toast.toast-danger {
    border-left-color: #ef4444;
}

.notification-toast.show {
    transform: translateX(0);
}

.toast-icon {
    color: #2563eb;
    font-size: 1.5rem;
}

.toast-icon.fa-check-circle { color: #10b981; }
.toast-icon.fa-exclamation-triangle { color: #f59e0b; }
.toast-icon.fa-times-circle { color: #ef4444; }

.toast-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.toast-message {
    color: #64748b;
    font-size: 0.9rem;
}

/* Animations */
@keyframes modalSlideIn {
    from {
        transform: scale(0.9) translateY(20px);
        opacity: 0;
    }
    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .roles-grid {
        grid-template-columns: 1fr;
    }

    .user-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .user-role, .user-status, .user-last-login {
        margin-right: 0;
    }

    .onboarding-flow {
        flex-direction: column;
        gap: 1rem;
    }

    .flow-arrow {
        transform: rotate(90deg);
    }

    .form-row {
        flex-direction: column;
        gap: 1rem;
    }

    .security-features .row {
        --bs-gutter-x: 1rem;
    }
}
</style>
`;

// Add user management-specific styles to head
document.head.insertAdjacentHTML('beforeend', userManagementStyles);