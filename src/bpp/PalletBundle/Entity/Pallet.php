<?php

/**
 * The pallet ojects cantains the spec of reels/pallets, and a method to generate
 * an image with layout according to those specs.
 */
namespace bpp\PalletBundle\Entity;
use Symfony\Component\HttpFoundation\Request;

class Pallet
{
    protected $pallet, $logger, $flash;
    protected $dir, $outdir, $outdirweb;
    public $debug_flag1=TRUE;
    public $debug_to_syslog=FALSE;
    // This is a very small app: so if variable are public, dont need a get/setter :-)
    public $layout, $rollwidth_mm, $diam_mm, $rows, $plength_mm, $pwidth_mm;
    public $maxLoadingHeight, $maxLoadingWeight, $rollkgs, $threed;
    // results
    public $image_path, $rollsperpallet=0, $palletheight=0, $kgsperpallet=0;
    public $weightexceeded, $heightexceeded;

    // Pallet canvas size on the screen
    //$pwidth=120;  // pallet width/dept in pixels
    //$plength=100; // down
    public $pwidth=360;  // need better reel icon before using bigger sizes
    public $plength=300; // down


    public function debug1($msg)
    {
        $msg=rtrim($msg);
        if (($this->debug_flag1==TRUE) && (strlen($msg)>0) ) {
            $this->logger->info('Debug1 ' . $msg);
            #echo "Debug1: $msg\n<br>";
        }
        //$this->get('session')->getFlashBag()->add(
        //$kernel->getContainer()->get('session')->getFlashBag()->add(
        $this->flash->add(
            'notice', 'flash=' . $msg
        );
    }

    /*
     * param $outdir: URL patch to output image /pallet/web/out : must get from request
     */
    public function __construct()
    {
        global $kernel;      // hack to get at the logger, request
        $container=$kernel->getContainer();
        $this->logger = $container->get('logger');
        $this->flash  = $container->get('session')->getFlashBag();

        // Directories where our script in, where output is stored.
        //$dir = $this->get('kernel')->getRootDir() . '/../web';
        //$this->outdirweb = dirname($_SERVER['REQUEST_URI']) . '/out/';
        $this->dir=$kernel->getRootDir() . '/../web';
        $this->outdir = $this->dir . '/out';
        $this->outdirweb = $container->get('request')->getBasePath() . '/out/';  // /pallet/web/out
        //$this->debug1('__FILE__=' . dirname(__FILE__) . ', REQUEST_URI=' . dirname($_SERVER['REQUEST_URI'])
        //    . ", DOCUMENT_ROOT=" . $container->get('request')->server->get('DOCUMENT_ROOT')
        //    . ", getBasePath="  .$container->get('request')->getBasePath()
        //);

        if (!is_writable($this->outdir)) {
            $this->debug1("$this->outdir does not exist or is not writeable, lets try to create it");
            if (!@mkdir($this->outdir, 700, true)) {
                die('Cannot create output directory: ' . $this->outdir . '. <br>Make sure this exists and belongs to the webserver user, e.g. www-data');
            }
        }

        // default values for the new object
        $this->setLayout('versq');
        $this->setThreed('0');
        $this->rollwidth_mm=300;
        $this->diam_mm=300;
        $this->rows=1;
        $this->plength_mm=1000;
        $this->pwidth_mm=1200;
        $this->maxLoadingHeight=1500;
        $this->maxLoadingWeight=800;
        $this->rollkgs=0;
        $this->threed=0;
    }
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

