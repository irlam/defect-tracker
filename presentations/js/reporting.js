// Reporting & Analytics Demo JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeAnalyticsDashboard();
    initializeInteractiveCharts();
    initializeFilters();
});

function initializeAnalyticsDashboard() {
    // Initialize dashboard components
    updateDashboard();
    initializeChartAnimations();
    initializePerformanceTable();
}

function updateDashboard() {
    const dateRange = document.getElementById('dateRange').value;
    const projectFilter = document.getElementById('projectFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;

    // Simulate data updates based on filters
    updateMetrics(dateRange, projectFilter, statusFilter);
    updateCharts(dateRange, projectFilter, statusFilter);
    updatePerformanceData(dateRange, projectFilter);

    // Show loading animation
    showLoadingAnimation();
}

function updateMetrics(dateRange, projectFilter, statusFilter) {
    // Simulate different metrics based on filters
    const metricsData = {
        '7d': { total: 1247, rate: '94%', time: '2.3 days', score: '8.7' },
        '30d': { total: 3420, rate: '93%', time: '2.5 days', score: '8.6' },
        '90d': { total: 9850, rate: '92%', time: '2.7 days', score: '8.4' },
        '1y': { total: 45200, rate: '91%', time: '3.1 days', score: '8.2' }
    };

    const data = metricsData[dateRange];

    // Animate metric updates
    animateMetric('totalDefects', data.total);
    animateMetric('resolutionRate', data.rate);
    animateMetric('avgResolutionTime', data.time);
    animateMetric('contractorScore', data.score);

    // Update trend indicators
    updateTrends(dateRange);
}

function animateMetric(elementId, newValue) {
    const element = document.getElementById(elementId);
    const currentValue = parseFloat(element.textContent.replace(/[^0-9.-]/g, '')) || 0;
    const targetValue = typeof newValue === 'string' ? parseFloat(newValue) : newValue;

    // Simple animation
    const duration = 1000;
    const steps = 20;
    const increment = (targetValue - currentValue) / steps;
    let current = currentValue;
    let step = 0;

    const timer = setInterval(() => {
        current += increment;
        step++;

        if (typeof newValue === 'string' && newValue.includes('%')) {
            element.textContent = Math.round(current) + '%';
        } else if (typeof newValue === 'string' && newValue.includes('days')) {
            element.textContent = current.toFixed(1) + ' days';
        } else {
            element.textContent = Math.round(current).toLocaleString();
        }

        if (step >= steps) {
            clearInterval(timer);
            element.textContent = newValue;
        }
    }, duration / steps);
}

function updateTrends(dateRange) {
    const trends = document.querySelectorAll('.metric-change');
    trends.forEach(trend => {
        // Simulate trend changes based on date range
        const isPositive = Math.random() > 0.3;
        const icon = trend.querySelector('i');
        const span = trend.querySelector('span');

        if (isPositive) {
            trend.className = 'metric-change positive';
            icon.className = 'fas fa-arrow-up';
            span.textContent = '+2% from last period';
        } else {
            trend.className = 'metric-change negative';
            icon.className = 'fas fa-arrow-down';
            span.textContent = '-1% from last period';
        }
    });
}

function updateCharts(dateRange, projectFilter, statusFilter) {
    // Update trend chart data points
    const dataPoints = document.querySelectorAll('.data-point');
    dataPoints.forEach((point, index) => {
        // Simulate different data based on filters
        const baseValue = 50;
        const variation = Math.sin(index * 0.5) * 20;
        const filterMultiplier = projectFilter === 'all' ? 1 : 0.7;
        const newValue = Math.round((baseValue + variation) * filterMultiplier);

        point.style.height = (newValue / 100 * 60) + '%';
        point.setAttribute('data-value', newValue);

        const tooltip = point.querySelector('.point-tooltip');
        const dateLabels = ['Jan 10', 'Jan 17', 'Jan 24', 'Jan 31', 'Feb 7', 'Feb 14', 'Feb 21', 'Feb 28', 'Mar 7'];
        tooltip.textContent = `${dateLabels[index]}: ${newValue} defects`;
    });

    // Update pie chart segments
    updatePieChart(statusFilter);
}

function updatePieChart(statusFilter) {
    const segments = document.querySelectorAll('.pie-segment');
    let percentages = [15, 35, 45, 5]; // Default: open, in-progress, resolved, closed

    if (statusFilter !== 'all') {
        // Adjust percentages based on status filter
        switch(statusFilter) {
            case 'open':
                percentages = [100, 0, 0, 0];
                break;
            case 'in-progress':
                percentages = [0, 100, 0, 0];
                break;
            case 'resolved':
                percentages = [0, 0, 100, 0];
                break;
            case 'closed':
                percentages = [0, 0, 0, 100];
                break;
        }
    }

    segments.forEach((segment, index) => {
        segment.style.setProperty('--percentage', percentages[index]);
        const label = segment.querySelector('.segment-label');
        const statusLabels = ['Open', 'In Progress', 'Resolved', 'Closed'];
        label.textContent = `${statusLabels[index]} (${percentages[index]}%)`;
    });
}

function updatePerformanceData(dateRange, projectFilter) {
    // Simulate different performance data
    const performanceRows = document.querySelectorAll('.performance-table .table-row:not(.header)');

    performanceRows.forEach((row, index) => {
        const baseScore = 9.2 - (index * 0.3);
        const variation = (Math.random() - 0.5) * 0.4;
        const newScore = Math.max(7.0, Math.min(9.5, baseScore + variation));

        const scoreValue = row.querySelector('.score-value');
        scoreValue.textContent = newScore.toFixed(1);

        // Update stars
        updateStars(row, newScore);

        // Update trend
        updatePerformanceTrend(row);
    });
}

function updateStars(row, score) {
    const starsContainer = row.querySelector('.score-stars');
    const fullStars = Math.floor(score);
    const hasHalfStar = score % 1 >= 0.5;

    starsContainer.innerHTML = '';

    for (let i = 0; i < fullStars; i++) {
        starsContainer.innerHTML += '<i class="fas fa-star"></i>';
    }

    if (hasHalfStar) {
        starsContainer.innerHTML += '<i class="fas fa-star-half-alt"></i>';
    }

    const remainingStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    for (let i = 0; i < remainingStars; i++) {
        starsContainer.innerHTML += '<i class="far fa-star"></i>';
    }
}

function updatePerformanceTrend(row) {
    const trendElement = row.querySelector('.col-trend');
    const isPositive = Math.random() > 0.4;
    const isNeutral = Math.random() > 0.7;

    if (isNeutral) {
        trendElement.innerHTML = '<i class="fas fa-minus text-warning"></i><span class="text-warning">0.0</span>';
    } else if (isPositive) {
        const change = (Math.random() * 0.5).toFixed(1);
        trendElement.innerHTML = `<i class="fas fa-arrow-up text-success"></i><span class="text-success">+${change}</span>`;
    } else {
        const change = (Math.random() * 0.5).toFixed(1);
        trendElement.innerHTML = `<i class="fas fa-arrow-down text-danger"></i><span class="text-danger">-${change}</span>`;
    }
}

function switchChartType(chartType) {
    const buttons = document.querySelectorAll('.chart-controls .btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const trendChart = document.querySelector('.trend-chart');
    trendChart.className = `trend-chart ${chartType}`;

    // Add transition effect
    trendChart.style.opacity = '0.7';
    setTimeout(() => {
        trendChart.style.opacity = '1';
    }, 300);
}

function sortPerformance(sortBy) {
    const table = document.querySelector('.performance-table');
    const rows = Array.from(table.querySelectorAll('.table-row:not(.header)'));

    rows.sort((a, b) => {
        let aValue, bValue;

        switch(sortBy) {
            case 'score':
                aValue = parseFloat(a.dataset.score);
                bValue = parseFloat(b.dataset.score);
                break;
            case 'defects':
                aValue = parseInt(a.dataset.defects);
                bValue = parseInt(b.dataset.defects);
                break;
            case 'time':
                aValue = parseFloat(a.dataset.time);
                bValue = parseFloat(b.dataset.time);
                break;
            default:
                return 0;
        }

        return bValue - aValue; // Descending order
    });

    // Reorder rows in DOM
    rows.forEach(row => table.appendChild(row));
}

function showMetricDetail(metricType) {
    const metricDetails = {
        'total-defects': {
            title: 'Total Defects Analysis',
            content: 'Comprehensive breakdown of defect volume across all projects and time periods.'
        },
        'resolution-rate': {
            title: 'Resolution Rate Trends',
            content: 'Analysis of defect resolution efficiency and factors affecting completion rates.'
        },
        'avg-resolution-time': {
            title: 'Resolution Time Analysis',
            content: 'Detailed breakdown of time-to-resolution by defect type, priority, and contractor.'
        },
        'contractor-performance': {
            title: 'Contractor Performance Metrics',
            content: 'Multi-dimensional analysis of contractor quality, speed, and reliability scores.'
        }
    };

    const detail = metricDetails[metricType];
    if (detail) {
        showMetricModal(detail.title, detail.content);
    }
}

function showMetricModal(title, content) {
    const modal = document.createElement('div');
    modal.className = 'metric-modal-overlay';
    modal.innerHTML = `
        <div class="metric-modal">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>${content}</p>
                <div class="metric-chart-placeholder">
                    <i class="fas fa-chart-bar"></i>
                    <span>Detailed ${title.toLowerCase()} visualization would appear here</span>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function previewReport(reportType) {
    const reportDetails = {
        'contractor-performance': {
            title: 'Contractor Performance Report',
            description: 'Weekly analysis of contractor efficiency and quality metrics',
            features: ['Performance scores', 'Completion times', 'Quality ratings', 'Trend analysis']
        },
        'project-progress': {
            title: 'Project Progress Dashboard',
            description: 'Real-time overview of defect resolution across all active projects',
            features: ['Live status updates', 'Progress charts', 'Risk indicators', 'Timeline projections']
        },
        'quality-metrics': {
            title: 'Quality Metrics Report',
            description: 'Monthly analysis of defect quality trends and improvement opportunities',
            features: ['Recurrence analysis', 'Rework rates', 'Quality scores', 'Improvement recommendations']
        },
        'defect-summary': {
            title: 'Defect Summary Report',
            description: 'Comprehensive overview of all defects by category and priority',
            features: ['Category breakdown', 'Priority analysis', 'Location mapping', 'Trend identification']
        },
        'floor-plan-analysis': {
            title: 'Floor Plan Analysis',
            description: 'Visual representation of defect distribution across building areas',
            features: ['Interactive floor plans', 'Heat maps', 'Zone analysis', 'Pattern recognition']
        },
        'cost-analysis': {
            title: 'Cost Analysis Report',
            description: 'Financial impact assessment of defect management activities',
            features: ['Repair costs', 'Delay penalties', 'Cost trends', 'Budget impact']
        },
        'aging-report': {
            title: 'Defect Aging Report',
            description: 'Analysis of defect age distribution and resolution bottlenecks',
            features: ['Age categories', 'SLA compliance', 'Bottleneck identification', 'Priority recommendations']
        },
        'sla-compliance': {
            title: 'SLA Compliance Report',
            description: 'Service level agreement adherence and performance tracking',
            features: ['Compliance rates', 'Deadline analysis', 'Performance trends', 'Improvement actions']
        },
        'predictive-analytics': {
            title: 'Predictive Analytics Dashboard',
            description: 'AI-powered insights for future defect prediction and prevention',
            features: ['Trend forecasting', 'Risk assessment', 'Preventive recommendations', 'Pattern analysis']
        }
    };

    const report = reportDetails[reportType];
    if (report) {
        showReportModal(report);
    }
}

function showReportModal(report) {
    const modal = document.createElement('div');
    modal.className = 'report-modal-overlay';
    modal.innerHTML = `
        <div class="report-modal">
            <div class="modal-header">
                <h3>${report.title}</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="report-description">${report.description}</p>
                <div class="report-features">
                    <h5>Report Features:</h5>
                    <ul>
                        ${report.features.map(feature => `<li>${feature}</li>`).join('')}
                    </ul>
                </div>
                <div class="report-preview">
                    <div class="preview-placeholder">
                        <i class="fas fa-file-alt"></i>
                        <span>Report preview would be displayed here</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal(this)">Close</button>
                <button class="btn btn-primary" onclick="generateReport('${report.title.toLowerCase().replace(/\s+/g, '-')}')">
                    <i class="fas fa-download"></i> Generate Report
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function generateReport(reportType) {
    // Simulate report generation
    showToast('Report generation started. You will receive an email when complete.', 'info');

    // Simulate progress
    setTimeout(() => {
        showToast('Report generated successfully! Check your downloads.', 'success');
    }, 3000);
}

function exportReport() {
    const format = prompt('Choose export format:', 'PDF');
    if (format) {
        showToast(`Exporting dashboard as ${format.toUpperCase()}...`, 'info');

        setTimeout(() => {
            showToast('Export completed! File downloaded.', 'success');
        }, 2000);
    }
}

function scheduleReport() {
    const modal = document.createElement('div');
    modal.className = 'schedule-modal-overlay';
    modal.innerHTML = `
        <div class="schedule-modal">
            <div class="modal-header">
                <h3>Schedule Automated Report</h3>
                <button class="modal-close" onclick="closeModal(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="schedule-form">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select id="scheduleReportType">
                            <option value="performance">Contractor Performance</option>
                            <option value="progress">Project Progress</option>
                            <option value="quality">Quality Metrics</option>
                            <option value="summary">Defect Summary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select id="scheduleFrequency">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Format</label>
                        <select id="scheduleFormat">
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipients (comma-separated)</label>
                        <input type="text" id="scheduleRecipients" placeholder="email1@company.com, email2@company.com">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal(this)">Cancel</button>
                <button class="btn btn-primary" onclick="saveSchedule()">
                    <i class="fas fa-save"></i> Schedule Report
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function saveSchedule() {
    // Simulate saving schedule
    showToast('Report schedule saved successfully!', 'success');
    closeModal();
}

function initializeChartAnimations() {
    // Add hover effects to chart points
    const dataPoints = document.querySelectorAll('.data-point');
    dataPoints.forEach(point => {
        point.addEventListener('mouseenter', function() {
            const tooltip = this.querySelector('.point-tooltip');
            tooltip.style.opacity = '1';
            tooltip.style.visibility = 'visible';
        });

        point.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.point-tooltip');
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        });
    });
}

function initializeFilters() {
    // Add change event listeners to filters
    const filters = ['dateRange', 'projectFilter', 'statusFilter'];
    filters.forEach(filterId => {
        const filter = document.getElementById(filterId);
        if (filter) {
            filter.addEventListener('change', updateDashboard);
        }
    });
}

function showLoadingAnimation() {
    // Add loading class to dashboard
    const dashboard = document.querySelector('.analytics-dashboard-demo');
    dashboard.classList.add('loading');

    setTimeout(() => {
        dashboard.classList.remove('loading');
    }, 500);
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
    const modal = button ? button.closest('.modal-overlay, .metric-modal-overlay, .report-modal-overlay, .schedule-modal-overlay') :
                          document.querySelector('.modal-overlay, .metric-modal-overlay, .report-modal-overlay, .schedule-modal-overlay');

    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Add CSS for analytics demo styles
const analyticsStyles = `
<style>
/* Analytics Dashboard */
.analytics-dashboard-demo {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.dashboard-controls {
    display: flex;
    gap: 2rem;
    align-items: center;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    flex-wrap: wrap;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 150px;
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
    margin-left: auto;
}

/* Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.metric-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #e2e8f0;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.metric-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.metric-content {
    flex: 1;
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.metric-label {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.metric-change {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.metric-change.positive {
    color: #059669;
}

.metric-change.negative {
    color: #dc2626;
}

/* Charts Row */
.charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.chart-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.chart-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-header h4 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
}

.chart-controls {
    display: flex;
    gap: 0.5rem;
}

.chart-controls .btn {
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
}

.chart-canvas {
    padding: 2rem;
    height: 300px;
    position: relative;
}

/* Trend Chart */
.trend-chart {
    position: relative;
    width: 100%;
    height: 100%;
}

.chart-grid {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
}

.grid-line {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 1px solid #e2e8f0;
}

.chart-data {
    position: relative;
    height: 100%;
    display: flex;
    align-items: end;
    justify-content: space-between;
    padding: 0 20px;
}

.data-point {
    position: relative;
    width: 30px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 4px 4px 0 0;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 5px;
}

.data-point:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: scale(1.1);
}

.point-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    margin-bottom: 8px;
}

.point-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1e293b;
}

/* Pie Chart */
.pie-chart {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto;
    border-radius: 50%;
    background: conic-gradient(
        #dc2626 0% 15%,
        #f59e0b 15% 50%,
        #10b981 50% 95%,
        #6b7280 95% 100%
    );
}

.pie-segment {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    clip-path: polygon(50% 50%, 50% 0%, var(--end-x, 100%) var(--end-y, 0%), 50% 50%);
}

.segment-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
}

