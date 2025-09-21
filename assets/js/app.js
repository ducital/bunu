// Packingo - 3D Container Loading Optimization System
// JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    
    
    // Form submission handling
    const planningForm = document.getElementById('planningForm');
    if (planningForm) {
        planningForm.addEventListener('submit', handleFormSubmission);
    }
    
    // Template download
    const createTemplateLink = document.getElementById('createTemplate');
    if (createTemplateLink) {
        createTemplateLink.addEventListener('click', downloadTemplate);
    }
    
    // File upload validation
    const fileInput = document.getElementById('excelFile');
    if (fileInput) {
        fileInput.addEventListener('change', validateFileUpload);
    }
    
    // Vehicle selection validation
    setupVehicleValidation();
    
    // Converter functionality
    setupConverterFunctionality();
});


function handleFormSubmission(event) {
    event.preventDefault();
    
    // Validate form
    if (!validateForm()) {
        return;
    }
    
    // Show loading state
    showLoading(true);
    
    // Submit form via AJAX
    const formData = new FormData(event.target);
    formData.append('action', 'plan');
    
    // Manually append vehicle selections (checkboxes are outside the form)
    document.querySelectorAll('input[name="containers[]"]:checked').forEach(input => {
        formData.append('containers[]', input.value);
    });
    document.querySelectorAll('input[name="trailers[]"]:checked').forEach(input => {
        formData.append('trailers[]', input.value);
    });
    
    fetch('process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);
        
        if (data.success) {
            displayResults(data.results);
        } else {
            showError(data.message || 'An error occurred during planning.');
        }
    })
    .catch(error => {
        showLoading(false);
        showError('Network error: ' + error.message);
    });
}

function validateForm() {
    // Check if file is selected
    const fileInput = document.getElementById('excelFile');
    if (!fileInput.files.length) {
        showError('Please select an Excel file.');
        return false;
    }
    
    // Check if at least one vehicle type is selected
    const containers = document.querySelectorAll('input[name="containers[]"]:checked');
    const trailers = document.querySelectorAll('input[name="trailers[]"]:checked');
    
    if (containers.length === 0 && trailers.length === 0) {
        showError('Please select at least one container or trailer type.');
        return false;
    }
    
    return true;
}

function validateFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Check file type
    const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
                         'application/vnd.ms-excel'];
    
    if (!allowedTypes.includes(file.type)) {
        showError('Please select a valid Excel file (.xlsx or .xls).');
        event.target.value = '';
        return;
    }
    
    // Check file size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
        showError('File size must be less than 10MB.');
        event.target.value = '';
        return;
    }
}

function setupVehicleValidation() {
    const vehicleCheckboxes = document.querySelectorAll('input[name="containers[]"], input[name="trailers[]"]');
    
    vehicleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateVehicleSelection();
        });
    });
}


function updateVehicleSelection() {
    const containers = document.querySelectorAll('input[name="containers[]"]:checked');
    const trailers = document.querySelectorAll('input[name="trailers[]"]:checked');
    const planButton = document.querySelector('button[type="submit"]');
    
    if (containers.length === 0 && trailers.length === 0) {
        planButton.disabled = true;
        planButton.classList.add('btn-secondary');
        planButton.classList.remove('btn-primary');
    } else {
        planButton.disabled = false;
        planButton.classList.add('btn-primary');
        planButton.classList.remove('btn-secondary');
    }
}

function downloadTemplate(event) {
    event.preventDefault();
    
    showLoading(true);
    
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=template'
    })
    .then(response => response.blob())
    .then(blob => {
        showLoading(false);
        
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'packingo_template.xlsx';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    })
    .catch(error => {
        showLoading(false);
        showError('Error downloading template: ' + error.message);
    });
}

