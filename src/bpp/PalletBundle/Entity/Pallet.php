<?php

namespace bpp\PalletBundle\Entity;

class Pallet
{
    protected $pallet, $threed;
    // This is a very small app: so if variable are public, dont need a get/setter :-)
    public $layout, $rollwidth_mm, $diam_mm, $rows, $plength_mm, $pwidth_mm;
    public $maxLoadingHeight, $maxLoadingWeight, $rollkgs;

    // Pallet canvas size on the screen
    //$pwidth=120;  // pallet width/dept in pixels
    //$plength=100; // down
    public $pwidth=360;  // need better reel icon before using bigger sizes
    public $plength=300; // down

    public function getLayout()
    {
        return $this->layout;
    }
    public function setLayout($layout)
    {
        $this->layout = $layout;
    }
    public function getThreed()
    {
        return $this->threed;
    }
    public function setThreed($threed)
    {
        $this->threed = $threed;
    }
}