.chart-legend {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 2rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: #374151;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
}

.legend-color.open { background: #dc2626; }
.legend-color.in-progress { background: #f59e0b; }
.legend-color.resolved { background: #10b981; }
.legend-color.closed { background: #6b7280; }

/* Performance Table */
.performance-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.table-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h4 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
}

.table-controls select {
    padding: 0.5rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 0.9rem;
}

.performance-table {
    width: 100%;
}

.table-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 1rem;
    padding: 1rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    align-items: center;
}

.table-row.header {
    background: #f8fafc;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-row:not(.header):hover {
    background: #f8fafc;
}

.col-contractor {
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
    font-size: 0.9rem;
}

.contractor-details strong {
    display: block;
    color: #1e293b;
    font-size: 0.9rem;
}

.contractor-details small {
    color: #6b7280;
    font-size: 0.8rem;
}

.score-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.score-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
}

.score-stars {
    color: #f59e0b;
    font-size: 0.8rem;
}

.col-defects, .col-time {
    text-align: center;
    font-weight: 600;
    color: #374151;
}

.col-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
}

/* Reports Grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

.report-category {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.category-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.category-header i {
    color: #2563eb;
    font-size: 1.5rem;
}

.category-header h4 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
}

.category-reports {
    padding: 1.5rem 2rem;
}

.report-item {
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.report-item:hover {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.05);
    transform: translateY(-2px);
}

.report-item:last-child {
    margin-bottom: 0;
}

.report-item h5 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1rem;
    font-weight: 600;
}

.report-item p {
    margin: 0 0 1rem 0;
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.4;
}

.report-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
}

.frequency, .format {
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-weight: 500;
    color: #475569;
}

/* Export Features */
.export-features .row {
    --bs-gutter-x: 2rem;
}