function displayResults(results) {
    // Get translations
    const t = window.translations || {};
    
    // Remove existing results
    const existingResults = document.querySelector('.results-section');
    if (existingResults) {
        existingResults.remove();
    }
    
    // Create results section
    const resultsSection = document.createElement('div');
    resultsSection.className = 'results-section';
    
    let html = `<h3><i class="fas fa-check-circle text-success me-2"></i>${t.results_title || 'Planning Results'}</h3>`;
    
    // Summary
    html += `<div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-primary">${results.summary.total_vehicles}</h5>
                    <small class="text-muted">${t.total_vehicles || 'Vehicles Used'}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-success">${results.summary.placed_loads}</h5>
                    <small class="text-muted">${t.placed || 'Loads Placed'}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-${results.summary.unplaced_loads > 0 ? 'warning' : 'success'}">${results.summary.unplaced_loads}</h5>
                    <small class="text-muted">${t.unplaced_loads || 'Unplaced Loads'}</small>
                </div>
            </div>
        </div>
    </div>`;
    
    // Vehicle details
    if (results.vehicles && results.vehicles.length > 0) {
        html += `<h4>${t.vehicle_details_title || 'Vehicle Loading Details'}</h4>`;
        
        results.vehicles.forEach(vehicle => {
            html += `<div class="vehicle-summary">
                <h5>${vehicle.label} (${vehicle.typeName})</h5>
                <div class="row">
                    <div class="col-md-6">
                        <small><strong>${t.dimensions || 'Dimensions'}:</strong> ${vehicle.iL}×${vehicle.iW}×${vehicle.iH}mm</small><br>
                        <small><strong>${t.max_weight || 'Max Weight'}:</strong> ${vehicle.maxKg.toLocaleString()}kg</small>
                    </div>
                    <div class="col-md-6">
                        <small><strong>${t.used_weight || 'Used Weight'}:</strong> ${vehicle.totalKg.toLocaleString()}kg (${((vehicle.totalKg/vehicle.maxKg)*100).toFixed(1)}%)</small><br>
                        <small><strong>${t.item_count || 'Loads Count'}:</strong> ${vehicle.loadCount}</small>
                    </div>
                </div>
            </div>`;
        });
    }
    
    // Download button
    html += `<div class="text-center mt-4">
        <a href="${results.download_url}" class="btn btn-success btn-lg">
            <i class="fas fa-download me-2"></i>${t.download_plan_cta || 'Download Detailed Plan (Excel)'}
        </a>
    </div>`;
    
    resultsSection.innerHTML = html;
    
    // Insert after main content
    const container = document.querySelector('.col-lg-8');
    if (container) {
        container.appendChild(resultsSection);
    }
    
    // Scroll to results
    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showLoading(show) {
    const button = document.querySelector('button[type="submit"]');
    const icon = button.querySelector('i');
    const text = button.querySelector('.btn-text') || button;
    
    if (show) {
        button.disabled = true;
        if (icon) icon.className = 'fas fa-spinner fa-spin me-2';
        if (!button.querySelector('.btn-text')) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><span class="btn-text">' + button.textContent + '</span>';
        }
        button.querySelector('.btn-text').textContent = 'Processing...';
    } else {
        button.disabled = false;
        if (icon) icon.className = 'fas fa-play me-2';
        if (button.querySelector('.btn-text')) {
            button.querySelector('.btn-text').textContent = button.getAttribute('data-original-text') || 'Plan';
        }
    }
}

function showError(message) {
    // Remove existing alerts
    const existingAlert = document.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create alert
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show';
    alert.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of main content
    const container = document.querySelector('.col-lg-8');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }
    
    // Scroll to alert
    alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

function showSuccess(message) {
    // Remove existing alerts
    const existingAlert = document.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create alert
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show';
    alert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of main content
    const container = document.querySelector('.col-lg-8');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 3000);
}

// === CONVERTER FUNCTIONALITY ===

