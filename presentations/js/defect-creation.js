// Defect Creation Demo JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeDemoWorkflow();
    initializeInteractiveElements();
});

function initializeDemoWorkflow() {
    const steps = document.querySelectorAll('.demo-step');
    const progressSteps = document.querySelectorAll('.progress-steps .step');
    const progressFill = document.getElementById('progressFill');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    let currentStep = 1;
    const totalSteps = steps.length;

    function updateWorkflow() {
        // Update step visibility
        steps.forEach((step, index) => {
            if (index + 1 === currentStep) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });

        // Update progress bar
        const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressFill.style.width = progress + '%';

        // Update progress steps
        progressSteps.forEach((step, index) => {
            if (index + 1 <= currentStep) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });

        // Update workflow visualization
        updateWorkflowVisualization();

        // Update buttons
        prevBtn.disabled = currentStep === 1;
        nextBtn.style.display = currentStep === totalSteps ? 'none' : 'inline-block';
        submitBtn.style.display = currentStep === totalSteps ? 'block' : 'none';

        // Update navigation text
        if (currentStep === totalSteps) {
            nextBtn.style.display = 'none';
        }
    }

    function updateWorkflowVisualization() {
        const workflowSteps = document.querySelectorAll('.workflow-step');
        workflowSteps.forEach((step, index) => {
            if (index + 1 <= currentStep) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }

    // Navigation event listeners
    nextBtn.addEventListener('click', function() {
        if (currentStep < totalSteps) {
            currentStep++;
            updateWorkflow();
        }
    });

    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            updateWorkflow();
        }
    });

    // Submit button functionality
    submitBtn.addEventListener('click', function() {
        simulateDefectSubmission();
    });

    // Initialize
    updateWorkflow();
}

function initializeInteractiveElements() {
    // Floor plan pin interaction
    const floorplanImage = document.querySelector('.floorplan-image');
    const locationPin = document.querySelector('.location-pin');
    const coordinates = document.querySelector('.coordinates');

    if (floorplanImage && locationPin) {
        floorplanImage.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            // Update pin position
            locationPin.style.left = x + 'px';
            locationPin.style.top = y + 'px';

            // Update coordinates display
            coordinates.textContent = `Coordinates: X: ${Math.round(x)}, Y: ${Math.round(y)}`;

            // Add click animation
            locationPin.style.animation = 'pinDrop 0.5s ease-out';
            setTimeout(() => {
                locationPin.style.animation = '';
            }, 500);
        });
    }

    // Image upload simulation
    const uploadZone = document.querySelector('.upload-zone');
    if (uploadZone) {
        uploadZone.addEventListener('click', function() {
            // Simulate file selection
            this.style.background = 'linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%)';
            this.style.borderColor = 'var(--primary-color)';

            setTimeout(() => {
                this.style.background = '';
                this.style.borderColor = '#dee2e6';
            }, 300);
        });

        // Drag and drop simulation
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = 'linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%)';
            this.style.borderColor = 'var(--primary-color)';
        });

        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.background = '';
            this.style.borderColor = '#dee2e6';
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '';
            this.style.borderColor = '#dee2e6';

            // Simulate successful upload
            showUploadSuccess();
        });
    }

    // Form field interactions
    const formInputs = document.querySelectorAll('.demo-form input, .demo-form select, .demo-form textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Add visual feedback
            this.style.boxShadow = '0 0 0 2px rgba(37, 99, 235, 0.2)';
            setTimeout(() => {
                this.style.boxShadow = '';
            }, 300);
        });
    });
}

function simulateDefectSubmission() {
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;

    // Change button to loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Defect...';
    submitBtn.disabled = true;

    // Simulate processing time
    setTimeout(() => {
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Defect Created Successfully!';
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-success', 'success');

        // Show success animation
        showSuccessAnimation();

        // Simulate notifications
        setTimeout(() => {
            showNotificationToast();
        }, 1000);

    }, 2000);
}

function showUploadSuccess() {
    // Create success message
    const successMsg = document.createElement('div');
    successMsg.className = 'upload-success';
    successMsg.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>Images uploaded successfully!</span>
    `;

    const uploadZone = document.querySelector('.upload-zone');
    uploadZone.appendChild(successMsg);

    setTimeout(() => {
        successMsg.remove();
    }, 3000);
}

function showSuccessAnimation() {
    // Create success overlay
    const overlay = document.createElement('div');
    overlay.className = 'success-overlay';
    overlay.innerHTML = `
        <div class="success-modal">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>Defect Created Successfully!</h3>
            <p>Notifications sent to assigned contractor and project manager.</p>
            <div class="success-details">
                <div class="detail-item">
                    <i class="fas fa-id-badge"></i>
                    <span>Defect ID: DT-2025-001</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-user"></i>
                    <span>Assigned to: XYZ Contractors Ltd.</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span>Due Date: March 15, 2025</span>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    setTimeout(() => {
        overlay.remove();
    }, 5000);
}

