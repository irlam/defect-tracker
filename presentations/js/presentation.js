// Presentation JavaScript - McGoff Defect Tracker

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all presentation features
    initializeAnimations();
    initializeScrollEffects();
    initializeInteractiveElements();
    initializeChartAnimations();
});

// Animation initialization
function initializeAnimations() {
    // Add entrance animations with delays
    const animatedElements = document.querySelectorAll('.animate-fade-in, .animate-fade-in-delay, .animate-slide-up, .animate-float');

    animatedElements.forEach((element, index) => {
        if (element.classList.contains('animate-fade-in-delay')) {
            element.style.animationDelay = `${index * 0.2}s`;
        }
    });
}

// Scroll-based animations
function initializeScrollEffects() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
            }
        });
    }, observerOptions);

    // Observe all elements with animate-on-scroll class
    document.querySelectorAll('.animate-on-scroll').forEach(element => {
        observer.observe(element);
    });
}

// Interactive elements
function initializeInteractiveElements() {
    // Metric card hover effects
    const metricCards = document.querySelectorAll('.metric-card');
    metricCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            // Add pulse animation
            this.style.animation = 'pulse 0.6s ease-in-out';
        });

        card.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
    });

    // Feature card click effects
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.addEventListener('click', function() {
            // Add click ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'ripple-effect';
            ripple.style.left = '50%';
            ripple.style.top = '50%';
            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Stat circle hover effects
    const statCircles = document.querySelectorAll('.stat-circle');
    statCircles.forEach(circle => {
        circle.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.boxShadow = '0 12px 35px rgba(0, 0, 0, 0.2)';
        });

        circle.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
        });
    });
}

// Chart animations
function initializeChartAnimations() {
    const chartBars = document.querySelectorAll('.chart-bar');

    // Animate chart bars on page load with delay
    chartBars.forEach((bar, index) => {
        bar.style.animation = `growUp 0.8s ease-out ${index * 0.1}s both`;
    });

    // Add hover effects to chart bars
    chartBars.forEach(bar => {
        bar.addEventListener('mouseenter', function() {
            this.style.opacity = '0.8';
            this.style.transform = 'scaleY(1.1)';
        });

        bar.addEventListener('mouseleave', function() {
            this.style.opacity = '1';
            this.style.transform = 'scaleY(1)';
        });
    });
}

// Voice over simulation
function simulateVoiceOver() {
    const narrationTexts = document.querySelectorAll('.narration-text p');
    let currentIndex = 0;

    function highlightNextText() {
        // Remove previous highlights
        narrationTexts.forEach(p => {
            p.classList.remove('speaking');
        });

        // Add highlight to current text
        if (currentIndex < narrationTexts.length) {
            narrationTexts[currentIndex].classList.add('speaking');
            currentIndex++;
        } else {
            currentIndex = 0;
        }
    }

    // Start voice over simulation
    const voiceOverInterval = setInterval(highlightNextText, 3000);

    // Stop after all text is highlighted
    setTimeout(() => {
        clearInterval(voiceOverInterval);
        narrationTexts.forEach(p => {
            p.classList.remove('speaking');
        });
    }, narrationTexts.length * 3000 + 1000);
}

// Initialize voice over on page load
setTimeout(simulateVoiceOver, 2000);

// Add CSS for additional animations
const additionalStyles = `
<style>
@keyframes growUp {
    from {
        transform: scaleY(0);
        transform-origin: bottom;
    }
    to {
        transform: scaleY(1);
        transform-origin: bottom;
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.ripple-effect {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple 0.6s linear;
    pointer-events: none;
}

@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

.narration-text p.speaking {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
    border-left: 4px solid var(--primary-color);
    padding-left: 1rem;
    transition: all 0.3s ease;
    animation: speaking 0.5s ease-in-out;
}

@keyframes speaking {
    0% {
        background: transparent;
        border-left-color: transparent;
    }
    50% {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.2) 0%, rgba(37, 99, 235, 0.1) 100%);
        border-left-color: var(--primary-color);
    }
    100% {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
        border-left-color: var(--primary-color);
    }
}

/* Enhanced hover effects */
.feature-card:hover .feature-icon {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(37, 99, 235, 0.4);
}

.stat-explanation-card:hover .stat-circle {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
}

/* Loading animations */
.loading-bar {
    height: 4px;
    background: var(--gradient-primary);
    position: fixed;
    top: 0;
    left: 0;
    width: 0%;
    z-index: 9999;
    animation: loading 2s ease-out;
}

@keyframes loading {
    0% { width: 0%; }
    50% { width: 70%; }
    100% { width: 100%; opacity: 0; }
}

/* Success animations */
.success-checkmark {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--success-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    animation: checkmark 0.6s ease-out;
}

@keyframes checkmark {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.8;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
`;

