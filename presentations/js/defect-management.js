// Defect Management Demo JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeManagementDashboard();
    initializeInteractiveElements();
    initializeStatusUpdates();
});

function initializeManagementDashboard() {
    // Initialize dashboard widgets
    updateStatusChart();
    initializeDefectTable();
    initializeAssignmentWorkflow();
}

function updateStatusChart() {
    // Animate chart bars on load
    const chartItems = document.querySelectorAll('.chart-item');
    chartItems.forEach((item, index) => {
        setTimeout(() => {
            const fill = item.querySelector('.chart-fill');
            fill.style.width = fill.style.width; // Trigger animation
        }, index * 200);
    });
}

function initializeDefectTable() {
    // Add hover effects and click handlers
    const defectRows = document.querySelectorAll('.defect-row');
    defectRows.forEach(row => {
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

function filterDefects(filterType) {
    const defectRows = document.querySelectorAll('.defect-row');
    const filterButtons = document.querySelectorAll('.widget-actions .btn');

    // Update button states
    filterButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    defectRows.forEach(row => {
        const status = row.dataset.status;
        const priority = row.dataset.priority;

        let show = true;

        switch(filterType) {
            case 'urgent':
                show = priority === 'urgent';
                break;
            case 'overdue':
                show = status === 'open' && priority === 'high';
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

function viewDefect(defectId) {
    // Simulate viewing defect details
    showDefectModal(defectId);
}

function updateStatus(defectId, newStatus) {
    const defectRow = document.querySelector(`[data-defect-id="${defectId}"]`) ||
                     document.querySelector('.defect-row'); // Fallback for demo

    if (defectRow) {
        const statusBadge = defectRow.querySelector('.status-badge');
        const oldStatus = statusBadge.className.split(' ').find(cls => cls.startsWith('status-'));

        // Update status badge
        statusBadge.className = `status-badge status-${newStatus}`;
        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);

        // Add success animation
        statusBadge.style.animation = 'statusUpdate 0.5s ease-out';
        setTimeout(() => {
            statusBadge.style.animation = '';
        }, 500);

        // Show notification
        showStatusUpdateNotification(defectId, newStatus);

        // Update progress timeline if resolved
        if (newStatus === 'resolved') {
            updateProgressTimeline(defectId);
        }
    }
}

function assignDefect(defectId) {
    // Simulate contractor assignment
    showAssignmentModal(defectId);
}

function closeDefect(defectId) {
    updateStatus(defectId, 'closed');
}

function showDefectModal(defectId) {
    const modal = document.createElement('div');
    modal.className = 'defect-modal-overlay';
    modal.innerHTML = `
        <div class="defect-modal">
            <div class="modal-header">
                <h3>Defect Details: ${defectId}</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="defect-details">
                    <div class="detail-section">
                        <h4>Description</h4>
                        <p>Cracked foundation wall requiring structural repair and waterproofing.</p>
                    </div>
                    <div class="detail-section">
                        <h4>Location</h4>
                        <p>Floor 2, Building A - East Wing</p>
                    </div>
                    <div class="detail-section">
                        <h4>Priority & Status</h4>
                        <div class="status-info">
                            <span class="priority-badge high">High Priority</span>
                            <span class="status-badge status-in-progress">In Progress</span>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h4>Assigned Contractor</h4>
                        <div class="contractor-info">
                            <div class="contractor-avatar">JD</div>
                            <div class="contractor-details">
                                <strong>John Doe</strong>
                                <small>Foundation Specialists Inc.</small>
                            </div>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h4>Timeline</h4>
                        <div class="timeline-preview">
                            <div class="timeline-item">
                                <span class="date">Mar 1, 9:00 AM</span>
                                <span class="event">Defect Created</span>
                            </div>
                            <div class="timeline-item">
                                <span class="date">Mar 1, 9:15 AM</span>
                                <span class="event">Assigned to Contractor</span>
                            </div>
                            <div class="timeline-item">
                                <span class="date">Mar 5, 2:00 PM</span>
                                <span class="event">Work Started</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function showAssignmentModal(defectId) {
    const modal = document.createElement('div');
    modal.className = 'assignment-modal-overlay';
    modal.innerHTML = `
        <div class="assignment-modal">
            <div class="modal-header">
                <h3>Assign Defect: ${defectId}</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="contractor-list">
                    <div class="contractor-option" onclick="selectContractor('jd')">
                        <div class="contractor-avatar">JD</div>
                        <div class="contractor-info">
                            <strong>John Doe</strong>
                            <small>Foundation Specialists Inc. • 95% success rate</small>
                        </div>
                        <div class="contractor-score">
                            <i class="fas fa-star"></i>
                            <span>9.2</span>
                        </div>
                    </div>
                    <div class="contractor-option" onclick="selectContractor('sm')">
                        <div class="contractor-avatar">SM</div>
                        <div class="contractor-info">
                            <strong>Sarah Miller</strong>
                            <small>Premier Construction • 92% success rate</small>
                        </div>
                        <div class="contractor-score">
                            <i class="fas fa-star"></i>
                            <span>8.9</span>
                        </div>
                    </div>
                    <div class="contractor-option" onclick="selectContractor('mr')">
                        <div class="contractor-avatar">MR</div>
                        <div class="contractor-info">
                            <strong>Mike Ross</strong>
                            <small>Elite Contractors • 88% success rate</small>
                        </div>
                        <div class="contractor-score">
                            <i class="fas fa-star"></i>
                            <span>8.5</span>
                        </div>
                    </div>
                </div>
                <div class="assignment-actions">
                    <button class="btn btn-primary" onclick="confirmAssignment('${defectId}')">
                        Assign Selected Contractor
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function selectContractor(contractorId) {
    const options = document.querySelectorAll('.contractor-option');
    options.forEach(option => option.classList.remove('selected'));

    const selectedOption = document.querySelector(`[onclick="selectContractor('${contractorId}')"]`);
    selectedOption.classList.add('selected');
}

function confirmAssignment(defectId) {
    const selectedContractor = document.querySelector('.contractor-option.selected');
    if (selectedContractor) {
        const contractorName = selectedContractor.querySelector('strong').textContent;

        // Update the defect row
        const defectRow = document.querySelector('.defect-row[data-status="open"]');
        if (defectRow) {
            const assigneeDiv = defectRow.querySelector('.defect-assignee');
            assigneeDiv.innerHTML = `
                <div class="assignee-avatar">${contractorName.split(' ').map(n => n[0]).join('')}</div>
                <span>${contractorName}</span>
            `;

            // Update status to assigned
            updateStatus(defectId, 'assigned');
        }

        // Close modal and show success
        closeModal();
        showAssignmentSuccessNotification(contractorName);
    }
}

function closeModal(button = null) {
    const modal = button ? button.closest('.modal-overlay, .defect-modal-overlay, .assignment-modal-overlay') :
                          document.querySelector('.modal-overlay, .defect-modal-overlay, .assignment-modal-overlay');

    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

function showStatusUpdateNotification(defectId, newStatus) {
    showToast(`Defect ${defectId} status updated to ${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}`, 'success');
}

function showAssignmentSuccessNotification(contractorName) {
    showToast(`Defect successfully assigned to ${contractorName}`, 'success');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `notification-toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${type === 'success' ? 'Success' : 'Info'}</div>
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

function updateProgressTimeline(defectId) {
    const timelineItems = document.querySelectorAll('.timeline-item');
    const resolvedItem = Array.from(timelineItems).find(item =>
        item.querySelector('h6').textContent === 'Resolution Complete'
    );

    if (resolvedItem) {
        resolvedItem.classList.remove('pending');
        resolvedItem.classList.add('completed');

        const marker = resolvedItem.querySelector('.timeline-marker');
        marker.innerHTML = '<i class="fas fa-check"></i>';

        const content = resolvedItem.querySelector('.timeline-content');
        const dateElement = content.querySelector('p');
        dateElement.textContent = new Date().toLocaleString();
    }
}

function initializeAssignmentWorkflow() {
    // Add hover effects to workflow stages
    const workflowStages = document.querySelectorAll('.workflow-stage');
    workflowStages.forEach(stage => {
        stage.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
            this.style.boxShadow = '0 12px 35px rgba(37, 99, 235, 0.2)';
        });

        stage.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
        });
    });
}

function initializeInteractiveElements() {
    // Add click handlers for quality control cards
    const qualityCards = document.querySelectorAll('.quality-card');
    qualityCards.forEach(card => {
        card.addEventListener('click', function() {
            // Add click animation
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);

            // Show demo of the feature
            showQualityDemo(this.querySelector('h4').textContent);
        });
    });
}

function showQualityDemo(featureTitle) {
    let demoContent = '';

    switch(featureTitle) {
        case 'Before/After Photos':
            demoContent = `
                <div class="photo-comparison">
                    <div class="photo-item">
                        <h5>Before</h5>
                        <div class="photo-placeholder">
                            <i class="fas fa-image"></i>
                            <span>Cracked wall photo</span>
                        </div>
                    </div>
                    <div class="photo-item">
                        <h5>After</h5>
                        <div class="photo-placeholder">
                            <i class="fas fa-image"></i>
                            <span>Repaired wall photo</span>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'Completion Checklist':
            demoContent = `
                <div class="checklist-demo">
                    <div class="checklist-item completed">
                        <i class="fas fa-check-square"></i>
                        <span>Structural repair completed</span>
                    </div>
                    <div class="checklist-item completed">
                        <i class="fas fa-check-square"></i>
                        <span>Waterproofing applied</span>
                    </div>
                    <div class="checklist-item completed">
                        <i class="fas fa-check-square"></i>
                        <span>Final inspection passed</span>
                    </div>
                    <div class="checklist-item pending">
                        <i class="far fa-square"></i>
                        <span>Documentation uploaded</span>
                    </div>
                </div>
            `;
            break;
        case 'Approval Workflow':
            demoContent = `
                <div class="approval-demo">
                    <div class="approval-step completed">
                        <div class="step-icon"><i class="fas fa-check"></i></div>
                        <div class="step-info">
                            <strong>Contractor Submitted</strong>
                            <small>March 8, 2025 - 10:00 AM</small>
                        </div>
                    </div>
                    <div class="approval-step active">
                        <div class="step-icon"><i class="fas fa-clock"></i></div>
                        <div class="step-info">
                            <strong>Project Manager Review</strong>
                            <small>Pending approval</small>
                        </div>
                    </div>
                    <div class="approval-step pending">
                        <div class="step-icon"><i class="fas fa-lock"></i></div>
                        <div class="step-info">
                            <strong>Final Approval</strong>
                            <small>After PM review</small>
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

// Add CSS for management demo styles
const managementStyles = `
<style>
/* Management Dashboard */
.management-dashboard {
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

.widget-content {
    padding: 2rem;
}

/* Status Chart */
.status-chart {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.chart-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chart-label {
    min-width: 100px;
    font-weight: 500;
    color: #374151;
}

.chart-bar {
    flex: 1;
    height: 24px;
    background: #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.chart-fill {
    height: 100%;
    border-radius: 12px;
    transition: width 1s ease-out;
}

.chart-value {
    min-width: 40px;
    text-align: right;
    font-weight: 600;
    color: #2563eb;
}

/* Defects Table */
.defects-table {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.defect-row {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    cursor: pointer;
}

.defect-info {
    flex: 1;
}

.defect-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.defect-meta {
    font-size: 0.875rem;
    color: #6b7280;
}

.defect-status {
    margin-right: 1rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-open {
    background: #fef2f2;
    color: #dc2626;
}

.status-in-progress {
    background: #fef3c7;
    color: #d97706;
}

.status-resolved {
    background: #d1fae5;
    color: #059669;
}

.status-closed {
    background: #f3f4f6;
    color: #6b7280;
}

.defect-assignee {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-right: 1rem;
    min-width: 120px;
}

.assignee-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.defect-actions {
    display: flex;
    gap: 0.5rem;
}

/* Assignment Workflow */
.assignment-workflow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
}

.workflow-stage {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    max-width: 200px;
}

.stage-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.workflow-stage h5 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.workflow-stage p {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1rem;
}

.stage-metric {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #2563eb;
}

.workflow-arrow {
    color: #cbd5e1;
    font-size: 1.5rem;
}

/* Progress Timeline */
.progress-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.progress-header {
    padding: 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-header h4 {
    margin: 0;
    color: #1e293b;
}

.progress-timeline {
    padding: 2rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    position: relative;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 40px;
    bottom: -2rem;
    width: 2px;
    background: #e2e8f0;
}

.timeline-marker {
    width: 40px;
    height: 40px;
    background: #e2e8f0;
    color: #6b7280;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.timeline-item.completed .timeline-marker {
    background: #10b981;
    color: white;
}

.timeline-item.active .timeline-marker {
    background: #2563eb;
    color: white;
    animation: pulse 2s infinite;
}

.timeline-content h6 {
    margin: 0 0 0.25rem 0;
    color: #1e293b;
    font-size: 0.9rem;
    font-weight: 600;
}

.timeline-content p {
    margin: 0 0 0.25rem 0;
    color: #6b7280;
    font-size: 0.875rem;
}

.timeline-content small {
    color: #94a3b8;
    font-size: 0.8rem;
}

/* Quality Control */
.quality-features .row {
    --bs-gutter-x: 2rem;
}

.quality-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
}

.quality-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.quality-icon {
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

.quality-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.quality-card p {
    color: #64748b;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.quality-metric {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2563eb;
}

.metric-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

/* Modal Styles */
.modal-overlay, .defect-modal-overlay, .assignment-modal-overlay, .demo-modal-overlay {
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

.modal-overlay.show, .defect-modal-overlay.show, .assignment-modal-overlay.show, .demo-modal-overlay.show {
    opacity: 1;
}

.defect-modal, .assignment-modal, .demo-modal {
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

/* Defect Details */
.defect-details {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-section h4 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1rem;
}

.detail-section p {
    margin: 0;
    color: #6b7280;
}

.status-info {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.priority-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.priority-badge.high {
    background: #fef2f2;
    color: #dc2626;
}

.contractor-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.contractor-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.contractor-details strong {
    display: block;
    color: #1e293b;
}

.contractor-details small {
    color: #6b7280;
}

.timeline-preview {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.timeline-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: #f8fafc;
    border-radius: 6px;
}

.date {
    font-weight: 600;
    color: #2563eb;
    font-size: 0.875rem;
}

.event {
    color: #374151;
    font-size: 0.875rem;
}

/* Contractor Assignment */
.contractor-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.contractor-option {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.contractor-option:hover {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.05);
}

.contractor-option.selected {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.1);
}

.contractor-score {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #f59e0b;
    font-weight: 600;
}

.assignment-actions {
    text-align: center;
}

/* Quality Demo Content */
.photo-comparison {
    display: flex;
    gap: 2rem;
    justify-content: center;
}

.photo-item {
    text-align: center;
}

.photo-item h5 {
    margin-bottom: 1rem;
    color: #1e293b;
}

.photo-placeholder {
    width: 200px;
    height: 150px;
    background: #f3f4f6;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    border: 2px dashed #cbd5e1;
}

.photo-placeholder i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.checklist-demo {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.checklist-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 6px;
}

.checklist-item.completed {
    background: #d1fae5;
    color: #059669;
}

.checklist-item i {
    font-size: 1.25rem;
}

.approval-demo {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.approval-step {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.approval-step.completed .step-icon {
    background: #10b981;
    color: white;
}

.approval-step.active .step-icon {
    background: #2563eb;
    color: white;
}

.approval-step.pending .step-icon {
    background: #e2e8f0;
    color: #6b7280;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.step-info strong {
    display: block;
    color: #1e293b;
}

.step-info small {
    color: #6b7280;
}

/* Animations */
@keyframes statusUpdate {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    }
}

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
    .assignment-workflow {
        flex-direction: column;
        gap: 1rem;
    }

    .workflow-arrow {
        transform: rotate(90deg);
    }

    .defect-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .defect-assignee, .defect-status {
        margin-right: 0;
    }

    .progress-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .photo-comparison {
        flex-direction: column;
        gap: 1rem;
    }

    .quality-features .row {
        --bs-gutter-x: 1rem;
    }
}
</style>
`;

// Add management-specific styles to head
document.head.insertAdjacentHTML('beforeend', managementStyles);