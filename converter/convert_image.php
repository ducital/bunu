<?php
// Image Conversion Handler with OCR Support
// Supports: Image → PDF, Word (OCR), Excel (OCR), TXT (OCR), JPG ↔ PNG

class ImageConverter {
    
    private $tempDir;
    private $uploadsDir;
    
    public function __construct() {
        $baseDir = dirname(__DIR__);
        $this->tempDir = $baseDir . '/temp/converter/';
        $this->uploadsDir = $baseDir . '/uploads/converter/';
        
        // Create directories if they don't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }
    
    /**
     * Convert Image to specified format
     */
    public function convert($inputFile, $outputFormat, $outputDir = null) {
        if (!$outputDir) {
            $outputDir = $this->tempDir;
        }
        
        $inputPath = $inputFile;
        $baseName = pathinfo($inputFile, PATHINFO_FILENAME);
        $timestamp = date('Y-m-d_H-i-s');
        $outputFileName = $baseName . '_' . $timestamp;
        
        try {
            switch (strtolower($outputFormat)) {
                case 'pdf':
                    return $this->convertToPDF($inputPath, $outputDir, $outputFileName);
                    
                case 'word':
                case 'docx':
                    return $this->convertToWord($inputPath, $outputDir, $outputFileName);
                    
                case 'excel':
                case 'xlsx':
                    return $this->convertToExcel($inputPath, $outputDir, $outputFileName);
                    
                case 'txt':
                    return $this->convertToTXT($inputPath, $outputDir, $outputFileName);
                    
                case 'jpg':
                case 'jpeg':
                    return $this->convertToJPG($inputPath, $outputDir, $outputFileName);
                    
                case 'png':
                    return $this->convertToPNG($inputPath, $outputDir, $outputFileName);
                    
                default:
                    throw new Exception("Unsupported output format: $outputFormat");
            }
        } catch (Exception $e) {
            error_log("Image conversion error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert Image to PDF using ImageMagick
     */
    private function convertToPDF($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.pdf';
        
        $command = sprintf(
            'magick %s %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("PDF conversion failed: " . implode("\n", $output));
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("PDF conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert Image to Word using OCR
     */
    private function convertToWord($inputPath, $outputDir, $baseName) {
        try {
            // First extract text using OCR
            $ocrText = $this->extractTextWithOCR($inputPath);
            
            if (empty($ocrText)) {
                throw new Exception("OCR failed: No text found in image");
            }
            
            // Create a simple HTML that can be converted to Word
            $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OCR Result</title>
</head>
<body>
    <h1>OCR Text Extraction Result</h1>
    <div style="font-family: Arial, sans-serif; line-height: 1.6;">
        ' . nl2br(htmlspecialchars($ocrText)) . '
    </div>
</body>
</html>';
            
            $htmlFile = $outputDir . $baseName . '.html';
            file_put_contents($htmlFile, $htmlContent);
            
            // Convert HTML to Word using LibreOffice
            $outputFile = $outputDir . $baseName . '.docx';
            $command = sprintf(
                'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($htmlFile)
            );
            
            exec($command, $output, $returnVar);
            
            // Clean up HTML file
            if (file_exists($htmlFile)) {
                unlink($htmlFile);
            }
            
            if ($returnVar !== 0) {
                throw new Exception("LibreOffice conversion failed: " . implode("\n", $output));
            }
            
            $originalOutput = $outputDir . pathinfo($htmlFile, PATHINFO_FILENAME) . '.docx';
            if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
                rename($originalOutput, $outputFile);
            }
            
            if (!file_exists($outputFile)) {
                throw new Exception("Output file not created after conversion");
            }
            
            return $outputFile;
            
        } catch (Exception $e) {
            throw new Exception("Word conversion failed: " . $e->getMessage());
        }
    }
    
    /**
     * Convert Image to Excel using OCR and table detection
     */
    private function convertToExcel($inputPath, $outputDir, $baseName) {
        // Extract text with OCR
        $ocrText = $this->extractTextWithOCR($inputPath);
        
        if (empty($ocrText)) {
            throw new Exception("OCR failed: No text found in image");
        }
        
        // Try to detect table structure
        $csvData = $this->parseTextToCSV($ocrText);
        
        // Create CSV file
        $csvFile = $outputDir . $baseName . '.csv';
        file_put_contents($csvFile, $csvData);
        
        // Convert CSV to Excel using LibreOffice
        $outputFile = $outputDir . $baseName . '.xlsx';
        $command = sprintf(
            'libreoffice --headless --convert-to xlsx --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($csvFile)
        );
        
        exec($command, $output, $returnVar);
        
        // Clean up CSV file
        unlink($csvFile);
        
        if ($returnVar !== 0) {
            throw new Exception("Excel conversion failed: " . implode("\n", $output));
        }
        
        $originalOutput = $outputDir . pathinfo($csvFile, PATHINFO_FILENAME) . '.xlsx';
        if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
            rename($originalOutput, $outputFile);
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("Excel conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert Image to TXT using OCR
     */
    private function convertToTXT($inputPath, $outputDir, $baseName) {
        $ocrText = $this->extractTextWithOCR($inputPath);
        
        if (empty($ocrText)) {
            throw new Exception("OCR failed: No text found in image");
        }
        
        $outputFile = $outputDir . $baseName . '.txt';
        file_put_contents($outputFile, $ocrText);
        
        return $outputFile;
    }
    
    /**
     * Convert Image to JPG
     */
    private function convertToJPG($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.jpg';
        
        $command = sprintf(
            'magick %s -quality 90 %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("JPG conversion failed: " . implode("\n", $output));
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("JPG conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert Image to PNG
     */
    private function convertToPNG($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.png';
        
        $command = sprintf(
            'magick %s %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("PNG conversion failed: " . implode("\n", $output));
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("PNG conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Extract text from image using Tesseract OCR
     */
    private function extractTextWithOCR($imagePath) {
        $outputBase = $this->tempDir . 'ocr_' . uniqid();
        
        $command = sprintf(
            'tesseract %s %s 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($outputBase)
        );
        
        exec($command, $output, $returnVar);
        
        $textFile = $outputBase . '.txt';
        
        if ($returnVar !== 0 || !file_exists($textFile)) {
            throw new Exception("OCR failed: " . implode("\n", $output));
        }
        
        $text = file_get_contents($textFile);
        unlink($textFile);
        
        return trim($text);
    }
    
    /**
     * Parse OCR text to CSV format for table detection
     */
    private function parseTextToCSV($text) {
        $lines = explode("\n", $text);
        $csvLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Try to detect columns by spaces or tabs
            $columns = preg_split('/\s{2,}|\t/', $line);
            
            // If only one column detected, try splitting by single spaces
            if (count($columns) === 1) {
                $columns = explode(' ', $line);
            }
            
            // Clean up columns
            $columns = array_map('trim', $columns);
            $columns = array_filter($columns, function($col) {
                return !empty($col);
            });
            
            if (!empty($columns)) {
                $csvLines[] = implode(',', array_map(function($col) {
                    return '"' . str_replace('"', '""', $col) . '"';
                }, $columns));
            }
        }
        
        return implode("\n", $csvLines);
    }
    
    /**
     * Get supported output formats for Image files
     */
    public static function getSupportedFormats() {
        return [
            'pdf' => 'PDF Document',
            'docx' => 'Word Document (OCR)',
            'xlsx' => 'Excel Spreadsheet (OCR)',
            'txt' => 'Plain Text (OCR)',
            'jpg' => 'JPEG Image',
            'png' => 'PNG Image'
        ];
    }
    
    /**
     * Validate if the file is a valid image
     */
    public static function isValidImageFile($filePath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp'
        ];
        
        return in_array($mimeType, $allowedMimes);
    }
    
    /**
     * Clean up old conversion files
     */
    public function cleanup($olderThanHours = 24) {
        $cutoffTime = time() - ($olderThanHours * 3600);
        
        $dirs = [$this->tempDir, $this->uploadsDir];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    unlink($file);
                }
            }
        }
    }
}
?>