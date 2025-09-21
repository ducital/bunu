<?php

require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelProcessor {
    private $config;

    public function __construct() {
        $this->config = new Config();
    }

    /**
     * Read loads from Excel file
     */
    public function readLoads($filePath, $lengthUnit, $weightUnit, $stackMode, $lang = 'en') {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();

        if (empty($data)) {
            throw new Exception("Excel file is empty");
        }

        // Normalize column headers
        $headers = array_map(function($h) {
            return strtolower(trim(str_replace(' ', '_', $h)));
        }, $data[0]);

        // Map column names
        $columnMap = $this->normalizeColumns($headers);
        
        // Required columns
        $required = ['yuk_no', 'load_name', 'uzunluk_mm', 'genislik_mm', 'yukseklik_mm', 'agirlik_kg'];
        foreach ($required as $req) {
            if (!isset($columnMap[$req])) {
                throw new Exception("Required column missing: $req");
            }
        }

        // Get conversion factors
        $lengthFactors = $this->config->getLengthFactors();
        $weightFactors = $this->config->getWeightFactors();
        $lengthFactor = $lengthFactors[$lengthUnit];
        $weightFactor = $weightFactors[$weightUnit];

        $loads = [];
        
        // Process each data row
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            
            if (empty($row) || !$row[0]) {
                continue; // Skip empty rows
            }

            // Extract values using column mapping
            $loadNo = $this->getValue($row, $columnMap['yuk_no']);
            $loadName = $this->getValue($row, $columnMap['load_name']);
            $length = floatval($this->getValue($row, $columnMap['uzunluk_mm'])) * $lengthFactor;
            $width = floatval($this->getValue($row, $columnMap['genislik_mm'])) * $lengthFactor;
            $height = floatval($this->getValue($row, $columnMap['yukseklik_mm'])) * $lengthFactor;
            $weight = floatval($this->getValue($row, $columnMap['agirlik_kg'])) * $weightFactor;

            // Determine stackability
            $stackable = true;
            if ($stackMode === 'excel' && isset($columnMap['istiflenebilir'])) {
                $stackValue = strtolower(trim($this->getValue($row, $columnMap['istiflenebilir'])));
                $stackable = in_array($stackValue, ['1', 'true', 'evet', 'yes', 'ja']);
            } elseif ($stackMode === 'no') {
                $stackable = false;
            }

            $loads[] = new Load($loadNo, $loadName, $length, $width, $height, $weight, $stackable);
        }

        return $loads;
    }

    /**
     * Normalize column headers and create mapping
     */
    private function normalizeColumns($headers) {
        $mapping = [];
        
        foreach ($headers as $index => $header) {
            $h = strtolower($header);
            
            // Load number - Enhanced to handle English formats
            if ((strpos($h, 'yuk') !== false || strpos($h, 'yük') !== false) && 
                (strpos($h, 'no') !== false || substr($h, -3) === '_no' || $h === 'yuk') ||
                (strpos($h, 'load') !== false && strpos($h, 'no') !== false) ||
                $h === 'load_no' || $h === 'loadno') {
                $mapping['yuk_no'] = $index;
            }
            // Load name - Enhanced for better English support
            elseif (((strpos($h, 'yuk') !== false || strpos($h, 'yük') !== false) && 
                     (strpos($h, 'adi') !== false || substr($h, -4) === '_adi' || strpos($h, 'ad') !== false)) ||
                    (strpos($h, 'load') !== false && strpos($h, 'name') !== false) ||
                    $h === 'name' || $h === 'load_name' || $h === 'loadname') {
                $mapping['load_name'] = $index;
            }
            // Dimensions - Enhanced to handle English formats
            elseif (strpos($h, 'uzun') === 0 || $h === 'length' || strpos($h, 'length') !== false) {
                $mapping['uzunluk_mm'] = $index;
            }
            elseif (strpos($h, 'genis') === 0 || strpos($h, 'geniş') === 0 || 
                    strpos($h, 'width') !== false || $h === 'width') {
                $mapping['genislik_mm'] = $index;
            }
            elseif (strpos($h, 'yuksek') === 0 || strpos($h, 'yüksek') === 0 || 
                    strpos($h, 'height') !== false || $h === 'height') {
                $mapping['yukseklik_mm'] = $index;
            }
            elseif (strpos($h, 'agir') === 0 || strpos($h, 'ağı') === 0 || 
                    strpos($h, 'weight') !== false || $h === 'weight') {
                $mapping['agirlik_kg'] = $index;
            }
            // Stackability - Enhanced for English
            elseif (strpos($h, 'istif') === 0 || strpos($h, 'stack') !== false || $h === 'stackable') {
                $mapping['istiflenebilir'] = $index;
            }
        }
        
        return $mapping;
    }

    /**
     * Get value from row at specific index, with fallback to empty string
     */
    private function getValue($row, $index) {
        return isset($row[$index]) ? $row[$index] : '';
    }

    /**
     * Export planning results to Excel
     */
    public function exportResults($vehicles, $unplaced, $outputPath, $lang = 'en') {
        $t = $this->config->getTranslations($lang);
        
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Plan');

        // Headers
        $headers = [
            'yuk_no', 'yuk_adi', 'uzunluk_mm', 'genislik_mm', 'yukseklik_mm', 
            'agirlik_kg', 'dorse_konteyner_tipi', 'vehicle_id', 'x_mm', 'y_mm', 'z_mm', 'status'
        ];
        
        $worksheet->fromArray($headers, null, 'A1');

        $currentRow = 2;
        $mergeRanges = [];

        // Process each vehicle
        foreach ($vehicles as $vehicle) {
            $placements = [];
            
            // Collect all placements from shelves
            foreach ($vehicle->shelves as $shelf) {
                $placements = array_merge($placements, $shelf->places);
            }
            
            // Sort placements by position
            usort($placements, function($a, $b) {
                if ($a->z != $b->z) return $a->z - $b->z;
                if ($a->y != $b->y) return $a->y - $b->y;
                return $a->x - $b->x;
            });

            if (!empty($placements)) {
                $startRow = $currentRow;
                
                foreach ($placements as $placement) {
                    $rowData = [
                        $placement->loadNo,
                        $placement->loadName,
                        $placement->L,
                        $placement->W,
                        $placement->H,
                        $placement->kg,
                        $vehicle->typeName,
                        $vehicle->label,
                        $placement->x,
                        $placement->y,
                        $placement->z,
                        $t['placed']
                    ];
                    
                    $worksheet->fromArray($rowData, null, 'A' . $currentRow);
                    $currentRow++;
                }
                
                $endRow = $currentRow - 1;
                
                // Mark for merging vehicle type column
                if ($endRow > $startRow) {
                    $mergeRanges[] = ['start' => $startRow, 'end' => $endRow, 'col' => 'G'];
                }
                
                // Add empty row between vehicles
                $currentRow++;
            }
        }

        // Add unplaced items
        if (!empty($unplaced)) {
            $currentRow += 2;
            $worksheet->setCellValue('A' . $currentRow, 'UNPLACED / Sığmayan YÜKLER');
            $currentRow += 2;
            
            foreach ($unplaced as $load) {
                $rowData = [
                    $load->no,
                    $load->loadName,
                    $load->L,
                    $load->W,
                    $load->H,
                    $load->kg,
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    $t['not_fit']
                ];
                
                $worksheet->fromArray($rowData, null, 'A' . $currentRow);
                $currentRow++;
            }
        }

        // Apply merges
        foreach ($mergeRanges as $merge) {
            if ($merge['end'] > $merge['start']) {
                $worksheet->mergeCells($merge['col'] . $merge['start'] . ':' . $merge['col'] . $merge['end']);
            }
        }

        // Set column widths
        foreach (range('A', 'L') as $col) {
            $worksheet->getColumnDimension($col)->setWidth(18);
        }

        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        
        return $outputPath;
    }

    /**
     * Create sample template Excel file
     */
    public function createTemplate($outputPath) {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $headers = ['yuk_no', 'yuk_adi', 'uzunluk_mm', 'genislik_mm', 'yukseklik_mm', 'agirlik_kg', 'istiflenebilir'];
        $worksheet->fromArray($headers, null, 'A1');
        
        // Sample data
        $sampleData = [
            [1, 'Engine_Block_A', 3202, 300, 200, 17800, 1],
            [144, 'Engine_Block_A', 1050, 216, 168, 8130, 1],
            [475, 'Small_Parts_B', 41, 33, 30, 39, 1],
            [479, 'Small_Parts_B', 41, 33, 30, 27, 1],
            [2, 'Heavy_Equipment_C', 3202, 300, 200, 14410, 0],
            [3, 'Machine_Parts_D', 1994, 210, 140, 9600, 0],
        ];
        
        $worksheet->fromArray($sampleData, null, 'A2');
        
        // Set column widths
        foreach (range('A', 'G') as $col) {
            $worksheet->getColumnDimension($col)->setWidth(15);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        
        return $outputPath;
    }
}