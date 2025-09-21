<?php

class PackingAlgorithm {
    private $config;

    public function __construct() {
        $this->config = new Config();
    }

    /**
     * Main planning algorithm
     * Returns array with 'vehicles' and 'unplaced' loads
     */
    public function plan($loads, $selectedTrailers, $selectedContainers) {
        // GROUP LOADS by similar characteristics for better placement efficiency
        $loadGroups = $this->groupLoadsByCharacteristics($loads);
        
        // Sort groups and loads within groups using enhanced strategy
        $loads = $this->sortGroupedLoads($loadGroups);
        
        $vehicles = [];
        $unplaced = [];
        
        // Priority order for trailers
        $trailerPriority = ['Standart Tır', 'Flatbed', 'Lowbed'];
        $selectedTrailersSorted = [];
        foreach ($trailerPriority as $trailer) {
            if (in_array($trailer, $selectedTrailers)) {
                $selectedTrailersSorted[] = $trailer;
            }
        }
        
        // Available vehicle types (trailers first, then containers)
        $availableTypes = [];
        $trailers = $this->config->getTrailers();
        $containers = $this->config->getContainers();
        
        foreach ($selectedTrailersSorted as $name) {
            $availableTypes[] = ['name' => $name, 'specs' => $trailers[$name]];
        }
        
        foreach ($selectedContainers as $name) {
            $availableTypes[] = ['name' => $name, 'specs' => $containers[$name]];
        }
        
        $counters = [];
        
        foreach ($loads as $load) {
            $placed = false;
            
            // VEHICLE TYPE SELECTION: Choose optimal vehicle type based on load profile
            $optimalTypes = $this->selectOptimalVehicleTypes($load, $availableTypes);
            
            // Try optimal vehicle types in smart order
            foreach ($optimalTypes as $type) {
                $typeName = $type['name'];
                $specs = $type['specs'];
                
                // First try existing vehicles of this type
                foreach ($vehicles as $vehicle) {
                    if ($vehicle->typeName !== $typeName) {
                        continue;
                    }
                    
                    $placement = $vehicle->tryPut($load);
                    if ($placement) {
                        $placed = true;
                        break;
                    }
                }
                
                if ($placed) {
                    break;
                }
                
                // Check if load can physically fit in this vehicle type
                $canFit = false;
                foreach ($load->getRotations() as $rotation) {
                    list($L, $W, $H) = $rotation;
                    if ($L <= $specs['iL'] && $W <= $specs['iW'] && $H <= $specs['iH']) {
                        $canFit = true;
                        break;
                    }
                }
                
                if (!$canFit) {
                    continue;
                }
                
                // Create new vehicle and try to place load
                if (!isset($counters[$typeName])) {
                    $counters[$typeName] = 0;
                }
                $counters[$typeName]++;
                
                $newVehicle = new Vehicle(
                    $typeName . ' #' . $counters[$typeName],
                    $typeName,
                    $specs['iL'], $specs['iW'], $specs['iH'],
                    $specs['max_kg']
                );
                
                $placement = $newVehicle->tryPut($load);
                if ($placement) {
                    $vehicles[] = $newVehicle;
                    $placed = true;
                    break;
                }
            }
            
            // If couldn't place anywhere, add to unplaced
            if (!$placed) {
                $unplaced[] = $load;
            }
        }
        
        // SIMPLE OPTIMIZATION: Remove empty or nearly empty vehicles
        $vehicles = $this->removeEmptyVehicles($vehicles);
        
        return [
            'vehicles' => $vehicles,
            'unplaced' => $unplaced
        ];
    }
    
    /**
     * SIMPLIFIED: Basic load sorting for maximum placement success
     */
    public function sortLoads($loads) {
        usort($loads, function($a, $b) {
            // Simply sort by volume - larger items first for easier placement
            return $b->vol - $a->vol;
        });
        
        return $loads;
    }

    /**
     * SAFE OPTIMIZATION: Remove completely empty vehicles
     * Simple but effective way to reduce vehicle count without complex bugs
     */
    private function removeEmptyVehicles($vehicles) {
        $cleanedVehicles = [];
        
        foreach ($vehicles as $vehicle) {
            $hasLoads = false;
            
            foreach ($vehicle->shelves as $shelf) {
                if (count($shelf->places) > 0) {
                    $hasLoads = true;
                    break;
                }
            }
            
            if ($hasLoads) {
                $cleanedVehicles[] = $vehicle;
            }
        }
        
        // Re-label vehicles
        $typeCounters = [];
        foreach ($cleanedVehicles as $vehicle) {
            if (!isset($typeCounters[$vehicle->typeName])) {
                $typeCounters[$vehicle->typeName] = 0;
            }
            $typeCounters[$vehicle->typeName]++;
            $vehicle->label = $vehicle->typeName . ' #' . $typeCounters[$vehicle->typeName];
        }
        
        return $cleanedVehicles;
    }

