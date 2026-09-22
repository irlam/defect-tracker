// Mobile Experience Presentation JavaScript
// Interactive mobile demo functionality

let currentScreen = 'dashboard';
let autoAdvanceEnabled = true;
let voiceOverPlaying = false;

// Initialize mobile demo
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileDemo();
    setupGestureHandlers();
    setupVoiceOver();
});

// Initialize mobile demo interface
function initializeMobileDemo() {
    // Set up initial screen
    switchMobileTab('dashboard');

    // Add touch event listeners
    const deviceScreen = document.getElementById('interactiveScreen');
    if (deviceScreen) {
        deviceScreen.addEventListener('touchstart', handleTouchStart, { passive: false });
        deviceScreen.addEventListener('touchmove', handleTouchMove, { passive: false });
        deviceScreen.addEventListener('touchend', handleTouchEnd, { passive: false });
    }

    // Initialize animations
    animateMobileElements();
}

// Mobile tab switching
function switchMobileTab(tabName) {
    // Hide all screens
    const screens = document.querySelectorAll('.mobile-app-screen');
    screens.forEach(screen => screen.classList.remove('active'));

    // Show selected screen
    const targetScreen = document.getElementById(tabName + '-screen');
    if (targetScreen) {
        targetScreen.classList.add('active');
        currentScreen = tabName;
    }

    // Update tab states
    const tabs = document.querySelectorAll('.app-tab');
    tabs.forEach(tab => tab.classList.remove('active'));

    const activeTab = document.querySelector(`[onclick="switchMobileTab('${tabName}')"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }

    // Add transition animation
    animateScreenTransition(tabName);
}

// Screen transition animations
function animateScreenTransition(screenName) {
    const screen = document.getElementById(screenName + '-screen');
    if (!screen) return;

    // Add slide animation
    screen.style.transform = 'translateX(100%)';
    screen.style.opacity = '0';

    setTimeout(() => {
        screen.style.transform = 'translateX(0)';
        screen.style.opacity = '1';
        screen.style.transition = 'all 0.3s ease-out';
    }, 50);
}

// Mobile menu toggle
function toggleMobileMenu() {
    const menu = document.querySelector('.mobile-menu');
    if (menu) {
        menu.classList.toggle('active');
    } else {
        // Create mobile menu if it doesn't exist
        createMobileMenu();
    }
}

// Create mobile menu overlay
function createMobileMenu() {
    const menuHTML = `
        <div class="mobile-menu-overlay" onclick="closeMobileMenu()">
            <div class="mobile-menu">
                <div class="menu-header">
                    <h3>Menu</h3>
                    <button onclick="closeMobileMenu()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="menu-items">
                    <a href="#" class="menu-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="menu-item">
                        <i class="fas fa-list"></i>
                        <span>My Defects</span>
                    </a>
                    <a href="#" class="menu-item">
                        <i class="fas fa-plus"></i>
                        <span>New Defect</span>
                    </a>
                    <a href="#" class="menu-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="#" class="menu-item">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <a href="#" class="menu-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', menuHTML);

    // Animate menu entrance
    setTimeout(() => {
        document.querySelector('.mobile-menu-overlay').classList.add('active');
    }, 10);
}

// Close mobile menu
function closeMobileMenu() {
    const overlay = document.querySelector('.mobile-menu-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.remove(), 300);
    }
}

// Show notifications
function showNotifications() {
    const notificationHTML = `
        <div class="mobile-notification-overlay" onclick="closeNotifications()">
            <div class="notification-panel">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <button onclick="closeNotifications()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="notification-list">
                    <div class="notification-item unread">
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Urgent: Electrical issue in Unit 1205</div>
                            <div class="notification-time">2 minutes ago</div>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Defect #1234 has been resolved</div>
                            <div class="notification-time">1 hour ago</div>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">New contractor assigned to your project</div>
                            <div class="notification-time">3 hours ago</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', notificationHTML);

    setTimeout(() => {
        document.querySelector('.mobile-notification-overlay').classList.add('active');
    }, 10);
}

// Close notifications
function closeNotifications() {
    const overlay = document.querySelector('.mobile-notification-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.remove(), 300);
    }
}

// View defect details
function viewDefect(defectId) {
    // Simulate navigation to defect detail screen
    const detailHTML = `
        <div class="mobile-app-screen" id="defect-detail-screen">
            <div class="mobile-app-header">
                <div class="app-nav">
                    <button class="nav-btn" onclick="goBackToDashboard()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="app-title">Defect #${defectId}</div>
                    <button class="nav-btn">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>

            <div class="mobile-app-content">
                <div class="defect-detail">
                    <div class="defect-header-detail">
                        <div class="defect-priority high">High Priority</div>
                        <div class="defect-status">In Progress</div>
                    </div>

                    <div class="defect-info">
                        <h3>Electrical outlet malfunction</h3>
                        <div class="defect-location">Unit 1205, Building A</div>
                        <div class="defect-description">
                            The electrical outlet in the kitchen is not functioning properly. No power output detected.
                        </div>
                    </div>

                    <div class="defect-photos">
                        <div class="photo-grid">
                            <div class="photo-item">
                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+Cjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOUI5QkE0IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5QaG90bzwvdGV4dD4KPHN2Zz4=" alt="Defect photo">
                            </div>
                        </div>
                    </div>

                    <div class="defect-actions">
                        <button class="action-btn primary" onclick="updateDefectStatus('completed')">
                            <i class="fas fa-check"></i>
                            Mark Complete
                        </button>
                        <button class="action-btn secondary" onclick="assignContractor()">
                            <i class="fas fa-user-plus"></i>
                            Assign Contractor
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Hide current screen and show detail
    const current = document.querySelector('.mobile-app-screen.active');
    if (current) current.classList.remove('active');

    document.getElementById('interactiveScreen').insertAdjacentHTML('beforeend', detailHTML);

    setTimeout(() => {
        document.getElementById('defect-detail-screen').classList.add('active');
    }, 10);
}

// Go back to dashboard
function goBackToDashboard() {
    const detailScreen = document.getElementById('defect-detail-screen');
    if (detailScreen) {
        detailScreen.classList.remove('active');
        setTimeout(() => detailScreen.remove(), 300);
    }

    switchMobileTab('dashboard');
}

// Filter defects
function filterDefects(filterType) {
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => tab.classList.remove('active'));

    const activeTab = document.querySelector(`[onclick="filterDefects('${filterType}')"]`);
    if (activeTab) activeTab.classList.add('active');

    // Animate filter change
    const defectList = document.querySelector('.defect-list');
    if (defectList) {
        defectList.style.opacity = '0.5';
        setTimeout(() => {
            defectList.style.opacity = '1';
        }, 200);
    }
}

