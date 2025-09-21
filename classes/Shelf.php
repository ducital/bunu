<?php

class Shelf {
    public $z0;        // Bottom Z coordinate of shelf
    public $height;    // Height of shelf
    public $rows;      // Array of [y_start, row_height, used_length]
    public $places;    // Array of Placement objects
    public $allStackable; // Whether all items on this shelf are stackable

    public function __construct($z0, $height) {
        $this->z0 = $z0;
        $this->height = $height;
        $this->rows = [];
        $this->places = [];
        $this->allStackable = true;
    }

    /**
     * Initialize shelf with vehicle dimensions
     */
    public function initializeBounds($vehicleL, $vehicleW) {
        $this->vehicleL = $vehicleL;
        $this->vehicleW = $vehicleW;
        // Start with one big free rectangle covering entire shelf area
        $this->freeRects = [
            ['x' => 0, 'y' => 0, 'width' => $vehicleL, 'height' => $vehicleW]
        ];
    }

    /**
     * FIXED SPACE FILLING: Unified placement using MaxRects algorithm only to prevent overlaps
     * Eliminates coordination issues between MaxRects and row-based systems
     */
    public function tryPlace(Load $load, $vehicleL, $vehicleW, $rotation) {
        list($L, $W, $H) = $rotation;
        
        if ($H > $this->height) {
            return null;
        }

        // Initialize MaxRects if not done
        if (!isset($this->freeRects)) {
            $this->initializeBounds($vehicleL, $vehicleW);
        }

        // USE MAXRECTS ONLY for consistent space management
        $bestPos = $this->findBestPositionMaxRects($L, $W);
        if ($bestPos) {
            // Verify position doesn't conflict with existing placements
            if ($this->checkPositionConflict($bestPos['x'], $bestPos['y'], $L, $W)) {
                return null;
            }
            
            $placement = new Placement(
                $load->no, $load->loadName, $L, $W, $H, $load->kg,
                $bestPos['x'], $bestPos['y'], $this->z0, $rotation
            );
            
            $this->places[] = $placement;
            $this->allStackable = $this->allStackable && $load->stack;
            
            // Update free rectangles after placement
            $this->updateFreeRects($bestPos['x'], $bestPos['y'], $L, $W);
            
            return $placement;
        }

        return null;
    }

    /**
     * SAFETY CHECK: Verify position doesn't conflict with existing placements
     * Prevents overlapping items from different placement systems
     */
    private function checkPositionConflict($x, $y, $width, $height) {
        foreach ($this->places as $place) {
            if ($this->rectanglesOverlap(
                $x, $y, $width, $height,
                $place->x, $place->y, $place->L, $place->W
            )) {
                return true; // Conflict found
            }
        }
        return false; // No conflict
    }

    /**
     * IMPROVED MAXRECTS: Find best position using MaxRects algorithm for space optimization
     */
    private function findBestPositionMaxRects($width, $height) {
        $bestScore = -1;
        $bestPos = null;
        
        foreach ($this->freeRects as $rect) {
            if ($rect['width'] >= $width && $rect['height'] >= $height) {
                // Best Short Side Fit (BSSF) heuristic - most space efficient
                $leftoverHoriz = $rect['width'] - $width;
                $leftoverVert = $rect['height'] - $height;
                $shortSide = min($leftoverHoriz, $leftoverVert);
                $longSide = max($leftoverHoriz, $leftoverVert);
                
                // Combined score: prefer smaller leftover (tighter fit)
                $score = $shortSide * 1000 + $longSide;
                
                if ($bestScore == -1 || $score < $bestScore) {
                    $bestScore = $score;
                    $bestPos = ['x' => $rect['x'], 'y' => $rect['y']];
                }
            }
        }
        
        return $bestPos;
    }

