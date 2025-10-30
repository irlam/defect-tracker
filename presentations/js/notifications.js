// Notifications Demo JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeNotificationDemo();
    initializeNotificationCenter();
    initializeSettings();
    startNotificationSimulation();
});

function initializeNotificationDemo() {
    // Initialize notification demo components
    setupNotificationAnimations();
    initializeToastSystem();
}

function setupNotificationAnimations() {
    // Add entrance animations to notification items
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('animate-in');
    });
}

function initializeToastSystem() {
    // Create toast container if it doesn't exist
    if (!document.querySelector('.toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
}

function showToast(message, type = 'info', duration = 4000) {
    const toastContainer = document.querySelector('.toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} animate-in`;

    const iconMap = {
        'success': 'check-circle',
        'error': 'times-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${iconMap[type] || 'info-circle'}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="closeToast(this)">
            <i class="fas fa-times"></i>
        </button>
    `;

    toastContainer.appendChild(toast);

    // Auto remove after duration
    setTimeout(() => {
        closeToast(toast.querySelector('.toast-close'));
    }, duration);
}

function closeToast(button) {
    const toast = button.closest('.toast');
    toast.classList.add('animate-out');
    setTimeout(() => {
        toast.remove();
    }, 300);
}

function initializeNotificationCenter() {
    // Initialize notification center functionality
    updateNotificationCounts();
    setupNotificationInteractions();
}

function updateNotificationCounts() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    const urgentCount = document.querySelectorAll('.notification-item.urgent').length;

    // Update any notification badges in the UI
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        if (badge.classList.contains('unread')) {
            badge.textContent = unreadCount;
        } else if (badge.classList.contains('urgent')) {
            badge.textContent = urgentCount;
        }
    });
}

function setupNotificationInteractions() {
    // Add click handlers for notification actions
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        // Mark as read when clicked
        item.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-actions') && !e.target.closest('.notification-checkbox')) {
                markAsRead(this);
            }
        });
    });
}

function filterNotifications() {
    const filterValue = document.getElementById('notificationFilter').value;
    const notificationItems = document.querySelectorAll('.notification-item');

    notificationItems.forEach(item => {
        const itemType = item.dataset.type;
        const shouldShow = filterValue === 'all' || itemType === filterValue;

        if (shouldShow) {
            item.style.display = 'flex';
            item.classList.add('animate-in');
        } else {
            item.style.display = 'none';
            item.classList.remove('animate-in');
        }
    });

    updateNotificationCounts();
}

function sortNotifications() {
    const sortValue = document.getElementById('notificationSort').value;
    const notificationCenter = document.querySelector('.notification-center');
    const notificationItems = Array.from(document.querySelectorAll('.notification-item'));

    notificationItems.sort((a, b) => {
        switch(sortValue) {
            case 'newest':
                return getNotificationTime(b) - getNotificationTime(a);
            case 'oldest':
                return getNotificationTime(a) - getNotificationTime(b);
            case 'priority':
                return getPriorityValue(b) - getPriorityValue(a);
            case 'unread':
                return (b.classList.contains('unread') ? 1 : 0) - (a.classList.contains('unread') ? 1 : 0);
            default:
                return 0;
        }
    });

    // Reorder DOM elements
    notificationItems.forEach(item => {
        notificationCenter.appendChild(item);
    });
}

function getNotificationTime(item) {
    const timeText = item.querySelector('.notification-time').textContent;
    // Simple time parsing for demo
    if (timeText.includes('minutes ago')) {
        return Date.now() - (parseInt(timeText) * 60 * 1000);
    } else if (timeText.includes('hour ago')) {
        return Date.now() - (parseInt(timeText) * 60 * 60 * 1000);
    } else if (timeText.includes('hours ago')) {
        return Date.now() - (parseInt(timeText) * 60 * 60 * 1000);
    }
    return Date.now();
}

function getPriorityValue(item) {
    if (item.classList.contains('urgent')) return 3;
    if (item.dataset.priority === 'high') return 2;
    if (item.dataset.priority === 'medium') return 1;
    return 0;
}

function markAsRead(notificationItem) {
    notificationItem.classList.remove('unread');
    updateNotificationCounts();
    showToast('Notification marked as read', 'success', 2000);
}

function markAllRead() {
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    unreadItems.forEach(item => {
        item.classList.remove('unread');
    });
    updateNotificationCounts();
    showToast(`Marked ${unreadItems.length} notifications as read`, 'success');
}

function clearAllNotifications() {
    if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        const notificationCenter = document.querySelector('.notification-center');
        const notificationItems = notificationCenter.querySelectorAll('.notification-item');

        notificationItems.forEach(item => {
            item.classList.add('animate-out');
            setTimeout(() => item.remove(), 300);
        });

        updateNotificationCounts();
        showToast('All notifications cleared', 'info');
    }
}

function toggleSelection(checkbox) {
    const notificationItem = checkbox.closest('.notification-item');
    notificationItem.classList.toggle('selected', checkbox.checked);
}

function nextPage() {
    // Simulate pagination
    showToast('Loading next page...', 'info', 1500);
    setTimeout(() => {
        showToast('Page loaded successfully', 'success');
    }, 1500);
}

function viewDefect(defectId) {
    showToast(`Opening defect #${defectId}...`, 'info');
    // Simulate navigation
    setTimeout(() => {
        showToast('Defect details loaded', 'success');
    }, 1000);
}

function assignToMe() {
    showToast('Assigning defect to you...', 'info');
    setTimeout(() => {
        showToast('Defect assigned successfully', 'success');
    }, 1000);
}

function snoozeNotification() {
    showToast('Notification snoozed for 1 hour', 'info');
}

function viewProject() {
    showToast('Opening project dashboard...', 'info');
}

function escalateIssue() {
    if (confirm('Are you sure you want to escalate this issue?')) {
        showToast('Issue escalated to management', 'warning');
    }
}

function viewHistory() {
    showToast('Loading defect history...', 'info');
    setTimeout(() => {
        showToast('History loaded', 'success');
    }, 1000);
}

function replyToComment() {
    showToast('Opening comment composer...', 'info');
}

function initializeSettings() {
    // Initialize settings tabs
    showSettingsTab('general');
}

function showSettingsTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.settings-tab');
    tabs.forEach(tab => tab.classList.remove('active'));

    // Remove active class from nav items
    const navItems = document.querySelectorAll('.settings-nav .nav-item');
    navItems.forEach(item => item.classList.remove('active'));

    // Show selected tab
    const selectedTab = document.getElementById(`${tabName}-settings`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    // Add active class to clicked nav item
    event.target.closest('.nav-item').classList.add('active');
}

function toggleSetting(settingType) {
    const status = event.target.checked ? 'enabled' : 'disabled';
    showToast(`${settingType.charAt(0).toUpperCase() + settingType.slice(1)} notifications ${status}`, 'info', 2000);
}

function addNewRule() {
    showToast('Opening rule builder...', 'info');
    setTimeout(() => {
        showToast('New rule created successfully', 'success');
    }, 1500);
}

function playVoiceOver() {
    const voiceOverText = document.querySelector('.voice-over-text');
    const text = voiceOverText.textContent;
    const words = text.split(' ');

    // Clear existing highlights
    voiceOverText.innerHTML = text;

    // Simulate voice over with text highlighting
    let wordIndex = 0;
    const highlightInterval = setInterval(() => {
        if (wordIndex < words.length) {
            // Create highlighted version
            const beforeHighlight = words.slice(0, wordIndex).join(' ');
            const currentWord = words[wordIndex];
            const afterHighlight = words.slice(wordIndex + 1).join(' ');

            voiceOverText.innerHTML = `${beforeHighlight} <span class="highlight-word">${currentWord}</span> ${afterHighlight}`;

            wordIndex++;
        } else {
            clearInterval(highlightInterval);
            // Reset after completion
            setTimeout(() => {
                voiceOverText.innerHTML = text;
            }, 2000);
        }
    }, 200); // Adjust speed as needed

    showToast('Playing voice narration...', 'info', 2000);
}

function toggleAutoAdvance() {
    const statusElement = document.getElementById('autoAdvanceStatus');
    const currentStatus = statusElement.textContent;
    const newStatus = currentStatus === 'ON' ? 'OFF' : 'ON';

    statusElement.textContent = newStatus;
    showToast(`Auto advance ${newStatus.toLowerCase()}`, 'info', 1500);
}

function startNotificationSimulation() {
    // Simulate real-time notifications
    let notificationCount = 0;

    const simulationInterval = setInterval(() => {
        if (notificationCount >= 3) {
            clearInterval(simulationInterval);
            return;
        }

        // Create a new notification
        createSimulatedNotification();
        notificationCount++;
    }, 8000); // New notification every 8 seconds
}

function createSimulatedNotification() {
    const notificationTypes = [
        {
            type: 'urgent',
            icon: 'exclamation-triangle',
            title: 'New Critical Defect',
            message: 'Emergency plumbing issue reported in Building B, Floor 5. Water damage detected.',
            priority: 'high'
        },
        {
            type: 'assignments',
            icon: 'user-tag',
            title: 'Defect Assignment',
            message: 'You have been assigned to electrical repair in Unit 2301.',
            priority: 'medium'
        },
        {
            type: 'updates',
            icon: 'check-circle',
            title: 'Status Update',
            message: 'Defect #1250 resolved by contractor team. Quality check completed.',
            priority: 'low'
        }
    ];

    const randomType = notificationTypes[Math.floor(Math.random() * notificationTypes.length)];
    const notificationCenter = document.querySelector('.notification-center');

    const notificationHTML = `
        <div class="notification-item unread ${randomType.type}" data-type="${randomType.type}" data-priority="${randomType.priority}" style="display: none;">
            <div class="notification-checkbox">
                <input type="checkbox" onchange="toggleSelection(this)">
            </div>
            <div class="notification-icon">
                <i class="fas fa-${randomType.icon}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-header">
                    <div class="notification-title">${randomType.title}</div>
                    <div class="notification-time">Just now</div>
                </div>
                <div class="notification-message">
                    ${randomType.message}
                </div>
                <div class="notification-meta">
                    <span class="meta-tag priority-${randomType.priority}">${randomType.priority.charAt(0).toUpperCase() + randomType.priority.slice(1)} Priority</span>
                    <span class="meta-tag type-${randomType.type}">${randomType.type.charAt(0).toUpperCase() + randomType.type.slice(1)}</span>
                    <span class="meta-project">Project: Demo Site</span>
                </div>
            </div>
            <div class="notification-actions">
                <button class="btn btn-sm btn-primary" onclick="viewDefect(12${Math.floor(Math.random() * 100)})">
                    <i class="fas fa-eye"></i>
                    View
                </button>
                <button class="btn btn-sm btn-success" onclick="assignToMe()">
                    <i class="fas fa-user-plus"></i>
                    Assign
                </button>
            </div>
        </div>
    `;

    // Add to top of notification center
    notificationCenter.insertAdjacentHTML('afterbegin', notificationHTML);

    // Animate in
    const newNotification = notificationCenter.firstElementChild;
    setTimeout(() => {
        newNotification.style.display = 'flex';
        newNotification.classList.add('animate-in');
    }, 100);

    // Show toast notification
    showToast(`New ${randomType.type} notification received`, 'info');

    // Update counts
    updateNotificationCounts();

    // Auto-scroll to top if needed
    notificationCenter.scrollTop = 0;
}

function startDemo() {
    showToast('Starting interactive demo...', 'info');
    setTimeout(() => {
        showToast('Demo initialized! Try interacting with notifications.', 'success');
    }, 1000);
}

function scheduleCall() {
    showToast('Opening scheduling calendar...', 'info');
    setTimeout(() => {
        showToast('Consultation scheduled for tomorrow at 2 PM', 'success');
    }, 1500);
}

// Add CSS for notifications demo styles
const notificationStyles = `
<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4rem 0;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    pointer-events: none;
}

.hero-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
}

.hero-content h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.hero-content p {
    font-size: 1.25rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    line-height: 1.6;
}

.hero-stats {
    display: flex;
    gap: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    display: block;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-top: 0.25rem;
}

.hero-visual {
    display: flex;
    justify-content: center;
}

.notification-demo {
    position: relative;
}

.phone-mockup {
    width: 300px;
    height: 600px;
    background: #000;
    border-radius: 30px;
    padding: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    position: relative;
}

.phone-mockup::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 50%;
    transform: translateX(-50%);
    width: 150px;
    height: 25px;
    background: #000;
    border-radius: 15px;
    z-index: 1;
}

.phone-screen {
    width: 100%;
    height: 100%;
    background: #1a1a1a;
    border-radius: 20px;
    padding: 20px;
    overflow: hidden;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification-item {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.notification-item .notification-icon {
    color: white;
    margin-bottom: 8px;
}

.notification-item .notification-title {
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 4px;
}

.notification-item .notification-message {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
    line-height: 1.3;
}

.notification-item .notification-time {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.7rem;
    margin-top: 4px;
}

/* Voice Over Section */
.voice-over-section {
    padding: 3rem 0;
    background: white;
}

.voice-over-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.voice-over-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.voice-over-header i {
    font-size: 2rem;
    color: #2563eb;
}

.voice-over-header h3 {
    margin: 0;
    color: #1e293b;
    font-size: 1.5rem;
    font-weight: 600;
}

.voice-over-text {
    font-size: 1.1rem;
    line-height: 1.6;
    color: #374151;
    margin-bottom: 1.5rem;
    font-style: italic;
}

.highlight-word {
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    animation: highlightPulse 0.3s ease;
}

@keyframes highlightPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.voice-over-controls {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Notification Types */
.notification-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.notification-type-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.notification-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.type-icon {
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

.notification-type-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.notification-type-card p {
    color: #64748b;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.type-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

.feature-tag {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    color: #2563eb;
}

/* Notification Center */
.notification-center-demo {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.center-controls {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.control-group label {
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.control-group select {
    padding: 0.5rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.control-group select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.control-actions {
    display: flex;
    gap: 1rem;
}

.notification-center {
    max-height: 600px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
    border-left: 4px solid #2563eb;
}

.notification-item.urgent {
    border-left-color: #dc2626;
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.05) 0%, rgba(239, 68, 68, 0.02) 100%);
}

.notification-item.selected {
    background: rgba(37, 99, 235, 0.1);
}

.notification-checkbox {
    margin-top: 0.25rem;
}

.notification-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #2563eb;
}

.notification-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.notification-item.urgent .notification-icon {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.notification-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 1rem;
}

.notification-time {
    color: #6b7280;
    font-size: 0.8rem;
    white-space: nowrap;
    margin-left: 1rem;
}

.notification-message {
    color: #374151;
    line-height: 1.5;
    margin-bottom: 0.75rem;
}

.notification-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.meta-tag {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.meta-tag.priority-high {
    background: #fef2f2;
    color: #dc2626;
}

.meta-tag.priority-medium {
    background: #fef3c7;
    color: #d97706;
}

.meta-tag.priority-low {
    background: #f0fdf4;
    color: #059669;
}

.meta-tag.type-urgent {
    background: #fee2e2;
    color: #dc2626;
}

.meta-tag.type-assignment {
    background: #e0f2fe;
    color: #0369a1;
}

.meta-tag.type-update {
    background: #f0fdf4;
    color: #059669;
}

.meta-tag.type-deadline {
    background: #fef3c7;
    color: #d97706;
}

.meta-tag.type-comment {
    background: #f3e8ff;
    color: #7c3aed;
}

.meta-project {
    color: #6b7280;
    font-size: 0.8rem;
    font-weight: 500;
}

.notification-actions {
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.notification-item:hover .notification-actions {
    opacity: 1;
}

.center-footer {
    padding: 1rem 2rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.9rem;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-indicator {
    color: #374151;
    font-weight: 500;
}

/* Settings */
.settings-demo {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.settings-sidebar {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 2rem 0;
}

.settings-nav {
    display: flex;
    flex-direction: column;
}

.nav-item {
    padding: 1rem 2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.nav-item:hover {
    background: rgba(37, 99, 235, 0.1);
}

.nav-item.active {
    background: rgba(37, 99, 235, 0.1);
    border-left-color: #2563eb;
    color: #2563eb;
}

.nav-item i {
    font-size: 1.25rem;
}

.settings-content {
    padding: 2rem;
}

.settings-tab {
    display: none;
}

.settings-tab.active {
    display: block;
}

.settings-tab h4 {
    margin-bottom: 2rem;
    color: #1e293b;
    font-size: 1.5rem;
    font-weight: 600;
}

.setting-group {
    margin-bottom: 2rem;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-info label {
    display: block;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.setting-info p {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 0;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #2563eb;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.channel-config {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.channel-item {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
}

.channel-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.channel-header i {
    font-size: 1.5rem;
    color: #2563eb;
}

.channel-header h5 {
    margin: 0;
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 600;
}

.channel-settings {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.setting-row label {
    font-weight: 500;
    color: #374151;
}

.setting-row select,
.setting-row input {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 0.9rem;
}

.rules-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.rule-item {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e2e8f0;
}

.rule-condition,
.rule-action {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.rule-condition strong,
.rule-action strong {
    color: #2563eb;
}

.rule-controls {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.schedule-settings {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.time-settings {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.time-input {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.time-input label {
    font-weight: 500;
    color: #374151;
    font-size: 0.9rem;
}

.weekday-settings {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.weekday-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.weekday-toggle input {
    width: 16px;
    height: 16px;
    accent-color: #2563eb;
}

.setting-description {
    color: #6b7280;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

/* Benefits */
.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.benefit-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.benefit-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.benefit-icon {
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

.benefit-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.benefit-card p {
    color: #64748b;
    line-height: 1.6;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 100px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.toast {
    background: white;
    border-radius: 8px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    padding: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    min-width: 300px;
    max-width: 400px;
    border-left: 4px solid #2563eb;
    transform: translateX(400px);
    transition: transform 0.3s ease;
}

.toast.animate-in {
    transform: translateX(0);
}

.toast.animate-out {
    transform: translateX(400px);
}

.toast.toast-success {
    border-left-color: #10b981;
}

.toast.toast-error {
    border-left-color: #ef4444;
}

.toast.toast-warning {
    border-left-color: #f59e0b;
}

.toast-icon {
    color: #2563eb;
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.toast.toast-success .toast-icon { color: #10b981; }
.toast.toast-error .toast-icon { color: #ef4444; }
.toast.toast-warning .toast-icon { color: #f59e0b; }

.toast-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.toast-message {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    margin-left: auto;
}

.toast-close:hover {
    background: #f3f4f6;
    color: #374151;
}

/* Animations */
@keyframes animateIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: animateIn 0.5s ease-out;
}

.animate-out {
    animation: animateIn 0.3s ease-out reverse;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-container {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
    }

    .hero-stats {
        justify-content: center;
    }

    .notification-types-grid {
        grid-template-columns: 1fr;
    }

    .center-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .control-actions {
        justify-content: center;
    }

    .notification-item {
        flex-direction: column;
        gap: 1rem;
    }

    .notification-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .notification-time {
        margin-left: 0;
    }

    .notification-actions {
        opacity: 1;
        justify-content: center;
    }

    .settings-demo {
        grid-template-columns: 1fr;
    }

    .settings-sidebar {
        order: 2;
    }

    .benefits-grid {
        grid-template-columns: 1fr;
    }

    .toast-container {
        left: 20px;
        right: 20px;
        top: 20px;
    }

    .toast {
        min-width: auto;
    }
}
</style>
`;

// Add notification-specific styles to head
document.head.insertAdjacentHTML('beforeend', notificationStyles);