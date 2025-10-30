// Floor Plan Integration Demo JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeFloorPlanDemo();
    setupInteractiveFloorPlan();
    initializeDefectPins();
    setupCanvasControls();
});

function initializeFloorPlanDemo() {
    // Initialize floor plan demo components
    loadInitialFloorPlan();
    setupPinInteractions();
    initializeZoomControls();
}

function setupInteractiveFloorPlan() {
    const canvas = document.getElementById('floorPlanCanvas');
    if (!canvas) return;

    // Load the initial floor plan
    loadFloorPlan('building-a', 'floor-3', 'defects');
}

function loadFloorPlan(building, floor, mode) {
    const canvas = document.getElementById('floorPlanCanvas');
    if (!canvas) return;

    // Clear existing content
    canvas.innerHTML = '';

    // Create floor plan container
    const floorPlanContainer = document.createElement('div');
    floorPlanContainer.className = 'interactive-floor-plan';
    floorPlanContainer.innerHTML = `
        <svg viewBox="0 0 1000 700" class="floor-plan-svg" id="interactiveSvg">
            <!-- Building outline -->
            <rect x="50" y="50" width="900" height="600" fill="#f8fafc" stroke="#374151" stroke-width="3" rx="10"/>

            <!-- Grid lines for reference -->
            <defs>
                <pattern id="grid" width="50" height="50" patternUnits="userSpaceOnUse">
                    <path d="M 50 0 L 0 0 0 50" fill="none" stroke="#e2e8f0" stroke-width="1" opacity="0.5"/>
                </pattern>
            </defs>
            <rect x="50" y="50" width="900" height="600" fill="url(#grid)"/>

            <!-- Rooms and spaces -->
            <g class="building-spaces">
                <!-- Office spaces -->
                <rect x="60" y="60" width="200" height="150" fill="#e2e8f0" stroke="#6b7280" stroke-width="2" rx="5" class="room" data-room="office-101"/>
                <text x="160" y="140" text-anchor="middle" font-size="14" fill="#374151" font-weight="600">Office 101</text>

                <rect x="270" y="60" width="200" height="150" fill="#e2e8f0" stroke="#6b7280" stroke-width="2" rx="5" class="room" data-room="office-102"/>
                <text x="370" y="140" text-anchor="middle" font-size="14" fill="#374151" font-weight="600">Office 102</text>

                <rect x="480" y="60" width="200" height="150" fill="#e2e8f0" stroke="#6b7280" stroke-width="2" rx="5" class="room" data-room="office-103"/>
                <text x="580" y="140" text-anchor="middle" font-size="14" fill="#374151" font-weight="600">Office 103</text>

                <rect x="690" y="60" width="250" height="150" fill="#dbeafe" stroke="#3b82f6" stroke-width="2" rx="5" class="room conference" data-room="conference-a"/>
                <text x="815" y="140" text-anchor="middle" font-size="14" fill="#1e40af" font-weight="600">Conference A</text>

                <!-- Hallway -->
                <rect x="60" y="220" width="880" height="50" fill="#f3f4f6" stroke="#9ca3af" stroke-width="2" rx="5" class="hallway"/>
                <text x="500" y="250" text-anchor="middle" font-size="12" fill="#6b7280">Main Corridor</text>

                <!-- More office spaces -->
                <rect x="60" y="280" width="200" height="150" fill="#e2e8f0" stroke="#6b7280" stroke-width="2" rx="5" class="room" data-room="office-104"/>
                <text x="160" y="360" text-anchor="middle" font-size="14" fill="#374151" font-weight="600">Office 104</text>

                <rect x="270" y="280" width="200" height="150" fill="#e2e8f0" stroke="#6b7280" stroke-width="2" rx="5" class="room" data-room="office-105"/>
                <text x="370" y="360" text-anchor="middle" font-size="14" fill="#374151" font-weight="600">Office 105</text>

                <rect x="480" y="280" width="200" height="150" fill="#e2e8f0" stroke="#6b7280" stroke-width="2" rx="5" class="room" data-room="office-106"/>
                <text x="580" y="360" text-anchor="middle" font-size="14" fill="#374151" font-weight="600">Office 106</text>

                <!-- Facilities -->
                <rect x="690" y="280" width="120" height="150" fill="#fef3c7" stroke="#d97706" stroke-width="2" rx="5" class="facility" data-room="restroom"/>
                <text x="750" y="360" text-anchor="middle" font-size="12" fill="#92400e" font-weight="600">Restroom</text>

                <rect x="820" y="280" width="120" height="150" fill="#fee2e2" stroke="#dc2626" stroke-width="2" rx="5" class="facility" data-room="emergency"/>
                <text x="880" y="360" text-anchor="middle" font-size="12" fill="#991b1b" font-weight="600">Emergency Exit</text>

                <!-- Stairwells -->
                <rect x="60" y="440" width="60" height="200" fill="#374151" stroke="#1e293b" stroke-width="2" rx="5" class="stairwell"/>
                <text x="90" y="540" text-anchor="middle" font-size="10" fill="white" transform="rotate(-90 90 540)">Stairs</text>

                <rect x="880" y="440" width="60" height="200" fill="#374151" stroke="#1e293b" stroke-width="2" rx="5" class="stairwell"/>
                <text x="910" y="540" text-anchor="middle" font-size="10" fill="white" transform="rotate(90 910 540)">Stairs</text>
            </g>

            <!-- Defects layer -->
            <g class="defects-layer" id="defectsLayer">
                <!-- Sample defects will be added here -->
            </g>

            <!-- Heat map overlay (hidden by default) -->
            <g class="heatmap-overlay" id="heatmapOverlay" style="display: none;">
                <!-- Heat map will be generated here -->
            </g>
        </svg>
    `;

    canvas.appendChild(floorPlanContainer);

    // Load defects based on mode
    if (mode === 'defects') {
        loadDefectsForFloor(building, floor);
    } else if (mode === 'heatmap') {
        generateHeatMap(building, floor);
    }

    // Setup interactions
    setupFloorPlanInteractions();
}

