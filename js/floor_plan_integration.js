/**
 * Floor Plan Integration - With Form State Preservation
 * Enhances create_defect.php to work with the separate floor plan selector
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-03-20 15:35:40
 * Current User's Login: irlam
 */

document.addEventListener('DOMContentLoaded', function() {
    // Setup the floor plan selection process
    const floorPlanDropdown = document.getElementById('floorPlanDropdown');
    const floorPlanContainer = document.getElementById('floorPlanContainer');
    const pinXInput = document.getElementById('pin_x');
    const pinYInput = document.getElementById('pin_y');
    const defectForm = document.getElementById('createDefectForm');
    
    if (!floorPlanDropdown || !floorPlanContainer || !defectForm) return;
    
    // Initialize form state management
    setupFormStatePreservation();
    
    // Check if we're returning from the floor plan selector
    const storedPinData = localStorage.getItem('floor_plan_pin');
    if (storedPinData) {
        try {
            // Restore form state first, then handle pin data
            restoreFormState();
            
            const pinData = JSON.parse(storedPinData);
            handlePinPlacement(pinData);
            localStorage.removeItem('floor_plan_pin');
        } catch (e) {
            console.error('Error handling stored pin data:', e);
        }
    } else {
        // Just restore form state if available
        restoreFormState();
    }
    
    // Replace the standard floor plan selection with a modal approach
    function setupFloorPlanSelector() {
        // Get all dropdown items
        const dropdownItems = document.querySelectorAll('#floorPlanDropdown + .dropdown-menu .dropdown-item');
        
        dropdownItems.forEach(item => {
            // Clone the item to remove existing event listeners
            const newItem = item.cloneNode(true);
            item.parentNode.replaceChild(newItem, item);
            
            // Add new event listener
            newItem.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Get floor plan details
                const floorPlanId = this.dataset.floorPlanId;
                const floorPlanName = this.dataset.floorPlanName;
                
                // Update the dropdown text
                document.getElementById('floorPlanSelectedText').textContent = floorPlanName;
                document.getElementById('floor_plan_id').value = floorPlanId;
                
                // Update button styling
                floorPlanDropdown.classList.add('selected', 'green');
                floorPlanDropdown.classList.remove('blue', 'btn-outline-secondary');
                
                // Save form state before navigating
                saveFormState();
                
                // Open the floor plan selector in mobile-optimized view
                openFloorPlanSelector(floorPlanId);
                
                // Close the dropdown
                const dropdown = bootstrap.Dropdown.getInstance(floorPlanDropdown);
                if (dropdown) {
                    dropdown.hide();
                }
            });
        });
        
        // Add a "Select Pin Location" button in the floor plan container
        if (!document.getElementById('selectPinButton')) {
            const selectPinButton = document.createElement('button');
            selectPinButton.id = 'selectPinButton';
            selectPinButton.type = 'button';
            selectPinButton.className = 'btn btn-primary mb-3';
            selectPinButton.innerHTML = '<i class="bx bx-map-pin"></i> Select Pin Location';
            selectPinButton.addEventListener('click', function() {
                const floorPlanId = document.getElementById('floor_plan_id').value;
                if (floorPlanId) {
                    // Save form state before navigating
                    saveFormState();
                    openFloorPlanSelector(floorPlanId);
                } else {
                    showToast('warning', 'Please select a floor plan first');
                }
            });
            
            // Add the button to the container
            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'text-center mb-3';
            buttonContainer.appendChild(selectPinButton);
            floorPlanContainer.prepend(buttonContainer);
        }
        
        // Create pin preview area if it doesn't exist yet
        if (!document.getElementById('pinPreviewContainer')) {
            const pinPreviewContainer = document.createElement('div');
            pinPreviewContainer.id = 'pinPreviewContainer';
            pinPreviewContainer.className = 'mb-3 text-center';
            pinPreviewContainer.innerHTML = `
                <div class="card mb-3" style="display: none;" id="pinStatusCard">
                    <div class="card-body p-2">
                        <h6 class="mb-0">
                            <i class="bx bx-map-pin text-success"></i> 
                            <span id="pinStatusText">Pin placed successfully</span>
                        </h6>
                    </div>
                </div>
            `;
            
            floorPlanContainer.appendChild(pinPreviewContainer);
        }
        
        // Listen for messages from the floor plan selector
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'floor_plan_pin') {
                handlePinPlacement(event.data.data);
            }
        });
    }
    
    // Open the floor plan selector window
    function openFloorPlanSelector(floorPlanId) {
        // Determine if we should use a new window or same window approach
        const isMobile = window.innerWidth < 768;
        const url = `floorplan_selector.php?id=${floorPlanId}`;
        
        if (isMobile) {
            // On mobile, open in same window
            window.location.href = url;
        } else {
            // On desktop, open in new window - now with better sizing
            const width = Math.min(1200, window.innerWidth * 0.9);
            const height = Math.min(900, window.innerHeight * 0.9);
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;
            
            const win = window.open(
                url,
                'floorPlanSelector',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
            
            // Focus the new window
            if (win) {
                win.focus();
            }
        }
    }
    
    // Handle pin placement data from the selector
    function handlePinPlacement(data) {
        if (!data || !data.pin_x || !data.pin_y) return;
        
        // Update the hidden inputs
        pinXInput.value = data.pin_x;
        pinYInput.value = data.pin_y;
        
        // Show success status
        const pinStatusCard = document.getElementById('pinStatusCard');
        if (pinStatusCard) {
            pinStatusCard.style.display = 'block';
        }
        
        // Show toast notification
        showToast('success', 'Pin location saved');
        
        // Make sure the floor plan ID matches
        if (data.floor_plan_id) {
            document.getElementById('floor_plan_id').value = data.floor_plan_id;
            
            // Also update the dropdown text if available
            const dropdownItems = document.querySelectorAll('#floorPlanDropdown + .dropdown-menu .dropdown-item');
            dropdownItems.forEach(item => {
                if (item.dataset.floorPlanId === data.floor_plan_id.toString()) {
                    document.getElementById('floorPlanSelectedText').textContent = item.dataset.floorPlanName;
                    floorPlanDropdown.classList.add('selected', 'green');
                    floorPlanDropdown.classList.remove('blue', 'btn-outline-secondary');
                }
            });
        }
    }
    
    // Setup form state preservation system
    function setupFormStatePreservation() {
        // Add event listener for the window before unload 
        // to handle accidentally closing the main window
        window.addEventListener('beforeunload', function() {
            // Only save if there's form data worth saving
            if (hasFormData()) {
                saveFormState();
            }
        });
    }
    
    // Check if the form has any data worth saving
    function hasFormData() {
        const inputs = defectForm.querySelectorAll('input[type="text"], textarea, select');
        for (const input of inputs) {
            if (input.value.trim() !== '') {
                return true;
            }
        }
        
        // Also check custom dropdowns with hidden inputs
        const hiddenInputs = defectForm.querySelectorAll('input[type="hidden"]');
        for (const input of hiddenInputs) {
            if (input.id && ['project_id', 'contractor_id', 'priority'].includes(input.id) && input.value.trim() !== '') {
                return true;
            }
        }
        
        return false;
    }
    
    // Save form state to localStorage
    function saveFormState() {
        const formData = {
            // Standard form fields
            title: document.getElementById('title')?.value || '',
            description: document.getElementById('description')?.value || '',
            due_date: document.querySelector('input[name="due_date"]')?.value || '',
            
            // Hidden inputs for custom dropdowns
            project_id: document.getElementById('project_id')?.value || '',
            contractor_id: document.getElementById('contractor_id')?.value || '',
            priority: document.getElementById('priority')?.value || '',
            floor_plan_id: document.getElementById('floor_plan_id')?.value || '',
            
            // Custom dropdown text displays
            projectSelectedText: document.getElementById('projectSelectedText')?.textContent || 'Select Project',
            contractorSelectedText: document.getElementById('contractorSelectedText')?.textContent || 'Select Contractor',
            prioritySelectedText: document.getElementById('prioritySelectedText')?.textContent || 'Select Priority',
            floorPlanSelectedText: document.getElementById('floorPlanSelectedText')?.textContent || 'Select Floor Plan',
            
            // Dropdown styling
            projectDropdownClass: document.getElementById('projectDropdown')?.className || '',
            contractorDropdownClass: document.getElementById('contractorDropdown')?.className || '',
            priorityDropdownClass: document.getElementById('priorityDropdown')?.className || '',
            floorPlanDropdownClass: document.getElementById('floorPlanDropdown')?.className || '',
            
            // Timestamp to expire old data
            timestamp: Date.now()
        };
        
        localStorage.setItem('defect_form_state', JSON.stringify(formData));
        console.log('Form state saved');
    }
    
    // Restore form state from localStorage
    function restoreFormState() {
        try {
            const savedData = localStorage.getItem('defect_form_state');
            if (!savedData) return;
            
            const formData = JSON.parse(savedData);
            
            // Check if data is too old (over 1 hour)
            if (Date.now() - formData.timestamp > 60 * 60 * 1000) {
                localStorage.removeItem('defect_form_state');
                return;
            }
            
            // Restore standard form fields
            if (document.getElementById('title')) {
                document.getElementById('title').value = formData.title || '';
            }
            
            if (document.getElementById('description')) {
                document.getElementById('description').value = formData.description || '';
            }
            
            const dueDateInput = document.querySelector('input[name="due_date"]');
            if (dueDateInput) {
                dueDateInput.value = formData.due_date || '';
            }
            
            // Restore hidden inputs for custom dropdowns
            if (document.getElementById('project_id')) {
                document.getElementById('project_id').value = formData.project_id || '';
            }
            
            if (document.getElementById('contractor_id')) {
                document.getElementById('contractor_id').value = formData.contractor_id || '';
            }
            
            if (document.getElementById('priority')) {
                document.getElementById('priority').value = formData.priority || '';
            }
            
            if (document.getElementById('floor_plan_id')) {
                document.getElementById('floor_plan_id').value = formData.floor_plan_id || '';
            }
            
            // Restore dropdown text displays
            if (document.getElementById('projectSelectedText')) {
                document.getElementById('projectSelectedText').textContent = 
                    formData.projectSelectedText !== 'Select Project' ? formData.projectSelectedText : 'Select Project';
            }
            
            if (document.getElementById('contractorSelectedText')) {
                document.getElementById('contractorSelectedText').textContent = 
                    formData.contractorSelectedText !== 'Select Contractor' ? formData.contractorSelectedText : 'Select Contractor';
            }
            
            if (document.getElementById('prioritySelectedText')) {
                document.getElementById('prioritySelectedText').textContent = 
                    formData.prioritySelectedText !== 'Select Priority' ? formData.prioritySelectedText : 'Select Priority';
            }
            
            if (document.getElementById('floorPlanSelectedText')) {
                document.getElementById('floorPlanSelectedText').textContent = 
                    formData.floorPlanSelectedText !== 'Select Floor Plan' ? formData.floorPlanSelectedText : 'Select Floor Plan';
            }
            
            // Restore dropdown styling
            if (document.getElementById('projectDropdown') && formData.projectDropdownClass) {
                document.getElementById('projectDropdown').className = formData.projectDropdownClass;
            }
            
            if (document.getElementById('contractorDropdown') && formData.contractorDropdownClass) {
                document.getElementById('contractorDropdown').className = formData.contractorDropdownClass;
            }
            
            if (document.getElementById('priorityDropdown') && formData.priorityDropdownClass) {
                document.getElementById('priorityDropdown').className = formData.priorityDropdownClass;
            }
            
            if (document.getElementById('floorPlanDropdown') && formData.floorPlanDropdownClass) {
                document.getElementById('floorPlanDropdown').className = formData.floorPlanDropdownClass;
            }
            
            console.log('Form state restored');
            
            // Clean up after successful restoration
            localStorage.removeItem('defect_form_state');
        } catch (e) {
            console.error('Error restoring form state:', e);
        }
    }
    
    // Helper function to show toast messages
    function showToast(icon, text) {
        if (window.Swal) {
            Swal.fire({
                icon: icon,
                text: text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            alert(text);
        }
    }
    
    // Initialize the floor plan selector
    setupFloorPlanSelector();
});