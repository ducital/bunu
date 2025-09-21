<?php

class Load {
    public $no;
    public $loadName;
    public $L;  // Length in mm
    public $W;  // Width in mm  
    public $H;  // Height in mm
    public $kg; // Weight in kg
    public $stack;
    public $vol;
    public $density; // kg/m³
    public $fragile; // Whether item is fragile
    public $priority; // Loading priority (1=high, 5=low)

    public function __construct($no, $loadName, $L, $W, $H, $kg, $stack) {
        $this->no = $no;
        $this->loadName = $loadName;
        $this->L = intval($L);
        $this->W = intval($W);
        $this->H = intval($H);
        $this->kg = floatval($kg);
        $this->stack = boolval($stack);
        $this->vol = $this->L * $this->W * $this->H;
        $this->density = $this->vol > 0 ? ($this->kg / ($this->vol / 1000000000)) : 0; // kg/m³
        $this->fragile = false;
        $this->priority = 3; // Default medium priority
    }

    /**
     * Get all possible rotations of this load
     * Returns array of [L, W, H] combinations
     */
    public function getRotations() {
        $rotations = [
            [$this->L, $this->W, $this->H],
            [$this->L, $this->H, $this->W],
            [$this->W, $this->L, $this->H],
            [$this->W, $this->H, $this->L],
            [$this->H, $this->L, $this->W],
            [$this->H, $this->W, $this->L]
        ];
        
        // Remove duplicates
        $unique = [];
        foreach ($rotations as $rot) {
            $key = implode('x', $rot);
            $unique[$key] = $rot;
        }
        
        return array_values($unique);
    }

    /**
     * Get preferred rotations based on stability and load characteristics
     */
    public function getPreferredRotations() {
        $rotations = $this->getRotations();
        
        // Sort rotations by preference:
        // 1. Lowest height first (more stable)
        // 2. Largest base area (more stable)
        // 3. Original orientation has slight preference
        usort($rotations, function($a, $b) {
            // Height comparison (lower is better)
            if ($a[2] != $b[2]) {
                return $a[2] - $b[2];
            }
            
            // Base area comparison (larger is better)
            $areaA = $a[0] * $a[1];
            $areaB = $b[0] * $b[1];
            if ($areaA != $areaB) {
                return $areaB - $areaA;
            }
            
            // Prefer original orientation
            $originalKey = $this->L . 'x' . $this->W . 'x' . $this->H;
            $aKey = implode('x', $a);
            $bKey = implode('x', $b);
            
            if ($aKey === $originalKey) return -1;
            if ($bKey === $originalKey) return 1;
            
            return 0;
        });
        
        return $rotations;
    }

    /**
     * Check if this load can support weight on top
     */
    public function canSupport($weightOnTop) {
        if (!$this->stack) {
            return false;
        }
        
        // Basic structural capacity based on density and base area
        $baseArea = $this->L * $this->W; // mm²
        $maxSupportKg = ($baseArea / 1000000) * 1000; // Rough estimate: 1000kg/m²
        
        return $weightOnTop <= $maxSupportKg;
    }
}