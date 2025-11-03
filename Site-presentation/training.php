<?php
/**
 * Site Presentation - Training Materials
 * 
 * Comprehensive training and documentation hub for the Defect Tracker system
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SessionManager.php';

SessionManager::start();
if (!SessionManager::isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Training Materials';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        .training-hero {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            padding: 3rem 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 2rem;
        }
        .training-hero h1 {
            color: white;
            margin-bottom: 1rem;
        }
        .training-hero p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.125rem;
        }
        .training-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .training-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .training-card h3 {
            color: var(--text-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .training-card-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        .module-list {
            list-style: none;
            padding: 0;
        }
        .module-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .module-list li:last-child {
            border-bottom: none;
        }
        .module-status {
            margin-left: auto;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-available {
            background: var(--success-color);
            color: white;
        }
        .status-coming-soon {
            background: var(--warning-color);
            color: var(--black);
        }
        .status-planned {
            background: var(--info-color);
            color: white;
        }
        .voice-over-placeholder {
            background: var(--surface-muted);
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 3rem 2rem;
            text-align: center;
            margin: 2rem 0;
        }
        .voice-over-placeholder i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <!-- Hero Section -->
        <div class="training-hero">
            <h1>
                <i class="fas fa-graduation-cap"></i>
                Training Materials
            </h1>
            <p>
                Learn how to use the Defect Tracker effectively with our comprehensive training resources
            </p>
        </div>

        <!-- Quick Start Guide -->
        <div class="training-card">
            <h3>
                <div class="training-card-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                Quick Start Guide
            </h3>
            <p class="text-muted mb-3">
                Get up and running quickly with these essential guides
            </p>
            <ul class="module-list">
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <span>Getting Started with Defect Tracker</span>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <span>Creating Your First Defect</span>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <span>Understanding User Roles</span>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-clock text-warning"></i>
                    <span>Navigation and Interface Overview</span>
                    <span class="module-status status-coming-soon">Coming Soon</span>
                </li>
            </ul>
        </div>

        <!-- Video Tutorials -->
        <div class="training-card">
            <h3>
                <div class="training-card-icon">
                    <i class="fas fa-video"></i>
                </div>
                Video Tutorials
            </h3>
            <p class="text-muted mb-3">
                Watch step-by-step video guides
            </p>
            
            <!-- Voice-over Placeholder -->
            <div class="voice-over-placeholder">
                <i class="fas fa-film"></i>
                <h4>Video Content Coming Soon</h4>
                <p class="text-muted">
                    We're currently producing professional video tutorials to help you master the Defect Tracker.
                    Check back soon for step-by-step video guides with voice-over narration.
                </p>
            </div>

            <ul class="module-list">
                <li>
                    <i class="fas fa-clock text-info"></i>
                    <span>Introduction to Defect Tracking (5 min)</span>
                    <span class="module-status status-planned">Planned</span>
                </li>
                <li>
                    <i class="fas fa-clock text-info"></i>
                    <span>Creating and Assigning Defects (10 min)</span>
                    <span class="module-status status-planned">Planned</span>
                </li>
                <li>
                    <i class="fas fa-clock text-info"></i>
                    <span>Working with Floor Plans (8 min)</span>
                    <span class="module-status status-planned">Planned</span>
                </li>
                <li>
                    <i class="fas fa-clock text-info"></i>
                    <span>Reporting and Analytics (12 min)</span>
                    <span class="module-status status-planned">Planned</span>
                </li>
                <li>
                    <i class="fas fa-clock text-info"></i>
                    <span>Admin Features and Settings (15 min)</span>
                    <span class="module-status status-planned">Planned</span>
                </li>
            </ul>
        </div>

        <!-- Role-Based Training -->
        <div class="training-card">
            <h3>
                <div class="training-card-icon">
                    <i class="fas fa-users"></i>
                </div>
                Role-Based Training
            </h3>
            <p class="text-muted mb-3">
                Training materials tailored to your role
            </p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-user-shield text-primary"></i> Admin Training</h5>
                    <ul class="module-list">
                        <li>
                            <span>System Configuration</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>User Management</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Backup & Maintenance</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-user-tie text-success"></i> Manager Training</h5>
                    <ul class="module-list">
                        <li>
                            <span>Project Management</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Team Coordination</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Reporting & Analytics</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-hard-hat text-warning"></i> Contractor Training</h5>
                    <ul class="module-list">
                        <li>
                            <span>Managing Assigned Tasks</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Submitting Evidence</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Mobile App Usage</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6 mb-3">
                    <h5><i class="fas fa-clipboard-check text-info"></i> Inspector Training</h5>
                    <ul class="module-list">
                        <li>
                            <span>Defect Documentation</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Quality Standards</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                        <li>
                            <span>Verification Process</span>
                            <span class="module-status status-coming-soon">Coming Soon</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Documentation -->
        <div class="training-card">
            <h3>
                <div class="training-card-icon">
                    <i class="fas fa-book"></i>
                </div>
                Documentation
            </h3>
            <p class="text-muted mb-3">
                Comprehensive reference materials
            </p>
            <ul class="module-list">
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <a href="/help_pages/navigation_guide.php" class="text-decoration-none">
                        Navigation Guide
                    </a>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <a href="/help_pages/view_role_matrix.php" class="text-decoration-none">
                        Role Capability Matrix
                    </a>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <a href="/help_pages/view_pwa_health.php" class="text-decoration-none">
                        PWA Features Guide
                    </a>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <a href="/Site-presentation/index.php" class="text-decoration-none">
                        System Analysis & Overview
                    </a>
                    <span class="module-status status-available">Available</span>
                </li>
                <li>
                    <i class="fas fa-check-circle text-success"></i>
                    <a href="/IMPROVEMENTS.md" class="text-decoration-none" target="_blank">
                        System Improvements Log
                    </a>
                    <span class="module-status status-available">Available</span>
                </li>
            </ul>
        </div>

        <!-- FAQs -->
        <div class="training-card">
            <h3>
                <div class="training-card-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                Frequently Asked Questions
            </h3>
            <p class="text-muted mb-3">
                Common questions and answers
            </p>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item bg-transparent border-0 mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do I reset my password?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Contact your system administrator to reset your password. They can update it through the User Management section.
                        </div>
                    </div>
                </div>
                <div class="accordion-item bg-transparent border-0 mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Can I use the app offline?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes! The Defect Tracker is a Progressive Web App (PWA) with offline capabilities. Install it to your device for the best experience.
                        </div>
                    </div>
                </div>
                <div class="accordion-item bg-transparent border-0 mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            How do I upload floor plans?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Navigate to Projects → Upload Floor Plan. You can upload images in JPG, PNG, or PDF format. Make sure to associate them with the correct project.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="/help_index.php" class="btn btn-primary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Help Center
            </a>
            <a href="/Site-presentation/index.php" class="btn btn-outline-primary btn-lg ms-2">
                <i class="fas fa-chart-line me-2"></i>
                View System Overview
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