    /**
     * GROUP LOADS by similar characteristics for optimal placement
     * Human planners naturally group similar items - this mimics that strategy
     */
    private function groupLoadsByCharacteristics($loads) {
        $groups = [];
        
        foreach ($loads as $load) {
            // Create grouping key based on multiple characteristics
            $groupKey = $this->generateGroupKey($load);
            
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'loads' => [],
                    'characteristics' => $this->analyzeGroupCharacteristics($load),
                    'priority' => $load->priority
                ];
            }
            
            $groups[$groupKey]['loads'][] = $load;
        }
        
        return $groups;
    }

    /**
     * Generate a grouping key based on load characteristics
     */
    private function generateGroupKey($load) {
        // Size categories (Small, Medium, Large, XLarge)
        $volume = $load->vol;
        if ($volume < 50000000) { // < 0.05 m³
            $sizeCategory = 'S';
        } elseif ($volume < 200000000) { // < 0.2 m³
            $sizeCategory = 'M';
        } elseif ($volume < 1000000000) { // < 1 m³
            $sizeCategory = 'L';
        } else {
            $sizeCategory = 'XL';
        }
        
        // Weight categories (Light, Medium, Heavy)
        $weight = $load->kg;
        if ($weight < 50) {
            $weightCategory = 'Light';
        } elseif ($weight < 200) {
            $weightCategory = 'Medium';
        } else {
            $weightCategory = 'Heavy';
        }
        
        // Density categories (Low, Medium, High)
        $density = $load->density;
        if ($density < 200) {
            $densityCategory = 'Low';
        } elseif ($density < 800) {
            $densityCategory = 'Med';
        } else {
            $densityCategory = 'High';
        }
        
        // Dimension ratio category (Long, Square, Tall)
        $maxDim = max($load->L, $load->W, $load->H);
        $minDim = min($load->L, $load->W, $load->H);
        $ratio = $maxDim / max($minDim, 1);
        
        if ($ratio > 4) {
            $shapeCategory = 'Long';
        } elseif ($ratio > 2) {
            $shapeCategory = 'Rect';
        } else {
            $shapeCategory = 'Square';
        }
        
        // Stackability
        $stackable = $load->stack ? 'Stack' : 'NoStack';
        
        // Priority
        $priority = 'P' . $load->priority;
        
        // Combine all characteristics into a group key
        return $sizeCategory . '_' . $weightCategory . '_' . $densityCategory . '_' . 
               $shapeCategory . '_' . $stackable . '_' . $priority;
    }

    /**
     * Analyze group characteristics for vehicle selection
     */
    private function analyzeGroupCharacteristics($load) {
        return [
            'avgVolume' => $load->vol,
            'avgWeight' => $load->kg,
            'avgDensity' => $load->density,
            'maxLength' => max($load->L, $load->W, $load->H),
            'maxWidth' => max($load->L, $load->W),
            'maxHeight' => $load->H,
            'stackable' => $load->stack,
            'priority' => $load->priority,
            'fragile' => $load->fragile
        ];
    }

    /**
     * SMART SORTING: Sort grouped loads for optimal processing order
     * Process high priority, heavy, non-stackable items first
     */
    private function sortGroupedLoads($groups) {
        // Sort groups by strategic importance
        uasort($groups, function($a, $b) {
            // Priority comparison (lower number = higher priority)
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            
            // Non-stackable items first (harder to place)
            $aStackable = $a['characteristics']['stackable'] ? 1 : 0;
            $bStackable = $b['characteristics']['stackable'] ? 1 : 0;
            if ($aStackable != $bStackable) {
                return $aStackable - $bStackable;
            }
            
            // Heavy items first (harder to place)
            if ($a['characteristics']['avgWeight'] != $b['characteristics']['avgWeight']) {
                return $b['characteristics']['avgWeight'] - $a['characteristics']['avgWeight'];
            }
            
            // Large items first (harder to place)
            return $b['characteristics']['avgVolume'] - $a['characteristics']['avgVolume'];
        });
        
        // Flatten groups back to load array, but keep loads within groups together
        $sortedLoads = [];
        foreach ($groups as $group) {
            // Sort loads within each group by volume (largest first)
            usort($group['loads'], function($a, $b) {
                return $b->vol - $a->vol;
            });
            
            $sortedLoads = array_merge($sortedLoads, $group['loads']);
        }
        
        return $sortedLoads;
    }

    /**
     * VEHICLE TYPE SELECTION: Choose optimal vehicle types based on load characteristics
     * Human planners consider load profile when selecting vehicle type
     */
    private function selectOptimalVehicleTypes($load, $availableTypes) {
        $scoredTypes = [];
        
        foreach ($availableTypes as $type) {
            $score = $this->scoreVehicleTypeForLoad($load, $type);
            $scoredTypes[] = [
                'type' => $type,
                'score' => $score
            ];
        }
        
        // Sort by score (higher score = better fit)
        usort($scoredTypes, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Return vehicle types in order of fitness
        $optimalTypes = [];
        foreach ($scoredTypes as $scoredType) {
            $optimalTypes[] = $scoredType['type'];
        }
        
        return $optimalTypes;
    }

    /**
     * Score vehicle type suitability for a specific load
     * Higher score = better fit for this load type
     */
    private function scoreVehicleTypeForLoad($load, $vehicleType) {
        $typeName = $vehicleType['name'];
        $specs = $vehicleType['specs'];
        $score = 0;
        
        // Calculate load characteristics
        $volume = $load->vol;
        $weight = $load->kg;
        $density = $load->density;
        $maxDim = max($load->L, $load->W, $load->H);
        $minDim = min($load->L, $load->W, $load->H);
        $aspectRatio = $maxDim / max($minDim, 1);
        
        // STANDART TIR (Enclosed trailer) scoring
        if ($typeName === 'Standart Tır') {
            $score += 50; // Base score
            
            // Good for stackable loads (protection)
            if ($load->stack) {
                $score += 20;
            }
            
            // Good for fragile items
            if ($load->fragile) {
                $score += 15;
            }
            
            // Good for high priority items (security)
            if ($load->priority <= 2) {
                $score += 10;
            }
            
            // Good for medium density items
            if ($density >= 200 && $density <= 800) {
                $score += 10;
            }
            
            // Penalty for very heavy items (loading difficulty)
            if ($weight > 1000) {
                $score -= 15;
            }
            
            // Penalty for very long items
            if ($aspectRatio > 5) {
                $score -= 10;
            }
        }
        
        // FLATBED scoring
        if ($typeName === 'Flatbed') {
            $score += 60; // Base score (versatile)
            
            // Excellent for heavy items
            if ($weight > 500) {
                $score += 25;
            }
            
            // Good for long items
            if ($aspectRatio > 3) {
                $score += 20;
            }
            
            // Good for high density items
            if ($density > 800) {
                $score += 15;
            }
            
            // Good for non-stackable items
            if (!$load->stack) {
                $score += 15;
            }
            
            // Penalty for fragile items
            if ($load->fragile) {
                $score -= 20;
            }
            
            // Slight penalty for very light items (underutilization)
            if ($weight < 100) {
                $score -= 5;
            }
        }
        
        // LOWBED scoring
        if ($typeName === 'Lowbed') {
            $score += 40; // Base score (specialized)
            
            // Excellent for very heavy items
            if ($weight > 1000) {
                $score += 30;
            }
            
            // Excellent for very long items
            if ($aspectRatio > 6) {
                $score += 25;
            }
            
            // Good for very high density items
            if ($density > 1000) {
                $score += 20;
            }
            
            // Good for tall items (low deck height)
            if ($load->H > 2000) {
                $score += 15;
            }
            
            // Good for machinery/equipment (high priority, heavy, non-stackable)
            if ($load->priority <= 2 && $weight > 500 && !$load->stack) {
                $score += 15;
            }
            
            // Penalty for light items (wasted capacity)
            if ($weight < 200) {
                $score -= 25;
            }
            
            // Penalty for small items
            if ($volume < 100000000) { // < 0.1 m³
                $score -= 15;
            }
        }
        
        // Dimension fit bonus (can the load actually fit?)
        $canFit = false;
        foreach ($load->getRotations() as $rotation) {
            list($L, $W, $H) = $rotation;
            if ($L <= $specs['iL'] && $W <= $specs['iW'] && $H <= $specs['iH']) {
                $canFit = true;
                break;
            }
        }
        
        if (!$canFit) {
            $score = -1000; // Cannot fit - eliminate this vehicle type
        } else {
            // Bonus for good dimension utilization
            $dimUtilization = min(
                $load->L / $specs['iL'],
                $load->W / $specs['iW'], 
                $load->H / $specs['iH']
            );
            $score += $dimUtilization * 10;
        }
        
        // Weight capacity check
        if ($weight > $specs['max_kg']) {
            $score = -1000; // Over weight limit - eliminate
        } else {
            // Bonus for good weight utilization
            $weightUtilization = $weight / $specs['max_kg'];
            $score += $weightUtilization * 5;
        }
        
        return $score;
    }
}