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

        //@todo include_once "../q/funcs.inc";
        /*$web="/var/www/";
        $web=$this->get('kernel')->getRootDir() . '/../web';
        $d="jobweb/pallet";
        $dir=$web . $d;*/
        //$dir= $this->get('kernel')->getRootDir() . '/../web' . $this->getRequest()->getBasePath();
        //$dir = $this->get('kernel')->locateResource('@PalletBundle/resources/public/images');
        //$this->debug1($dir);
        #$prog=$argv[0];
        #$prog="$dir/index.php";

        //@todo: create a form with default values
        //$this->debug1( $request->query->get('file') );  // get /pallet/foo?file=ss
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

        $form = $this->createFormBuilder($pallet)
            //->add('layout', 'text')
            ->add('layout', 'choice', array(
                    'choices' => array(
                        'versq'=>'Vertical square',
                        'verint'=>'Vertical interleaved'),
                    'required' => true,
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

            ->add('Calculate', 'submit')
            ->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->makePallet($pallet);
            return new Response("Pallet: submitted layout= " . $pallet->layout) ;
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

        $image_ver='/reelv.jpg';   // reelv2.png
        $image_hor='/reelh.jpg';
        $heightwarning='';

        // -- go
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
        //$result = new Imagick();   // create new canvas for pallet + stacked rolls
    }
}
