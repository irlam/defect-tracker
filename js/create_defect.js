/**
 * create_defect.js
 * Handles floor plan interaction and defect creation
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-03-11 21:26:08
 * Current User's Login: irlam
 */

// Global variables
let currentPin = null;
let pinPlaced = false;
let currentScale = 1;
let currentTranslate = { x: 0, y: 0 };
let lastTranslate = { x: 0, y: 0 };
let pdfDoc = null;
let holdTimer = null;
const holdDuration = 1000;
let selectedImage = null;
let isDrawing = false;
let drawingContext = null;
let drawingCanvas = null;

// Set PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// Toast notification function
function showToast(icon, text) {
    Swal.fire({
        icon: icon,
        text: text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
}

// Clear existing pin from the overlay
function clearExistingPin() {
    const overlay = document.getElementById('pinOverlay');
    while (overlay.firstChild) {
        overlay.removeChild(overlay.firstChild);
    }
    currentPin = null;
    pinPlaced = false;
    document.getElementById('pin_x').value = '';
    document.getElementById('pin_y').value = '';
}

// Form submission handler
// Form submission handler - UPDATED VERSION
function handleFormSubmission(event) {
    event.preventDefault();
    console.log('Form submission started');

    $('#loadingModal').modal('show');

    captureCanvasData()
        .then(canvasBlob => {
            // Create FormData from the form to preserve ALL form fields
            const formData = new FormData(event.target);
            
            // Log the due_date field to verify it's included
            console.log('Due date in form submission:', formData.get('due_date'));
            
            // Handle image processing
            let hasImages = false;
            
            // Remove any existing images[] entries to add them back cleanly
            const originalImages = formData.getAll('images[]');
            formData.delete('images[]');
            
            // First check if we have any edited images
            if (window.editedImages && Object.values(window.editedImages).length > 0) {
                Object.values(window.editedImages).forEach((file, i) => {
                    console.log('Adding edited file to form:', file.name, file.size);
                    formData.append('images[]', file);
                    hasImages = true;
                });
            }
            
            // Then add any camera/original images that weren't edited
            if (window.originalImages && Object.values(window.originalImages).length > 0) {
                Object.values(window.originalImages).forEach((file, i) => {
                    // Only add if we don't have an edited version
                    const indexKey = file.name || i.toString();
                    if (!window.editedImages || !window.editedImages[indexKey]) {
                        console.log('Adding original file to form:', file.name, file.size);
                        formData.append('images[]', file);
                        hasImages = true;
                    }
                });
            }
            
            // If we don't have special images, add back the original ones from the file input
            if (!hasImages && originalImages.length > 0) {
                originalImages.forEach(file => {
                    formData.append('images[]', file);
                    hasImages = true;
                });
            }

            if (!hasImages) {
                console.warn('No images found to submit! Check if files were captured correctly.');
            }

            if (canvasBlob) {
                formData.append('canvas_image', canvasBlob, 'canvas_image.png');
            }

            // Submit the form with our FormData containing ALL form fields including due_date
            fetch('create_defect.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok: ${response.status} - ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                $('#loadingModal').modal('hide');
                console.log('Form submission successful:', data);
                showToast('success', 'Defect created successfully!');
                window.location.href = 'defects.php';
            })
            .catch(error => {
                $('#loadingModal').modal('hide');
                console.error('Form submission error:', error);
                showToast('error', `Error creating defect: ${error.message}`);
            });
        })
        .catch(error => {
            $('#loadingModal').modal('hide');
            console.error('Error capturing canvas data:', error);
            showToast('error', `Error capturing canvas data: ${error}`);
        });
}
// Load floor plan from URL
async function loadFloorPlan(url) {
    try {
        document.getElementById('pdfLoadingOverlay').style.display = 'flex';
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
        document.getElementById('pdfLoadingOverlay').style.display = 'none';
    } catch (error) {
        console.error('Error loading floor plan:', error);
        document.getElementById('pdfLoadingOverlay').style.display = 'none';
        throw error;
    }
}

// Load image floor plan
async function loadImage(url) {
    return new Promise((resolve, reject) => {
        const canvas = document.getElementById('pdfCanvas');
        const context = canvas.getContext('2d');
        const container = document.querySelector('.pdf-container');
        const img = new Image();
        img.onload = function() {
            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;
            const scaleX = containerWidth / img.width;
            const scaleY = containerHeight / img.height;
            const scale = Math.min(scaleX, scaleY) * 0.95;
            canvas.width = img.width * scale;
            canvas.height = img.height * scale;
            canvas.style.position = 'absolute';
            canvas.style.left = '50%';
            canvas.style.top = '50%';
            canvas.style.transform = 'translate(-50%, -50%)';
            context.drawImage(img, 0, 0, canvas.width, canvas.height);
            setupPinPlacement(canvas);
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
        const container = document.querySelector('.pdf-container');
        const containerWidth = container.clientWidth;
        const containerHeight = container.clientHeight;
        const viewport = page.getViewport({ scale: 1.0 });
        const scaleX = containerWidth / viewport.width;
        const scaleY = containerHeight / viewport.height;
        const scale = Math.min(scaleX, scaleY) * 0.95;
        const scaledViewport = page.getViewport({ scale });
        canvas.width = scaledViewport.width;
        canvas.height = scaledViewport.height;
        canvas.style.position = 'absolute';
        canvas.style.left = '50%';
        canvas.style.top = '50%';
        canvas.style.transform = 'translate(-50%, -50%)';
        const renderContext = {
            canvasContext: context,
            viewport: scaledViewport
        };
        await page.render(renderContext);
        setupPinPlacement(canvas);
    } catch (error) {
        console.error('Error loading PDF:', error);
        throw error;
    }
}

// Set up pin placement on floor plan
function setupPinPlacement(canvas) {
    const overlay = document.getElementById('pinOverlay');
    let isHolding = false;
    let isDragging = false;
    let startX, startY;

    overlay.style.width = canvas.width + 'px';
    overlay.style.height = canvas.height + 'px';
    overlay.style.left = canvas.style.left;
    overlay.style.top = canvas.style.top;
    overlay.style.transform = canvas.style.transform;

    function getRelativeCoordinates(event) {
        const rect = overlay.getBoundingClientRect();
        let x, y;
        if (event.type.startsWith('touch')) {
            x = (event.touches[0].clientX - rect.left) / rect.width;
            y = (event.touches[0].clientY - rect.top) / rect.height;
        } else {
            x = (event.clientX - rect.left) / rect.width;
            y = (event.clientY - rect.top) / rect.height;
        }
        return { x, y };
    }

    function startHold(x, y) {
        isHolding = true;
        holdTimer = setTimeout(() => {
            if (isHolding && !isDragging) {
                placePin(x, y);
            }
        }, holdDuration);
    }

    function endHold() {
        isHolding = false;
        if (holdTimer) {
            clearTimeout(holdTimer);
            holdTimer = null;
        }
    }

    const startEventListeners = ['mousedown', 'touchstart'];
    const moveEventListeners = ['mousemove', 'touchmove'];
    const endEventListeners = ['mouseup', 'mouseleave', 'touchend', 'touchcancel'];

    // Remove any existing event listeners first to avoid duplicates
    const cloneOverlay = overlay.cloneNode(true);
    overlay.parentNode.replaceChild(cloneOverlay, overlay);
    const newOverlay = document.getElementById('pinOverlay');

    startEventListeners.forEach(eventName => {
        newOverlay.addEventListener(eventName, (e) => {
            e.preventDefault();
            const coords = getRelativeCoordinates(e);
            startX = e.clientX || (e.touches && e.touches[0].clientX);
            startY = e.clientY || (e.touches && e.touches[0].clientY);
            startHold(coords.x, coords.y);
        });
    });

    moveEventListeners.forEach(eventName => {
        newOverlay.addEventListener(eventName, (e) => {
            if (isHolding) {
                const currentX = e.clientX || (e.touches && e.touches.length > 0 ? e.touches[0].clientX : startX);
                const currentY = e.clientY || (e.touches && e.touches.length > 0 ? e.touches[0].clientY : startY);
                const deltaX = Math.abs(currentX - startX);
                const deltaY = Math.abs(currentY - startY);
                if (deltaX > 5 || deltaY > 5) {
                    isDragging = true;
                    endHold();
                }
            }
        });
    });

    endEventListeners.forEach(eventName => {
        newOverlay.addEventListener(eventName, () => {
            endHold();
            setTimeout(() => {
                isDragging = false;
            }, 0);
        });
    });
}

// Place pin on the floor plan
function placePin(x, y) {
    clearExistingPin();
    currentPin = document.createElement('div');
    currentPin.className = 'location-pin';
    const overlay = document.getElementById('pinOverlay');
    currentPin.style.left = (x * 100) + '%';
    currentPin.style.top = (y * 100) + '%';
    currentPin.style.transform = 'translate(-50%, -100%)';
    overlay.appendChild(currentPin);
    document.getElementById('pin_x').value = x;
    document.getElementById('pin_y').value = y;
    pinPlaced = true;
    showToast('success', 'Pin placed successfully');
}

// Update canvas transform (for zoom)
function updateCanvasTransform() {
    const canvas = document.getElementById('pdfCanvas');
    const overlay = document.getElementById('pinOverlay');
    const transform = `translate(-50%, -50%) scale(${currentScale}) translate(${currentTranslate.x}px, ${currentTranslate.y}px)`;
    canvas.style.transform = transform;
    overlay.style.transform = transform;
}
// Initialize image gallery
function initializeImageGallery() {
    const imageGallery = document.getElementById('imageGallery');
    imageGallery.addEventListener('click', function(event) {
        if (event.target.tagName === 'IMG') {
            if (selectedImage) {
                selectedImage.classList.remove('selected-image');
            }
            selectedImage = event.target;
            selectedImage.classList.add('selected-image');
            openDrawingModal(selectedImage);
        }
    });
}

// Handle image uploads
function handleImageUpload(files) {
    const imageGallery = document.getElementById('imageGallery');
    imageGallery.innerHTML = ''; // Clear existing images

    Array.from(files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-thumbnail';
            img.style.maxWidth = '150px';
            img.style.margin = '5px';
            img.dataset.index = index; // Add the index as a data attribute
            imageGallery.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// Take picture using device camera
function takePicture() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'camera';
    
    input.addEventListener('change', function(event) {
        const files = event.target.files;
        if (!files || files.length === 0) {
            console.error('No files selected from camera');
            return;
        }
        
        console.log('Camera captured', files.length, 'files');
        
        // CRITICAL FIX: Store the original files globally so we don't lose them
        if (!window.originalImages) window.originalImages = {};
        
        // Store each file with a unique key
        Array.from(files).forEach((file, index) => {
            const key = 'camera_' + Date.now() + '_' + index;
            window.originalImages[key] = file;
            console.log('Stored original camera file:', key, file.name, file.size);
        });
        
        // Update the visual preview
        handleImageUpload(files);
        
        showToast('success', 'Image captured successfully');
    });
    
    input.click();
}

// Open drawing modal for an image
function openDrawingModal(image) {
    const drawingModalElement = document.getElementById('drawingModal');
    const drawingModal = new bootstrap.Modal(drawingModalElement);
    const drawingContainer = document.getElementById('drawingContainer');
    drawingCanvas = document.getElementById('drawingCanvas');
    drawingContext = drawingCanvas.getContext('2d');

    const img = new Image();
    img.onload = function() {
        // Set a maximum width and height
        const maxWidth = 800;
        const maxHeight = 600;
        
        // Calculate aspect ratio-preserving dimensions
        let width = img.width;
        let height = img.height;
        
        if (width > maxWidth) {
            height = (height * maxWidth) / width;
            width = maxWidth;
        }
        
        if (height > maxHeight) {
            width = (width * maxHeight) / height;
            height = maxHeight;
        }
        
        // Set canvas size
        drawingCanvas.width = width;
        drawingCanvas.height = height;
        drawingContext.drawImage(img, 0, 0, width, height);
        drawingContainer.style.width = width + 'px';
        drawingContainer.style.height = height + 'px';
    };
    img.src = image.src;

    setupDrawingCanvas();
    drawingModal.show();
}

// Save drawing edits
function saveDrawingEdits() {
    if (!drawingCanvas || !selectedImage) {
        console.error('Drawing canvas or selected image is null');
        return;
    }
    
    const index = selectedImage.dataset.index;
    console.log('Saving drawing edits for image index:', index);
    
    // Convert the edited canvas to a blob
    drawingCanvas.toBlob(function(blob) {
        // Create a unique file name to avoid any issues
        const fileName = "edited_image_" + Date.now() + ".png";
        const editedFile = new File([blob], fileName, {type: "image/png"});
        
        // Store the original and edited files - initialize if needed
        if (!window.editedImages) window.editedImages = {};
        
        // Store using the image's dataset index
        window.editedImages[index] = editedFile;
        
        console.log('Stored edited image at index:', index, 'size:', editedFile.size);
        
        // Update the preview 
        selectedImage.src = URL.createObjectURL(blob);
        
        // Close the modal
        const drawingModal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
        if (drawingModal) {
            drawingModal.hide();
        }
        
        showToast('success', 'Image markup saved');
    }, 'image/png');
}

// Setup drawing canvas for touch and mouse drawing
function setupDrawingCanvas() {
    let drawingActive = false;
    let lastX = 0;
    let lastY = 0;

    function drawOnCanvas(event) {
        if (!drawingActive) return;

        const rect = drawingCanvas.getBoundingClientRect();
        let x, y;
        
        // Handle both mouse and touch events
        if (event.type.startsWith('touch')) {
            x = event.touches[0].clientX - rect.left;
            y = event.touches[0].clientY - rect.top;
        } else {
            x = event.clientX - rect.left;
            y = event.clientY - rect.top;
        }

        drawingContext.strokeStyle = 'red';
        drawingContext.lineWidth = 2;
        drawingContext.lineCap = 'round';
        drawingContext.beginPath();
        drawingContext.moveTo(lastX, lastY);
        drawingContext.lineTo(x, y);
        drawingContext.stroke();

        lastX = x;
        lastY = y;
    }

    // Clean up existing event listeners to avoid duplicates
    const cloneCanvas = drawingCanvas.cloneNode(true);
    drawingCanvas.parentNode.replaceChild(cloneCanvas, drawingCanvas);
    drawingCanvas = cloneCanvas;
    drawingContext = drawingCanvas.getContext('2d');

    // Mouse event listeners
    drawingCanvas.addEventListener('mousedown', (event) => {
        drawingActive = true;
        const rect = drawingCanvas.getBoundingClientRect();
        lastX = event.clientX - rect.left;
        lastY = event.clientY - rect.top;
    });

    drawingCanvas.addEventListener('mousemove', drawOnCanvas);

    drawingCanvas.addEventListener('mouseup', () => {
        drawingActive = false;
    });

    drawingCanvas.addEventListener('mouseleave', () => {
        drawingActive = false;
    });

    // Touch event listeners
    drawingCanvas.addEventListener('touchstart', (event) => {
        drawingActive = true;
        const rect = drawingCanvas.getBoundingClientRect();
        lastX = event.touches[0].clientX - rect.left;
        lastY = event.touches[0].clientY - rect.top;
        event.preventDefault();
    });

    drawingCanvas.addEventListener('touchmove', (event) => {
        drawOnCanvas(event);
        event.preventDefault();
    });

    drawingCanvas.addEventListener('touchend', () => {
        drawingActive = false;
    });

    drawingCanvas.addEventListener('touchcancel', () => {
        drawingActive = false;
    });
}

// Clear drawing canvas
function clearCanvas() {
    if (!drawingCanvas || !drawingContext) {
        console.error('Drawing canvas or context not initialized.');
        return;
    }

    drawingContext.clearRect(0, 0, drawingCanvas.width, drawingCanvas.height);
}
// Capture canvas data for submission
async function captureCanvasData() {
    return new Promise((resolve, reject) => {
        const canvas = document.getElementById('pdfCanvas');
        if (!canvas) {
            console.warn('pdfCanvas is null. This might be okay if no floor plan is selected.');
            resolve(null); // Resolve with null if there's no canvas
            return;
        }

        try {
            canvas.toBlob(function(blob) {
                if (!blob) {
                    console.error('Error: Canvas to Blob conversion failed.');
                    reject('Canvas to Blob conversion failed.');
                    return;
                }
                resolve(blob); // Resolve with the blob
            }, 'image/png');
        } catch (e) {
            console.error('Error in canvas.toBlob:', e);
            reject('Error converting canvas to blob: ' + e.message);
        }
    });
}

// Setup dropdown selection
function setupDropdown(dropdownId, inputId) {
    const dropdownButton = document.getElementById(dropdownId);
    const dropdownMenu = document.querySelector(`#${dropdownId} + .dropdown-menu`);
    
    if (!dropdownButton || !dropdownMenu) {
        console.error(`Could not find dropdown elements for ${dropdownId}`);
        return;
    }
    
    const dropdownItems = dropdownMenu.querySelectorAll('.dropdown-item');
    
    // Get the correct selected text element based on dropdown ID
    let selectedTextElementId = '';
    if (dropdownId === 'projectDropdown') selectedTextElementId = 'projectSelectedText';
    else if (dropdownId === 'contractorDropdown') selectedTextElementId = 'contractorSelectedText';
    else if (dropdownId === 'priorityDropdown') selectedTextElementId = 'prioritySelectedText';
    else if (dropdownId === 'floorPlanDropdown') selectedTextElementId = 'floorPlanSelectedText';
    
    const selectedTextElement = document.getElementById(selectedTextElementId);
    
    if (!selectedTextElement) {
        console.error(`Could not find selected text element for ${dropdownId}`);
        return;
    }

    dropdownItems.forEach(item => {
        item.addEventListener('click', function(event) {
            event.preventDefault();
            let selectedValue, selectedText;

            if (dropdownId === 'projectDropdown') {
                selectedValue = this.dataset.projectId;
                selectedText = this.dataset.projectName;
            } else if (dropdownId === 'contractorDropdown') {
                selectedValue = this.dataset.contractorId;
                selectedText = this.dataset.contractorName;
            } else if (dropdownId === 'priorityDropdown') {
                selectedValue = this.dataset.priorityValue;
                selectedText = this.textContent;
				setDueDateFromPriority(selectedValue); // Set due date based on priority
            } else if (dropdownId === 'floorPlanDropdown') {
                selectedValue = this.dataset.floorPlanId;
                selectedText = this.dataset.floorPlanName;
                const imagePath = this.dataset.imagePath;
                const filePath = this.dataset.filePath;

                document.getElementById('floor_plan_path').value = imagePath;
                document.getElementById('floorPlanContainer').style.display = 'block';
                clearExistingPin();
                loadFloorPlan(filePath).then(() => {
                    console.log('Floor plan loaded successfully:', imagePath);
                }).catch(error => {
                    console.error('Error loading floor plan:', error);
                    document.getElementById('pdfLoadingOverlay').style.display = 'none';
                    showToast('error', 'Failed to load floor plan image. Please try again.');
                });
            }

            selectedTextElement.textContent = selectedText;
            dropdownButton.classList.add('selected');
            dropdownButton.classList.remove('blue');
            dropdownButton.classList.remove('btn-outline-secondary');
            dropdownButton.classList.add('green');
            document.getElementById(inputId).value = selectedValue;
        });
    });
}

// Update pin position (exported for inline scripts)
window.updatePinPosition = function(scale) {
    if (currentPin) {
        // Update pin position based on scale if needed
    }
};
// Function to automatically set due date based on priority selection
function setDueDateFromPriority(priorityValue) {
    console.log('Setting due date based on priority:', priorityValue);
    const dueDateInput = document.querySelector('input[name="due_date"]');
    
    if (!dueDateInput) {
        console.error('Due date input not found');
        return;
    }
    
    const currentDate = new Date();
    let dueDate = new Date(currentDate);
    
    // Calculate due date based on priority
    switch(priorityValue) {
        case 'critical':
            // Within 24 hours
            dueDate.setDate(currentDate.getDate() + 1);
            break;
        case 'high':
            // 1-2 business days
            dueDate.setDate(currentDate.getDate() + 2);
            break;
        case 'medium':
            // 3-5 business days
            dueDate.setDate(currentDate.getDate() + 5);
            break;
        case 'low':
            // 5-7 business days
            dueDate.setDate(currentDate.getDate() + 7);
            break;
        default:
            // Default to 3 business days if no match
            dueDate.setDate(currentDate.getDate() + 3);
    }
    
    // Format date as YYYY-MM-DD for the input field
    const year = dueDate.getFullYear();
    const month = String(dueDate.getMonth() + 1).padStart(2, '0');
    const day = String(dueDate.getDate()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}`;
    
    console.log('Setting due date to:', formattedDate);
    dueDateInput.value = formattedDate;
}
// Initialize everything when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Setup form submission
    const form = document.getElementById('createDefectForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmission);
    } else {
        console.error('Form with ID "createDefectForm" not found');
    }
    
    // Initialize dropdowns
    setupDropdown('projectDropdown', 'project_id');
    setupDropdown('contractorDropdown', 'contractor_id');
    setupDropdown('priorityDropdown', 'priority');
    setupDropdown('floorPlanDropdown', 'floor_plan_id');

    // Add button event handlers
    document.getElementById('clearPinButton').addEventListener('click', clearExistingPin);
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

    // Image upload handlers
    document.getElementById('imageUpload').addEventListener('change', function(event) {
        const files = event.target.files;
        handleImageUpload(files);
    });

    // Take picture handler
    document.getElementById('takePictureButton').addEventListener('click', takePicture);

    // Initialize image gallery
    initializeImageGallery();
    
    // Add event listener for clear drawing button if it exists
    const clearDrawingButton = document.getElementById('clearDrawingButton');
    if (clearDrawingButton) {
        clearDrawingButton.addEventListener('click', clearCanvas);
    }
    
    // Add event listener for the Save Drawing button
    const saveDrawingButton = document.getElementById('saveDrawingButton');
    if (saveDrawingButton) {
        saveDrawingButton.addEventListener('click', function() {
            saveDrawingEdits();
        });
    }
    
    // Add event listener for recent descriptions dropdown
    document.querySelectorAll('.dropdown-menu a[data-description]').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const description = this.getAttribute('data-description');
            if (description) {
                const descTextarea = document.getElementById('description');
                const currentDescription = descTextarea.value;
                descTextarea.value = currentDescription ? currentDescription + "\n" + description : description;
            }
        });
    });

    // Initialize window.editedImages if it doesn't exist
    if (!window.editedImages) {
        window.editedImages = {};
    }
});