// View defect details from list
function viewDefectDetails(defectId) {
    viewDefect(defectId);
}

// Take photo simulation
function takePhoto() {
    // Simulate camera interface
    const cameraHTML = `
        <div class="camera-overlay" onclick="closeCamera()">
            <div class="camera-interface">
                <div class="camera-header">
                    <button onclick="closeCamera()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="camera-title">Take Photo</div>
                    <button onclick="capturePhoto()">
                        <i class="fas fa-circle"></i>
                    </button>
                </div>
                <div class="camera-viewfinder">
                    <div class="viewfinder-content">
                        <div class="capture-guide">
                            <i class="fas fa-crosshairs"></i>
                        </div>
                        <div class="capture-hint">Position the defect in the center</div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', cameraHTML);

    setTimeout(() => {
        document.querySelector('.camera-overlay').classList.add('active');
    }, 10);
}

// Close camera
function closeCamera() {
    const overlay = document.querySelector('.camera-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.remove(), 300);
    }
}

// Capture photo
function capturePhoto() {
    // Simulate photo capture
    const viewfinder = document.querySelector('.viewfinder-content');
    if (viewfinder) {
        viewfinder.innerHTML = `
            <div class="photo-captured">
                <i class="fas fa-check-circle"></i>
                <div class="capture-message">Photo captured successfully!</div>
            </div>
        `;

        setTimeout(() => {
            closeCamera();
            addPhotoToPreview();
        }, 1500);
    }
}

// Add photo to preview
function addPhotoToPreview() {
    const preview = document.querySelector('.photo-preview');
    if (preview) {
        const photoHTML = `
            <div class="preview-photo">
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+Cjx0ZXh0IHg9IjUwIiB5PSI1NSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOUI5QkE0IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5QaG90bzwvdGV4dD4KPHN2Zz4=" alt="Captured photo">
                <button class="remove-photo" onclick="removePhoto(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        preview.insertAdjacentHTML('beforeend', photoHTML);
    }
}

// Remove photo
function removePhoto(button) {
    const photo = button.closest('.preview-photo');
    if (photo) {
        photo.remove();
    }
}

// Choose from gallery
function chooseFromGallery() {
    // Simulate gallery selection
    addPhotoToPreview();
}

// Get current location
function getCurrentLocation() {
    const locationBtn = document.querySelector('.location-btn');
    if (locationBtn) {
        locationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';
        locationBtn.disabled = true;

        setTimeout(() => {
            locationBtn.innerHTML = '<i class="fas fa-check"></i> Location set: Unit 1205';
            locationBtn.disabled = false;

            // Update location input
            const locationInput = document.querySelector('input[placeholder="Room number or area"]');
            if (locationInput) {
                locationInput.value = 'Unit 1205, Building A';
            }
        }, 2000);
    }
}

