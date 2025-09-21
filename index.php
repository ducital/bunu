<?php
// Packingo - 3D Container Loading Optimization System
// Based on the original Python algorithm by H. DUMAN BOZKURT

// Start session for file handling
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include autoloader and classes
require_once 'vendor/autoload.php';
require_once 'classes/Config.php';
require_once 'classes/Load.php';
require_once 'classes/Placement.php';
require_once 'classes/Shelf.php';
require_once 'classes/Vehicle.php';
require_once 'classes/PackingAlgorithm.php';
require_once 'classes/ExcelProcessor.php';

$config = new Config();
$currentLang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
$_SESSION['lang'] = $currentLang;

$t = $config->getTranslations($currentLang);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="description" content="Packingo - 3D Konteyner Yükleme Optimizasyon Sistemi. Konteynerlere ve dorseleré eşyaları verimli şekilde yerleştirin. Gelişmiş algoritmalar ile kargo planlaması ve lojistik optimizasyonu. Excel entegrasyonu ve çok dilli destek.">
    <meta name="keywords" content="konteyner yükleme, 3D optimizasyon, paketleme algoritması, lojistik, kargo planlaması, nakliye optimizasyonu, dorse yükleme, tır yükleme, yük planlaması, sevkiyat optimizasyonu, konteyner dolumu, kargo yerleşimi, taşımacılık çözümleri, depo yönetimi, yük dağılımı, alan kullanımı, verimli paketleme, lojistik planlama, ulaştırma optimizasyonu, ticari taşımacılık, dosya dönüştürücü, belge dönüşümü, excel dönüştürme, pdf dönüştürme, word dönüştürme, görsel dönüştürme, office dönüştürücü, belge formatı değiştirme, OCR, optik karakter tanıma, container loading, 3D optimization, packing algorithm, logistics, cargo planning, shipping optimization, trailer loading, truck loading, freight planning, warehouse management, space utilization, efficient packing, transportation optimization, commercial shipping, file converter, document conversion, excel converter, pdf converter, word converter, image converter, office converter, document format conversion, optical character recognition">
    <meta name="author" content="Packingo">
    <title><?php echo $t['title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/packingo-logo.png">
</head>
<body>
    <div class="container-fluid">
        <header class="p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                        <img src="assets/images/packingo-logo.png" alt="Packingo Logo" class="logo-glow" style="height: 120px; width: auto;">
                    </div>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <!-- Info Button -->
                    <button type="button" class="btn btn-info me-3" data-bs-toggle="modal" data-bs-target="#howToUseModal">
                        <i class="fas fa-question-circle me-2"></i><?php echo $t['how_to_use']; ?>
                    </button>
                    
                    <!-- Language Selector -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-globe me-2"></i><?php echo $t['language']; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item <?php echo $currentLang === 'en' ? 'active' : ''; ?>" href="/?lang=en">🇺🇸 English</a></li>
                            <li><a class="dropdown-item <?php echo $currentLang === 'tr' ? 'active' : ''; ?>" href="/?lang=tr">🇹🇷 Türkçe</a></li>
                            <li><a class="dropdown-item <?php echo $currentLang === 'de' ? 'active' : ''; ?>" href="/?lang=de">🇩🇪 Deutsch</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <!-- Tab Navigation -->
        <nav class="mb-4">
            <div class="nav nav-pills nav-fill" id="main-nav" role="tablist">
                <button class="nav-link active" id="planner-tab" data-bs-toggle="pill" data-bs-target="#planner" type="button">
                    <i class="fas fa-boxes me-2"></i><?php echo $t['container_planner']; ?>
                </button>
                <button class="nav-link" id="converter-tab" data-bs-toggle="pill" data-bs-target="#converter" type="button">
                    <i class="fas fa-sync-alt me-2"></i><?php echo $t['file_converter']; ?>
                </button>
            </div>
        </nav>

        <!-- Tab Content -->
        <div class="tab-content" id="main-content">
            <!-- Container Planner Tab -->
            <div class="tab-pane fade show active" id="planner" role="tabpanel">
                <main>
                    <div class="row">
                        <div class="col-lg-8">
                    <!-- Upload Section -->
                    <div class="clean-section">
                        <h5 class="section-title"><?php echo $t['upload_file']; ?></h5>
                            
                        <form id="planningForm" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx,.xls" required>
                                <div class="form-text mt-2">
                                    <small><?php echo $t['supported_formats']; ?></small>
                                </div>
                                <div class="mt-2">
                                    <a href="#" id="createTemplate" class="text-decoration-none" style="color: #FF8C00;">
                                        <i class="fas fa-download me-1"></i><?php echo $t['template']; ?>
                                    </a>
                                </div>
                            </div>
                    </div>
                    
                    <!-- Info Section -->
                    <div class="clean-section">
                        <h5 class="section-title"><?php echo $t['info']; ?></h5>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="lengthUnit" class="form-label fw-semibold"><?php echo $t['length_unit']; ?></label>
                                <select class="form-select" id="lengthUnit" name="lengthUnit">
                                    <?php foreach($t['units_len'] as $unit): ?>
                                        <option value="<?php echo $unit; ?>" <?php echo $unit === 'mm' ? 'selected' : ''; ?>><?php echo $unit; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="weightUnit" class="form-label fw-semibold"><?php echo $t['weight_unit']; ?></label>
                                <select class="form-select" id="weightUnit" name="weightUnit">
                                    <?php foreach($t['units_w'] as $unit): ?>
                                        <option value="<?php echo $unit; ?>" <?php echo $unit === 'kg' ? 'selected' : ''; ?>><?php echo $unit; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h6 class="subsection-title"><?php echo $t['stackable_src']; ?></h6>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="stackMode" id="stackExcel" value="excel" checked>
                            <label class="form-check-label" for="stackExcel"><?php echo $t['stack_excel']; ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="stackMode" id="stackYes" value="yes">
                            <label class="form-check-label" for="stackYes"><?php echo $t['stack_yes']; ?></label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="stackMode" id="stackNo" value="no">
                            <label class="form-check-label" for="stackNo"><?php echo $t['stack_no']; ?></label>
                        </div>
                    </div>

                    
                    <!-- Plan Button -->
                    <div class="text-center mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-play me-2"></i><?php echo $t['plan']; ?>
                        </button>
                    </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <!-- Container Types -->
                    <div class="clean-section">
                        <h6 class="subsection-title"><?php echo $t['container_types']; ?></h6>
                        <?php foreach($config->getContainers() as $name => $specs): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="containers[]" value="<?php echo $name; ?>" id="cont_<?php echo md5($name); ?>">
                                <label class="form-check-label" for="cont_<?php echo md5($name); ?>">
                                    <strong><?php echo $name; ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo number_format($specs['iL']); ?>×<?php echo number_format($specs['iW']); ?>×<?php echo number_format($specs['iH']); ?>mm<br>
                                        Max: <?php echo number_format($specs['max_kg']); ?>kg
                                    </small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Trailer Types -->
                    <div class="clean-section">
                        <h6 class="subsection-title"><?php echo $t['trailer_types']; ?></h6>
                        <?php foreach($config->getTrailers() as $name => $specs): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="trailers[]" value="<?php echo $name; ?>" id="trail_<?php echo md5($name); ?>">
                                <label class="form-check-label" for="trail_<?php echo md5($name); ?>">
                                    <strong><?php echo $name; ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo number_format($specs['iL']); ?>×<?php echo number_format($specs['iW']); ?>×<?php echo number_format($specs['iH']); ?>mm<br>
                                        Max: <?php echo number_format($specs['max_kg']); ?>kg
                                    </small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
                </main>
            </div>
            <!-- End Container Planner Tab -->

            <!-- File Converter Tab -->
            <div class="tab-pane fade" id="converter" role="tabpanel">
                <main>
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- File Upload Section -->
                            <div class="clean-section">
                                <h5 class="section-title"><?php echo $t['select_file_to_convert']; ?></h5>
                                
                                <form id="converterForm" method="post" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <input type="file" class="form-control" id="converterFile" name="converterFile" accept=".docx,.xlsx,.pdf,.jpg,.jpeg,.png" required>
                                        <div class="form-text mt-2">
                                            <small><?php echo $t['max_file_size_10mb']; ?></small>
                                        </div>
                                    </div>
                                    
                                    <!-- Target Format Selection -->
                                    <div class="mb-3">
                                        <label for="targetFormat" class="form-label fw-semibold"><?php echo $t['target_format']; ?></label>
                                        <select class="form-select" id="targetFormat" name="targetFormat" required>
                                            <option value=""><?php echo $t['target_format']; ?>...</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Convert Button -->
                                    <div class="text-center mb-4">
                                        <button type="submit" class="btn btn-success btn-lg" disabled>
                                            <i class="fas fa-sync-alt me-2"></i><?php echo $t['convert']; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Conversion Options -->
                            <div class="clean-section">
                                <h6 class="subsection-title"><?php echo $t['supported_input_formats']; ?></h6>
                                
                                <!-- Word Conversions -->
                                <div class="mb-3">
                                    <h6 class="text-primary">📄 <?php echo $t['word_conversions']; ?></h6>
                                    <small class="text-muted d-block"><?php echo $t['word_to_pdf']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['word_to_excel']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['word_to_txt']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['word_to_image']; ?></small>
                                </div>

                                <!-- Excel Conversions -->
                                <div class="mb-3">
                                    <h6 class="text-success">📊 <?php echo $t['excel_conversions']; ?></h6>
                                    <small class="text-muted d-block"><?php echo $t['excel_to_pdf']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['excel_to_word']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['excel_to_txt']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['excel_to_image']; ?></small>
                                </div>

                                <!-- PDF Conversions -->
                                <div class="mb-3">
                                    <h6 class="text-danger">📁 <?php echo $t['pdf_conversions']; ?></h6>
                                    <small class="text-muted d-block"><?php echo $t['pdf_to_word']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['pdf_to_excel']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['pdf_to_txt']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['pdf_to_image']; ?></small>
                                </div>

                                <!-- Image Conversions -->
                                <div class="mb-3">
                                    <h6 class="text-warning">🖼️ <?php echo $t['image_conversions']; ?></h6>
                                    <small class="text-muted d-block"><?php echo $t['image_to_pdf']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['image_to_word']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['image_to_excel']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['image_to_txt']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['jpg_to_png']; ?></small>
                                    <small class="text-muted d-block"><?php echo $t['png_to_jpg']; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <!-- End File Converter Tab -->
        </div>
        <!-- End Tab Content -->

        <!-- How To Use Modal -->
        <div class="modal fade" id="howToUseModal" tabindex="-1" aria-labelledby="howToUseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="howToUseModalLabel">
                            <i class="fas fa-graduation-cap me-2"></i>Packingo <?php echo $t['how_to_use']; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        
                        <!-- Step Navigation -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <nav>
                                    <div class="nav nav-pills nav-fill" id="tutorial-nav" role="tablist">
                                        <button class="nav-link active" id="step1-tab" data-bs-toggle="pill" data-bs-target="#step1" type="button">
                                            <i class="fas fa-file-excel me-2"></i>1. <?php echo $t['tutorial_step1']; ?>
                                        </button>
                                        <button class="nav-link" id="step2-tab" data-bs-toggle="pill" data-bs-target="#step2" type="button">
                                            <i class="fas fa-upload me-2"></i>2. <?php echo $t['tutorial_step2']; ?>
                                        </button>
                                        <button class="nav-link" id="step3-tab" data-bs-toggle="pill" data-bs-target="#step3" type="button">
                                            <i class="fas fa-truck me-2"></i>3. <?php echo $t['tutorial_step3']; ?>
                                        </button>
                                        <button class="nav-link" id="step4-tab" data-bs-toggle="pill" data-bs-target="#step4" type="button">
                                            <i class="fas fa-chart-bar me-2"></i>4. <?php echo $t['tutorial_step4']; ?>
                                        </button>
                                    </div>
                                </nav>
                            </div>
                        </div>

                        <!-- Step Content -->
                        <div class="tab-content" id="tutorial-content">
                            
                            <!-- Step 1: Excel Preparation -->
                            <div class="tab-pane fade show active" id="step1" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-primary mb-3">📋 <?php echo $t['tutorial_step1']; ?></h6>
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle me-2"></i><?php echo $t['required_columns']; ?></h6>
                                            <ul class="mb-0">
                                                <li><strong><?php echo $t['column_a']; ?>:</strong> yuk_no</li>
                                                <li><strong><?php echo $t['column_b']; ?>:</strong> yuk_adi</li>
                                                <li><strong><?php echo $t['column_c']; ?>:</strong> uzunluk_mm</li>
                                                <li><strong><?php echo $t['column_d']; ?>:</strong> genislik_mm</li>
                                                <li><strong><?php echo $t['column_e']; ?>:</strong> yukseklik_mm</li>
                                                <li><strong><?php echo $t['column_f']; ?>:</strong> agirlik_kg</li>
                                                <li><strong><?php echo $t['column_g']; ?>:</strong> istiflenebilir</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <h6><i class="fas fa-exclamation-triangle me-2"></i><?php echo $t['important_notes']; ?></h6>
                                            <ul class="mb-0">
                                                <li><?php echo $t['note_1']; ?></li>
                                                <li><?php echo $t['note_2']; ?></li>
                                                <li><?php echo $t['note_3']; ?></li>
                                                <li><?php echo $t['note_4']; ?></li>
                                                <li><?php echo $t['note_5']; ?></li>
                                                <li><?php echo $t['note_6']; ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-success mb-3">✅ <?php echo $t['sample_excel_format']; ?></h6>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-primary">
                                                    <tr>
                                                        <th>yuk_no</th>
                                                        <th>yuk_adi</th>
                                                        <th>uzunluk_mm</th>
                                                        <th>genislik_mm</th>
                                                        <th>yukseklik_mm</th>
                                                        <th>agirlik_kg</th>
                                                        <th>istiflenebilir</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>1</td>
                                                        <td>Engine_Block_A</td>
                                                        <td>3202</td>
                                                        <td>300</td>
                                                        <td>200</td>
                                                        <td>17800</td>
                                                        <td>1</td>
                                                    </tr>
                                                    <tr>
                                                        <td>144</td>
                                                        <td>Engine_Block_A</td>
                                                        <td>1050</td>
                                                        <td>216</td>
                                                        <td>168</td>
                                                        <td>8130</td>
                                                        <td>1</td>
                                                    </tr>
                                                    <tr>
                                                        <td>475</td>
                                                        <td>Small_Parts_B</td>
                                                        <td>41</td>
                                                        <td>33</td>
                                                        <td>30</td>
                                                        <td>39</td>
                                                        <td>1</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-center mt-3">
                                            <button class="btn btn-sm btn-success" onclick="document.getElementById('createTemplate').click();">
                                                <i class="fas fa-download me-2"></i><?php echo $t['download_sample_excel']; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: File Upload -->
                            <div class="tab-pane fade" id="step2" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8 mx-auto text-center">
                                        <h6 class="fw-bold text-primary mb-4"><?php echo $t['upload_excel_title']; ?></h6>
                                        
                                        <div class="upload-demo p-4 border rounded mb-4">
                                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                            <p class="text-muted"><?php echo $t['drag_drop_text']; ?></p>
                                            <small class="text-muted"><?php echo $t['supported_formats']; ?></small>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center justify-content-center p-3 border rounded">
                                                    <i class="fas fa-check-circle text-success fa-2x me-2"></i>
                                                    <div>
                                                        <strong><?php echo $t['file_uploaded']; ?></strong><br>
                                                        <small class="text-muted">urun_listesi.xlsx</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center justify-content-center p-3 border rounded">
                                                    <i class="fas fa-cog fa-spin text-primary fa-2x me-2"></i>
                                                    <div>
                                                        <strong><?php echo $t['processing']; ?></strong><br>
                                                        <small class="text-muted"><?php echo $t['reading_data']; ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center justify-content-center p-3 border rounded">
                                                    <i class="fas fa-list-alt text-info fa-2x me-2"></i>
                                                    <div>
                                                        <strong>128 <?php echo $t['products_found']; ?></strong><br>
                                                        <small class="text-muted"><?php echo $t['successfully_read']; ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Vehicle Selection -->
                            <div class="tab-pane fade" id="step3" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-4"><?php echo $t['select_vehicles_title']; ?></h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-secondary mb-3"><?php echo $t['container_types']; ?></h6>
                                        <div class="border rounded p-3 mb-3">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    <strong>20' DV Container</strong><br>
                                                    <small class="text-muted">5900×2350×2390mm - Max: 28000kg</small>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    <strong>40' DV Container</strong><br>
                                                    <small class="text-muted">12035×2350×2390mm - Max: 30000kg</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-secondary mb-3"><?php echo $t['trailer_types']; ?></h6>
                                        <div class="border rounded p-3 mb-3">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    <strong>Standart Tır</strong><br>
                                                    <small class="text-muted">13600×2450×2700mm - Max: 24000kg</small>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" disabled>
                                                <label class="form-check-label">
                                                    <strong>Lowbed</strong><br>
                                                    <small class="text-muted">25000×2550×3000mm - Max: 35000kg</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-success">
                                    <h6><i class="fas fa-lightbulb me-2"></i><?php echo $t['suggestions_title']; ?></h6>
                                    <ul class="mb-0">
                                        <li><?php echo $t['suggestion_1']; ?></li>
                                        <li><?php echo $t['suggestion_2']; ?></li>
                                        <li><?php echo $t['suggestion_3']; ?></li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Step 4: Results -->
                            <div class="tab-pane fade" id="step4" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-4">📊 <?php echo $t['tutorial_step4']; ?></h6>
                                
                                <div class="row">
                                    <div class="col-md-8">

                                        <!-- Vehicle Details Preview -->
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead class="table-primary">
                                                    <tr>
                                                        <th><?php echo $t['vehicle']; ?></th>
                                                        <th><?php echo $t['item_count']; ?></th>
                                                        <th><?php echo $t['weight']; ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="table-success">
                                                        <td><strong>40' DV Container #1</strong></td>
                                                        <td>67 <?php echo $t['products_found']; ?></td>
                                                        <td>18,500 kg</td>
                                                    </tr>
                                                    <tr class="table-warning">
                                                        <td><strong>Standart Tır #1</strong></td>
                                                        <td>45 <?php echo $t['products_found']; ?></td>
                                                        <td>12,200 kg</td>
                                                    </tr>
                                                    <tr class="table-info">
                                                        <td><strong>20' DV Container #1</strong></td>
                                                        <td>16 <?php echo $t['products_found']; ?></td>
                                                        <td>6,800 kg</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0"><i class="fas fa-download me-2"></i><?php echo $t['download_options']; ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-success btn-sm">
                                                        <i class="fas fa-file-excel me-2"></i><?php echo $t['excel_report']; ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i><?php echo $t['close']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <footer class="text-center py-3 mt-5">
            <small class="text-muted"><?php echo $t['footer']; ?></small>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pass translations to JavaScript
        window.translations = <?php echo json_encode($t, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>