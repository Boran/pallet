<?php

/*
 * Generate pallet image
 * With layout of reels for 5 different scenarios
 * Inputs are provided via web parameters.
 */
namespace bpp\PalletBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use bpp\PalletBundle\Entity\Pallet;

class DefaultController extends Controller
{
    public $debug_flag1=TRUE;
    public $debug_to_syslog=FALSE;

    public function debug1($msg)
    {
        $msg=rtrim($msg);
        if (($this->debug_flag1==TRUE) && (strlen($msg)>0) ) {
            $logger=$this->get('logger');
            $logger->info('Debug1 ' . $msg);
            #echo "Debug1: $msg\n<br>";
        }
        $this->get('session')->getFlashBag()->add(
            'notice', 'flash=' . $msg
        );
    }
    /**
     * @Route("/pallet/{name}")
     * @Template()
     *     will look for index.html.twig automatically
     */
    public function indexAction(Request $request, $name)
    {
        $this->debug1("indexAction $name "  );
        //------ defaults ----------------
        // Pallet canvas size on the screen
        $pwidth=360;  // need better reel icon before using bigger sizes
        $plength=300; // down
        $image_ver='/reelv.jpg';   // reelv2.png
        $image_hor='/reelh.jpg';
        $heightwarning='';


        // @todo: pull values optionally for get/post arguments
        //$this->debug1( $request->query->get('file') );  // get /pallet/foo?file=ss

        // create a Pallet object with default values, and a form
        $pallet = new Pallet();
        $pallet->setLayout('versq');
        $pallet->setThreed('0');
        $pallet->rollwidth_mm=300;
        $pallet->diam_mm=300;
        $pallet->rows=1;
        $pallet->plength_mm=1000;
        $pallet->pwidth_mm=1200;
        $pallet->maxLoadingHeight=1500;
        $pallet->maxLoadingWeight=800;
        $pallet->rollkgs=0;
        $pallet->threed=0;

        $form = $this->createFormBuilder($pallet)
            ->add('layout', 'choice', array(
                    'choices' => array(
                        'versq' =>'Vertical square',
                        'verint'=>'Vertical interleaved',
                        'horint'=>'Horizontal interleaved',
                        'horsq' =>'Horizontal square',
                        'horpyr'=>'Horizontal pyramid'),
                    'required'  => true,
                ))
            ->add('threed', 'text')
            ->add('rollwidth_mm', 'integer')
            ->add('diam_mm', 'integer')
            ->add('rows', 'integer')
            ->add('plength_mm', 'integer')
            ->add('pwidth_mm', 'integer')
            ->add('maxLoadingHeight', 'integer')
            ->add('maxLoadingWeight', 'integer')
            ->add('rollkgs', 'integer')
            ->add('threed', 'choice', array(
                    'choices' => array(
                        '0'=>'No',
                        '1'=>'Yes'),
                    'required' => true,
                ))
            ->add('Calculate', 'submit')
            ->getForm();
        $this->debug1('form built');

        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->makePallet($pallet);
            return new Response("Pallet: submitted layout= " . $pallet->layout
              . ", rollkgs=$pallet->rollkgs" );
        }
        //return array('form' => $form->createView);  // use Twig
        return $this->render('PalletBundle:Default:index.html.twig', array(
            'form' => $form->createView(), ));
//----------------------
        //return array('name' => $name);  // use Twig
        //return new Response("Pallet: $name");
    }

    /**
     * @param $pallet
     * Create the pallet image, based on the pallet spec
     * @todo: send back an image?
     */
    public function makePallet($pallet)
    {
        $layout=$pallet->layout;
        $rollwidth_mm=$pallet->rollwidth_mm;
        $diam_mm=$pallet->diam_mm;
        $rows=$pallet->rows;
        $pwidth_mm=$pallet->pwidth_mm;
        $plength_mm=$pallet->plength_mm;
        $maxLoadingHeight=$pallet->maxLoadingHeight;
        $maxLoadingWeight=$pallet->maxLoadingWeight;
        $rollkgs=$pallet->rollkgs;
        $pwidth=$pallet->pwidth;
        $plength=$pallet->plength;
        $threed=$pallet->threed;   //  enable 3D

        $f='output.jpg';     // @todo: parater or "download"
        $image_ver='/reelv.jpg';   // reelv2.png
        $image_hor='/reelh.jpg';
        $heightwarning='';

        // Directories where our script in, where output is stored.
        //$dir=dirname(__FILE__);
        $dir = $this->get('kernel')->getRootDir() . '/../web';
        $this->debug1('__FILE__=' . dirname(__FILE__) . ', REQUEST_URI=' . dirname($_SERVER['REQUEST_URI'])
          . ", dir=$dir");
        $outdir=$dir . '/out';
        //$outdirweb = dirname($_SERVER['REQUEST_URI']) . '/out/';
        $outdirweb = '/pallet/web/out/';  // @todo FIX!!

        if (!is_writable($outdir)) {
            $this->debug1("$outdir does not exist or is not writeable, lets try to create it");
            if (!@mkdir($outdir, 700, true)) {
                die('Cannot create output directory: ' . $outdir . '. <br>Make sure this exists and belongs to the webserver user, e.g. www-data');
            }
        }


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
        $this->debug1($str);

        // -- pallet base --
        //$pallet = new Imagick($dir . '/pallet.png');
        //$palletprops = $pallet->getImageGeometry();
        // canvas: is 2D pallet: add enough space for 3d overhang
        try
        {
            // create new canvas for pallet + stacked rolls
            // note how '/' is prefixed for thsi global library
            $result = new \Imagick();

            $result->newImage($plength+3*$radius, $pwidth+3*$radius, 'white');
            // add picture: $result->compositeImage($pallet, imagick::COMPOSITE_OVER, 0, $palletyoffset);
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
        $this->debug1('OK: empty pallet image created');


        // ---------- Each reel ----
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
            $core = new ImagickDraw();
            $core->setFillColor('darkgrey');
            $core->circle($radius+$offset3d, $radius+$offset3d, $radius, $radius+$coreradius); //

            $reel   = new \Imagick();
            $reel->newImage($diam, $diam, new \ImagickPixel( 'none' ) );
            $reel->setImageOpacity(0.1);  // allow space free on pallet to be visible
            $reel->drawImage($circle);
            $reel->drawImage($core);
            //$reel->rotateImage('none', 90);
        }
        $this->debug1('OK: reel created');

        // Origin
        #$x=0; $y=$palletyoffset+15;
        #$x=$pwidth*.90; $y=$palletyoffset-40;
         $x=2; $y=2;
        #$result->compositeImage($reel, imagick::COMPOSITE_OVER, $x,$y);
        //$rowoffset=$p1/200;
        $rowoffset=7;

        /* ------------ layout the reels -------------------*/
        if ($layout == 'versq') {     // -- vertical square --
            $across=floor($p1/$diam_mm);
            $up=floor($p2/$diam_mm);   // round() if we want to allow an overhang
            $nrollsperrow=$across * $up;   // nr rolls per row
            $rollsperpallet=$nrollsperrow*$rows;
            $palletheight=$rollwidth_mm*$rows;
            $this->debug1("vertical square: nrollsperrow=$nrollsperrow across=$across up=$up rollsperpallet=$rollsperpallet palletheight=$palletheight");

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
                            $result->compositeImage($reel, imagick::COMPOSITE_OVER,
                                //($i)*$diam-$radius -($rows-$row-2)*$rowoffset, $j*$diam-$diam +$row*$rowoffset);
                                ($i)*$diam-$radius +$row*$rowoffset -5, $j*$diam-$diam +$row*$rowoffset);
                            // TODO
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
            $rollsperpallet=0;
            $palletheight=$rollwidth_mm*$rows;
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
                            $rollsperpallet++;
                            $result->compositeImage($reel, imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset +$left, $y +$row*$rowoffset +$top);
                        }
                    }
                }
            }
            $this->debug1("vertical interlinked: nrollsperrow=$nrollsperrow across=$across up=$up rollsperpallet=$rollsperpallet palletheight=$palletheight");


        } else if ($layout == 'horsq') {     // -- horiz. square --
            $rowoffset=$radius*0.75;
            $across=1;
            $up=floor($p2/$diam_mm);
            $nrollsperrow=$across * $up;   // nr rolls per row
            $rollsperpallet=$nrollsperrow*$rows;
            $palletheight=$diam_mm*$rows;
            $this->debug1("horizontal square: nrollsperrow=$nrollsperrow across=$across up=$up rollsperpallet=$rollsperpallet palletheight=$palletheight");
            for ($row = 0; $row < $rows; $row++) {
                for ($j = 0; $j < $up; $j++) {
                    $result->compositeImage($reel, imagick::COMPOSITE_OVER,
                        $x+ $row*$rowoffset , $y  +$j*$diam);
                }
            }


        } else if ($layout == 'horint') {     // -- horiz. interlink --
            $rowoffset=$radius*0.65;
            $across=1;
            $up=floor($p2/$diam_mm);
            $rollsperpallet=0;
            $palletheight=$diam_mm+ $CompressedDiameter_mm*($rows-1);
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
                        $rollsperpallet++;
                        $result->compositeImage($reel, imagick::COMPOSITE_OVER,
                            $x+ $row*$rowoffset , $y +$top);
                    }
                }
            }
            $this->debug1("horizontal interlinked: across=$across up=$up rollsperpallet=$rollsperpallet palletheight=$palletheight");


        } else if ($layout == 'horpyr') {     // -- horiz. pyramid --
            $rowoffset=$radius*0.65;
            $across=1;
            $up=floor($p2/$diam_mm);
            $rollsperpallet=0;
            $palletheight=$diam_mm+ $CompressedDiameter_mm*($rows-1);
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
                            $rollsperpallet++;
                            $result->compositeImage($reel, imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset , $y +$top);
                        }
                    } else {
                        $top=$diam*$j +$radius;
                        if ( ($row>2*$j+1) || ($j>$up-$row/2-1) ) {
                            // hide reel on pyramid edge
                        } else {
                            //echo "odd row=$row j=$j top=$top <br>";
                            $rollsperpallet++;
                            $result->compositeImage($reel, imagick::COMPOSITE_OVER,
                                $x+ $row*$rowoffset , $y +$top);
                        }

                    }
                }
            }
            $this->debug1("horizontal pyramid: across=$across up=$up rollsperpallet=$rollsperpallet palletheight=$palletheight");


        } else {  // testing, draw one reel
            //$reel->rotateImage('none', 90);
            $result->compositeImage($reel, imagick::COMPOSITE_OVER, 1, 1);
            //$result->rotateImage('none', 33);
        }

        if ($palletheight>$maxLoadingHeight) {
            $this-debug("MaxLoadingHeight $maxLoadingHeight exceeded.");
        }


        // ------------ prepare display ----------------------
        //
        $result->setImageFormat('jpg');
        //$result->rotateImage('white', -45);
        // TODO: or tilt?
        if ($f == 'download') {
            debug1("send pallet.jpg to $caller for download");
            //$result->scaleImage(120, 100); // reduce to thumbnail
            ob_clean();                         // Clear buffer
            Header("Content-Description: File Transfer");
            Header("Content-Type: application/force-download");
            header('Content-Type: image/jpeg'); // Send JPEG header
            Header("Content-Disposition: attachment; filename=" . "pallet.jpg");
            $result->writeImage($dir . '/out/' . $f);  // Write to disk anyway?
            //header('Content-Length: ' . $dir . '/out/' . $f);  // TODO: Calculate image size?
            echo $result;                          // Output to browser

        } else {
            //$result->scaleImage(75, 90); // reduce to thumbnail
            //$result->scaleImage(200, 240); // increase
            try
            {
                $result->writeImage($outdir . '/' . $f);       // Write to disk
            }
            catch(Exception $e)
            {
                die('Error Imagick: ' . $e->getMessage() );
            }
            //echo "<img src=$outdirweb$f alt='Generated image'>";
            $this->debug1("generated $outdirweb$f");
        }
        //echo "<img src=/$d/out/output2.jpg alt='Generated image'>";
        $result->destroy();



    }   // makePallet
}