// Save defect
function saveDefect() {
    // Simulate saving
    const saveBtn = document.querySelector('.app-nav .nav-btn[onclick="saveDefect()"]');
    if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        saveBtn.disabled = true;

        setTimeout(() => {
            saveBtn.innerHTML = '<i class="fas fa-check"></i>';
            showSuccessMessage('Defect saved successfully!');

            setTimeout(() => {
                goBackToDashboard();
            }, 1500);
        }, 2000);
    }
}

// Show success message
function showSuccessMessage(message) {
    const toast = document.createElement('div');
    toast.className = 'mobile-toast success';
    toast.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Touch gesture handling
let touchStartX = 0;
let touchStartY = 0;
let touchEndX = 0;
let touchEndY = 0;

function handleTouchStart(e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
}

function handleTouchMove(e) {
    if (!touchStartX || !touchStartY) return;

    touchEndX = e.touches[0].clientX;
    touchEndY = e.touches[0].clientY;
}

function handleTouchEnd(e) {
    if (!touchStartX || !touchStartY) return;

    const deltaX = touchEndX - touchStartX;
    const deltaY = touchEndY - touchStartY;
    const absDeltaX = Math.abs(deltaX);
    const absDeltaY = Math.abs(deltaY);

    // Determine gesture type
    if (absDeltaX > absDeltaY && absDeltaX > 50) {
        // Horizontal swipe
        if (deltaX > 0) {
            handleSwipeRight();
        } else {
            handleSwipeLeft();
        }
    } else if (absDeltaY > absDeltaX && absDeltaY > 50) {
        // Vertical swipe
        if (deltaY > 0) {
            handleSwipeDown();
        } else {
            handleSwipeUp();
        }
    } else {
        // Tap
        handleTap(e);
    }

    // Reset touch coordinates
    touchStartX = 0;
    touchStartY = 0;
    touchEndX = 0;
    touchEndY = 0;
}

// Gesture handlers
function handleSwipeRight() {
    // Navigate back or to previous screen
    if (currentScreen !== 'dashboard') {
        goBackToDashboard();
    }
}

function handleSwipeLeft() {
    // Navigate to next logical screen
    const screenOrder = ['dashboard', 'defects', 'add', 'reports', 'profile'];
    const currentIndex = screenOrder.indexOf(currentScreen);
    if (currentIndex < screenOrder.length - 1) {
        switchMobileTab(screenOrder[currentIndex + 1]);
    }
}

function handleSwipeUp() {
    // Scroll content up
    const content = document.querySelector('.mobile-app-content');
    if (content) {
        content.scrollTop += 100;
    }
}

function handleSwipeDown() {
    // Scroll content down
    const content = document.querySelector('.mobile-app-content');
    if (content) {
        content.scrollTop -= 100;
    }
}

function handleTap(e) {
    // Handle tap on interactive elements
    const target = e.target.closest('.activity-item, .defect-card, .app-tab, .action-btn');
    if (target) {
        target.click();
    }
}

// Demo control functions
function simulateTap() {
    // Simulate a tap on the current active screen
    const activeScreen = document.querySelector('.mobile-app-screen.active');
    if (activeScreen) {
        const interactiveElement = activeScreen.querySelector('.activity-item, .defect-card, .app-tab');
        if (interactiveElement) {
            interactiveElement.click();
            showGestureFeedback('tap');
        }
    }
}

function simulateSwipe() {
    // Simulate swipe to next screen
    handleSwipeLeft();
    showGestureFeedback('swipe');
}

function simulatePinch() {
    // Simulate pinch gesture (for floor plans)
    showGestureFeedback('pinch');
    // Could trigger zoom functionality if on floor plan screen
}

// Show gesture feedback
function showGestureFeedback(gesture) {
    const feedback = document.createElement('div');
    feedback.className = 'gesture-feedback';
    feedback.innerHTML = `
        <i class="fas fa-${gesture === 'tap' ? 'hand-point-up' : gesture === 'swipe' ? 'arrows-alt-h' : 'search-plus'}"></i>
        <span>${gesture.charAt(0).toUpperCase() + gesture.slice(1)} gesture simulated</span>
    `;

    document.body.appendChild(feedback);

    setTimeout(() => {
        feedback.classList.add('show');
    }, 10);

    setTimeout(() => {
        feedback.classList.remove('show');
        setTimeout(() => feedback.remove(), 300);
    }, 2000);
}

// Voice over functionality
function setupVoiceOver() {
    // Voice over text for mobile presentation
    const voiceOverText = `
        Welcome to our mobile-first defect tracking experience. Our responsive design ensures that every feature works seamlessly across smartphones, tablets, and desktops.

        With instant camera capture, you can photograph defects directly within the app using your device's native camera with automatic image enhancement and defect detection.

        Voice-activated reporting lets you dictate defect reports hands-free while inspecting, with intelligent categorization and real-time transcription.

        GPS-enabled navigation provides precise indoor and outdoor navigation to defect locations with turn-by-turn directions and estimated arrival times.

        And offline capability means you can continue working without internet connection, with all data syncing automatically when connectivity is restored.

        Experience the future of mobile defect management with our comprehensive mobile solution.
    `;

    // Store voice over text for narration
    window.mobileVoiceOverText = voiceOverText;
}

function playVoiceOver() {
    if (voiceOverPlaying) return;

    voiceOverPlaying = true;
    const playBtn = document.querySelector('[onclick="playVoiceOver()"]');
    if (playBtn) {
        playBtn.innerHTML = '<i class="fas fa-stop"></i> Playing...';
        playBtn.onclick = stopVoiceOver;
    }

    // Simulate voice over with text highlighting
    const text = window.mobileVoiceOverText;
    const words = text.split(' ');
    let wordIndex = 0;

    const voiceOverInterval = setInterval(() => {
        if (wordIndex >= words.length || !voiceOverPlaying) {
            clearInterval(voiceOverInterval);
            stopVoiceOver();
            return;
        }

        // Highlight current word (simplified simulation)
        console.log('Speaking:', words[wordIndex]);
        wordIndex++;
    }, 200); // Adjust timing for realistic speech pace
}

function stopVoiceOver() {
    voiceOverPlaying = false;
    const playBtn = document.querySelector('[onclick="playVoiceOver()"]');
    if (playBtn) {
        playBtn.innerHTML = '<i class="fas fa-play"></i> Play Narration';
        playBtn.onclick = playVoiceOver;
    }
}

function toggleAutoAdvance() {
    autoAdvanceEnabled = !autoAdvanceEnabled;
    const statusEl = document.getElementById('autoAdvanceStatus');
    if (statusEl) {
        statusEl.textContent = autoAdvanceEnabled ? 'ON' : 'OFF';
    }
}

// Animate mobile elements on load
function animateMobileElements() {
    // Animate phone mockup entrance
    const phoneMockup = document.querySelector('.phone-mockup');
    if (phoneMockup) {
        phoneMockup.style.opacity = '0';
        phoneMockup.style.transform = 'translateY(50px)';

        setTimeout(() => {
            phoneMockup.style.transition = 'all 0.8s ease-out';
            phoneMockup.style.opacity = '1';
            phoneMockup.style.transform = 'translateY(0)';
        }, 500);
    }

    // Animate gesture hints
    const gestureHints = document.querySelectorAll('.gesture-hint');
    gestureHints.forEach((hint, index) => {
        hint.style.opacity = '0';
        hint.style.transform = 'translateX(-30px)';

        setTimeout(() => {
            hint.style.transition = 'all 0.6s ease-out';
            hint.style.opacity = '1';
            hint.style.transform = 'translateX(0)';
        }, 1000 + (index * 200));
    });

    // Animate mobile features
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.8s ease-out forwards';
            }
        });
    }, {
        threshold: 0.1
    });

    document.querySelectorAll('.mobile-feature-card').forEach(card => {
        observer.observe(card);
    });
}

