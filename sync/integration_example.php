<?php
// Example of how to integrate with existing PHP files

// 1. At the top of your PHP file that needs offline capabilities:
require_once __DIR__ . '/sync/init.php';

// 2. In your HTML header section:
?>
<!DOCTYPE html>
<html>
<head>
    <title>Defect Tracker</title>
    <!-- Your existing head content -->
    <?php sync_init_client(); // This initializes the client-side sync functionality ?>
</head>
<body>
    <!-- Your existing HTML content -->
    
    <!-- 3. Example for a form that needs to work offline: -->
    <form id="defect-form">
        <input type="text" id="title" placeholder="Defect Title">
        <textarea id="description" placeholder="Description"></textarea>
        <input type="file" id="attachment">
        <button type="submit">Submit Defect</button>
    </form>
    
    <script>
    // 4. JavaScript to make the form work offline:
    document.getElementById('defect-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const title = document.getElementById('title').value;
        const description = document.getElementById('description').value;
        const attachmentField = document.getElementById('attachment');
        let attachmentData = null;
        
        // Process the image file if one is selected
        if (attachmentField.files.length > 0) {
            const file = attachmentField.files[0];
            attachmentData = await new Promise(resolve => {
                const reader = new FileReader();
                reader.onload = e => resolve(e.target.result);
                reader.readAsDataURL(file);
            });
        }
        
        // Save to local IndexedDB first
        try {
            // Save the defect data
            const defectId = await window.dbManager.add('defects', {
                title: title,
                description: description,
                status: 'new',
                created_at: new Date().toISOString(),
                // Add any other fields needed
            });
            
            // If there's an attachment, save it separately
            if (attachmentData) {
                await window.dbManager.add('attachments', {
                    defect_id: defectId,
                    file_data: attachmentData,
                    file_name: attachmentField.files[0].name,
                    file_type: attachmentField.files[0].type
                });
            }
            
            // Try to sync immediately if online
            if (navigator.onLine) {
                window.syncManager.synchronize();
            }
            
            alert("Defect saved" + (navigator.onLine ? " and synced" : " (offline mode)"));
            // Reset form
            this.reset();
            
        } catch (error) {
            console.error('Error saving defect:', error);
            alert("Error saving defect: " + error.message);
        }
    });
    </script>
</body>
</html>