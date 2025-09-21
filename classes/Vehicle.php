<?php

class Vehicle {
    public $label;     // e.g., "Tenteli #1"
    public $typeName;  // e.g., "Tenteli"
    public $iL;        // Internal Length
    public $iW;        // Internal Width
    public $iH;        // Internal Height
    public $maxKg;     // Maximum weight capacity
    public $shelves;   // Array of Shelf objects
    public $totalKg;   // Current total weight
    public $centerOfGravityX; // X coordinate of center of gravity
    public $centerOfGravityY; // Y coordinate of center of gravity
    public $centerOfGravityZ; // Z coordinate of center of gravity
    public $weightDistribution; // Array of weight distribution points

    public function __construct($label, $typeName, $iL, $iW, $iH, $maxKg) {
        $this->label = $label;
        $this->typeName = $typeName;
        $this->iL = $iL;
        $this->iW = $iW;
        $this->iH = $iH;
        $this->maxKg = $maxKg;
        $this->shelves = [];
        $this->totalKg = 0.0;
        $this->centerOfGravityX = 0;
        $this->centerOfGravityY = 0;
        $this->centerOfGravityZ = 0;
        $this->weightDistribution = [];
    }

    /**
     * Ensure a shelf of given height exists, create if needed
     * For lowbed, always create new shelf (no stacking)
     */
    public function ensureShelf($height, $isLowbed = false, $loadName = '') {
        if ($isLowbed) {
            // For lowbed, check if we can add a new shelf
            $usedHeight = 0;
            foreach ($this->shelves as $shelf) {
                $usedHeight += $shelf->height;
            }
            
            $remaining = $this->iH - $usedHeight;
            if ($height <= $remaining) {
                $shelf = new Shelf($usedHeight, $height);
                $this->shelves[] = $shelf;
                return $shelf;
            }
            return null;
        }

        // Original logic for other vehicle types
        foreach ($this->shelves as $shelf) {
            if ($height <= $shelf->height) {
                return $shelf;
            }
        }

        $usedHeight = 0;
        foreach ($this->shelves as $shelf) {
            $usedHeight += $shelf->height;
        }
        
        $remaining = $this->iH - $usedHeight;
        if ($height <= $remaining) {
            // FIXED: Check stacking constraints for proper load placement
            // Non-stackable items should not have shelves placed above them
            if (count($this->shelves) > 0) {
                foreach ($this->shelves as $shelf) {
                    if (!$shelf->allStackable) {
                        // Found a shelf with non-stackable items, no new shelf allowed above
                        return null;
                    }
                }
            }
            
            $shelf = new Shelf($usedHeight, $height);
            $this->shelves[] = $shelf;
            return $shelf;
        }

        return null;
    }

