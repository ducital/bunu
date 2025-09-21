<?php
// PDF Document Conversion Handler
// Supports: PDF → Word, Excel, TXT, JPG/PNG

class PDFConverter {
    
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
     * Convert PDF document to specified format
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
                case 'png':
                    return $this->convertToImage($inputPath, $outputDir, $outputFileName, $outputFormat);
                    
                default:
                    throw new Exception("Unsupported output format: $outputFormat");
            }
        } catch (Exception $e) {
            error_log("PDF conversion error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert PDF to Word using ImageMagick + OCR + LibreOffice
     */
    private function convertToWord($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.docx';
        
        // First try direct LibreOffice conversion
        $command = sprintf(
            'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnVar);
        
        // Check if direct conversion worked
        $originalOutput = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.docx';
        if ($returnVar === 0 && file_exists($originalOutput)) {
            if ($originalOutput !== $outputFile) {
                rename($originalOutput, $outputFile);
            }
            return $outputFile;
        }
        
        // If direct conversion failed, try OCR approach
        try {
            // Convert PDF to images (all pages)
            $tempImagePattern = $outputDir . $baseName . '_page_%03d.png';
            $imageCommand = sprintf(
                'magick -density 300 %s -quality 90 %s 2>&1',
                escapeshellarg($inputPath),
                escapeshellarg($tempImagePattern)
            );
            
            exec($imageCommand, $imageOutput, $imageReturnVar);
            
            if ($imageReturnVar !== 0) {
                throw new Exception("Image conversion failed: " . implode("\n", $imageOutput));
            }
            
            // Find all generated images
            $pattern = str_replace('%03d', '*', $tempImagePattern);
            $imageFiles = glob($pattern);
            
            if (empty($imageFiles)) {
                throw new Exception("No images generated from PDF");
            }
            
            // Extract text from all pages using OCR
            $allOcrText = [];
            foreach ($imageFiles as $imageFile) {
                $pageText = $this->extractTextWithOCR($imageFile);
                if (!empty($pageText)) {
                    $allOcrText[] = $pageText;
                }
                unlink($imageFile); // Clean up as we go
            }
            
            $ocrText = implode("\n\n--- Page Break ---\n\n", $allOcrText);
            
            if (empty($ocrText)) {
                throw new Exception("OCR failed: No text found in PDF");
            }
            
            // Create HTML content
            $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PDF OCR Result</title>
</head>
<body>
    <h1>PDF Text Extraction Result</h1>
    <div style="font-family: Arial, sans-serif; line-height: 1.6;">
        ' . nl2br(htmlspecialchars($ocrText)) . '
    </div>
</body>
</html>';
            
            $htmlFile = $outputDir . $baseName . '.html';
            file_put_contents($htmlFile, $htmlContent);
            
            // Convert HTML to Word
            $htmlCommand = sprintf(
                'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($htmlFile)
            );
            
            exec($htmlCommand, $htmlOutput, $htmlReturnVar);
            
            // Clean up HTML file
            unlink($htmlFile);
            
            if ($htmlReturnVar !== 0) {
                throw new Exception("HTML to Word conversion failed: " . implode("\n", $htmlOutput));
            }
            
            $htmlOriginalOutput = $outputDir . pathinfo($htmlFile, PATHINFO_FILENAME) . '.docx';
            if (file_exists($htmlOriginalOutput) && $htmlOriginalOutput !== $outputFile) {
                rename($htmlOriginalOutput, $outputFile);
            }
            
            if (!file_exists($outputFile)) {
                throw new Exception("Word conversion failed: Output file not created");
            }
            
            return $outputFile;
            
        } catch (Exception $e) {
            throw new Exception("Word conversion failed: " . $e->getMessage());
        }
    }
    
    /**
     * Convert PDF to Excel using LibreOffice
     */
    private function convertToExcel($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.xlsx';
        
        $command = sprintf(
            'libreoffice --headless --convert-to xlsx --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Excel conversion failed: " . implode("\n", $output));
        }
        
        $originalOutput = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.xlsx';
        if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
            rename($originalOutput, $outputFile);
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("Excel conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert PDF to TXT using pdftotext (if available) or fallback to LibreOffice
     */
    private function convertToTXT($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.txt';
        
        // Try pdftotext first (more accurate for PDFs)
        $command = sprintf('which pdftotext 2>/dev/null');
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0) {
            // pdftotext is available
            $command = sprintf(
                'pdftotext %s %s 2>&1',
                escapeshellarg($inputPath),
                escapeshellarg($outputFile)
            );
        } else {
            // Fallback to LibreOffice
            $command = sprintf(
                'libreoffice --headless --convert-to txt --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($inputPath)
            );
        }
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("TXT conversion failed: " . implode("\n", $output));
        }
        
        // If LibreOffice was used, rename the file
        if (strpos($command, 'libreoffice') !== false) {
            $originalOutput = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.txt';
            if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
                rename($originalOutput, $outputFile);
            }
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("TXT conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert PDF to Image (JPG/PNG) using ImageMagick
     */
    private function convertToImage($inputPath, $outputDir, $baseName, $imageFormat) {
        $outputFilePattern = $outputDir . $baseName . '_page_%d.' . strtolower($imageFormat);
        
        // Use ImageMagick to convert PDF to images
        $command = sprintf(
            'magick -density 300 %s -quality 90 %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputFilePattern)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Image conversion failed: " . implode("\n", $output));
        }
        
        // Find generated image files
        $pattern = str_replace('%d', '*', $outputFilePattern);
        $imageFiles = glob($pattern);
        
        if (empty($imageFiles)) {
            throw new Exception("Image conversion failed: No output files generated");
        }
        
        if (count($imageFiles) === 1) {
            return $imageFiles[0];
        } else {
            return $this->createZipArchive($imageFiles, $outputDir . $baseName . '_pages.zip');
        }
    }
    
    /**
     * Create ZIP archive for multiple files
     */
    private function createZipArchive($files, $zipPath) {
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Cannot create ZIP archive");
        }
        
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        
        $zip->close();
        
        // Clean up individual files
        foreach ($files as $file) {
            unlink($file);
        }
        
        return $zipPath;
    }
    
    /**
     * Get supported output formats for PDF files
     */
    public static function getSupportedFormats() {
        return [
            'docx' => 'Word Document',
            'xlsx' => 'Excel Spreadsheet',
            'txt' => 'Plain Text',
            'jpg' => 'JPEG Image',
            'png' => 'PNG Image'
        ];
    }
    
    /**
     * Validate if the file is a valid PDF document
     */
    public static function isValidPDFFile($filePath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        return $mimeType === 'application/pdf';
    }
    
    /**
     * Extract text from image using Tesseract OCR
     */
    private function extractTextWithOCR($imagePath) {
        $outputBase = $this->tempDir . 'ocr_' . uniqid();
        
        $command = sprintf(
            'tesseract %s %s -l eng 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($outputBase)
        );
        
        exec($command, $output, $returnVar);
        
        $textFile = $outputBase . '.txt';
        
        if ($returnVar !== 0 || !file_exists($textFile)) {
            // Return empty string instead of throwing exception for individual pages
            return '';
        }
        
        $text = file_get_contents($textFile);
        unlink($textFile);
        
        return trim($text);
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