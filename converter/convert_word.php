<?php
// Word Document Conversion Handler
// Supports: Word → PDF, Excel, TXT, JPG/PNG

class WordConverter {
    
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
     * Convert Word document to specified format
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
            error_log("Word conversion error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert Word to PDF using LibreOffice
     */
    private function convertToPDF($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.pdf';
        
        // Use LibreOffice headless mode to convert to PDF
        $command = sprintf(
            'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("PDF conversion failed: " . implode("\n", $output));
        }
        
        // LibreOffice creates the file with original name, rename it to our format
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
     * Convert Word to Excel using HTML intermediate and table extraction
     */
    private function convertToExcel($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.xlsx';
        
        try {
            // First convert Word to HTML
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
            
            // Extract tables and text from HTML
            $csvData = $this->extractTablesFromHtml($htmlFile);
            
            // Clean up HTML file
            unlink($htmlFile);
            
            // Create CSV file
            $csvFile = $outputDir . $baseName . '.csv';
            file_put_contents($csvFile, $csvData);
            
            // Convert CSV to Excel
            $command2 = sprintf(
                'libreoffice --headless --convert-to xlsx --outdir %s %s 2>&1',
                escapeshellarg($outputDir),
                escapeshellarg($csvFile)
            );
            
            exec($command2, $output2, $returnVar2);
            
            // Clean up CSV file
            unlink($csvFile);
            
            if ($returnVar2 !== 0) {
                throw new Exception("Excel conversion failed: " . implode("\n", $output2));
            }
            
            $originalOutput = $outputDir . pathinfo($csvFile, PATHINFO_FILENAME) . '.xlsx';
            if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
                rename($originalOutput, $outputFile);
            }
            
            if (!file_exists($outputFile)) {
                throw new Exception("Excel conversion failed: Output file not created");
            }
            
            return $outputFile;
            
        } catch (Exception $e) {
            throw new Exception("Excel conversion failed: " . $e->getMessage());
        }
    }
    
    /**
     * Convert Word to TXT using LibreOffice
     */
    private function convertToTXT($inputPath, $outputDir, $baseName) {
        $outputFile = $outputDir . $baseName . '.txt';
        
        // Use LibreOffice to convert to text format
        $command = sprintf(
            'libreoffice --headless --convert-to txt --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($inputPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("TXT conversion failed: " . implode("\n", $output));
        }
        
        // Rename to our format
        $originalOutput = $outputDir . pathinfo($inputPath, PATHINFO_FILENAME) . '.txt';
        if (file_exists($originalOutput) && $originalOutput !== $outputFile) {
            rename($originalOutput, $outputFile);
        }
        
        if (!file_exists($outputFile)) {
            throw new Exception("TXT conversion failed: Output file not created");
        }
        
        return $outputFile;
    }
    
    /**
     * Convert Word to Image (JPG/PNG) using LibreOffice + ImageMagick
     */
    private function convertToImage($inputPath, $outputDir, $baseName, $imageFormat) {
        // First convert to PDF, then PDF to images
        $pdfFile = $this->convertToPDF($inputPath, $outputDir, $baseName . '_temp');
        
        $outputFilePattern = $outputDir . $baseName . '_page_%d.' . strtolower($imageFormat);
        
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
        
        // If multiple pages, return the first one or zip them
        if (count($imageFiles) === 1) {
            return $imageFiles[0];
        } else {
            // Multiple pages - create a zip file
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
     * Get supported output formats for Word files
     */
    public static function getSupportedFormats() {
        return [
            'pdf' => 'PDF Document',
            'xlsx' => 'Excel Spreadsheet',
            'txt' => 'Plain Text',
            'jpg' => 'JPEG Image',
            'png' => 'PNG Image'
        ];
    }
    
    /**
     * Validate if the file is a valid Word document
     */
    public static function isValidWordFile($filePath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/msword' // .doc
        ];
        
        return in_array($mimeType, $allowedMimes);
    }
    
    /**
     * Extract tables and text from HTML and convert to CSV format
     */
    private function extractTablesFromHtml($htmlFile) {
        $htmlContent = file_get_contents($htmlFile);
        $csvLines = [];
        
        // Try to extract tables first
        if (preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $htmlContent, $tableMatches)) {
            foreach ($tableMatches[1] as $tableContent) {
                // Extract rows from table
                if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableContent, $rowMatches)) {
                    foreach ($rowMatches[1] as $rowContent) {
                        // Extract cells from row
                        if (preg_match_all('/<t[hd][^>]*>(.*?)<\/t[hd]>/is', $rowContent, $cellMatches)) {
                            $cells = [];
                            foreach ($cellMatches[1] as $cellContent) {
                                // Clean up cell content
                                $cellText = strip_tags($cellContent);
                                $cellText = html_entity_decode($cellText, ENT_QUOTES, 'UTF-8');
                                $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                                $cells[] = $cellText;
                            }
                            if (!empty($cells)) {
                                // Escape cells for CSV
                                $escapedCells = array_map(function($cell) {
                                    return '"' . str_replace('"', '""', $cell) . '"';
                                }, $cells);
                                $csvLines[] = implode(',', $escapedCells);
                            }
                        }
                    }
                }
                $csvLines[] = ''; // Empty line between tables
            }
        }
        
        // If no tables found, extract paragraphs as single-column data
        if (empty($csvLines)) {
            // Remove HTML tags and extract text content
            $textContent = strip_tags($htmlContent);
            $textContent = html_entity_decode($textContent, ENT_QUOTES, 'UTF-8');
            
            // Split into paragraphs
            $paragraphs = explode("\n", $textContent);
            
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (!empty($paragraph) && strlen($paragraph) > 2) {
                    // Escape for CSV
                    $csvLines[] = '"' . str_replace('"', '""', $paragraph) . '"';
                }
            }
        }
        
        // If still empty, add a default message
        if (empty($csvLines)) {
            $csvLines[] = '"Document Content","No readable content found"';
        }
        
        return implode("\n", $csvLines);
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