function setupConverterFunctionality() {
    // File converter form handling
    const converterForm = document.getElementById('converterForm');
    if (converterForm) {
        converterForm.addEventListener('submit', handleConverterSubmission);
    }
    
    // File input change handler for converter
    const converterFileInput = document.getElementById('converterFile');
    if (converterFileInput) {
        converterFileInput.addEventListener('change', handleConverterFileChange);
    }
    
    // Target format change handler
    const targetFormatSelect = document.getElementById('targetFormat');
    if (targetFormatSelect) {
        targetFormatSelect.addEventListener('change', validateConverterForm);
    }
}

function handleConverterFileChange(event) {
    const file = event.target.files[0];
    const targetFormatSelect = document.getElementById('targetFormat');
    const convertButton = document.querySelector('#converterForm button[type="submit"]');
    
    if (!file) {
        targetFormatSelect.innerHTML = '<option value="">Select target format...</option>';
        convertButton.disabled = true;
        return;
    }
    
    // Validate file type and size
    if (!validateConverterFile(file)) {
        event.target.value = '';
        return;
    }
    
    // Detect file type and load supported formats
    const fileType = detectFileType(file);
    if (!fileType) {
        showError('Unsupported file type. Please select a Word, Excel, PDF, or Image file.');
        event.target.value = '';
        return;
    }
    
    // Load supported target formats
    loadSupportedFormats(fileType);
}

function validateConverterFile(file) {
    // Check file size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
        showError('File size must be less than 10MB.');
        return false;
    }
    
    // Check file type
    const allowedTypes = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/msword', // .doc
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel', // .xls
        'application/pdf', // .pdf
        'image/jpeg', // .jpg
        'image/png', // .png
        'image/gif', // .gif
        'image/bmp' // .bmp
    ];
    
    if (!allowedTypes.includes(file.type)) {
        const extension = file.name.split('.').pop().toLowerCase();
        const allowedExtensions = ['docx', 'doc', 'xlsx', 'xls', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp'];
        
        if (!allowedExtensions.includes(extension)) {
            showError('Please select a valid file: Word (.docx, .doc), Excel (.xlsx, .xls), PDF (.pdf), or Image (.jpg, .png, .gif, .bmp).');
            return false;
        }
    }
    
    return true;
}

function detectFileType(file) {
    const extension = file.name.split('.').pop().toLowerCase();
    
    // Word documents
    if (['docx', 'doc'].includes(extension) || 
        ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'].includes(file.type)) {
        return 'word';
    }
    
    // Excel documents
    if (['xlsx', 'xls'].includes(extension) || 
        ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'].includes(file.type)) {
        return 'excel';
    }
    
    // PDF documents
    if (extension === 'pdf' || file.type === 'application/pdf') {
        return 'pdf';
    }
    
    // Images
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(extension) || file.type.startsWith('image/')) {
        return 'image';
    }
    
    return null;
}

function loadSupportedFormats(inputType) {
    const targetFormatSelect = document.getElementById('targetFormat');
    
    // Define supported formats for each input type
    const supportedFormats = {
        word: {
            'pdf': 'PDF Document',
            'xlsx': 'Excel Spreadsheet', 
            'txt': 'Plain Text',
            'jpg': 'JPEG Image',
            'png': 'PNG Image'
        },
        excel: {
            'pdf': 'PDF Document',
            'docx': 'Word Document',
            'txt': 'Plain Text (CSV)',
            'jpg': 'JPEG Image',
            'png': 'PNG Image'
        },
        pdf: {
            'docx': 'Word Document',
            'xlsx': 'Excel Spreadsheet',
            'txt': 'Plain Text',
            'jpg': 'JPEG Image',
            'png': 'PNG Image'
        },
        image: {
            'pdf': 'PDF Document',
            'docx': 'Word Document (OCR)',
            'xlsx': 'Excel Spreadsheet (OCR)',
            'txt': 'Plain Text (OCR)',
            'jpg': 'JPEG Image',
            'png': 'PNG Image'
        }
    };
    
    const formats = supportedFormats[inputType] || {};
    
    // Clear and populate options
    targetFormatSelect.innerHTML = '<option value="">Select target format...</option>';
    
    for (const [value, label] of Object.entries(formats)) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        targetFormatSelect.appendChild(option);
    }
    
    validateConverterForm();
}

