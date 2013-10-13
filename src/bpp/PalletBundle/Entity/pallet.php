<?php

namespace bpp\PalletBundle\Entity;

class Pallet
{
    protected $pallet, $threed;
    //if variable are public, dont need a get/setter :-)
    public $layout, $rollwidth_mm, $diam_mm, $rows;

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