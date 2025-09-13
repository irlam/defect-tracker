document.addEventListener('DOMContentLoaded', function() {
    // Animate stat cards
    const statCards = document.querySelectorAll('.stat-card');
    animateStatCards(statCards);
    
    // Initialize charts
    initializeCharts();
    
    // Add scroll animations
    addScrollAnimations();
});

// Function to animate the stat cards
function animateStatCards(cards) {
    cards.forEach((card, index) => {
        const targetValue = parseInt(card.getAttribute('data-value'));
        const valueDisplay = card.querySelector('.card-value');
        
        setTimeout(() => {
            animateCountUp(valueDisplay, targetValue);
        }, index * 200);
    });
}

// Animation for counting up
function animateCountUp(element, target) {
    let start = 0;
    const duration = 1500;
    const startTime = performance.now();
    
    function updateCount(currentTime) {
        const elapsedTime = currentTime - startTime;
        if (elapsedTime > duration) {
            element.textContent = target;
            return;
        }
        
        const progress = elapsedTime / duration;
        const currentValue = Math.floor(progress * target);
        element.textContent = currentValue;
        requestAnimationFrame(updateCount);
    }
    
    requestAnimationFrame(updateCount);
}

// Initialize and render charts
function initializeCharts() {
    // Defect Status Distribution Chart
    const statusCtx = document.getElementById('defectStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Open', 'In Progress', 'Pending', 'Completed', 'Verified', 'Rejected', 'Accepted'],
            datasets: [{
                data: [15, 28, 12, 35, 20, 8, 22],
                backgroundColor: [
                    '#3b82f6', // Open
                    '#6366f1', // In Progress
                    '#f59e0b', // Pending
                    '#10b981', // Completed
                    '#8b5cf6', // Verified
                    '#ef4444', // Rejected
                    '#14b8a6'  // Accepted
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Defect Resolution Timeline Chart
    const timelineCtx = document.getElementById('defectTimelineChart').getContext('2d');
    const timelineChart = new Chart(timelineCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                {
                    label: 'Reported',
                    data: [42, 55, 48, 58, 60, 53],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Resolved',
                    data: [30, 45, 40, 50, 58, 48],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Adjust canvas height
    const chartWrappers = document.querySelectorAll('.chart-wrapper');
    chartWrappers.forEach(wrapper => {
        const canvas = wrapper.querySelector('canvas');
        canvas.height = 300;
    });
}

// Add scroll animations
function addScrollAnimations() {
    // Elements to animate
    const elements = [
        ...document.querySelectorAll('.diagram-component'),
        ...document.querySelectorAll('.feature-card'),
        ...document.querySelectorAll('.schema-table'),
        ...document.querySelectorAll('.workflow-diagram'),
        ...document.querySelectorAll('.role-card')
    ];
    
    // Create observers
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fadeIn');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    // Observe elements
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
    
    // Animate architecture connectors
    anime({
        targets: '.connector',
        height: [0, '3rem'],
        duration: 1200,
        delay: 500,
        easing: 'easeOutQuad'
    });
}