function showNotificationToast() {
    const toast = document.createElement('div');
    toast.className = 'notification-toast';
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-bell"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">Notifications Sent</div>
            <div class="toast-message">Contractor and manager notified instantly</div>
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

// Add CSS for demo-specific styles
const demoStyles = `
<style>
/* Workflow Visualization */
.creation-workflow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.workflow-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.workflow-step.active {
    opacity: 1;
    transform: scale(1.05);
}

.step-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.workflow-step.active .step-icon {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: white;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.step-content h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.25rem;
}

.step-content p {
    font-size: 0.8rem;
    color: #6b7280;
}

.workflow-arrow {
    color: #cbd5e1;
    font-size: 1.2rem;
}

/* Demo Workflow */
.demo-workflow {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.workflow-progress {
    padding: 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.progress-bar {
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 2px;
    width: 0%;
    transition: width 0.5s ease;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.step.active {
    opacity: 1;
}

.step-number {
    width: 30px;
    height: 30px;
    background: #cbd5e1;
    color: #64748b;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: #2563eb;
    color: white;
}

.step-label {
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 500;
}

.demo-content {
    padding: 2rem;
    min-height: 400px;
}

.demo-step {
    display: none;
}

.demo-step.active {
    display: block;
}

.demo-form .form-group {
    margin-bottom: 1.5rem;
}

.demo-form label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    display: block;
}

.demo-form .form-control {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
}

.demo-form .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Floor Plan Demo */
.floorplan-container {
    display: flex;
    gap: 2rem;
    align-items: flex-start;
}

.floorplan-image {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    cursor: crosshair;
}

.floorplan-image img {
    display: block;
    width: 100%;
    max-width: 400px;
}

.location-pin {
    position: absolute;
    transform: translate(-50%, -100%);
    color: #dc2626;
    font-size: 2rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    animation: pinDrop 0.5s ease-out;
}

@keyframes pinDrop {
    0% {
        transform: translate(-50%, -200%) scale(0);
    }
    50% {
        transform: translate(-50%, -120%) scale(1.2);
    }
    100% {
        transform: translate(-50%, -100%) scale(1);
    }
}

.pin-instructions {
    flex: 1;
}

.pin-instructions p {
    margin-bottom: 0.5rem;
    color: #6b7280;
}

.coordinates {
    background: #f1f5f9;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #2563eb;
}

/* Image Upload Demo */
.image-upload-area {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.upload-zone:hover {
    border-color: #2563eb;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.02) 100%);
}

.upload-zone i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.upload-zone p {
    margin: 0.5rem 0;
    color: #64748b;
    font-weight: 500;
}

.upload-zone small {
    color: #94a3b8;
}

.uploaded-images {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.image-preview {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.image-preview img {
    display: block;
    width: 120px;
    height: 120px;
    object-fit: cover;
}

.image-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

/* Submit Demo */
.submission-summary {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    color: #166534;
}

.summary-item:last-child {
    margin-bottom: 0;
}

.submit-actions {
    text-align: center;
}

#submitBtn.success {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-color: #16a34a;
}

/* Demo Navigation */
.demo-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

/* Benefits Section */
.benefits-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
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
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.benefit-metric {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    color: #2563eb;
    font-size: 0.9rem;
}

/* Success Overlay */
.success-overlay {
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
    animation: fadeIn 0.3s ease-out;
}

.success-modal {
    background: white;
    padding: 3rem;
    border-radius: 16px;
    text-align: center;
    max-width: 500px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: modalSlideIn 0.5s ease-out;
}

.success-icon {
    font-size: 4rem;
    color: #16a34a;
    margin-bottom: 1.5rem;
}

.success-modal h3 {
    color: #1e293b;
    margin-bottom: 1rem;
}

.success-modal p {
    color: #64748b;
    margin-bottom: 2rem;
}

.success-details {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 2rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    color: #475569;
}

.detail-item:last-child {
    margin-bottom: 0;
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

.notification-toast.show {
    transform: translateX(0);
}

.toast-icon {
    color: #2563eb;
    font-size: 1.5rem;
}

.toast-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.toast-message {
    color: #64748b;
    font-size: 0.9rem;
}

/* Upload Success */
.upload-success {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #16a34a;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    animation: slideInFromBottom 0.3s ease-out;
}

@keyframes slideInFromBottom {
    from {
        transform: translate(-50%, 100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, -50%);
        opacity: 1;
    }
}

@keyframes modalSlideIn {
    from {
        transform: scale(0.8) translateY(20px);
        opacity: 0;
    }
    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

/* Back to Dashboard */
.back-to-dashboard {
    padding: 60px 0;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: white;
    text-align: center;
}

.dashboard-link .btn {
    margin-bottom: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-link p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .creation-workflow {
        flex-direction: column;
        gap: 1rem;
    }

    .workflow-arrow {
        transform: rotate(90deg);
    }

    .floorplan-container {
        flex-direction: column;
        gap: 1rem;
    }

    .uploaded-images {
        justify-content: center;
    }

    .demo-navigation {
        flex-direction: column;
        gap: 1rem;
    }

    .benefits-grid {
        grid-template-columns: 1fr;
    }
}
</style>
`;

// Add demo-specific styles to head
document.head.insertAdjacentHTML('beforeend', demoStyles);