// Download app simulation
function downloadApp() {
    showSuccessMessage('App download started! Check your app store.');
}

// View demo video simulation
function viewDemoVideo() {
    const videoModal = document.createElement('div');
    videoModal.className = 'video-modal-overlay';
    videoModal.innerHTML = `
        <div class="video-modal">
            <div class="video-header">
                <h3>Mobile App Demo Video</h3>
                <button onclick="closeVideoModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="video-content">
                <div class="video-placeholder">
                    <i class="fas fa-play-circle"></i>
                    <div class="video-title">Mobile Defect Tracking Demo</div>
                    <div class="video-duration">2:34</div>
                </div>
                <p>This demo video showcases the full mobile experience, including camera capture, voice reporting, GPS navigation, and offline functionality.</p>
            </div>
        </div>
    `;

    document.body.appendChild(videoModal);

    setTimeout(() => {
        videoModal.classList.add('active');
    }, 10);
}

// Close video modal
function closeVideoModal() {
    const modal = document.querySelector('.video-modal-overlay');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.remove(), 300);
    }
}

// Update defect status
function updateDefectStatus(status) {
    showSuccessMessage(`Defect status updated to ${status}!`);
}

// Assign contractor
function assignContractor() {
    // Simulate contractor assignment
    const contractors = ['John Smith', 'Sarah Johnson', 'Mike Davis', 'Lisa Chen'];
    const randomContractor = contractors[Math.floor(Math.random() * contractors.length)];

    setTimeout(() => {
        showSuccessMessage(`Assigned to ${randomContractor}`);
    }, 1000);
}

// Show defect filters
function showDefectFilters() {
    // Toggle filter visibility
    const filters = document.querySelector('.defect-filters');
    if (filters) {
        filters.classList.toggle('active');
    }
}