function loadDefectsForFloor(building, floor) {
    const defectsLayer = document.getElementById('defectsLayer');
    if (!defectsLayer) return;

    // Clear existing defects
    defectsLayer.innerHTML = '';

    // Sample defect data
    const defects = [
        { id: 1, x: 160, y: 120, priority: 'high', type: 'electrical', room: 'office-101', description: 'Outlet malfunction' },
        { id: 2, x: 370, y: 120, priority: 'medium', type: 'hvac', room: 'office-102', description: 'Noisy air vent' },
        { id: 3, x: 580, y: 120, priority: 'low', type: 'plumbing', room: 'office-103', description: 'Leaky faucet' },
        { id: 4, x: 815, y: 120, priority: 'high', type: 'structural', room: 'conference-a', description: 'Cracked ceiling tile' },
        { id: 5, x: 160, y: 340, priority: 'medium', type: 'lighting', room: 'office-104', description: 'Flickering lights' },
        { id: 6, x: 370, y: 340, priority: 'low', type: 'cosmetic', room: 'office-105', description: 'Scratched wall' },
        { id: 7, x: 580, y: 340, priority: 'high', type: 'safety', room: 'office-106', description: 'Loose handrail' }
    ];

    defects.forEach(defect => {
        addDefectPin(defect);
    });
}

function addDefectPin(defect) {
    const defectsLayer = document.getElementById('defectsLayer');
    if (!defectsLayer) return;

    const pinColors = {
        'high': '#dc2626',
        'medium': '#f59e0b',
        'low': '#10b981'
    };

    const pin = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    pin.className = 'defect-pin';
    pin.setAttribute('data-defect-id', defect.id);
    pin.setAttribute('data-priority', defect.priority);

    pin.innerHTML = `
        <circle cx="${defect.x}" cy="${defect.y}" r="12" fill="${pinColors[defect.priority]}" stroke="white" stroke-width="3" class="pin-circle"/>
        <circle cx="${defect.x}" cy="${defect.y}" r="8" fill="white" opacity="0.9"/>
        <text x="${defect.x}" y="${defect.y + 3}" text-anchor="middle" font-size="10" fill="${pinColors[defect.priority]}" font-weight="bold">${defect.id}</text>
    `;

    // Add click handler
    pin.addEventListener('click', () => showDefectDetails(defect));

    defectsLayer.appendChild(pin);
}

