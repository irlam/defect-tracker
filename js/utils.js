/**
 * js/utils.js
 * Common utility functions for the defect tracker
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

class DefectTrackerUtils {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        this.debounceTimers = new Map();
    }

    /**
     * Show toast notification
     */
    showToast(type, message, duration = 3000) {
        // Check if SweetAlert2 is available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duration
            });
        } else {
            // Fallback to browser alert
            alert(`${type.toUpperCase()}: ${message}`);
        }
    }

    /**
     * Make AJAX request with CSRF protection
     */
    async makeRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken && { 'X-CSRF-Token': this.csrfToken })
            },
            credentials: 'same-origin'
        };

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            console.error('Request failed:', error);
            this.showToast('error', 'Network error occurred. Please try again.');
            throw error;
        }
    }

    /**
     * Debounce function execution
     */
    debounce(key, func, delay = 300) {
        if (this.debounceTimers.has(key)) {
            clearTimeout(this.debounceTimers.get(key));
        }

        const timer = setTimeout(() => {
            func();
            this.debounceTimers.delete(key);
        }, delay);

        this.debounceTimers.set(key, timer);
    }

    /**
     * Validate form fields
     */
    validateForm(formElement, rules = {}) {
        const errors = [];
        const formData = new FormData(formElement);

        for (const [field, rule] of Object.entries(rules)) {
            const value = formData.get(field);
            const element = formElement.querySelector(`[name="${field}"]`);

            // Clear previous validation styling
            if (element) {
                element.classList.remove('is-invalid', 'is-valid');
                const feedback = element.parentElement.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
            }

            // Required field validation
            if (rule.required && (!value || value.trim() === '')) {
                errors.push({ field, message: rule.message || `${field} is required` });
                continue;
            }

            // Skip other validations if field is empty and not required
            if (!value || value.trim() === '') continue;

            // Email validation
            if (rule.type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    errors.push({ field, message: 'Please enter a valid email address' });
                }
            }

            // Length validation
            if (rule.minLength && value.length < rule.minLength) {
                errors.push({ field, message: `${field} must be at least ${rule.minLength} characters` });
            }

            if (rule.maxLength && value.length > rule.maxLength) {
                errors.push({ field, message: `${field} must not exceed ${rule.maxLength} characters` });
            }

            // Custom validation function
            if (rule.validate && typeof rule.validate === 'function') {
                const customResult = rule.validate(value);
                if (customResult !== true) {
                    errors.push({ field, message: customResult });
                }
            }
        }

        // Apply validation styling
        errors.forEach(error => {
            const element = formElement.querySelector(`[name="${error.field}"]`);
            if (element) {
                element.classList.add('is-invalid');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = error.message;
                element.parentElement.appendChild(feedback);
            }
        });

        // Mark valid fields
        for (const field of Object.keys(rules)) {
            const hasError = errors.some(error => error.field === field);
            if (!hasError) {
                const element = formElement.querySelector(`[name="${field}"]`);
                if (element && element.value.trim() !== '') {
                    element.classList.add('is-valid');
                }
            }
        }

        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Validate file upload
     */
    validateFile(file, options = {}) {
        const {
            maxSize = 5 * 1024 * 1024, // 5MB default
            allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
            allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf']
        } = options;

        const errors = [];

        // Size validation
        if (file.size > maxSize) {
            errors.push(`File size (${this.formatFileSize(file.size)}) exceeds maximum allowed size (${this.formatFileSize(maxSize)})`);
        }

        // Type validation
        if (!allowedTypes.includes(file.type)) {
            errors.push(`File type "${file.type}" is not allowed`);
        }

        // Extension validation
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(extension)) {
            errors.push(`File extension ".${extension}" is not allowed`);
        }

        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Initialize loading spinner
     */
    showLoading(element, text = 'Loading...') {
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner d-flex align-items-center justify-content-center';
        spinner.innerHTML = `
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            <span>${text}</span>
        `;
        
        element.style.position = 'relative';
        element.appendChild(spinner);
        
        return spinner;
    }

    /**
     * Remove loading spinner
     */
    hideLoading(spinner) {
        if (spinner && spinner.parentElement) {
            spinner.parentElement.removeChild(spinner);
        }
    }

    /**
     * Auto-save functionality
     */
    initAutoSave(formElement, saveUrl, interval = 30000) {
        let lastSaveTime = Date.now();
        let saveTimer;

        const autoSave = async () => {
            try {
                const formData = new FormData(formElement);
                formData.append('auto_save', '1');
                
                const response = await this.makeRequest(saveUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    lastSaveTime = Date.now();
                    this.updateAutoSaveIndicator('saved');
                } else {
                    this.updateAutoSaveIndicator('error');
                }
            } catch (error) {
                console.error('Auto-save failed:', error);
                this.updateAutoSaveIndicator('error');
            }
        };

        const scheduleAutoSave = () => {
            clearTimeout(saveTimer);
            this.updateAutoSaveIndicator('pending');
            saveTimer = setTimeout(autoSave, interval);
        };

        // Listen for form changes
        formElement.addEventListener('input', scheduleAutoSave);
        formElement.addEventListener('change', scheduleAutoSave);

        // Initial save timer
        scheduleAutoSave();

        return {
            save: autoSave,
            stop: () => clearTimeout(saveTimer)
        };
    }

    /**
     * Update auto-save indicator
     */
    updateAutoSaveIndicator(status) {
        let indicator = document.getElementById('auto-save-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'auto-save-indicator';
            indicator.className = 'auto-save-indicator';
            document.body.appendChild(indicator);
        }

        const messages = {
            saved: { text: 'All changes saved', class: 'success' },
            pending: { text: 'Saving...', class: 'warning' },
            error: { text: 'Save failed', class: 'danger' }
        };

        const message = messages[status] || messages.pending;
        indicator.textContent = message.text;
        indicator.className = `auto-save-indicator alert alert-${message.class}`;
    }

    /**
     * Smooth scroll to element
     */
    scrollToElement(element, offset = 0) {
        const elementPosition = element.offsetTop - offset;
        window.scrollTo({
            top: elementPosition,
            behavior: 'smooth'
        });
    }

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            
            this.showToast('success', 'Copied to clipboard');
            return true;
        } catch (error) {
            console.error('Copy failed:', error);
            this.showToast('error', 'Failed to copy to clipboard');
            return false;
        }
    }

    /**
     * Initialize tooltips (Bootstrap)
     */
    initTooltips() {
        if (typeof bootstrap !== 'undefined') {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
            });
        }
    }

    /**
     * Initialize popovers (Bootstrap)
     */
    initPopovers() {
        if (typeof bootstrap !== 'undefined') {
            const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
            popovers.forEach(popover => {
                new bootstrap.Popover(popover);
            });
        }
    }

    /**
     * Get URL parameters
     */
    getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        const result = {};
        for (const [key, value] of params) {
            result[key] = value;
        }
        return result;
    }

    /**
     * Update URL without page reload
     */
    updateUrl(params, replaceState = false) {
        const url = new URL(window.location);
        
        for (const [key, value] of Object.entries(params)) {
            if (value === null || value === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        }

        if (replaceState) {
            history.replaceState(null, '', url);
        } else {
            history.pushState(null, '', url);
        }
    }
}

// Initialize global utils instance
window.utils = new DefectTrackerUtils();

// Initialize common features when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap components
    utils.initTooltips();
    utils.initPopovers();
    
    // Add CSRF token to meta if available
    if (typeof csrfToken !== 'undefined' && !document.querySelector('meta[name="csrf-token"]')) {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = csrfToken;
        document.head.appendChild(meta);
    }
    
    // Global error handler for uncaught errors
    window.addEventListener('error', function(event) {
        console.error('Global error:', event.error);
        if (typeof utils !== 'undefined') {
            utils.showToast('error', 'An unexpected error occurred');
        }
    });
    
    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled promise rejection:', event.reason);
        if (typeof utils !== 'undefined') {
            utils.showToast('error', 'An unexpected error occurred');
        }
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DefectTrackerUtils;
}