    /**
     * Check if load can be placed on lowbed (load name matching)
     */
    public function canPlaceOnLowbed(Load $load) {
        if ($this->typeName !== 'Lowbed') {
            return true;
        }

        // If no loads placed yet, allow
        if (count($this->shelves) === 0) {
            return true;
        }

        $hasAnyPlaces = false;
        $existingNames = [];
        
        foreach ($this->shelves as $shelf) {
            if (count($shelf->places) > 0) {
                $hasAnyPlaces = true;
                foreach ($shelf->places as $place) {
                    $existingNames[] = $place->loadName;
                }
            }
        }

        if (!$hasAnyPlaces) {
            return true;
        }

        // Check if new load name matches existing ones
        foreach ($existingNames as $existingName) {
            if ($load->loadName === $existingName) {
                return true;
            }
            // Check for partial matching
            if ($this->hasPartialMatch($load->loadName, $existingName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two load names have partial matching
     */
    private function hasPartialMatch($name1, $name2) {
        $words1 = array_map('strtolower', explode(' ', $name1));
        $words2 = array_map('strtolower', explode(' ', $name2));
        
        return count(array_intersect($words1, $words2)) > 0;
    }

    /**
     * Try to place a load in this vehicle
     * Returns Placement object if successful, null if failed
     */
    public function tryPut(Load $load) {
        if ($this->totalKg + $load->kg > $this->maxKg) {
            return null;
        }

        // Special handling for lowbed
        if ($this->typeName === 'Lowbed' && !$this->canPlaceOnLowbed($load)) {
            return null;
        }

        // Use intelligent rotation selection from Load class
        $rotations = $load->getPreferredRotations();

        $isLowbed = $this->typeName === 'Lowbed';

        // SIMPLIFIED APPROACH: Focus on placement success rather than complex optimization
        foreach ($rotations as $rotation) {
            list($L, $W, $H) = $rotation;
            
            if ($L > $this->iL || $W > $this->iW || $H > $this->iH) {
                continue;
            }

            // For non-lowbed, try existing shelves first
            if (!$isLowbed) {
                foreach ($this->shelves as $shelf) {
                    $placement = $shelf->tryPlace($load, $this->iL, $this->iW, $rotation);
                    if ($placement) {
                        $this->totalKg += $load->kg;
                        $this->updateCenterOfGravity($placement);
                        return $placement;
                    }
                }
            }

            // Try to create new shelf
            $shelf = $this->ensureShelf($H, $isLowbed, $load->loadName);
            if ($shelf) {
                $placement = $shelf->tryPlace($load, $this->iL, $this->iW, $rotation);
                if ($placement) {
                    $this->totalKg += $load->kg;
                    $this->updateCenterOfGravity($placement);
                    return $placement;
                }
            }
        }

        return null;
    }

    /**
     * Update center of gravity after placing a load
     */
    private function updateCenterOfGravity($placement) {
        if ($this->totalKg <= 0) {
            return; // Avoid division by zero
        }

        // Calculate the center point of the placed load
        $loadCenterX = $placement->x + ($placement->L / 2);
        $loadCenterY = $placement->y + ($placement->W / 2);
        $loadCenterZ = $placement->z + ($placement->H / 2);

        // Update weighted center of gravity
        $prevWeight = $this->totalKg - $placement->kg;
        
        if ($prevWeight > 0) {
            // Weighted average with existing center of gravity
            $this->centerOfGravityX = (($this->centerOfGravityX * $prevWeight) + ($loadCenterX * $placement->kg)) / $this->totalKg;
            $this->centerOfGravityY = (($this->centerOfGravityY * $prevWeight) + ($loadCenterY * $placement->kg)) / $this->totalKg;
            $this->centerOfGravityZ = (($this->centerOfGravityZ * $prevWeight) + ($loadCenterZ * $placement->kg)) / $this->totalKg;
        } else {
            // First load sets the center of gravity
            $this->centerOfGravityX = $loadCenterX;
            $this->centerOfGravityY = $loadCenterY;
            $this->centerOfGravityZ = $loadCenterZ;
        }

        // Store weight distribution point
        $this->weightDistribution[] = [
            'x' => $loadCenterX,
            'y' => $loadCenterY,
            'z' => $loadCenterZ,
            'kg' => $placement->kg
        ];
    }

    /**
     * Check if center of gravity is within acceptable bounds
     */
    public function isCenterOfGravityValid() {
        // For most vehicles, center of gravity should be:
        // - Between 30% and 70% of length (X-axis)
        // - Between 25% and 75% of width (Y-axis)
        // - As low as possible (Z-axis)
        
        $cgXPercent = ($this->centerOfGravityX / $this->iL) * 100;
        $cgYPercent = ($this->centerOfGravityY / $this->iW) * 100;
        
        // Stricter bounds for lowbed (more critical for stability)
        if ($this->typeName === 'Lowbed') {
            return $cgXPercent >= 40 && $cgXPercent <= 60 && 
                   $cgYPercent >= 35 && $cgYPercent <= 65;
        }
        
        // Standard bounds for other vehicle types
        return $cgXPercent >= 30 && $cgXPercent <= 70 && 
               $cgYPercent >= 25 && $cgYPercent <= 75;
    }

    /**
     * Calculate placement score based on center of gravity impact
     */
    public function calculatePlacementScore($placement) {
        // Simulate what center of gravity would be after this placement
        $tempWeight = $this->totalKg + $placement->kg;
        $loadCenterX = $placement->x + ($placement->L / 2);
        $loadCenterY = $placement->y + ($placement->W / 2);
        
        $tempCgX = (($this->centerOfGravityX * $this->totalKg) + ($loadCenterX * $placement->kg)) / $tempWeight;
        $tempCgY = (($this->centerOfGravityY * $this->totalKg) + ($loadCenterY * $placement->kg)) / $tempWeight;
        
        // Score based on how close to center the CG would be
        $idealCgX = $this->iL / 2;
        $idealCgY = $this->iW / 2;
        
        $cgDeviationX = abs($tempCgX - $idealCgX) / $idealCgX;
        $cgDeviationY = abs($tempCgY - $idealCgY) / $idealCgY;
        
        // Lower deviation = better score (0-100 scale)
        $score = 100 - (($cgDeviationX + $cgDeviationY) * 50);
        
        return max(0, min(100, $score));
    }

    /**
     * Calculate vehicle utilization efficiency score for load placement
     * Lower score = better fit (less wasted space)
     */
    public function calculateFitScore($load) {
        // Calculate volume utilization
        $usedVolume = 0;
        foreach ($this->shelves as $shelf) {
            foreach ($shelf->places as $place) {
                $usedVolume += $place->L * $place->W * $place->H;
            }
        }
        
        $totalVolume = $this->iL * $this->iW * $this->iH;
        $currentUtilization = $totalVolume > 0 ? ($usedVolume / $totalVolume) * 100 : 0;
        
        // Estimate utilization after placing this load
        $loadVolume = $load->L * $load->W * $load->H;
        $estimatedUtilization = $totalVolume > 0 ? (($usedVolume + $loadVolume) / $totalVolume) * 100 : 0;
        
        // Weight utilization
        $weightUtilization = $this->maxKg > 0 ? (($this->totalKg + $load->kg) / $this->maxKg) * 100 : 0;
        
        // Penalty for low utilization (prefer fuller vehicles)
        $utilizationPenalty = 100 - $estimatedUtilization;
        
        // Penalty for weight overload (hard constraint)
        $weightPenalty = $weightUtilization > 100 ? 10000 : 0;
        
        // Penalty for height mismatch
        $heightPenalty = 0;
        $loadHeight = min($load->L, $load->W, $load->H); // Minimum dimension as height
        foreach ($load->getRotations() as $rotation) {
            $h = $rotation[2];
            if ($h <= $this->iH) {
                $heightPenalty = min($heightPenalty, abs($this->iH - $h) / $this->iH * 10);
            }
        }
        
        // Total fit score (lower = better fit)
        $fitScore = $utilizationPenalty + $weightPenalty + $heightPenalty;
        
        return $fitScore;
    }

    /**
     * Check if this vehicle can potentially fit the load (quick pre-check)
     */
    public function canPotentiallyFit($load) {
        // Weight check
        if ($this->totalKg + $load->kg > $this->maxKg) {
            return false;
        }
        
        // Dimension check - try all rotations
        foreach ($load->getRotations() as $rotation) {
            list($L, $W, $H) = $rotation;
            if ($L <= $this->iL && $W <= $this->iW && $H <= $this->iH) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get current volume utilization percentage
     */
    public function getVolumeUtilization() {
        $usedVolume = 0;
        foreach ($this->shelves as $shelf) {
            foreach ($shelf->places as $place) {
                $usedVolume += $place->L * $place->W * $place->H;
            }
        }
        
        $totalVolume = $this->iL * $this->iW * $this->iH;
        return $totalVolume > 0 ? ($usedVolume / $totalVolume) * 100 : 0;
    }

    /**
     * Preview what center of gravity would be after a placement (without modifying vehicle)
     */
    private function previewCenterOfGravity($placement) {
        $tempWeight = $this->totalKg + $placement->kg;
        
        if ($tempWeight <= 0) {
            return ['valid' => true, 'x' => 0, 'y' => 0, 'z' => 0];
        }

        $loadCenterX = $placement->x + ($placement->L / 2);
        $loadCenterY = $placement->y + ($placement->W / 2);
        $loadCenterZ = $placement->z + ($placement->H / 2);

        $tempCgX = (($this->centerOfGravityX * $this->totalKg) + ($loadCenterX * $placement->kg)) / $tempWeight;
        $tempCgY = (($this->centerOfGravityY * $this->totalKg) + ($loadCenterY * $placement->kg)) / $tempWeight;
        $tempCgZ = (($this->centerOfGravityZ * $this->totalKg) + ($loadCenterZ * $placement->kg)) / $tempWeight;

        // Check if center of gravity would be valid using relaxed bounds
        $cgXPercent = ($tempCgX / $this->iL) * 100;
        $cgYPercent = ($tempCgY / $this->iW) * 100;
        
        $bounds = $this->getRelaxedCGBounds();
        $valid = $cgXPercent >= $bounds['x_min'] && $cgXPercent <= $bounds['x_max'] && 
                 $cgYPercent >= $bounds['y_min'] && $cgYPercent <= $bounds['y_max'];

        return [
            'valid' => $valid,
            'x' => $tempCgX,
            'y' => $tempCgY,
            'z' => $tempCgZ
        ];
    }

    /**
     * Check if we should ignore center of gravity constraints for early placements
     * This prevents deadlock when first items can't be placed due to corner positioning
     */
    private function shouldIgnoreCGForFirstPlacements() {
        // Allow much more flexibility - only enforce strict CG after significant loading
        $totalPlacements = 0;
        foreach ($this->shelves as $shelf) {
            $totalPlacements += count($shelf->places);
        }
        
        // Be lenient for first 8 placements or until we have substantial weight
        return $totalPlacements < 8 || $this->totalKg < 1500;
    }

    /**
     * Get relaxed center of gravity bounds for more flexible placement
     */
    private function getRelaxedCGBounds() {
        // Much wider bounds during initial loading phase
        if ($this->shouldIgnoreCGForFirstPlacements()) {
            return [
                'x_min' => 15, 'x_max' => 85,  // Very wide X bounds (15-85%)
                'y_min' => 15, 'y_max' => 85   // Very wide Y bounds (15-85%)
            ];
        }
        
        // Standard bounds for established loading
        if ($this->typeName === 'Lowbed') {
            return [
                'x_min' => 40, 'x_max' => 60,  // Lowbed stricter
                'y_min' => 35, 'y_max' => 65
            ];
        }
        
        // Normal bounds for other vehicles
        return [
            'x_min' => 30, 'x_max' => 70,
            'y_min' => 25, 'y_max' => 75
        ];
    }
}