function showDefectDetails(defect) {
    // Remove existing tooltip
    const existingTooltip = document.querySelector('.defect-tooltip-popup');
    if (existingTooltip) {
        existingTooltip.remove();
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'defect-tooltip-popup';
    tooltip.innerHTML = `
        <div class="tooltip-header">
            <span class="defect-id">#${defect.id.toString().padStart(4, '0')}</span>
            <span class="defect-priority priority-${defect.priority}">${defect.priority.charAt(0).toUpperCase() + defect.priority.slice(1)}</span>
            <button class="tooltip-close" onclick="closeDefectTooltip()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="tooltip-content">
            <h4>${defect.description}</h4>
            <div class="defect-meta">
                <div class="meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>${defect.room.replace('-', ' ').toUpperCase()}</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-tools"></i>
                    <span>${defect.type.charAt(0).toUpperCase() + defect.type.slice(1)}</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>Reported 2 days ago</span>
                </div>
            </div>
            <div class="tooltip-actions">
                <button class="btn btn-primary btn-sm" onclick="viewFullDefect(${defect.id})">
                    <i class="fas fa-eye"></i>
                    View Details
                </button>
                <button class="btn btn-success btn-sm" onclick="assignToMe()">
                    <i class="fas fa-user-plus"></i>
                    Assign to Me
                </button>
                <button class="btn btn-warning btn-sm" onclick="addNote()">
                    <i class="fas fa-sticky-note"></i>
                    Add Note
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(tooltip);

    // Position tooltip near the defect pin
    const pinElement = document.querySelector(`[data-defect-id="${defect.id}"] .pin-circle`);
    if (pinElement) {
        const rect = pinElement.getBoundingClientRect();
        tooltip.style.left = (rect.left + rect.width / 2 - 150) + 'px';
        tooltip.style.top = (rect.top - 10 - tooltip.offsetHeight) + 'px';
    }

    // Add animation
    setTimeout(() => tooltip.classList.add('show'), 10);
}

function closeDefectTooltip() {
    const tooltip = document.querySelector('.defect-tooltip-popup');
    if (tooltip) {
        tooltip.classList.remove('show');
        setTimeout(() => tooltip.remove(), 300);
    }
}

function generateHeatMap(building, floor) {
    const heatmapOverlay = document.getElementById('heatmapOverlay');
    if (!heatmapOverlay) return;

    // Show heatmap overlay
    heatmapOverlay.style.display = 'block';

    // Clear existing heatmap
    heatmapOverlay.innerHTML = '';

    // Generate heat zones based on defect density
    const heatZones = [
        { x: 60, y: 60, width: 410, height: 210, intensity: 0.8, color: '#dc2626' }, // High density area
        { x: 480, y: 280, width: 200, height: 150, intensity: 0.6, color: '#f59e0b' }, // Medium density
        { x: 690, y: 60, width: 250, height: 150, intensity: 0.4, color: '#10b981' }, // Low density
        { x: 60, y: 440, width: 880, height: 200, intensity: 0.2, color: '#6b7280' }  // Very low density
    ];

    heatZones.forEach(zone => {
        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('x', zone.x);
        rect.setAttribute('y', zone.y);
        rect.setAttribute('width', zone.width);
        rect.setAttribute('height', zone.height);
        rect.setAttribute('fill', zone.color);
        rect.setAttribute('fill-opacity', zone.intensity * 0.3);
        rect.setAttribute('stroke', zone.color);
        rect.setAttribute('stroke-width', '2');
        rect.setAttribute('stroke-opacity', zone.intensity * 0.5);
        rect.setAttribute('rx', '5');

        heatmapOverlay.appendChild(rect);
    });

    // Hide regular defects when showing heatmap
    const defectsLayer = document.getElementById('defectsLayer');
    if (defectsLayer) {
        defectsLayer.style.display = 'none';
    }
}

function setupFloorPlanInteractions() {
    const svg = document.getElementById('interactiveSvg');
    if (!svg) return;

    let selectedTool = 'select';
    let isPinning = false;

    svg.addEventListener('click', function(e) {
        if (selectedTool === 'pin' && !isPinning) {
            const rect = svg.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            // Convert to SVG coordinates
            const svgPoint = svg.createSVGPoint();
            svgPoint.x = x;
            svgPoint.y = y;
            const transformedPoint = svgPoint.matrixTransform(svg.getScreenCTM().inverse());

            addNewDefectPin(transformedPoint.x, transformedPoint.y);
        }
    });
}

function selectTool(tool) {
    // Update tool selection UI
    const toolBtns = document.querySelectorAll('.tool-btn');
    toolBtns.forEach(btn => btn.classList.remove('active'));

    const selectedBtn = document.querySelector(`[onclick="selectTool('${tool}')"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }

    // Update cursor and interaction mode
    const canvas = document.getElementById('floorPlanCanvas');
    if (canvas) {
        canvas.style.cursor = tool === 'pin' ? 'crosshair' : tool === 'measure' ? 'crosshair' : 'default';
    }

    showToast(`Selected ${tool} tool`, 'info', 1500);
}

function addNewDefectPin(x, y) {
    const newDefect = {
        id: Date.now() % 1000, // Simple ID generation
        x: x,
        y: y,
        priority: 'medium',
        type: 'general',
        room: 'unknown',
        description: 'New defect pin'
    };

    addDefectPin(newDefect);
    showToast('Defect pin added! Click to edit details.', 'success');
}

function initializeDefectPins() {
    // Setup interactions for existing pins in hero section
    const pins = document.querySelectorAll('.defect-pin');
    pins.forEach(pin => {
        pin.addEventListener('mouseenter', function() {
            const defectId = this.getAttribute('data-defect-id');
            const tooltip = document.getElementById(`tooltip-${defectId}`);
            if (tooltip) {
                tooltip.style.display = 'block';
            }
        });

        pin.addEventListener('mouseleave', function() {
            const defectId = this.getAttribute('data-defect-id');
            const tooltip = document.getElementById(`tooltip-${defectId}`);
            if (tooltip) {
                tooltip.style.display = 'none';
            }
        });
    });
}

function setupCanvasControls() {
    // Setup zoom and pan controls
    let zoomLevel = 1;
    let panX = 0;
    let panY = 0;

    const svg = document.getElementById('interactiveSvg');
    if (!svg) return;

    // Zoom functions
    window.zoomInViewer = function() {
        zoomLevel = Math.min(zoomLevel * 1.2, 3);
        updateZoom();
    };

    window.zoomOutViewer = function() {
        zoomLevel = Math.max(zoomLevel / 1.2, 0.5);
        updateZoom();
    };

    window.fitToScreen = function() {
        zoomLevel = 1;
        panX = 0;
        panY = 0;
        updateZoom();
    };

    function updateZoom() {
        svg.style.transform = `scale(${zoomLevel}) translate(${panX}px, ${panY}px)`;
        svg.style.transformOrigin = 'center center';

        // Update zoom level display
        const zoomDisplay = document.querySelector('.zoom-level');
        if (zoomDisplay) {
            zoomDisplay.textContent = Math.round(zoomLevel * 100) + '%';
        }
    }

    // Initialize zoom display
    updateZoom();
}

function changeBuilding() {
    const building = document.getElementById('buildingSelect').value;
    const floor = document.getElementById('floorSelect').value;
    const mode = document.getElementById('viewModeSelect').value;

    loadFloorPlan(building, floor, mode);
    showToast(`Switched to ${building.replace('-', ' ').toUpperCase()}`, 'info');
}

function changeFloor() {
    const building = document.getElementById('buildingSelect').value;
    const floor = document.getElementById('floorSelect').value;
    const mode = document.getElementById('viewModeSelect').value;

    loadFloorPlan(building, floor, mode);
    showToast(`Switched to ${floor.replace('-', ' ').toUpperCase()}`, 'info');
}

function changeViewMode() {
    const building = document.getElementById('buildingSelect').value;
    const floor = document.getElementById('floorSelect').value;
    const mode = document.getElementById('viewModeSelect').value;

    loadFloorPlan(building, floor, mode);

    const modeNames = {
        'defects': 'Defect View',
        'heatmap': 'Heat Map',
        'maintenance': 'Maintenance Zones',
        'empty': 'Empty Plan'
    };

    showToast(`Switched to ${modeNames[mode]}`, 'info');
}

function addDefect() {
    selectTool('pin');
    showToast('Click on the floor plan to add a defect pin', 'info', 3000);
}

function exportFloorPlan() {
    showToast('Exporting floor plan...', 'info');
    setTimeout(() => {
        showToast('Floor plan exported successfully!', 'success');
    }, 2000);
}

// Feature demo functions
function demoPinning() {
    selectTool('pin');
    showToast('Try clicking on the floor plan to pin defects!', 'info', 3000);
}

function showHeatMap() {
    changeViewMode();
    document.getElementById('viewModeSelect').value = 'heatmap';
    changeViewMode();
}

function showNavigation() {
    // Simulate navigation path
    const svg = document.getElementById('interactiveSvg');
    if (!svg) return;

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M 160 120 Q 400 200 580 340 Q 700 400 815 120');
    path.setAttribute('stroke', '#2563eb');
    path.setAttribute('stroke-width', '4');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke-dasharray', '10,5');
    path.setAttribute('class', 'navigation-path');

    svg.appendChild(path);
    showToast('Navigation path calculated for defect inspection route', 'success');
}

function switchFloor() {
    const floors = ['floor-1', 'floor-2', 'floor-3', 'floor-4'];
    const currentFloor = document.getElementById('floorSelect').value;
    const currentIndex = floors.indexOf(currentFloor);
    const nextIndex = (currentIndex + 1) % floors.length;

    document.getElementById('floorSelect').value = floors[nextIndex];
    changeFloor();
}

function zoomToDefect() {
    // Zoom to first defect
    const firstPin = document.querySelector('.defect-pin .pin-circle');
    if (firstPin) {
        firstPin.scrollIntoView({ behavior: 'smooth', block: 'center' });
        zoomInViewer();
        showToast('Zoomed to defect location', 'info');
    }
}

function showMobileView() {
    showToast('Switching to mobile-optimized floor plan view...', 'info');
    setTimeout(() => {
        showToast('Mobile view activated! Try pinch-to-zoom gestures.', 'success');
    }, 1000);
}

// Filter functions
function filterByPriority(priority) {
    const pins = document.querySelectorAll('.defect-pin');
    pins.forEach(pin => {
        const pinPriority = pin.getAttribute('data-priority');
        if (pinPriority === priority || priority === 'all') {
            pin.style.opacity = '1';
            pin.style.pointerEvents = 'auto';
        } else {
            pin.style.opacity = '0.3';
            pin.style.pointerEvents = 'none';
        }
    });

    showToast(`Filtered to ${priority} priority defects`, 'info');
}

function filterByRoom(roomType) {
    // This would filter pins by room type in a real implementation
    showToast(`Filtered to ${roomType} rooms`, 'info');
}

function showRecentDefects() {
    // Highlight recent defects
    const pins = document.querySelectorAll('.defect-pin');
    pins.forEach(pin => {
        pin.classList.add('recent-highlight');
        setTimeout(() => pin.classList.remove('recent-highlight'), 3000);
    });

    showToast('Showing defects reported in the last 7 days', 'info');
}

function generateReport() {
    showToast('Generating floor plan defect report...', 'info');
    setTimeout(() => {
        showToast('Report generated! Check your downloads.', 'success');
    }, 2000);
}

// Additional utility functions
function viewFullDefect(defectId) {
    showToast(`Opening full details for defect #${defectId}`, 'info');
}

function assignToMe() {
    showToast('Defect assigned to you successfully', 'success');
}

function addNote() {
    showToast('Note added to defect', 'success');
}

function playVoiceOver() {
    const voiceOverText = document.querySelector('.voice-over-text');
    const text = voiceOverText.textContent;
    const words = text.split(' ');

    voiceOverText.innerHTML = text;

    let wordIndex = 0;
    const highlightInterval = setInterval(() => {
        if (wordIndex < words.length) {
            const beforeHighlight = words.slice(0, wordIndex).join(' ');
            const currentWord = words[wordIndex];
            const afterHighlight = words.slice(wordIndex + 1).join(' ');

            voiceOverText.innerHTML = `${beforeHighlight} <span class="highlight-word">${currentWord}</span> ${afterHighlight}`;
            wordIndex++;
        } else {
            clearInterval(highlightInterval);
            setTimeout(() => {
                voiceOverText.innerHTML = text;
            }, 2000);
        }
    }, 250);

    showToast('Playing voice narration...', 'info', 2000);
}

function toggleAutoAdvance() {
    const statusElement = document.getElementById('autoAdvanceStatus');
    const currentStatus = statusElement.textContent;
    const newStatus = currentStatus === 'ON' ? 'OFF' : 'ON';
    statusElement.textContent = newStatus;
    showToast(`Auto advance ${newStatus.toLowerCase()}`, 'info', 1500);
}

function startFloorPlanDemo() {
    showToast('Starting interactive floor plan demo...', 'info');
    setTimeout(() => {
        showToast('Demo ready! Try the different tools and features.', 'success');
    }, 1000);
}

function uploadFloorPlan() {
    showToast('Opening floor plan upload dialog...', 'info');
    setTimeout(() => {
        showToast('Floor plan uploaded successfully! Processing...', 'success');
    }, 1500);
}

// Zoom functions for hero section
function zoomIn() {
    const svg = document.querySelector('.floor-plan-svg');
    if (svg) {
        const currentScale = svg.style.transform ? parseFloat(svg.style.transform.replace('scale(', '').replace(')', '')) : 1;
        const newScale = Math.min(currentScale * 1.2, 2);
        svg.style.transform = `scale(${newScale})`;
    }
}

function zoomOut() {
    const svg = document.querySelector('.floor-plan-svg');
    if (svg) {
        const currentScale = svg.style.transform ? parseFloat(svg.style.transform.replace('scale(', '').replace(')', '')) : 1;
        const newScale = Math.max(currentScale / 1.2, 0.5);
        svg.style.transform = `scale(${newScale})`;
    }
}

function resetView() {
    const svg = document.querySelector('.floor-plan-svg');
    if (svg) {
        svg.style.transform = 'scale(1)';
    }
}

function loadInitialFloorPlan() {
    // Load initial floor plan after a short delay
    setTimeout(() => {
        loadFloorPlan('building-a', 'floor-3', 'defects');
    }, 500);
}

function setupPinInteractions() {
    // Setup interactions for pins in the hero section
    const pins = document.querySelectorAll('.hero-visual .defect-pin');
    pins.forEach(pin => {
        pin.addEventListener('click', function() {
            const defectId = this.getAttribute('data-defect-id');
            showToast(`Defect #${defectId} selected`, 'info');
        });
    });
}

function initializeZoomControls() {
    // Initialize zoom controls for hero section
    const zoomControls = document.querySelector('.floor-controls');
    if (zoomControls) {
        // Already handled by onclick attributes in HTML
    }
}

// Add CSS for floor plan demo styles
const floorPlanStyles = `
<style>
/* Hero Section Floor Plan */
.floor-plan-demo {
    position: relative;
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.demo-floor-plan {
    position: relative;
}

.floor-plan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
}

.floor-plan-header h4 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
    font-weight: 600;
}

.floor-controls {
    display: flex;
    gap: 10px;
}

.floor-controls .btn {
    padding: 8px 12px;
    font-size: 0.9rem;
}

.floor-plan-canvas {
    position: relative;
    height: 400px;
    overflow: hidden;
    border-radius: 10px;
    background: #f8fafc;
}

.floor-plan-svg {
    width: 100%;
    height: 100%;
    transition: transform 0.3s ease;
}

.defect-pin {
    cursor: pointer;
    transition: all 0.3s ease;
}

.defect-pin:hover .pin-circle {
    r: 16;
    stroke-width: 4;
}

.defect-tooltip {
    position: absolute;
    background: white;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    z-index: 1000;
    min-width: 200px;
}

.tooltip-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.defect-id {
    font-weight: 600;
    color: #1e293b;
}

.defect-priority {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.defect-priority.priority-high {
    background: #fef2f2;
    color: #dc2626;
}

.defect-priority.priority-medium {
    background: #fef3c7;
    color: #d97706;
}

.defect-priority.priority-low {
    background: #f0fdf4;
    color: #059669;
}

.tooltip-content h4 {
    margin: 0 0 8px 0;
    color: #1e293b;
    font-size: 0.9rem;
}

.tooltip-content p {
    margin: 0 0 8px 0;
    color: #6b7280;
    font-size: 0.8rem;
}

.tooltip-actions {
    text-align: center;
}

.tooltip-actions .btn {
    font-size: 0.8rem;
    padding: 4px 12px;
}

/* Features Grid */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.feature-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.feature-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.feature-icon {
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

.feature-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.feature-card p {
    color: #64748b;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.feature-demo {
    margin-top: 1rem;
}

/* Interactive Floor Plan Viewer */
.floor-plan-viewer {
    display: grid;
    grid-template-columns: 300px 1fr 250px;
    gap: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.viewer-controls {
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
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

.viewer-actions {
    display: flex;
    gap: 1rem;
    margin-top: auto;
}

.viewer-canvas {
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}

.canvas-toolbar {
    padding: 1rem;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tool-group {
    display: flex;
    gap: 0.5rem;
}

.tool-btn {
    padding: 0.5rem 1rem;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tool-btn:hover {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.05);
}

.tool-btn.active {
    border-color: #2563eb;
    background: #2563eb;
    color: white;
}

.zoom-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.zoom-btn {
    width: 32px;
    height: 32px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.zoom-btn:hover {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.05);
}

.zoom-level {
    font-weight: 600;
    color: #374151;
    min-width: 50px;
    text-align: center;
}

.canvas-area {
    flex: 1;
    position: relative;
    overflow: hidden;
}

.interactive-floor-plan {
    width: 100%;
    height: 100%;
    position: relative;
}

.floor-plan-svg {
    width: 100%;
    height: 100%;
    cursor: default;
}

.room:hover {
    fill: #dbeafe;
    stroke: #2563eb;
    stroke-width: 3;
}

.conference:hover {
    fill: #fef3c7;
}

.facility:hover {
    stroke-width: 3;
}

.stairwell:hover {
    fill: #1e293b;
}

.defect-pin:hover {
    transform: scale(1.2);
}

.navigation-path {
    animation: dash 2s linear infinite;
}

@keyframes dash {
    to {
        stroke-dashoffset: -15;
    }
}

.loading-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6b7280;
}

.loading-placeholder i {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.viewer-sidebar {
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-left: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.sidebar-section h5 {
    margin: 0 0 1rem 0;
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 600;
}

.defect-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.stat-row:last-child {
    border-bottom: none;
}

.stat-label {
    color: #6b7280;
    font-size: 0.9rem;
}

.stat-value {
    font-weight: 600;
    color: #1e293b;
}

.stat-value.priority-high {
    color: #dc2626;
}

.stat-value.priority-medium {
    color: #d97706;
}

.stat-value.priority-low {
    color: #059669;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.action-btn:hover {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.05);
    transform: translateX(4px);
}

.legend-items {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
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
    border-radius: 50%;
    flex-shrink: 0;
}

/* Benefits Showcase */
.benefits-showcase {
    display: flex;
    flex-direction: column;
    gap: 3rem;
}

.benefit-highlight {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.benefit-highlight:nth-child(even) {
    direction: rtl;
}

.benefit-highlight:nth-child(even) .highlight-visual {
    grid-column: 2;
}

.benefit-highlight:nth-child(even) .highlight-content {
    grid-column: 1;
    direction: ltr;
}

.highlight-visual {
    position: relative;
}

.pattern-visualization {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.pattern-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    margin-bottom: 1rem;
}

.pattern-cell {
    aspect-ratio: 1;
    background: #e2e8f0;
    border-radius: 4px;
}

.pattern-cell.high-density {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

.pattern-cell.medium-density {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
}

.pattern-cell.low-density {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
}

.pattern-legend {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    font-size: 0.9rem;
    color: #6b7280;
}

.scale-bar {
    display: flex;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.scale-segment {
    flex: 1;
}

.scale-segment.low { background: #10b981; }
.scale-segment.medium { background: #f59e0b; }
.scale-segment.high { background: #dc2626; }

.efficiency-visualization {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.efficiency-metrics {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 2rem;
}

.metric-circle {
    position: relative;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
}

.metric-label {
    font-size: 0.8rem;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.communication-visualization {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.communication-flow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.flow-step {
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    color: white;
    padding: 1rem;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 120px;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
}

.flow-step i {
    font-size: 1.5rem;
}

.flow-arrow {
    font-size: 1.5rem;
    color: #6b7280;
    font-weight: bold;
}

.highlight-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
}

.highlight-content p {
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.benefit-points {
    list-style: none;
    padding: 0;
    margin: 0;
}

.benefit-points li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
    color: #374151;
}

.benefit-points li:before {
    content: "✓";
    color: #10b981;
    font-weight: bold;
    margin-right: 0.5rem;
}

/* Defect Tooltip Popup */
.defect-tooltip-popup {
    position: fixed;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid #e2e8f0;
    z-index: 9999;
    min-width: 300px;
    max-width: 400px;
    opacity: 0;
    transform: scale(0.9) translateY(10px);
    transition: all 0.3s ease;
}

.defect-tooltip-popup.show {
    opacity: 1;
    transform: scale(1) translateY(0);
}

.tooltip-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tooltip-close {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.tooltip-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.tooltip-content {
    padding: 1.5rem;
}

.tooltip-content h4 {
    margin: 0 0 1rem 0;
    color: #1e293b;
    font-size: 1.1rem;
}

.defect-meta {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #6b7280;
    font-size: 0.9rem;
}

.meta-item i {
    color: #2563eb;
    width: 16px;
}

.tooltip-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Voice Over */
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

/* Responsive Design */
@media (max-width: 1024px) {
    .floor-plan-viewer {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr auto;
    }

    .viewer-controls {
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
        order: 1;
    }

    .viewer-sidebar {
        border-left: none;
        border-top: 1px solid #e2e8f0;
        order: 3;
    }

    .benefit-highlight {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .benefit-highlight:nth-child(even) {
        direction: ltr;
    }

    .benefit-highlight:nth-child(even) .highlight-visual,
    .benefit-highlight:nth-child(even) .highlight-content {
        grid-column: 1;
    }
}

@media (max-width: 768px) {
    .features-grid {
        grid-template-columns: 1fr;
    }

    .efficiency-metrics {
        flex-direction: column;
        gap: 1rem;
    }

    .communication-flow {
        flex-direction: column;
        gap: 0.5rem;
    }

    .communication-flow .flow-arrow {
        transform: rotate(90deg);
    }

    .pattern-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .canvas-toolbar {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .tool-group {
        justify-content: center;
    }

    .zoom-controls {
        justify-content: center;
    }

    .viewer-controls {
        flex-direction: row;
        flex-wrap: wrap;
    }

    .control-group {
        min-width: 150px;
    }

    .viewer-actions {
        justify-content: center;
    }

    .defect-tooltip-popup {
        position: fixed;
        top: 20px !important;
        left: 20px !important;
        right: 20px !important;
        max-width: none !important;
    }
}
</style>
`;

// Add floor plan-specific styles to head
document.head.insertAdjacentHTML('beforeend', floorPlanStyles);