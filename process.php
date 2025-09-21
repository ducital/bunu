<?php
// Backend processor for Packingo - 3D Container Loading Optimization System

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error display to prevent JSON corruption
ini_set('log_errors', 1); // Log errors instead

// Include autoloader and classes
require_once 'vendor/autoload.php';
require_once 'classes/Config.php';
require_once 'classes/Load.php';
require_once 'classes/Placement.php';
require_once 'classes/Shelf.php';
require_once 'classes/Vehicle.php';
require_once 'classes/PackingAlgorithm.php';
require_once 'classes/ExcelProcessor.php';

// Headers will be set per action

$config = new Config();
$currentLang = $_SESSION['lang'] ?? 'en';
$t = $config->getTranslations($currentLang);

try {
    $action = $_POST['action'] ?? '';
    
    // Fallback: if no action but excelFile is present, assume 'plan' action
    if (empty($action) && isset($_FILES['excelFile'])) {
        $action = 'plan';
    }
    
    switch ($action) {
        case 'plan':
            handlePlanningRequest();
            break;
            
        case 'template':
            handleTemplateDownload();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    // Clear any output buffer that might contain error messages
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => 'Error occurred in process.php'
    ]);
} catch (Error $e) {
    // Handle fatal errors too
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage(),
        'debug' => 'Fatal error in process.php'
    ]);
}

function handlePlanningRequest() {
    global $config, $t;
    
    // Start output buffering to catch any stray output
    ob_start();
    
    // Set JSON response header
    header('Content-Type: application/json');
    
    // Validate file upload
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception($t['must_select_file']);
    }
    
    // Security: Validate file type and size
    $allowedExtensions = ['xlsx', 'xls'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    $extension = strtolower(pathinfo($_FILES['excelFile']['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('Invalid file type. Only Excel files (.xlsx, .xls) are allowed.');
    }
    
    // Additional MIME type validation using finfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['excelFile']['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/zip'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file format detected.');
        }
    }
    
    if ($_FILES['excelFile']['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 10MB.');
    }
    
    $selectedContainers = $_POST['containers'] ?? [];
    $selectedTrailers = $_POST['trailers'] ?? [];
    
    if (empty($selectedContainers) && empty($selectedTrailers)) {
        throw new Exception($t['must_select_vehicle']);
    }
    
    $lengthUnit = $_POST['lengthUnit'] ?? 'mm';
    $weightUnit = $_POST['weightUnit'] ?? 'kg';
    $stackMode = $_POST['stackMode'] ?? 'excel';
    
    // Process uploaded file securely
    $uploadDir = './uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate safe filename
    $safeFileName = uniqid('upload_', true) . '.' . $extension;
    $filePath = $uploadDir . $safeFileName;
    
    if (!move_uploaded_file($_FILES['excelFile']['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file');
    }
    
    try {
        // Process Excel file and run planning algorithm
        $excelProcessor = new ExcelProcessor();
        $loads = $excelProcessor->readLoads($filePath, $lengthUnit, $weightUnit, $stackMode, $_SESSION['lang']);
        
        if (empty($loads)) {
            throw new Exception('No valid loads found in Excel file');
        }
        
        // Run planning algorithm
        $algorithm = new PackingAlgorithm();
        $result = $algorithm->plan($loads, $selectedTrailers, $selectedContainers);
        
        $vehicles = $result['vehicles'];
        $unplaced = $result['unplaced'];
        
        // Generate output file
        $outputDir = 'temp/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $outputFileName = 'plan_' . date('Y-m-d_H-i-s') . '.xlsx';
        $outputPath = $outputDir . $outputFileName;
        
        $excelProcessor->exportResults($vehicles, $unplaced, $outputPath, $_SESSION['lang']);
        
        // Prepare summary data
        $summary = [
            'total_vehicles' => count($vehicles),
            'placed_loads' => 0,
            'unplaced_loads' => count($unplaced),
            'total_weight_used' => 0,
            'total_volume_used' => 0
        ];
        
        $vehicleData = [];
        foreach ($vehicles as $vehicle) {
            $loadCount = 0;
            foreach ($vehicle->shelves as $shelf) {
                $loadCount += count($shelf->places);
            }
            $summary['placed_loads'] += $loadCount;
            $summary['total_weight_used'] += $vehicle->totalKg;
            
            $vehicleData[] = [
                'label' => $vehicle->label,
                'typeName' => $vehicle->typeName,
                'iL' => $vehicle->iL,
                'iW' => $vehicle->iW,
                'iH' => $vehicle->iH,
                'maxKg' => $vehicle->maxKg,
                'totalKg' => $vehicle->totalKg,
                'loadCount' => $loadCount,
                'utilizationPercent' => round(($vehicle->totalKg / $vehicle->maxKg) * 100, 1)
            ];
        }
        
        // Clean up uploaded file
        unlink($filePath);
        
        // Clean output buffer and send clean JSON response
        ob_clean();
        
        echo json_encode([
            'success' => true,
            'message' => $t['done'],
            'results' => [
                'summary' => $summary,
                'vehicles' => $vehicleData,
                'download_url' => $outputPath
            ]
        ]);
        
    } catch (Exception $e) {
        // Clean up uploaded file
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        throw $e;
    }
}

function handleTemplateDownload() {
    try {
        $excelProcessor = new ExcelProcessor();
        $templatePath = 'temp/packingo_template.xlsx';
        
        // Ensure temp directory exists
        if (!is_dir('temp/')) {
            mkdir('temp/', 0755, true);
        }
        
        $excelProcessor->createTemplate($templatePath);
        
        // Set headers for file download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="packingo_template.xlsx"');
        header('Content-Length: ' . filesize($templatePath));
        header('Cache-Control: no-cache, must-revalidate');
        
        // Output file
        readfile($templatePath);
        
        // Clean up
        unlink($templatePath);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error creating template: ' . $e->getMessage()
        ]);
    }
}
?>