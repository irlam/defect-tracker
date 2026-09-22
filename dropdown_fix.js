/**
 * Dropdown selection fix
 */
document.addEventListener('DOMContentLoaded', function() {
    // Fix dropdown functionality
    function setupDropdownFix() {
        const dropdowns = ['projectDropdown', 'contractorDropdown', 'priorityDropdown', 'floorPlanDropdown'];
        
        dropdowns.forEach(dropdownId => {
            const dropdownItems = document.querySelectorAll(`#${dropdownId} + .dropdown-menu .dropdown-item`);
            dropdownItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    let selectedValue, selectedText;
                    const dropdownButton = document.getElementById(dropdownId);
                    let selectedTextElement;
                    
                    // Get the correct IDs based on dropdown
                    if (dropdownId === 'projectDropdown') {
                        selectedValue = this.dataset.projectId;
                        selectedText = this.dataset.projectName;
                        selectedTextElement = document.getElementById('projectSelectedText');
                        document.getElementById('project_id').value = selectedValue;
                    } else if (dropdownId === 'contractorDropdown') {
                        selectedValue = this.dataset.contractorId;
                        selectedText = this.dataset.contractorName;
                        selectedTextElement = document.getElementById('contractorSelectedText');
                        document.getElementById('contractor_id').value = selectedValue;
                    } else if (dropdownId === 'priorityDropdown') {
                        selectedValue = this.dataset.priorityValue;
                        selectedText = this.textContent;
                        selectedTextElement = document.getElementById('prioritySelectedText');
                        document.getElementById('priority').value = selectedValue;
                    } else if (dropdownId === 'floorPlanDropdown') {
                        selectedValue = this.dataset.floorPlanId;
                        selectedText = this.dataset.floorPlanName;
                        selectedTextElement = document.getElementById('floorPlanSelectedText');
                        document.getElementById('floor_plan_id').value = selectedValue;
                        
                        // Load floor plan
                        const imagePath = this.dataset.imagePath;
                        const filePath = this.dataset.filePath;
                        document.getElementById('floor_plan_path').value = imagePath;
                        document.getElementById('floorPlanContainer').style.display = 'block';
                    }
                    
                    // Update the text and styling
                    if (selectedTextElement) {
                        selectedTextElement.textContent = selectedText;
                    }
                    
                    dropdownButton.classList.add('selected', 'green');
                    dropdownButton.classList.remove('blue', 'btn-outline-secondary');
                });
            });
        });
    }
    
    // Fix form submission if needed
    const createDefectForm = document.getElementById('createDefectForm');
    if (createDefectForm && !createDefectForm.hasAttribute('data-event-bound')) {
        createDefectForm.setAttribute('data-event-bound', 'true');
        createDefectForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Show loading modal
            if (window.bootstrap && bootstrap.Modal) {
                const loadingModal = document.getElementById('loadingModal');
                if (loadingModal) {
                    const bsModal = new bootstrap.Modal(loadingModal);
                    bsModal.show();
                }
            }
            
            // Submit the form directly instead of using fetch
            this.submit();
        });
    }
    
    // Fix the recent descriptions dropdown
    const descriptionLinks = document.querySelectorAll('.dropdown-menu a[data-description]');
    descriptionLinks.forEach(link => {
        link.addEventListener('click', function(e) {
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
    
    // Run the dropdown fix
    setupDropdownFix();
});