    /**
     * @param $outdir, $outdirweb
     * Create the pallet image, based on the pallet spec
     * store image location in $image_path
     */
    public function makePallet()
    {
        $layout=$this->layout;
        $rollwidth_mm=$this->rollwidth_mm;
        $diam_mm=$this->diam_mm;
        $rows=$this->rows;
        $pwidth_mm=$this->pwidth_mm;
        $plength_mm=$this->plength_mm;
        $rollkgs=$this->rollkgs;
        $pwidth=$this->pwidth;
        $plength=$this->plength;
        $threed=$this->threed;   //  enable 3D

        // -- outputs --
        $this->rollsperpallet=0;
        $this->palletheight=0;
        $f='output.jpg';     // @todo: parameter or "download"
        //$image_ver='/reelv.jpg';   // reelv2.png
        //$image_hor='/reelh.jpg';
        //$heightwarning='';

        // -- Pallet: calculate scaling
        $p2=$pwidth_mm;
        $p1=$plength_mm;
        $pscaley=$pwidth_mm/$pwidth;  // ratio of pixes to mm
        $pscalex=$plength_mm/$plength;  // ratio of pixes to mm
        $this->debug1("rollwidth=$rollwidth_mm diam=$diam_mm rows=$rows mm/pixel: pscalex=$pscalex pscaley=$pscaley
        pallet mm: $pwidth_mm x $plength_mm");

        if ($layout=='versq' || $layout=='verint') {  // vertical
            $diam=floor($diam_mm/$pscalex);
            $rollwidth=floor($rollwidth_mm/$pscaley);
        } else {
            // horizontal: scaling is opposite
            $diam=floor($diam_mm/$pscaley);
            $rollwidth=floor($rollwidth_mm/$pscalex);
        }
        $radius=$diam/2;
        $radius_mm=$diam_mm/2;
        $CompressedDiameter=floor(sqrt(3*$radius*$radius));
        $CompressedDiameter_mm=floor(sqrt(3*$radius_mm*$radius_mm));
        $str="Layout=$layout: px rollwidth=$rollwidth diam=$diam radius=$radius CompressedDiameter=$CompressedDiameter pallet:$pwidth X $plength";
        //$bpp::PalletBundle::Controller::DefaultController::DefaultController->debug1($str);

        // -- draw pallet base --
        //$pallet = new Imagick($dir . '/pallet.png');
        //$palletprops = $pallet->getImageGeometry();
        // canvas: is 2D pallet: add enough space for 3d overhang
        try
        {
            // create new canvas for pallet + stacked rolls
            // note how '/' is prefixed for thsi global library
            $result = new \Imagick();

            $result->newImage($plength+3*$radius, $pwidth+3*$radius, 'white');
            // add picture: $result->compositeImage($pallet, \imagick::COMPOSITE_OVER, 0, $palletyoffset);
            $rect = new \ImagickDraw();    // the wooden part of the pallet
            $rect->setStrokeColor('SaddleBrown');
            $rect->setStrokeWidth(1);
            $rect->setFillColor('burlywood');
            // simple square pallet
            //$rect->rectangle(0,0, $plength+2, $pwidth+4);

            // nicer: create planks in both directions: 3 horiz.
            $rect->rectangle(0,0,                  $plength+2, $pwidth/7+4);
            $rect->rectangle(0,$pwidth-$pwidth/1.7,  $plength+2, $pwidth-$pwidth/1.7+$pwidth/7+4);
            $rect->rectangle(0,$pwidth-$pwidth/7,  $plength+2, $pwidth+4);

            $rect->setFillColor('burlywood1');    // dark planks, leave edges offset for realism
            $rect->rectangle(4           ,2, 1*$plength/7, $pwidth+2);
            $rect->rectangle(2*$plength/7,2, 3*$plength/7, $pwidth+2);
            $rect->rectangle(4*$plength/7,2, 5*$plength/7, $pwidth+2);
            $rect->rectangle(6*$plength/7,2, 7*$plength/7-2, $pwidth+2);
            //$rect->setStrokeWidth(3);
            //$rect->setStrokeColor('brown');
            //$rect->line(0,0, 0, $pwidth+2);
            //$rect->line(0,$pwidth+2, $plength+2, $pwidth+2);
            $result->drawImage($rect);
        }
        catch(Exception $e)
        {
            die('Error Imagick: ' . $e->getMessage() );
        }
        //$this->debug1('OK: empty pallet image created');


        // ---------- draw each reel ----
        //$reel   = new Imagick($dir . $image_ver);
        //$reel->scaleImage($diam, $rollwidth);  // Scale reel accoring to diameter and width
        //$reel->resizeImage($diam, $rollwidth);  // looks better than scaling
        if ($layout=='versq' || $layout=='verint') {
            if ($threed==1) {
                //$offset3d=$radius*0.5;
                $offset3d=$radius*0.2;
                $circle = new \ImagickDraw();
                $circle->setStrokeColor('black');
                $circle->setFillColor('snow1');
                $circle->ellipse($radius,$radius, $radius,$radius, 0,360); // Roll bottom originXY radiusXY,
                $circle->setFillColor('white');
                $circle->ellipse($radius+$offset3d,$radius+$offset3d, $radius,$radius, 0,360); // Roll top originXY radiusXY,
                $circle->setStrokeWidth(1);
                $coreradius= 96/2/$pscalex; // defsult core size 96mm in px
                $core = new \ImagickDraw();
                $core->setFillColor('darkgrey');
                $core->setStrokeColor('black');
                $core->setStrokeWidth(1);
                //$core->circle($radius+$offset3d, $radius+$offset3d, $radius, $radius+$coreradius); //
                $core->circle($radius+$offset3d, $radius+$offset3d, $radius+$offset3d, $radius+$offset3d+$coreradius); //
                //$core->ellipse($radius+$offset3d, $radius+$offset3d, $radius, $radius+$coreradius, 0,360); //
                $reel   = new \Imagick();
                $reel->newImage($diam+$offset3d, $diam+$offset3d, new \ImagickPixel( 'none' ) );
                $reel->setImageOpacity(0.07);  // allow row layers to be seen a bit

            } else {
                $circle = new \ImagickDraw();
                $circle->setFillColor('white');
                //$circle->setFillColor('lightgrey');
                $circle->setStrokeColor('grey');
                //$circle->circle($radius, $radius, $radius, $diam-1); //
                $circle->ellipse($radius,$radius, $radius,$radius, 0,360); //
                $circle->setStrokeColor('black');
                $circle->setStrokeWidth(1);
                $coreradius= 96/2/$pscalex; // defsult core size 96mm in px
                $core = new \ImagickDraw();
                $core->setFillColor('darkgrey');
                $core->circle($radius, $radius, $radius, $radius+$coreradius); //
                $reel   = new \Imagick();
                $reel->newImage($diam, $diam, new \ImagickPixel( 'none' ) );
                $reel->setImageOpacity(0.1);  // allow space free on pallet to be hidden
            }

            $reel->drawImage($circle);
            $reel->drawImage($core);
            // TODO: stroke colour + size for core and outer edge

        } else if ($layout=='horsq' || $layout=='horint' || $layout=='horpyr') {
            // three ellipses, rectangle
            $margin=2;
            $circle = new \ImagickDraw();
            $circle->setStrokeColor('darkgrey');
            $circle->setFillColor('lightgrey');
            $circle->ellipse($rollwidth+$radius/3-$margin,$radius, $radius/3,$radius, 0,360); // Roll bottom originXY radiusXY,
            $circle->setStrokeColor('none');
            $circle->setStrokeWidth(1);
            $circle->rectangle($radius/3,0, $rollwidth+$radius/3,$diam);
            $circle->setStrokeWidth(2);
            $circle->setStrokeColor('darkgrey');
            $circle->line($radius/3,$diam, $rollwidth+$radius/3,$diam); // bottom line

            $circle->setFillColor('silver');
            $circle->ellipse($radius/3,$radius, $radius/3,$radius, 0,360); // reel top (on the left)
            $circle->line($radius/3,0, $rollwidth-$radius/3,0); // reel top (on the left)
            // core
            $circle->setStrokeColor('black');
            $circle->setFillColor('darkgrey');
            $coreradius= 96/2/$pscalex; // defsult core size 96mm in px
            $circle->ellipse($radius/3,$radius, $radius/8,$coreradius, 0,360); // core

            $reel   = new \Imagick();   // square canvas to put above reel on
            $reel->newImage($rollwidth+$radius/3*2, $diam, new \ImagickPixel( 'none' ) );
            $reel->setImageOpacity(0.01);  // allow space free on pallet to be visible
            $reel->drawImage($circle);

        } else {
            $offset3d=7;
            $circle = new \ImagickDraw();
            $circle->setStrokeColor('black');
            $circle->setFillColor('gray');
            $circle->ellipse($radius,$radius, $radius,$radius, 0,360); // Roll bottom originXY radiusXY,
            $circle->setFillColor('lightgray');
            //$circle->circle($radius+10, $radius+10, $radius+10, $diam-1); //
            $circle->ellipse($radius+$offset3d,$radius+$offset3d, $radius,$radius, 0,360); // Roll bottom originXY radiusXY,
            //$circle->ellipse($radius,$radius, $radius,$radius, 0,360); //
            //$circle->circle($radius-10, $radius-10, $radius-20, $diam-20); //
            $circle->setStrokeWidth(1);
            $coreradius= 96/2/$pscalex; // defsult core size 96mm in px
            $core = new \ImagickDraw();
            $core->setFillColor('darkgrey');
            $core->circle($radius+$offset3d, $radius+$offset3d, $radius, $radius+$coreradius); //

            $reel   = new \Imagick();
            $reel->newImage($diam, $diam, new \ImagickPixel( 'none' ) );
            $reel->setImageOpacity(0.1);  // allow space free on pallet to be visible
            $reel->drawImage($circle);
            $reel->drawImage($core);
            //$reel->rotateImage('none', 90);
        }
        //$this->debug1('OK: reel created');

        // Origin
        #$x=0; $y=$palletyoffset+15;
        #$x=$pwidth*.90; $y=$palletyoffset-40;
        $x=2; $y=2;
        #$result->compositeImage($reel, \imagick::COMPOSITE_OVER, $x,$y);
        //$rowoffset=$p1/200;
        $rowoffset=7;

        /* ------ layout the reels ----------*/
        if ($layout == 'versq') {     // -- vertical square --
            $across=floor($p1/$diam_mm);
            $up=floor($p2/$diam_mm);   // round() if we want to allow an overhang
            $nrollsperrow=$across * $up;   // nr rolls per row
            $this->rollsperpallet=$nrollsperrow*$rows;
            $this->palletheight=$rollwidth_mm*$rows;
            $this->debug1("vertical square: nrollsperrow=$nrollsperrow across=$across up=$up rollsperpallet=$this->rollsperpallet palletheight=$this->palletheight");

            if ($threed==1) {
                // Display from bottom-right to top left
                #$rowoffset=$rollwidth_mm/$pscalex/3;  // how much to offset each row
                //$rowoffset=$radius*0.8;
                $rowoffset=$radius*0.5;
                //$rowoffset=0;
                for ($row = 0; $row < $rows; $row++) {
                    for ($j = $up; $j >0; $j--) {
                        for ($i = $across; $i >0; $i--) {
                            //debug1("$i $j $row : ");
                            $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                                //($i)*$diam-$radius -($rows-$row-2)*$rowoffset, $j*$diam-$diam +$row*$rowoffset);
                                ($i)*$diam-$radius +$row*$rowoffset -5, $j*$diam-$diam +$row*$rowoffset);
                            // TODO: improve
                        }
                    }
                }

            } else {  // threed
                $rowoffset=$radius*0.3;
                for ($row = 0; $row < $rows; $row++) {
                    for ($j = 0; $j < $up; $j++) {
                        for ($i = 0; $i < $across; $i++) {
                            $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset + $i*$diam, $y  +$j*$diam);
                        }
                    }
                }
            }


        } else if ($layout == 'verint') {  // -- vertical interlinked --
            if ($threed==1) {
                $rowoffset=$radius*0.25;
            } else {  // threed
                $rowoffset=$radius*0.25;
                //$rowoffset=$rollwidth_mm/$pscalex/3;  // how much to offset each row
            }
            $across=floor($p1/$diam_mm);
            $up=1 +floor(($p2-$diam_mm)/$CompressedDiameter_mm);
            if ( ($p1-$diam_mm*$across) >= $radius_mm) {
                $nrollsperrow=$across * $up;
            } else {
                $nrollsperrow=$across * $up  - floor($up/2);
            }
            $this->rollsperpallet=0;
            $this->palletheight=$rollwidth_mm*$rows;
            // TODO: draw from bottom right
            for ($row = 0; $row < $rows; $row++) {
                for ($j = 0; $j < $up; $j++) {
                    for ($i = 0; $i < $across; $i++) {
                        // calculate X and Y Left and Top
                        if ($j==0) {   // first row is easy
                            $top=0;
                        } else {
                            $fuzz=$radius/4; // spacing 1/2nd row: idont know why this is needed
                            $top=$diam // first row height
                                +floor(($j-1)*$CompressedDiameter) -$fuzz;
                        }
                        if (($j % 2)==0) { // even rows
                            $left= $i*$diam;
                        } else {
                            $left= $i*$diam +floor($radius);
                        }

                        if ( (($j % 2)!=0)    // odd rows
                            && ($i==$across-1) // last roll on the right of this row
                            && ( ($p1-$diam_mm*$across)< $radius_mm) ) {
                            // do not create a roll on the right, not rnough space
                        } else {        // add reel image
                            $this->rollsperpallet++;
                            $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset +$left, $y +$row*$rowoffset +$top);
                        }
                    }
                }
            }
            $this->debug1("vertical interlinked: nrollsperrow=$nrollsperrow across=$across up=$up rollsperpallet=$this->rollsperpallet palletheight=$this->palletheight");

        } else if ($layout == 'horsq') {     // -- horiz. square --
            $rowoffset=$radius*0.75;
            $across=1;
            $up=floor($p2/$diam_mm);
            $nrollsperrow=$across * $up;   // nr rolls per row
            $this->rollsperpallet=$nrollsperrow*$rows;
            $this->palletheight=$diam_mm*$rows;
            $this->debug1("horizontal square: nrollsperrow=$nrollsperrow across=$across up=$up rollsperpallet=$this->rollsperpallet palletheight=$this->alletheight");
            for ($row = 0; $row < $rows; $row++) {
                for ($j = 0; $j < $up; $j++) {
                    $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                        $x+ $row*$rowoffset , $y  +$j*$diam);
                }
            }

        } else if ($layout == 'horint') {     // -- horiz. interlink --
            $rowoffset=$radius*0.65;
            $across=1;
            $up=floor($p2/$diam_mm);
            $this->rollsperpallet=0;
            $this->palletheight=$diam_mm+ $CompressedDiameter_mm*($rows-1);
            for ($row = 0; $row < $rows; $row++) {
                for ($j = 0; $j < $up; $j++) {

                    // calculate X and Y Left and Top
                    if (($row % 2)==0) {    // even rows
                        $top=$diam*$j;
                    } else {
                        $top=$diam*$j +$radius;
                    }
                    //echo "row=$row j=$j top=$top <br>";
                    if ( (($row % 2)!=0)    // odd rows
                        && ($j==$up-1) // last roll on the right of this row
                        && ( ($p2-$diam_mm*$up)< $radius_mm) ) {
                        // do not create a roll on the bottom, not enough space
                    } else {        // add reel image
                        $this->rollsperpallet++;
                        $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                            $x+ $row*$rowoffset , $y +$top);
                    }
                }
            }
            $this->debug1("horizontal interlinked: across=$across up=$up rollsperpallet=$this->rollsperpallet palletheight=$this->palletheight");

        } else if ($layout == 'horpyr') {     // -- horiz. pyramid --
            $rowoffset=$radius*0.65;
            $across=1;
            $up=floor($p2/$diam_mm);
            $this->rollsperpallet=0;
            $this->palletheight=$diam_mm+ $CompressedDiameter_mm*($rows-1);
            for ($row = 0; $row < $rows; $row++) {
                for ($j = 0; $j < $up; $j++) {

                    if (($row % 2)==0) {    // even rows
                        $top=$diam*$j;
                        if ( ($row>2*$j+1)
                            || ($j>$up-$row/2-1) ) {
                            //echo "even row=$row j=$j top=$top SKIP $up " . $row/2 . "<br>";;
                            // hide reel on pyramid edge
                        } else {
                            //echo "even row=$row j=$j top=$top <br>";
                            $this->rollsperpallet++;
                            $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset , $y +$top);
                        }
                    } else {
                        $top=$diam*$j +$radius;
                        if ( ($row>2*$j+1) || ($j>$up-$row/2-1) ) {
                            // hide reel on pyramid edge
                        } else {
                            //echo "odd row=$row j=$j top=$top <br>";
                            $this->rollsperpallet++;
                            $result->compositeImage($reel, \imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset , $y +$top);
                        }

                    }
                }
            }
            $this->debug1("horizontal pyramid: across=$across up=$up rollsperpallet=$this->rollsperpallet palletheight=$this->palletheight");

        } else {  // testing, draw one reel
            //$reel->rotateImage('none', 90);
            $result->compositeImage($reel, \imagick::COMPOSITE_OVER, 1, 1);
            //$result->rotateImage('none', 33);
        }

        if ($this->palletheight > $this->maxLoadingHeight) {
            $this->heightexceeded="Max loading height $this->maxLoadingHeight mm exceeded";
            $this->debug1($this->heightexceeded);
        }
        $this->kgsperpallet = $this->rollsperpallet * $this->rollkgs;  // total weight
        if ($this->kgsperpallet > $this->maxLoadingWeight) {
            $this->weightexceeded="Max weight $this->maxLoadingWeight kgs exceeded";
            $this->debug1($this->weightexceeded);
        }

        // ------------ prepare display ----------------------
        //
        $result->setImageFormat('jpg');
        //$result->rotateImage('white', -45);
        // TODO: or tilt?
        if ($f == 'download') {
            $this->debug1("send pallet.jpg to caller for download");
            //$result->scaleImage(120, 100); // reduce to thumbnail
            ob_clean();                         // Clear buffer
            Header("Content-Description: File Transfer");
            Header("Content-Type: application/force-download");
            header('Content-Type: image/jpeg'); // Send JPEG header
            Header("Content-Disposition: attachment; filename=" . "pallet.jpg");
            $result->writeImage($this->outdir . '/' . $f);  // Write to disk anyway?
            //header('Content-Length: ' . $dir . '/out/' . $f);  // TODO: Calculate image size?
            echo $result;                          // todo: Output to browser

        } else {
            //$result->scaleImage(75, 90); // reduce to thumbnail
            //$result->scaleImage(200, 240); // increase
            try
            {
                $result->writeImage($this->outdir . '/' . $f);       // Write to disk
            }
            catch(Exception $e)
            {
                die('Error Imagick: ' . $e->getMessage() );
            }
            //echo "<img src=$this->outdirweb$f alt='Generated image'>";
            $this->debug1("generated $this->outdirweb$f");
        }
        //echo "<img src=/$d/out/output2.jpg alt='Generated image'>";
        $this->image_path=$this->outdirweb . $f;    // save the resulting link
        $result->destroy();


    }   // makePallet

}