/**
 * export.js
 * Current Date and Time (UTC): 2025-01-26 17:21:49
 * Current User's Login: irlam
 */

function exportData(format) {
    const exportBtn = document.querySelector('#exportDropdown');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Exporting...';

    try {
        // Create hidden form for POST request
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = 'export.php';
        form.target = '_blank'; // Open in new tab to avoid page reload

        // Add format parameter
        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'format';
        formatInput.value = format;
        form.appendChild(formatInput);

        // Add to document and submit
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        // Show success message
        const toast = new bootstrap.Toast(document.getElementById('exportSuccessToast'));
        toast.show();
    } catch (error) {
        console.error('Export error:', error);
        alert('Export failed: ' + error.message);
    } finally {
        // Reset button text after short delay
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
        }, 1000);
    }
}