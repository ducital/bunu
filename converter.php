<?php
// Main File Converter Coordinator
// Handles all file conversion requests and routes them to appropriate converters

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include converter classes
require_once 'converter/convert_word.php';
require_once 'converter/convert_excel.php';
require_once 'converter/convert_pdf.php';
require_once 'converter/convert_image.php';

// Include config for translations
require_once 'classes/Config.php';

class FileConverter {
    
    private $config;
    private $currentLang;
    private $t;
    private $uploadsDir;
    private $tempDir;
    
    public function __construct() {
        $this->config = new Config();
        $this->currentLang = $_SESSION['lang'] ?? 'en';
        $this->t = $this->config->getTranslations($this->currentLang);
        
        $baseDir = __DIR__;
        $this->uploadsDir = $baseDir . '/uploads/converter/';
        $this->tempDir = $baseDir . '/temp/converter/';
        
        // Create directories if they don't exist
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    /**
     * Convert filesystem path to web URL
     */
    private function pathToUrl($fsPath) {
        $baseDir = __DIR__;
        $relativePath = str_replace($baseDir, '', $fsPath);
        return $relativePath;
    }
    
    /**
     * Check if required CLI tools are available
     */
    public function checkToolsAvailability() {
        $tools = [
            'libreoffice' => 'LibreOffice',
            'convert' => 'ImageMagick',
            'tesseract' => 'Tesseract OCR'
        ];
        
        $available = [];
        $missing = [];
        
        foreach ($tools as $command => $name) {
            $output = shell_exec("which $command 2>/dev/null");
            if (!empty($output)) {
                $available[$command] = $name;
            } else {
                $missing[$command] = $name;
            }
        }
        
        return [
            'available' => $available,
            'missing' => $missing,
            'all_available' => empty($missing)
        ];
    }
    
    /**
     * Handle conversion request
     */
    public function handleConversion() {
        try {
            // Validate request
            if (!isset($_FILES['converterFile']) || $_FILES['converterFile']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($this->t['must_select_file']);
            }
            
            if (!isset($_POST['targetFormat']) || empty($_POST['targetFormat'])) {
                throw new Exception('Target format not specified');
            }
            
            // Validate file
            $uploadedFile = $_FILES['converterFile'];
            $targetFormat = $_POST['targetFormat'];
            
            // Security: Validate file type and size
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($uploadedFile['size'] > $maxSize) {
                throw new Exception('File too large. Maximum size is 10MB.');
            }
            
            // Determine file type and validate
            $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $fileType = $this->detectFileType($uploadedFile['tmp_name'], $fileExtension);
            
            if (!$fileType) {
                throw new Exception($this->t['file_not_supported']);
            }
            
            // Generate safe filename
            $safeFileName = uniqid('convert_', true) . '.' . $fileExtension;
            $uploadPath = $this->uploadsDir . $safeFileName;
            
            if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to upload file');
            }
            
            // Perform conversion
            $outputFile = $this->performConversion($uploadPath, $fileType, $targetFormat);
            
            // Clean up uploaded file
            unlink($uploadPath);
            
            // Return success response
            return [
                'success' => true,
                'message' => $this->t['conversion_complete'],
                'download_url' => $this->pathToUrl($outputFile),
                'filename' => basename($outputFile)
            ];
            
        } catch (Exception $e) {
            // Clean up on error
            if (isset($uploadPath) && file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Detect file type from MIME type and extension
     */
    private function detectFileType($filePath, $extension) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Word documents
        if (in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword'
        ]) || in_array($extension, ['docx', 'doc'])) {
            return 'word';
        }
        
        // Excel documents  
        if (in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ]) || in_array($extension, ['xlsx', 'xls'])) {
            return 'excel';
        }
        
        // PDF documents
        if ($mimeType === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }
        
        // Images
        if (strpos($mimeType, 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
            return 'image';
        }
        
        return false;
    }
    
    /**
     * Perform the actual conversion
     */
    private function performConversion($inputFile, $inputType, $outputFormat) {
        switch ($inputType) {
            case 'word':
                $converter = new WordConverter();
                if (!in_array($outputFormat, array_keys(WordConverter::getSupportedFormats()))) {
                    throw new Exception("Word to $outputFormat conversion not supported");
                }
                break;
                
            case 'excel':
                $converter = new ExcelConverter();
                if (!in_array($outputFormat, array_keys(ExcelConverter::getSupportedFormats()))) {
                    throw new Exception("Excel to $outputFormat conversion not supported");
                }
                break;
                
            case 'pdf':
                $converter = new PDFConverter();
                if (!in_array($outputFormat, array_keys(PDFConverter::getSupportedFormats()))) {
                    throw new Exception("PDF to $outputFormat conversion not supported");
                }
                break;
                
            case 'image':
                $converter = new ImageConverter();
                if (!in_array($outputFormat, array_keys(ImageConverter::getSupportedFormats()))) {
                    throw new Exception("Image to $outputFormat conversion not supported");
                }
                break;
                
            default:
                throw new Exception("Unsupported input file type: $inputType");
        }
        
        return $converter->convert($inputFile, $outputFormat, $this->tempDir);
    }
    
    /**
     * Get available target formats for a given input type
     */
    public function getSupportedFormats($inputType) {
        switch ($inputType) {
            case 'word':
                return WordConverter::getSupportedFormats();
            case 'excel':
                return ExcelConverter::getSupportedFormats();
            case 'pdf':
                return PDFConverter::getSupportedFormats();
            case 'image':
                return ImageConverter::getSupportedFormats();
            default:
                return [];
        }
    }
    
    /**
     * Clean up old files
     */
    public function cleanup() {
        $converters = [
            new WordConverter(),
            new ExcelConverter(),
            new PDFConverter(),
            new ImageConverter()
        ];
        
        foreach ($converters as $converter) {
            $converter->cleanup(24); // Clean files older than 24 hours
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        $fileConverter = new FileConverter();
        
        switch ($action) {
            case 'convert':
                $result = $fileConverter->handleConversion();
                break;
                
            case 'tools_check':
                $toolsStatus = $fileConverter->checkToolsAvailability();
                $result = [
                    'success' => true,
                    'tools' => $toolsStatus
                ];
                break;
                
            case 'get_formats':
                $inputType = $_POST['inputType'] ?? '';
                $formats = $fileConverter->getSupportedFormats($inputType);
                $result = [
                    'success' => true,
                    'formats' => $formats
                ];
                break;
                
            case 'cleanup':
                $fileConverter->cleanup();
                $result = [
                    'success' => true,
                    'message' => 'Cleanup completed'
                ];
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    
    echo json_encode($result);
    exit;
}

// If not an AJAX request, redirect to main page
header('Location: /?converter=1');
exit;
?>