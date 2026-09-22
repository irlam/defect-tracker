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
        
        .floorplan-container {
            touch-action: none;
            user-select: none;
        }

        .floorplan-container.pan-mode {
            cursor: grab;
        }

        .floorplan-container.pan-mode.grabbing {
            cursor: grabbing;
        }

        .floorplan-container.pin-mode {
            cursor: crosshair;
        }
        
        #pdfCanvas, #pinOverlay {
            position: absolute;
            left: 0;
            top: 0;
            transform-origin: 0 0;
        }

        #pdfCanvas {
            pointer-events: none;
        }

        #pinOverlay {
            touch-action: none;
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
            pointer-events: auto;
            cursor: move;
            touch-action: none;
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
<body class="tool-body" data-bs-theme="dark">
    <div class="floorplan-container pan-mode">
        <div class="instruction" id="interactionHint">Drag to move • wheel/pinch to zoom • choose Place Pin to mark location</div>
        <canvas id="pdfCanvas"></canvas>
        <div id="pinOverlay"></div>
        <div id="pdfLoadingOverlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); display: flex; justify-content: center; align-items: center; font-size: 20px;">
            Loading floor plan...
        </div>
        
        <div class="controls">
            <div class="row">
                <div class="col-7">
                    <div class="btn-group w-100">
                        <button type="button" id="panModeButton" class="btn btn-primary" title="Pan floor plan"><i class="bx bx-move"></i> Pan</button>
                        <button type="button" id="pinModeButton" class="btn btn-outline-primary" title="Place defect pin"><i class="bx bx-map-pin"></i> Place Pin</button>
                        <button type="button" id="zoomInButton" class="btn btn-outline-primary" title="Zoom in"><i class="bx bx-zoom-in"></i></button>
                        <button type="button" id="zoomOutButton" class="btn btn-outline-primary" title="Zoom out"><i class="bx bx-zoom-out"></i></button>
                        <button type="button" id="resetZoomButton" class="btn btn-outline-primary" title="Fit floor plan"><i class="bx bx-expand"></i></button>
                    </div>
                </div>
                <div class="col-5">
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

        // Interactive floor plan state
        const floorPlanId = <?php echo json_encode($floorPlan['id']); ?>;
        const floorPlanPath = <?php echo json_encode($floorPlan['file_path']); ?>;
        const container = document.querySelector('.floorplan-container');
        const canvas = document.getElementById('pdfCanvas');
        const overlay = document.getElementById('pinOverlay');
        const controlsHeight = 86;

        let pdfDoc = null;
        let currentPin = null;
        let fitScale = 1;
        let currentScale = 1;
        let currentTranslate = { x: 0, y: 0 };
        let interactionMode = 'pan';
        let activePointerId = null;
        let pointerStart = null;
        let translateStart = null;
        let pinDragPointerId = null;
        let pinchStart = null;

        document.addEventListener('DOMContentLoaded', async function() {
            ensureCoordinateInputs();
            setMode('pan');

            try {
                await loadFloorPlan(floorPlanPath);
                setupInteractions();
                document.getElementById('pdfLoadingOverlay').style.display = 'none';
            } catch (error) {
                console.error('Error loading floor plan:', error);
                showAlert('error', 'Failed to load floor plan');
            }

            document.getElementById('panModeButton').addEventListener('click', () => setMode('pan'));
            document.getElementById('pinModeButton').addEventListener('click', () => setMode('pin'));
            document.getElementById('zoomInButton').addEventListener('click', () => zoomAt(1.25, container.clientWidth / 2, (container.clientHeight - controlsHeight) / 2));
            document.getElementById('zoomOutButton').addEventListener('click', () => zoomAt(0.8, container.clientWidth / 2, (container.clientHeight - controlsHeight) / 2));
            document.getElementById('resetZoomButton').addEventListener('click', fitToView);
            document.getElementById('clearPinButton').addEventListener('click', clearExistingPin);
            window.addEventListener('resize', () => {
                if (Math.abs(currentScale - fitScale) < 0.01) fitToView();
                else updateCanvasTransform();
            });

            document.getElementById('confirmButton').addEventListener('click', () => {
                const pinX = document.getElementById('pin_x').value;
                const pinY = document.getElementById('pin_y').value;
                if (pinX === '' || pinY === '') {
                    showAlert('warning', 'Please place a pin on the floor plan');
                    return;
                }

                const payload = {
                    pin_x: parseFloat(pinX),
                    pin_y: parseFloat(pinY),
                    floor_plan_id: floorPlanId
                };

                if (window.opener) {
                    window.opener.postMessage({ type: 'floor_plan_pin', data: payload }, window.location.origin);
                    window.close();
                } else {
                    localStorage.setItem('floor_plan_pin', JSON.stringify(payload));
                    window.location.href = 'create_defect.php';
                }
            });
        });

        function ensureCoordinateInputs() {
            for (const id of ['pin_x', 'pin_y']) {
                if (!document.getElementById(id)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = id;
                    document.body.appendChild(input);
                }
            }
        }

        function setMode(mode) {
            interactionMode = mode;
            container.classList.toggle('pan-mode', mode === 'pan');
            container.classList.toggle('pin-mode', mode === 'pin');
            document.getElementById('panModeButton')?.classList.toggle('btn-primary', mode === 'pan');
            document.getElementById('panModeButton')?.classList.toggle('btn-outline-primary', mode !== 'pan');
            document.getElementById('pinModeButton')?.classList.toggle('btn-primary', mode === 'pin');
            document.getElementById('pinModeButton')?.classList.toggle('btn-outline-primary', mode !== 'pin');
            const hint = document.getElementById('interactionHint');
            if (hint) {
                hint.textContent = mode === 'pin'
                    ? 'Click or tap the exact defect location'
                    : 'Drag to move • wheel/pinch to zoom • choose Place Pin to mark location';
            }
        }

        async function loadFloorPlan(url) {
            const cleanUrl = url.split('?')[0];
            const ext = cleanUrl.split('.').pop().toLowerCase();
            if (['jpg','jpeg','png','gif','webp'].includes(ext)) return loadImage(url);
            if (ext === 'pdf') return loadPDF(url);
            throw new Error('Unsupported file type: ' + ext);
        }

        function loadImage(url) {
            return new Promise((resolve, reject) => {
                const context = canvas.getContext('2d');
                const img = new Image();
                img.onload = () => {
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    canvas.style.width = img.naturalWidth + 'px';
                    canvas.style.height = img.naturalHeight + 'px';
                    context.drawImage(img, 0, 0);
                    syncOverlaySize();
                    fitToView();
                    resolve();
                };
                img.onerror = () => reject(new Error('Failed to load image'));
                img.src = url;
            });
        }

        async function loadPDF(url) {
            const loadingTask = pdfjsLib.getDocument(url);
            pdfDoc = await loadingTask.promise;
            const page = await pdfDoc.getPage(1);
            const viewport = page.getViewport({ scale: 1 });
            const context = canvas.getContext('2d');

            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            canvas.style.width = canvas.width + 'px';
            canvas.style.height = canvas.height + 'px';
            await page.render({ canvasContext: context, viewport }).promise;
            syncOverlaySize();
            fitToView();
        }

        function syncOverlaySize() {
            overlay.style.width = canvas.width + 'px';
            overlay.style.height = canvas.height + 'px';
        }

        function viewportHeight() {
            return Math.max(160, container.clientHeight - controlsHeight);
        }

        function centerForScale(scale) {
            return {
                x: (container.clientWidth - canvas.width * scale) / 2,
                y: (viewportHeight() - canvas.height * scale) / 2
            };
        }

        function fitToView() {
            const scaleX = (container.clientWidth * 0.96) / Math.max(1, canvas.width);
            const scaleY = (viewportHeight() * 0.94) / Math.max(1, canvas.height);
            fitScale = Math.max(0.05, Math.min(scaleX, scaleY));
            currentScale = fitScale;
            currentTranslate = { x: 0, y: 0 };
            updateCanvasTransform();
        }

        function updateCanvasTransform() {
            const center = centerForScale(currentScale);
            const tx = center.x + currentTranslate.x;
            const ty = center.y + currentTranslate.y;
            const transform = `translate(${tx}px, ${ty}px) scale(${currentScale})`;
            canvas.style.transform = transform;
            overlay.style.transform = transform;
        }

        function contentPointAt(clientX, clientY, scale = currentScale, translate = currentTranslate) {
            const rect = container.getBoundingClientRect();
            const center = centerForScale(scale);
            return {
                x: (clientX - rect.left - center.x - translate.x) / scale,
                y: (clientY - rect.top - center.y - translate.y) / scale
            };
        }

        function zoomAt(factor, clientX, clientY) {
            if (!canvas.width || !canvas.height) return;
            const oldScale = currentScale;
            const content = contentPointAt(clientX, clientY, oldScale, currentTranslate);
            const minScale = Math.max(fitScale * 0.5, 0.03);
            const maxScale = Math.max(fitScale * 12, 8);
            const newScale = Math.max(minScale, Math.min(maxScale, oldScale * factor));
            if (Math.abs(newScale - oldScale) < 0.0001) return;

            const rect = container.getBoundingClientRect();
            const newCenter = centerForScale(newScale);
            currentScale = newScale;
            currentTranslate = {
                x: clientX - rect.left - newCenter.x - content.x * newScale,
                y: clientY - rect.top - newCenter.y - content.y * newScale
            };
            updateCanvasTransform();
        }

        function placePinAtClient(clientX, clientY) {
            const point = contentPointAt(clientX, clientY);
            const x = Math.max(0, Math.min(1, point.x / canvas.width));
            const y = Math.max(0, Math.min(1, point.y / canvas.height));
            if (point.x < 0 || point.x > canvas.width || point.y < 0 || point.y > canvas.height) {
                showAlert('warning', 'Choose a point inside the floor plan');
                return;
            }
            placePin(x, y);
            setMode('pan');
        }

        function placePin(x, y) {
            clearExistingPin();
            currentPin = document.createElement('div');
            currentPin.className = 'location-pin';
            currentPin.style.left = (x * 100) + '%';
            currentPin.style.top = (y * 100) + '%';
            overlay.appendChild(currentPin);
            document.getElementById('pin_x').value = x.toFixed(6);
            document.getElementById('pin_y').value = y.toFixed(6);
            setupPinDragging(currentPin);
            showAlert('success', 'Pin placed', 'Drag the pin to fine-tune its position');
        }

        function clearExistingPin() {
            overlay.querySelectorAll('.location-pin').forEach(pin => pin.remove());
            currentPin = null;
            document.getElementById('pin_x').value = '';
            document.getElementById('pin_y').value = '';
        }

        function setupPinDragging(pin) {
            pin.addEventListener('pointerdown', e => {
                e.preventDefault();
                e.stopPropagation();
                pinDragPointerId = e.pointerId;
                pin.setPointerCapture(e.pointerId);
                pin.style.filter = 'drop-shadow(0 0 10px rgba(0,123,255,.8))';
            });

            pin.addEventListener('pointermove', e => {
                if (pinDragPointerId !== e.pointerId) return;
                e.preventDefault();
                const point = contentPointAt(e.clientX, e.clientY);
                const x = Math.max(0, Math.min(1, point.x / canvas.width));
                const y = Math.max(0, Math.min(1, point.y / canvas.height));
                pin.style.left = (x * 100) + '%';
                pin.style.top = (y * 100) + '%';
                document.getElementById('pin_x').value = x.toFixed(6);
                document.getElementById('pin_y').value = y.toFixed(6);
            });

            const finish = e => {
                if (pinDragPointerId !== e.pointerId) return;
                try { pin.releasePointerCapture(e.pointerId); } catch (_) {}
                pinDragPointerId = null;
                pin.style.filter = '';
            };
            pin.addEventListener('pointerup', finish);
            pin.addEventListener('pointercancel', finish);
        }

        function setupInteractions() {
            overlay.addEventListener('pointerdown', e => {
                if (e.button !== undefined && e.button !== 0) return;
                if (pinDragPointerId !== null) return;
                e.preventDefault();

                if (interactionMode === 'pin') {
                    placePinAtClient(e.clientX, e.clientY);
                    return;
                }

                activePointerId = e.pointerId;
                pointerStart = { x: e.clientX, y: e.clientY };
                translateStart = { ...currentTranslate };
                overlay.setPointerCapture(e.pointerId);
                container.classList.add('grabbing');
            });

            overlay.addEventListener('pointermove', e => {
                if (activePointerId !== e.pointerId || !pointerStart) return;
                e.preventDefault();
                currentTranslate = {
                    x: translateStart.x + (e.clientX - pointerStart.x),
                    y: translateStart.y + (e.clientY - pointerStart.y)
                };
                updateCanvasTransform();
            });

            const finishPan = e => {
                if (activePointerId !== e.pointerId) return;
                try { overlay.releasePointerCapture(e.pointerId); } catch (_) {}
                activePointerId = null;
                pointerStart = null;
                translateStart = null;
                container.classList.remove('grabbing');
            };
            overlay.addEventListener('pointerup', finishPan);
            overlay.addEventListener('pointercancel', finishPan);

            container.addEventListener('wheel', e => {
                e.preventDefault();
                zoomAt(e.deltaY < 0 ? 1.12 : 0.89, e.clientX, e.clientY);
            }, { passive: false });

            container.addEventListener('touchstart', e => {
                if (e.touches.length !== 2) return;
                e.preventDefault();
                const a=e.touches[0], b=e.touches[1];
                pinchStart = {
                    distance: Math.hypot(a.clientX-b.clientX, a.clientY-b.clientY),
                    scale: currentScale,
                    midpoint: { x:(a.clientX+b.clientX)/2, y:(a.clientY+b.clientY)/2 },
                    content: contentPointAt((a.clientX+b.clientX)/2,(a.clientY+b.clientY)/2)
                };
            }, { passive:false });

            container.addEventListener('touchmove', e => {
                if (e.touches.length !== 2 || !pinchStart) return;
                e.preventDefault();
                const a=e.touches[0], b=e.touches[1];
                const distance=Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY);
                const midpoint={x:(a.clientX+b.clientX)/2,y:(a.clientY+b.clientY)/2};
                const newScale=Math.max(Math.max(fitScale*.5,.03),Math.min(Math.max(fitScale*12,8),pinchStart.scale*(distance/pinchStart.distance)));
                const rect=container.getBoundingClientRect();
                const center=centerForScale(newScale);
                currentScale=newScale;
                currentTranslate={
                    x:midpoint.x-rect.left-center.x-pinchStart.content.x*newScale,
                    y:midpoint.y-rect.top-center.y-pinchStart.content.y*newScale
                };
                updateCanvasTransform();
            }, { passive:false });

            container.addEventListener('touchend', e => {
                if (e.touches.length < 2) pinchStart=null;
            });
            container.addEventListener('touchcancel', () => { pinchStart=null; });
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