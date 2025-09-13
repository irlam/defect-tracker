<?php
/**
 * Floor Plan Selector - Defect Tracker (Improved Version)
 * floorplan_selector.php
 * Current Date: 2025-03-20
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get floor plan ID from URL
$floorPlanId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$floorPlanId) {
    header("Location: create_defect.php");
    exit();
}

// Get floor plan details
$floorPlanQuery = "SELECT id, floor_name, image_path, file_path FROM floor_plans WHERE id = :id AND status = 'active'";
$floorPlanStmt = $db->prepare($floorPlanQuery);
$floorPlanStmt->bindParam(':id', $floorPlanId);
$floorPlanStmt->execute();
$floorPlan = $floorPlanStmt->fetch(PDO::FETCH_ASSOC);

if (!$floorPlan) {
    header("Location: create_defect.php");
    exit();
}

// Return coordinates if form is submitted
$pinX = null;
$pinY = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin_x']) && isset($_POST['pin_y'])) {
    $pinX = filter_input(INPUT_POST, 'pin_x', FILTER_VALIDATE_FLOAT);
    $pinY = filter_input(INPUT_POST, 'pin_y', FILTER_VALIDATE_FLOAT);
    
    if ($pinX !== false && $pinY !== false) {
        echo json_encode([
            'success' => true,
            'pin_x' => $pinX,
            'pin_y' => $pinY,
            'floor_plan_id' => $floorPlanId
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Floor Plan Pin Placement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            background-color: #f8f9fa;
        }
        
        .floorplan-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #f8f9fa;
            z-index: 1000;
            overflow: hidden;
            cursor: grab;
        }
        
        .floorplan-container.grabbing {
            cursor: grabbing;
        }
        
        #pdfCanvas, #pinOverlay {
            position: absolute;
            left: 0;
            top: 0;
        }
        
        .controls {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 10px;
            z-index: 1001;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .location-pin {
            position: absolute;
            width: 50px;
            height: 50px;
            background-image: url('uploads/images/location-pin.svg');
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
            transform: translate(-50%, -100%);
        }
        
        .touch-indicator {
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(0, 123, 255, 0.3);
            border: 2px solid rgba(0, 123, 255, 0.6);
            pointer-events: none;
            transform: translate(-50%, -50%);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            70% { transform: translate(-50%, -50%) scale(1.3); opacity: 0.7; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        
        .timer-indicator {
            position: absolute;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            pointer-events: none;
            transform: translate(-50%, -50%);
        }
        
        .timer-circle {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(rgb(0, 123, 255) 0%, rgba(0, 123, 255, 0.3) 0%);
            transition: background 0.1s linear;
        }
        
        .instruction {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1002;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="floorplan-container">
        <div class="instruction">Touch/click and hold for 1 second to place pin</div>
        <canvas id="pdfCanvas"></canvas>
        <div id="pinOverlay"></div>
        <div id="pdfLoadingOverlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); display: flex; justify-content: center; align-items: center; font-size: 20px;">
            Loading floor plan...
        </div>
        
        <div class="controls">
            <div class="row">
                <div class="col-6">
                    <div class="btn-group w-100">
                        <button type="button" id="zoomInButton" class="btn btn-outline-primary"><i class="bx bx-zoom-in"></i></button>
                        <button type="button" id="zoomOutButton" class="btn btn-outline-primary"><i class="bx bx-zoom-out"></i></button>
                        <button type="button" id="resetZoomButton" class="btn btn-outline-primary"><i class="bx bx-refresh"></i></button>
                    </div>
                </div>
                <div class="col-6">
                    <div class="btn-group w-100">
                        <button type="button" id="clearPinButton" class="btn btn-outline-danger"><i class="bx bx-trash"></i> Clear</button>
                        <button type="button" id="confirmButton" class="btn btn-success"><i class="bx bx-check"></i> Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2/dist/sweetalert2.min.js"></script>
    <script>
        // Set PDF.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Global variables
        const floorPlanId = <?php echo json_encode($floorPlan['id']); ?>;
        const floorPlanPath = <?php echo json_encode($floorPlan['file_path']); ?>;
        let currentPin = null;
        let currentScale = 1;
        let currentTranslate = { x: 0, y: 0 };
        let lastTranslate = { x: 0, y: 0 };
        let pdfDoc = null;
        let holdTimer = null;
        const holdDuration = 1000; // 1 second hold for pin placement
        let touchIndicator = null;
        let timerIndicator = null;
        let timerStartTime = 0;
        let initialPosition = { x: 0, y: 0 };
        let isTouching = false;
        let isMouseHolding = false;
        let isDragging = false;
        let isPinDragging = false;
        let dragOffset = { x: 0, y: 0 };
        
        // Load floor plan on page load
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                await loadFloorPlan(floorPlanPath);
                setupPinchZoom();
                setupPanControl();
                document.getElementById('pdfLoadingOverlay').style.display = 'none';
            } catch (error) {
                console.error('Error loading floor plan:', error);
                showAlert('error', 'Failed to load floor plan');
            }
            
            // Button event handlers
            document.getElementById('zoomInButton').addEventListener('click', () => {
                currentScale *= 1.2;
                updateCanvasTransform();
            });
            
            document.getElementById('zoomOutButton').addEventListener('click', () => {
                currentScale *= 0.8;
                updateCanvasTransform();
            });
            
            document.getElementById('resetZoomButton').addEventListener('click', () => {
                currentScale = 1;
                currentTranslate = { x: 0, y: 0 };
                lastTranslate = { x: 0, y: 0 };
                updateCanvasTransform();
            });
            
            document.getElementById('clearPinButton').addEventListener('click', clearExistingPin);
            
            document.getElementById('confirmButton').addEventListener('click', () => {
                const pinX = document.getElementById('pin_x').value;
                const pinY = document.getElementById('pin_y').value;
                
                if (!pinX || !pinY) {
                    showAlert('warning', 'Please place a pin on the floor plan');
                    return;
                }
                
                // Return to parent window with pin coordinates
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'floor_plan_pin',
                        data: {
                            pin_x: parseFloat(pinX),
                            pin_y: parseFloat(pinY),
                            floor_plan_id: floorPlanId
                        }
                    }, '*');
                    window.close();
                } else {
                    // Fallback if opened in same window
                    localStorage.setItem('floor_plan_pin', JSON.stringify({
                        pin_x: parseFloat(pinX),
                        pin_y: parseFloat(pinY),
                        floor_plan_id: floorPlanId
                    }));
                    window.location.href = 'create_defect.php';
                }
            });
        });
        
        // Load floor plan image or PDF
        async function loadFloorPlan(url) {
            try {
                const fileExtension = url.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
                const isPDF = fileExtension === 'pdf';
                
                if (isImage) {
                    await loadImage(url);
                } else if (isPDF) {
                    await loadPDF(url);
                } else {
                    throw new Error('Unsupported file type: ' + fileExtension);
                }
            } catch (error) {
                console.error('Error loading floor plan:', error);
                throw error;
            }
        }
        
        // Load image floor plan
        async function loadImage(url) {
            return new Promise((resolve, reject) => {
                const canvas = document.getElementById('pdfCanvas');
                const context = canvas.getContext('2d');
                const container = document.querySelector('.floorplan-container');
                const img = new Image();
                
                img.onload = function() {
                    const containerWidth = container.clientWidth;
                    const containerHeight = container.clientHeight;
                    const scaleX = containerWidth / img.width;
                    const scaleY = containerHeight / img.height;
                    
                    // Use 95% of available space instead of 90%
                    const scale = Math.min(scaleX, scaleY) * 0.95;
                    
                    canvas.width = img.width;
                    canvas.height = img.height;
                    canvas.style.width = `${img.width}px`;
                    canvas.style.height = `${img.height}px`;
                    
                    context.drawImage(img, 0, 0, img.width, img.height);
                    
                    // Center the canvas with the larger scale
                    currentScale = scale;
                    updateCanvasTransform();
                    
                    setupPinPlacement();
                    resolve();
                };
                
                img.onerror = function() {
                    reject(new Error('Failed to load image: ' + url));
                };
                
                img.src = url;
            });
        }
        
        // Load PDF floor plan
        async function loadPDF(url) {
            try {
                const loadingTask = pdfjsLib.getDocument(url);
                pdfDoc = await loadingTask.promise;
                const page = await pdfDoc.getPage(1);
                const canvas = document.getElementById('pdfCanvas');
                const context = canvas.getContext('2d');
                const container = document.querySelector('.floorplan-container');
                
                const viewport = page.getViewport({ scale: 1.0 });
                const containerWidth = container.clientWidth;
                const containerHeight = container.clientHeight;
                const scaleX = containerWidth / viewport.width;
                const scaleY = containerHeight / viewport.height;
                
                // Use 95% of available space
                const scale = Math.min(scaleX, scaleY) * 0.95;
                
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.width = `${viewport.width}px`;
                canvas.style.height = `${viewport.height}px`;
                
                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                
                await page.render(renderContext);
                
                // Center the canvas with the larger scale
                currentScale = scale;
                updateCanvasTransform();
                
                setupPinPlacement();
            } catch (error) {
                console.error('Error loading PDF:', error);
                throw error;
            }
        }
        
        // Update canvas transform 
        function updateCanvasTransform() {
            const canvas = document.getElementById('pdfCanvas');
            const overlay = document.getElementById('pinOverlay');
            const container = document.querySelector('.floorplan-container');
            
            // Calculate centering position
            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;
            const canvasWidth = parseInt(canvas.style.width || canvas.width);
            const canvasHeight = parseInt(canvas.style.height || canvas.height);
            
            const centerX = (containerWidth - canvasWidth * currentScale) / 2;
            const centerY = (containerHeight - canvasHeight * currentScale) / 2;
            
            const translateX = centerX + currentTranslate.x;
            const translateY = centerY + currentTranslate.y;
            
            // Apply transform
            const transform = `translate(${translateX}px, ${translateY}px) scale(${currentScale})`;
            canvas.style.transform = transform;
            overlay.style.transform = transform;
            
            // Match overlay size to canvas
            overlay.style.width = `${canvasWidth}px`;
            overlay.style.height = `${canvasHeight}px`;
        }
        
        // Setup pin placement with touch and hold
        function setupPinPlacement() {
            const overlay = document.getElementById('pinOverlay');
            const container = document.querySelector('.floorplan-container');
            overlay.innerHTML = '';  // Clear overlay
            
            // Create empty hidden inputs for pin coordinates
            if (!document.getElementById('pin_x')) {
                const pinXInput = document.createElement('input');
                pinXInput.type = 'hidden';
                pinXInput.id = 'pin_x';
                document.body.appendChild(pinXInput);
                
                const pinYInput = document.createElement('input');
                pinYInput.type = 'hidden';
                pinYInput.id = 'pin_y';
                document.body.appendChild(pinYInput);
            }
            
            // Helper function to get relative coordinates
            function getRelativeCoordinates(clientX, clientY) {
                const rect = overlay.getBoundingClientRect();
                return {
                    x: (clientX - rect.left) / currentScale / overlay.clientWidth,
                    y: (clientY - rect.top) / currentScale / overlay.clientHeight
                };
            }
            
            // ----- TOUCH EVENTS ----- //
            
            // Touch start - begin timing for potential pin placement
            overlay.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return; // Only handle single touches
                
                const touch = e.touches[0];
                initialPosition = { 
                    x: touch.clientX, 
                    y: touch.clientY 
                };
                
                // Get relative position within the overlay
                const rect = overlay.getBoundingClientRect();
                const overlayX = (touch.clientX - rect.left) / currentScale;
                const overlayY = (touch.clientY - rect.top) / currentScale;
                
                // Start the hold timer
                timerStartTime = Date.now();
                isTouching = true;
                isDragging = false;
                
                // Create touch indicator and timer
                createTouchIndicator(touch.clientX, touch.clientY);
                createTimerIndicator(touch.clientX, touch.clientY);
                
                // Start the timer animation
                requestAnimationFrame(updateTimerIndicator);
                
                // Set hold timer
                holdTimer = setTimeout(() => {
                    if (isTouching && !isDragging) {
                        const coords = getRelativeCoordinates(touch.clientX, touch.clientY);
                        placePin(coords.x, coords.y);
                        
                        // Vibrate device if supported (100ms)
                        if (navigator.vibrate) {
                            navigator.vibrate(100);
                        }
                    }
                }, holdDuration);
            });
            
            // Touch move - cancel pin placement if dragged too far
            overlay.addEventListener('touchmove', function(e) {
                if (!isTouching || e.touches.length !== 1) return;
                
                const touch = e.touches[0];
                const deltaX = Math.abs(touch.clientX - initialPosition.x);
                const deltaY = Math.abs(touch.clientY - initialPosition.y);
                
                // If moved more than 10px, consider it a drag
                if (deltaX > 10 || deltaY > 10) {
                    isDragging = true;
                    if (holdTimer) {
                        clearTimeout(holdTimer);
                        holdTimer = null;
                    }
                    
                    // Remove indicators
                    removeTouchIndicator();
                    removeTimerIndicator();
                }
                
                // Update touch indicator position
                if (touchIndicator) {
                    touchIndicator.style.left = `${touch.clientX}px`;
                    touchIndicator.style.top = `${touch.clientY}px`;
                }
                
                // Update timer indicator position
                if (timerIndicator) {
                    timerIndicator.style.left = `${touch.clientX}px`;
                    timerIndicator.style.top = `${touch.clientY}px`;
                }
                
                e.preventDefault(); // Prevent scrolling
            });
            
            // Touch end - clean up
            overlay.addEventListener('touchend', function() {
                isTouching = false;
                if (holdTimer) {
                    clearTimeout(holdTimer);
                    holdTimer = null;
                }
                
                // Remove indicators
                removeTouchIndicator();
                removeTimerIndicator();
            });
            
            // Touch cancel - clean up
            overlay.addEventListener('touchcancel', function() {
                isTouching = false;
                if (holdTimer) {
                    clearTimeout(holdTimer);
                    holdTimer = null;
                }
                
                // Remove indicators
                removeTouchIndicator();
                removeTimerIndicator();
            });
            
            // ----- MOUSE EVENTS ----- //
            
            // Mouse down - begin timing for potential pin placement
            overlay.addEventListener('mousedown', function(e) {
                // Skip if using touch
                if (isTouching) return;
                
                initialPosition = { 
                    x: e.clientX, 
                    y: e.clientY 
                };
                
                // Start the hold timer
                timerStartTime = Date.now();
                isMouseHolding = true;
                isDragging = false;
                
                // Create indicators
                createTouchIndicator(e.clientX, e.clientY);
                createTimerIndicator(e.clientX, e.clientY);
                
                // Start the timer animation
                requestAnimationFrame(updateTimerIndicator);
                
                // Set hold timer
                holdTimer = setTimeout(() => {
                    if (isMouseHolding && !isDragging) {
                        const coords = getRelativeCoordinates(e.clientX, e.clientY);
                        placePin(coords.x, coords.y);
                    }
                }, holdDuration);
            });
            
            // Mouse move - cancel pin placement if dragged too far
            document.addEventListener('mousemove', function(e) {
                if (!isMouseHolding) return;
                
                const deltaX = Math.abs(e.clientX - initialPosition.x);
                const deltaY = Math.abs(e.clientY - initialPosition.y);
                
                // If moved more than 10px, consider it a drag
                if (deltaX > 10 || deltaY > 10) {
                    isDragging = true;
                    if (holdTimer) {
                        clearTimeout(holdTimer);
                        holdTimer = null;
                    }
                    
                    // Remove indicators
                    removeTouchIndicator();
                    removeTimerIndicator();
                }
                
                // Update indicator positions
                if (touchIndicator) {
                    touchIndicator.style.left = `${e.clientX}px`;
                    touchIndicator.style.top = `${e.clientY}px`;
                }
                
                if (timerIndicator) {
                    timerIndicator.style.left = `${e.clientX}px`;
                    timerIndicator.style.top = `${e.clientY}px`;
                }
            });
            
            // Mouse up - clean up
            document.addEventListener('mouseup', function() {
                isMouseHolding = false;
                if (holdTimer) {
                    clearTimeout(holdTimer);
                    holdTimer = null;
                }
                
                // Remove indicators
                removeTouchIndicator();
                removeTimerIndicator();
            });
            
            // Mouse leave - clean up
            overlay.addEventListener('mouseleave', function() {
                isMouseHolding = false;
                if (holdTimer) {
                    clearTimeout(holdTimer);
                    holdTimer = null;
                }
                
                // Remove indicators
                removeTouchIndicator();
                removeTimerIndicator();
            });
        }
        
        // Create visual touch indicator
        function createTouchIndicator(x, y) {
            // Remove any existing indicator
            removeTouchIndicator();
            
            // Create new indicator
            touchIndicator = document.createElement('div');
            touchIndicator.className = 'touch-indicator';
            touchIndicator.style.left = `${x}px`;
            touchIndicator.style.top = `${y}px`;
            document.body.appendChild(touchIndicator);
        }
        
        // Remove touch indicator
        function removeTouchIndicator() {
            if (touchIndicator) {
                touchIndicator.remove();
                touchIndicator = null;
            }
        }
        
        // Create timer indicator
        function createTimerIndicator(x, y) {
            // Remove any existing indicator
            removeTimerIndicator();
            
            // Create new indicator
            timerIndicator = document.createElement('div');
            timerIndicator.className = 'timer-indicator';
            timerIndicator.style.left = `${x}px`;
            timerIndicator.style.top = `${y}px`;
            
            // Create timer circle
            const timerCircle = document.createElement('div');
            timerCircle.className = 'timer-circle';
            timerIndicator.appendChild(timerCircle);
            
            document.body.appendChild(timerIndicator);
        }
        
        // Remove timer indicator
        function removeTimerIndicator() {
            if (timerIndicator) {
                timerIndicator.remove();
                timerIndicator = null;
            }
        }
        
        // Update timer indicator animation
        function updateTimerIndicator() {
            if (!timerIndicator || (!isTouching && !isMouseHolding)) return;
            
            const elapsed = Date.now() - timerStartTime;
            const progress = Math.min(elapsed / holdDuration, 1);
            
            // Update the conic gradient
            const timerCircle = timerIndicator.querySelector('.timer-circle');
            if (timerCircle) {
                timerCircle.style.background = `conic-gradient(rgb(0, 123, 255) ${progress * 360}deg, rgba(0, 123, 255, 0.3) 0%)`;
            }
            
            if (progress < 1 && (isTouching || isMouseHolding)) {
                requestAnimationFrame(updateTimerIndicator);
            }
        }
        
        // Place pin on the floor plan
        function placePin(x, y) {
            clearExistingPin();
            
            // Create new pin
            currentPin = document.createElement('div');
            currentPin.className = 'location-pin';
            const overlay = document.getElementById('pinOverlay');
            currentPin.style.left = (x * 100) + '%';
            currentPin.style.top = (y * 100) + '%';
            overlay.appendChild(currentPin);
            
            // Set hidden input values
            document.getElementById('pin_x').value = x;
            document.getElementById('pin_y').value = y;
            
            // Make pin draggable
            setupPinDragging(currentPin);
            
            showAlert('success', 'Pin placed successfully!', 'You can drag the pin to adjust its position');
        }
        
        // Clear existing pin
        function clearExistingPin() {
            const overlay = document.getElementById('pinOverlay');
            while (overlay.firstChild) {
                overlay.removeChild(overlay.firstChild);
            }
            currentPin = null;
            
            // Clear hidden input values
            document.getElementById('pin_x').value = '';
            document.getElementById('pin_y').value = '';
        }
        
        // Make pin draggable
        function setupPinDragging(pin) {
            // Touch drag
            pin.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return;
                
                e.stopPropagation();
                
                isPinDragging = true;
                const touch = e.touches[0];
                const pinRect = pin.getBoundingClientRect();
                
                // Calculate offset from touch point to pin center
                dragOffset = {
                    x: touch.clientX - (pinRect.left + pinRect.width / 2),
                    y: touch.clientY - (pinRect.top + pinRect.height / 2)
                };
                
                // Add shadow to indicate active dragging
                pin.style.filter = 'drop-shadow(0 0 10px rgba(0,123,255,0.8))';
            });
            
            // Mouse drag - start
            pin.addEventListener('mousedown', function(e) {
                e.stopPropagation();
                
                isPinDragging = true;
                const pinRect = pin.getBoundingClientRect();
                
                // Calculate offset from mouse point to pin center
                dragOffset = {
                    x: e.clientX - (pinRect.left + pinRect.width / 2),
                    y: e.clientY - (pinRect.top + pinRect.height / 2)
                };
                
                // Add shadow to indicate active dragging
                pin.style.filter = 'drop-shadow(0 0 10px rgba(0,123,255,0.8))';
            });
            
            // Touch drag - move
            document.addEventListener('touchmove', function(e) {
                if (!isPinDragging || e.touches.length !== 1) return;
                
                e.preventDefault();
                
                const touch = e.touches[0];
                updatePinPosition(touch.clientX, touch.clientY);
            });
            
            // Mouse drag - move
            document.addEventListener('mousemove', function(e) {
                if (!isPinDragging) return;
                
                e.preventDefault();
                updatePinPosition(e.clientX, e.clientY);
            });
            
            // Touch drag - end
            document.addEventListener('touchend', function() {
                if (!isPinDragging) return;
                
                isPinDragging = false;
                pin.style.filter = '';
                
                showAlert('success', 'Pin position updated');
            });
            
                        // Mouse drag - end
            document.addEventListener('mouseup', function() {
                if (!isPinDragging) return;
                
                isPinDragging = false;
                pin.style.filter = '';
                
                showAlert('success', 'Pin position updated');
            });
            
            // Mouse drag - cancel
            document.addEventListener('mouseleave', function() {
                if (!isPinDragging) return;
                
                isPinDragging = false;
                pin.style.filter = '';
            });
            
            // Function to update pin position during drag
            function updatePinPosition(clientX, clientY) {
                const overlay = document.getElementById('pinOverlay');
                const rect = overlay.getBoundingClientRect();
                
                // Calculate new position (compensating for drag offset)
                const newX = (clientX - dragOffset.x - rect.left) / currentScale;
                const newY = (clientY - dragOffset.y - rect.top) / currentScale;
                
                // Convert to relative coordinates (0-1)
                const relativeX = newX / overlay.clientWidth;
                const relativeY = newY / overlay.clientHeight;
                
                // Constrain within bounds
                const boundedX = Math.max(0, Math.min(1, relativeX));
                const boundedY = Math.max(0, Math.min(1, relativeY));
                
                // Update pin position
                pin.style.left = (boundedX * 100) + '%';
                pin.style.top = (boundedY * 100) + '%';
                
                // Update hidden input values
                document.getElementById('pin_x').value = boundedX;
                document.getElementById('pin_y').value = boundedY;
            }
        }
        
        // Setup pinch-zoom functionality
        function setupPinchZoom() {
            const container = document.querySelector('.floorplan-container');
            let initialDistance = 0;
            let initialScale = 1;
            
            container.addEventListener('touchstart', function(e) {
                if (e.touches.length === 2) {
                    e.preventDefault();
                    
                    // Calculate initial distance between touch points
                    const dx = e.touches[0].clientX - e.touches[1].clientX;
                    const dy = e.touches[0].clientY - e.touches[1].clientY;
                    initialDistance = Math.sqrt(dx * dx + dy * dy);
                    initialScale = currentScale;
                }
            });
            
            container.addEventListener('touchmove', function(e) {
                if (e.touches.length === 2 && initialDistance > 0) {
                    e.preventDefault();
                    
                    // Calculate new distance
                    const dx = e.touches[0].clientX - e.touches[1].clientX;
                    const dy = e.touches[0].clientY - e.touches[1].clientY;
                    const newDistance = Math.sqrt(dx * dx + dy * dy);
                    
                    // Calculate scale factor
                    const scaleFactor = newDistance / initialDistance;
                    currentScale = initialScale * scaleFactor;
                    
                    // Constrain scale
                    currentScale = Math.max(0.5, Math.min(5, currentScale));
                    
                    updateCanvasTransform();
                }
            });
            
            container.addEventListener('touchend', function() {
                initialDistance = 0;
            });
            
            container.addEventListener('touchcancel', function() {
                initialDistance = 0;
            });
            
            // Add mouse wheel zoom
            container.addEventListener('wheel', function(e) {
                e.preventDefault();
                
                // Determine zoom direction
                const delta = e.deltaY || e.detail || e.wheelDelta;
                
                if (delta > 0) {
                    // Zoom out
                    currentScale = Math.max(0.5, currentScale * 0.9);
                } else {
                    // Zoom in
                    currentScale = Math.min(5, currentScale * 1.1);
                }
                
                updateCanvasTransform();
            });
        }
        
        // Setup pan control for both touch and mouse
        function setupPanControl() {
            const container = document.querySelector('.floorplan-container');
            let startX, startY;
            let isPanning = false;
            
            // Mouse pan events
            container.addEventListener('mousedown', function(e) {
                // Skip if we're interacting with the pin
                if (isPinDragging || isMouseHolding) return;
                
                container.classList.add('grabbing');
                isPanning = true;
                startX = e.clientX - currentTranslate.x;
                startY = e.clientY - currentTranslate.y;
            });
            
            document.addEventListener('mousemove', function(e) {
                if (!isPanning) return;
                
                e.preventDefault();
                currentTranslate.x = e.clientX - startX;
                currentTranslate.y = e.clientY - startY;
                
                updateCanvasTransform();
            });
            
            document.addEventListener('mouseup', function() {
                if (!isPanning) return;
                
                container.classList.remove('grabbing');
                isPanning = false;
                lastTranslate.x = currentTranslate.x;
                lastTranslate.y = currentTranslate.y;
            });
            
            // Touch pan events (for single finger pan)
            container.addEventListener('touchstart', function(e) {
                if (e.touches.length === 1 && !isPinDragging) {
                    startX = e.touches[0].clientX - currentTranslate.x;
                    startY = e.touches[0].clientY - currentTranslate.y;
                }
            });
            
            container.addEventListener('touchmove', function(e) {
                if (e.touches.length === 1 && isDragging && !isPinDragging) {
                    e.preventDefault();
                    isPanning = true;
                    
                    currentTranslate.x = e.touches[0].clientX - startX;
                    currentTranslate.y = e.touches[0].clientY - startY;
                    
                    updateCanvasTransform();
                }
            });
            
            container.addEventListener('touchend', function() {
                if (isPanning) {
                    lastTranslate.x = currentTranslate.x;
                    lastTranslate.y = currentTranslate.y;
                    isPanning = false;
                }
            });
        }
        
        // Show alert
        function showAlert(icon, title, text = '') {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                toast: true,
                position: 'top',
                showConfirmButton: false,
                timer: 3000
            });
        }
    </script>
</body>
</html>