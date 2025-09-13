async function compressImage(imageFile) {
    const options = {
        maxSizeMB: 1,             // Maximum file size in MB
        maxWidthOrHeight: 1920,   // Max width/height of the output image
        useWebWorker: true,       // Use web workers for better performance
        initialQuality: 0.8,      // Initial compression quality (0.8 = 80%)
    };

    try {
        const compressedFile = await imageCompression(imageFile, options);
        // Create a new File object with the original name but compressed data
        return new File([compressedFile], imageFile.name, {
            type: compressedFile.type,
            lastModified: new Date().getTime()
        });
    } catch (error) {
        console.error('Error compressing image:', error);
        return imageFile; // Return original file if compression fails
    }
}

async function handleImageUpload(files) {
    const gallery = $('#imageGallery');
    gallery.empty();
    
    const compressedFiles = [];
    const totalFiles = files.length;
    let processedFiles = 0;

    // Show loading indicator
    const loadingDiv = $('<div class="text-center" id="compression-progress">')
        .html('<div class="spinner-border text-primary" role="status"></div>' +
              '<p class="mt-2">Compressing images... <span id="progress-text">0%</span></p>');
    gallery.append(loadingDiv);

    try {
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type.startsWith('image/')) {
                // Only compress JPEG/JPG images
                if (file.type === 'image/jpeg' || file.type === 'image/jpg') {
                    const compressedFile = await compressImage(file);
                    compressedFiles.push(compressedFile);
                } else {
                    compressedFiles.push(file);
                }

                // Update progress
                processedFiles++;
                const progress = Math.round((processedFiles / totalFiles) * 100);
                $('#progress-text').text(`${progress}%`);

                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = $('<img>')
                        .attr('src', e.target.result)
                        .addClass('img-thumbnail')
                        .css({ 'max-width': '200px', 'height': 'auto', 'margin': '5px' });
                    
                    const sizeText = $('<div>')
                        .addClass('text-muted small')
                        .text(`Size: ${(compressedFiles[i].size / 1024 / 1024).toFixed(2)} MB`);
                    
                    const container = $('<div>')
                        .addClass('d-inline-block text-center')
                        .append(img)
                        .append(sizeText);
                    
                    gallery.append(container);
                };
                reader.readAsDataURL(compressedFiles[i]);
            }
        }
    } finally {
        // Remove loading indicator
        $('#compression-progress').remove();
    }

    // Return the array of compressed files
    return compressedFiles;
}

// Create a custom file input handler to replace the original files
function createCustomFileInput(originalInput) {
    const dataTransfer = new DataTransfer();
    
    // Return the function that updates the original input
    return async function(files) {
        const compressedFiles = await handleImageUpload(files);
        
        // Clear existing files
        dataTransfer.items.clear();
        
        // Add compressed files to the DataTransfer object
        compressedFiles.forEach(file => {
            dataTransfer.items.add(file);
        });
        
        // Update the original file input with compressed files
        originalInput.files = dataTransfer.files;
    };
}