// Add additional styles to head
document.head.insertAdjacentHTML('beforeend', additionalStyles);

// Add loading bar on page load
const loadingBar = document.createElement('div');
loadingBar.className = 'loading-bar';
document.body.appendChild(loadingBar);

// Remove loading bar after animation
setTimeout(() => {
    loadingBar.remove();
}, 2000);

// Add click tracking for analytics
document.addEventListener('click', function(e) {
    const target = e.target.closest('a, button, .feature-card, .stat-explanation-card');
    if (target) {
        // Track clicks for analytics
        console.log('Clicked element:', target.className || target.tagName);
    }
});

// Performance monitoring
if ('performance' in window) {
    window.addEventListener('load', function() {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log('Page load time:', perfData.loadEventEnd - perfData.fetchStart, 'ms');
        }, 0);
    });
}

// Add keyboard navigation support
document.addEventListener('keydown', function(e) {
    // Space bar to pause/play animations
    if (e.code === 'Space') {
        e.preventDefault();
        document.body.classList.toggle('animations-paused');
    }
});

// Add reduced motion support
if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transition', 'none');
    document.body.classList.add('reduced-motion');
}

// Presentation Gallery Functions
function showPresentationGallery() {
    const modal = new bootstrap.Modal(document.getElementById('presentationGalleryModal'));
    modal.show();
}

function navigateToPresentation(presentationUrl) {
    // Close the modal first
    const modal = bootstrap.Modal.getInstance(document.getElementById('presentationGalleryModal'));
    if (modal) {
        modal.hide();
    }

    // Navigate to the presentation
    window.location.href = presentationUrl;
}

function startPresentationTour() {
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('presentationGalleryModal'));
    if (modal) {
        modal.hide();
    }

    // Start a guided tour through all presentations
    const presentations = [
        'defect-creation.html',
        'defect-management.html',
        'user-management.html',
        'reporting.html',
        'notifications.html',
        'floor-plans.html',
        'mobile.html',
        'index.html' // Back to dashboard
    ];

    let currentIndex = 0;
    let tourInterval;

    function showNextPresentation() {
        if (currentIndex < presentations.length) {
            // Add a loading indicator
            showTourProgress(currentIndex + 1, presentations.length);

            // Navigate to next presentation after a delay
            setTimeout(() => {
                window.location.href = presentations[currentIndex];
                currentIndex++;
            }, 2000);
        } else {
            // Tour complete
            clearInterval(tourInterval);
        }
    }

    // Start the tour
    showNextPresentation();
    tourInterval = setInterval(showNextPresentation, 35000); // 35 seconds per presentation + 2 second delay
}

function showTourProgress(current, total) {
    // Remove existing progress indicator
    const existing = document.querySelector('.tour-progress');
    if (existing) existing.remove();

    // Create progress indicator
    const progress = document.createElement('div');
    progress.className = 'tour-progress';
    progress.innerHTML = `
        <div class="tour-progress-content">
            <div class="tour-progress-bar">
                <div class="tour-progress-fill" style="width: ${(current/total)*100}%"></div>
            </div>
            <div class="tour-progress-text">
                Guided Tour: Presentation ${current} of ${total}
                <button onclick="stopPresentationTour()" class="tour-stop-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(progress);

    // Auto-hide after 3 seconds
    setTimeout(() => {
        if (progress.parentNode) {
            progress.remove();
        }
    }, 3000);
}

function stopPresentationTour() {
    // Remove progress indicator and stop tour
    const progress = document.querySelector('.tour-progress');
    if (progress) progress.remove();

    // You could also clear any intervals here if needed
    // For now, just remove the progress indicator
}

// Add tour progress styles dynamically
const tourStyles = `
    .tour-progress {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 25px;
        padding: 0;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        animation: slideDown 0.3s ease-out;
    }

    .tour-progress-content {
        padding: 12px 20px;
        color: white;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .tour-progress-bar {
        width: 200px;
        height: 6px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
        overflow: hidden;
    }

    .tour-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #34d399);
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .tour-progress-text {
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tour-stop-btn {
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        cursor: pointer;
        padding: 2px;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .tour-stop-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .tour-progress-content {
            padding: 10px 15px;
        }

        .tour-progress-bar {
            width: 150px;
        }

        .tour-progress-text {
            font-size: 12px;
        }
    }
`;

// Add styles to document head
const styleSheet = document.createElement('style');
styleSheet.textContent = tourStyles;
document.head.appendChild(styleSheet);