.export-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.export-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.export-icon {
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

.export-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.export-card p {
    color: #64748b;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.export-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

.option-tag {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    color: #2563eb;
}

/* Modal Styles */
.modal-overlay, .metric-modal-overlay, .report-modal-overlay, .schedule-modal-overlay {
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

.modal-overlay.show, .metric-modal-overlay.show, .report-modal-overlay.show, .schedule-modal-overlay.show {
    opacity: 1;
}

.metric-modal, .report-modal, .schedule-modal {
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

/* Loading Animation */
.analytics-dashboard-demo.loading {
    opacity: 0.7;
    pointer-events: none;
}

.analytics-dashboard-demo.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #2563eb;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    transform: translate(-50%, -50%);
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

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .control-group {
        min-width: auto;
    }

    .control-actions {
        margin-left: 0;
        justify-content: center;
    }

    .metrics-grid {
        grid-template-columns: 1fr;
    }

    .charts-row {
        grid-template-columns: 1fr;
    }

    .table-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        text-align: center;
    }

    .table-row.header {
        display: none;
    }

    .reports-grid {
        grid-template-columns: 1fr;
    }

    .export-features .row {
        --bs-gutter-x: 1rem;
    }
}
</style>
`;

// Add analytics-specific styles to head
document.head.insertAdjacentHTML('beforeend', analyticsStyles);