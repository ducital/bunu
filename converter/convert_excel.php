<?php
// Excel Spreadsheet Conversion Handler
// Supports: Excel → PDF, Word, TXT, JPG/PNG

class ExcelConverter {
    
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
     * Convert Excel document to specified format
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
            error_log("Excel conversion error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert Excel to PDF using LibreOffice
     */
    private function convertToPDF($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.pdf';
        
        $command = sprintf(
            'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("PDF conversion failed: " . implode("\n", $output));
        }
        
        $originalOutput = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.pdf';
        if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
            rename($originalOutput, $outputFile);
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("PDF conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert Excel to Word using HTML intermediate for structured data
     */
    private function convertToWord($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.docx';
        
        try {
            // First convert Excel to HTML to preserve table structure
            $htmlFile = $outputDir . $baseName . '.html';
            $command = sprintf(
                'libreoffice --headless --convert-to html --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($inputPath)
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("HTML conversion failed: " . implode("\n", $output));
            }
            
            $originalHtmlOutput = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.html';
            if (file_exists($originalHtmlOutput) && $originalHtmlOutput !== $htmlFile) {
                rename($originalHtmlOutput, $htmlFile);
            }
            
            if (!file_exists($htmlFile)) {
                throw new Exception("HTML file not created");
            }
            
            // Enhance HTML with better styling for Word conversion
            $this->enhanceHtmlForWord($htmlFile);
            
            // Convert enhanced HTML to Word
            $command2 = sprintf(
                'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($htmlFile)
            );
            
            exec($command2, $output2, $returnVar2);
            
            // Clean up HTML file
            unlink($htmlFile);
            
            if ($returnVar2 !== 0) {
                throw new Exception("Word conversion failed: " . implode("\n", $output2));
            }
            
            $originalOutput = $outputDir . pathinfo($htmlFile, PATHINFO_FILENAME) . '.docx';
            if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
                rename($originalOutput, $outputFile);
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
     * Convert Excel to TXT using LibreOffice
     */
    private function convertToTXT($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.txt';
        
        $command = sprintf(
            'libreoffice --headless --convert-to csv --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("CSV conversion failed: " . implode("\n", $output));
        }
        
        // Convert CSV to more readable TXT format
        $csvFile = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.csv';
        if (file_exists($csvFile)) {
            $csvContent = file_get_contents($csvFile);
            $txtContent = str_replace(',', "\t", $csvContent); // Convert commas to tabs
            file_put_contents($outputFile, $txtContent);
            unlink($csvFile);
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("TXT conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert Excel to Image (JPG/PNG) using LibreOffice + ImageMagick
     */
    private function convertToImage($inputPath, $outputDir, $baseName, $imageFormat) {
        // First convert to PDF, then PDF to images
        $pdfFile = $this->convertToPDF($inputPath, $outputDir, $baseName . '_temp');
        
        $outputFilePattern = $outputDir . $baseName . '_sheet_%d.' . strtolower($imageFormat);
        
        // Use ImageMagick to convert PDF to images
        $command = sprintf(
            'magick -density 300 %s -quality 90 %s 2>&1',
            escapeshellarg($pdfFile),
            escapeshellarg($outputFilePattern)
        );
        
        exec($command, $output, $returnVar);
        
        // Clean up temporary PDF
        if (file_exists($pdfFile)) {
            unlink($pdfFile);
        }
        
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
            return $this->createZipArchive($imageFiles, $outputDir . $baseName . '_sheets.zip');
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
     * Get supported output formats for Excel files
     */
    public static function getSupportedFormats() {
        return [
            'pdf' => 'PDF Document',
            'docx' => 'Word Document',
            'txt' => 'Plain Text (CSV)',
            'jpg' => 'JPEG Image',
            'png' => 'PNG Image'
        ];
    }
    
    /**
     * Validate if the file is a valid Excel document
     */
    public static function isValidExcelFile($filePath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel' // .xls
        ];
        
        return in_array($mimeType, $allowedMimes);
    }
    
    /**
     * Enhance HTML with better styling for Word conversion
     */
    private function enhanceHtmlForWord($htmlFile) {
        $htmlContent = file_get_contents($htmlFile);
        
        // Add better CSS styling for tables and document structure
        $cssStyles = '
<style>
body {
    font-family: Arial, sans-serif;
    font-size: 12pt;
    line-height: 1.4;
    margin: 20px;
}
table {
    border-collapse: collapse;
    width: 100%;
    margin: 10px 0;
}
th, td {
    border: 1px solid #333;
    padding: 8px;
    text-align: left;
    vertical-align: top;
}
th {
    background-color: #f0f0f0;
    font-weight: bold;
}
h1, h2, h3 {
    color: #333;
    margin: 20px 0 10px 0;
}
.sheet-header {
    font-size: 14pt;
    font-weight: bold;
    color: #2c5aa0;
    margin: 30px 0 10px 0;
    border-bottom: 2px solid #2c5aa0;
    padding-bottom: 5px;
}
</style>';
        
        // Insert CSS styles into the head
        if (strpos($htmlContent, '</head>') !== false) {
            $htmlContent = str_replace('</head>', $cssStyles . '</head>', $htmlContent);
        } else {
            // If no head tag, add it
            $htmlContent = str_replace('<html>', '<html><head>' . $cssStyles . '</head>', $htmlContent);
        }
        
        // Add sheet headers for multiple sheets
        $htmlContent = preg_replace('/<h1[^>]*>([^<]+)<\/h1>/', '<div class="sheet-header">$1</div>', $htmlContent);
        
        file_put_contents($htmlFile, $htmlContent);
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