    /**
     * Update free rectangles after placing an item - space filling core logic
     */
    private function updateFreeRects($placedX, $placedY, $placedW, $placedH) {
        $newFreeRects = [];
        
        foreach ($this->freeRects as $rect) {
            // Check if placement intersects with this free rectangle
            if ($this->rectanglesOverlap(
                $placedX, $placedY, $placedW, $placedH,
                $rect['x'], $rect['y'], $rect['width'], $rect['height']
            )) {
                // Split the rectangle into smaller free rectangles
                $splitRects = $this->splitRectangle($rect, $placedX, $placedY, $placedW, $placedH);
                $newFreeRects = array_merge($newFreeRects, $splitRects);
            } else {
                // Rectangle not affected, keep it
                $newFreeRects[] = $rect;
            }
        }
        
        // Remove redundant rectangles and merge when possible
        $this->freeRects = $this->removeDuplicateRectangles($newFreeRects);
    }

    /**
     * Check if two rectangles intersect
     */
    private function rectanglesIntersect($x1, $y1, $w1, $h1, $x2, $y2, $w2, $h2) {
        return !($x1 >= $x2 + $w2 || $x2 >= $x1 + $w1 || $y1 >= $y2 + $h2 || $y2 >= $y1 + $h1);
    }



    /**
     * Find best position for rectangle using MaxRects heuristics with multiple candidates
     * Returns ['x' => x, 'y' => y] or null if can't fit
     */
    private function findBestPosition($width, $height) {
        $candidates = [];

        foreach ($this->freeRects as $rect) {
            if ($rect['width'] >= $width && $rect['height'] >= $height) {
                // Generate multiple candidate positions within this free rectangle
                $positions = $this->generateCandidatePositions($rect, $width, $height);
                
                foreach ($positions as $pos) {
                    // Best Short Side Fit heuristic
                    $leftoverHoriz = $rect['width'] - $width;
                    $leftoverVert = $rect['height'] - $height;
                    $score = min($leftoverHoriz, $leftoverVert);
                    
                    // Add slight preference for positions closer to bottom-left
                    $distanceScore = sqrt($pos['x'] * $pos['x'] + $pos['y'] * $pos['y']) * 0.01;
                    $totalScore = $score + $distanceScore;
                    
                    $candidates[] = [
                        'x' => $pos['x'],
                        'y' => $pos['y'],
                        'score' => $totalScore
                    ];
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort candidates by score and return the best one
        usort($candidates, function($a, $b) {
            return $a['score'] - $b['score'];
        });

        return ['x' => $candidates[0]['x'], 'y' => $candidates[0]['y']];
    }

    /**
     * Generate multiple candidate positions within a free rectangle
     */
    private function generateCandidatePositions($rect, $width, $height) {
        $positions = [];
        
        // Always include top-left corner (original behavior)
        $positions[] = ['x' => $rect['x'], 'y' => $rect['y']];
        
        // If there's extra space, add more positions
        $extraWidth = $rect['width'] - $width;
        $extraHeight = $rect['height'] - $height;
        
        if ($extraWidth > 0) {
            // Top-right position
            $positions[] = ['x' => $rect['x'] + $extraWidth, 'y' => $rect['y']];
        }
        
        if ($extraHeight > 0) {
            // Bottom-left position  
            $positions[] = ['x' => $rect['x'], 'y' => $rect['y'] + $extraHeight];
        }
        
        if ($extraWidth > 0 && $extraHeight > 0) {
            // Bottom-right position
            $positions[] = ['x' => $rect['x'] + $extraWidth, 'y' => $rect['y'] + $extraHeight];
            
            // Center position if there's significant extra space
            if ($extraWidth > 100 && $extraHeight > 100) {
                $positions[] = [
                    'x' => $rect['x'] + intval($extraWidth / 2),
                    'y' => $rect['y'] + intval($extraHeight / 2)
                ];
            }
        }
        
        return $positions;
    }

    /**
     * Update free rectangles after placing a rectangle
     */
    private function placeRectangle($x, $y, $width, $height) {
        $newRects = [];

        foreach ($this->freeRects as $rect) {
            if ($this->rectanglesOverlap($x, $y, $width, $height, $rect['x'], $rect['y'], $rect['width'], $rect['height'])) {
                // Split the overlapping rectangle
                $splits = $this->splitRectangle($rect, $x, $y, $width, $height);
                $newRects = array_merge($newRects, $splits);
            } else {
                // Keep non-overlapping rectangle
                $newRects[] = $rect;
            }
        }

        $this->freeRects = $this->removeDuplicateRectangles($newRects);
    }

    /**
     * Check if two rectangles overlap
     */
    private function rectanglesOverlap($x1, $y1, $w1, $h1, $x2, $y2, $w2, $h2) {
        return !($x1 >= $x2 + $w2 || $x2 >= $x1 + $w1 || $y1 >= $y2 + $h2 || $y2 >= $y1 + $h1);
    }

    /**
     * Split rectangle when overlapped by placement
     */
    private function splitRectangle($rect, $placedX, $placedY, $placedW, $placedH) {
        $splits = [];
        
        $rectX = $rect['x'];
        $rectY = $rect['y'];
        $rectW = $rect['width'];
        $rectH = $rect['height'];

        // Left split
        if ($placedX > $rectX) {
            $splits[] = [
                'x' => $rectX,
                'y' => $rectY,
                'width' => $placedX - $rectX,
                'height' => $rectH
            ];
        }

        // Right split
        if ($placedX + $placedW < $rectX + $rectW) {
            $splits[] = [
                'x' => $placedX + $placedW,
                'y' => $rectY,
                'width' => ($rectX + $rectW) - ($placedX + $placedW),
                'height' => $rectH
            ];
        }

        // Bottom split
        if ($placedY > $rectY) {
            $splits[] = [
                'x' => $rectX,
                'y' => $rectY,
                'width' => $rectW,
                'height' => $placedY - $rectY
            ];
        }

        // Top split
        if ($placedY + $placedH < $rectY + $rectH) {
            $splits[] = [
                'x' => $rectX,
                'y' => $placedY + $placedH,
                'width' => $rectW,
                'height' => ($rectY + $rectH) - ($placedY + $placedH)
            ];
        }

        return $splits;
    }

    /**
     * Remove duplicate and contained rectangles - FIXED version
     */
    private function removeDuplicateRectangles($rects) {
        $filtered = [];
        
        foreach ($rects as $rect) {
            if ($rect['width'] <= 0 || $rect['height'] <= 0) {
                continue; // Skip invalid rectangles
            }
            
            $isContained = false;
            
            // Check if this rectangle is contained within any existing rectangle
            foreach ($filtered as $existing) {
                if ($rect['x'] >= $existing['x'] && 
                    $rect['y'] >= $existing['y'] && 
                    $rect['x'] + $rect['width'] <= $existing['x'] + $existing['width'] && 
                    $rect['y'] + $rect['height'] <= $existing['y'] + $existing['height']) {
                    $isContained = true;
                    break;
                }
            }
            
            if (!$isContained) {
                // Remove any existing rectangles that are contained within this new rectangle
                $filtered = array_filter($filtered, function($existing) use ($rect) {
                    return !($existing['x'] >= $rect['x'] && 
                             $existing['y'] >= $rect['y'] && 
                             $existing['x'] + $existing['width'] <= $rect['x'] + $rect['width'] && 
                             $existing['y'] + $existing['height'] <= $rect['y'] + $rect['height']);
                });
                
                $filtered[] = $rect;
            }
        }
        
        return array_values($filtered); // Re-index array
    }

    /**
     * Preview placement without actually placing (for scoring) - FIXED
     */
    public function previewPlacement($load, $vehicleL, $vehicleW, $rotation) {
        list($L, $W, $H) = $rotation;
        
        if ($H > $this->height) {
            return null;
        }

        // Initialize free rectangles if not done yet
        if (empty($this->freeRects)) {
            $this->initializeBounds($vehicleL, $vehicleW);
        }

        // Find best position using our MaxRects algorithm
        $bestPos = $this->findBestPosition($L, $W);
        if ($bestPos === null) {
            return null;
        }

        // Return preview placement without modifying shelf state
        return new Placement(
            $load->no, $load->loadName, $L, $W, $H, $load->kg,
            $bestPos['x'], $bestPos['y'], $this->z0, $rotation
        );
    }
}