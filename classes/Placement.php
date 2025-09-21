<?php

class Placement {
    public $loadNo;
    public $loadName;
    public $L;  // Placed dimensions
    public $W;
    public $H;
    public $kg;
    public $x;  // Position coordinates
    public $y;
    public $z;
    public $rotation; // [L, W, H] of the placed rotation

    public function __construct($loadNo, $loadName, $L, $W, $H, $kg, $x, $y, $z, $rotation) {
        $this->loadNo = $loadNo;
        $this->loadName = $loadName;
        $this->L = $L;
        $this->W = $W;
        $this->H = $H;
        $this->kg = $kg;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->rotation = $rotation;
    }
}