function validateConverterForm() {
    const fileInput = document.getElementById('converterFile');
    const targetFormatSelect = document.getElementById('targetFormat');
    const convertButton = document.querySelector('#converterForm button[type="submit"]');
    
    const hasFile = fileInput.files.length > 0;
    const hasFormat = targetFormatSelect.value !== '';
    
    convertButton.disabled = !(hasFile && hasFormat);
    
    if (hasFile && hasFormat) {
        convertButton.classList.remove('btn-secondary');
        convertButton.classList.add('btn-success');
    } else {
        convertButton.classList.remove('btn-success');
        convertButton.classList.add('btn-secondary');
    }
}

function handleConverterSubmission(event) {
    event.preventDefault();
    
    // Validate converter form
    if (!validateConverterFormSubmission()) {
        return;
    }
    
    // Show loading state
    showConverterLoading(true);
    
    // Submit form via AJAX
    const formData = new FormData(event.target);
    formData.append('action', 'convert');
    
    fetch('converter.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showConverterLoading(false);
        
        if (data.success) {
            displayConverterResults(data);
        } else {
            showError(data.message || 'An error occurred during conversion.');
        }
    })
    .catch(error => {
        showConverterLoading(false);
        showError('Network error: ' + error.message);
    });
}

function validateConverterFormSubmission() {
    // Check if file is selected
    const fileInput = document.getElementById('converterFile');
    if (!fileInput.files.length) {
        showError('Please select a file to convert.');
        return false;
    }
    
    // Check if target format is selected
    const targetFormatSelect = document.getElementById('targetFormat');
    if (!targetFormatSelect.value) {
        showError('Please select a target format.');
        return false;
    }
    
    return true;
}

function showConverterLoading(show) {
    const button = document.querySelector('#converterForm button[type="submit"]');
    const icon = button.querySelector('i');
    const text = button.querySelector('.btn-text') || button;
    
    if (show) {
        button.disabled = true;
        if (icon) icon.className = 'fas fa-spinner fa-spin me-2';
        if (!button.querySelector('.btn-text')) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><span class="btn-text">' + button.textContent + '</span>';
        }
        button.querySelector('.btn-text').textContent = window.translations?.converting || 'Converting...';
    } else {
        button.disabled = false;
        if (icon) icon.className = 'fas fa-sync-alt me-2';
        if (button.querySelector('.btn-text')) {
            button.querySelector('.btn-text').textContent = window.translations?.convert || 'Convert';
        }
    }
}

function displayConverterResults(data) {
    const t = window.translations || {};
    
    // Remove existing results
    const existingResults = document.querySelector('.converter-results-section');
    if (existingResults) {
        existingResults.remove();
    }
    
    // Create results section
    const resultsSection = document.createElement('div');
    resultsSection.className = 'converter-results-section mt-4';
    
    let html = `
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle me-2"></i>${t.conversion_complete || 'Conversion Complete'}</h5>
            <p class="mb-3">${data.message}</p>
            <div class="text-center">
                <a href="${data.download_url}" class="btn btn-success btn-lg" download="${data.filename}">
                    <i class="fas fa-download me-2"></i>${t.download_converted || 'Download Converted File'}
                </a>
            </div>
        </div>
    `;
    
    resultsSection.innerHTML = html;
    
    // Insert after converter form
    const converterSection = document.querySelector('#converter .col-lg-8');
    if (converterSection) {
        converterSection.appendChild(resultsSection);
    }
    
    // Scroll to results
    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Reset form
    document.getElementById('converterForm').reset();
    document.getElementById('targetFormat').innerHTML = '<option value="">Select target format...</option>';
    validateConverterForm();
}