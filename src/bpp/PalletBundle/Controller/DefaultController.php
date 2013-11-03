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
     * @Route("/{name}")
     * @Template()
     *     will look for index.html.twig automatically
     */
    public function indexAction(Request $request, $name)
    {
        $this->debug1("indexAction received name=$name "  );
        //------ defaults ----------------
        // Pallet canvas size on the screen
        //$pwidth=360;  // need better reel icon before using bigger sizes
        //$plength=300; // down
        //$image_ver='/reelv.jpg';   // reelv2.png
        //$image_hor='/reelh.jpg';
        //$heightwarning='';

        // @todo: pull values optionally for get/post arguments
        //$this->debug1( $request->query->get('file') );  // get /pallet/foo?file=ss

        // Directories where our script in, where output is stored.
        // to be passed to the pallet object
        $dir = $this->get('kernel')->getRootDir() . '/../web';
        //$this->debug1('__FILE__=' . dirname(__FILE__) . ', REQUEST_URI=' . dirname($_SERVER['REQUEST_URI'])
        //    . ", dir=$dir");
        $outdir=$dir . '/out';
        //$outdirweb = dirname($_SERVER['REQUEST_URI']) . '/out/';
        //$this->getRequest()->getBasePath()
        $outdirweb = '/pallet/web/out/';  // @todo FIX!!
        if (!is_writable($outdir)) {
            $this->debug1("$outdir does not exist or is not writeable, lets try to create it");
            if (!@mkdir($outdir, 700, true)) {
                die('Cannot create output directory: ' . $outdir . '. <br>Make sure this exists and belongs to the webserver user, e.g. www-data');
            }
        }

        // create a Pallet object with default values, and a form
        $pallet = new Pallet();
        $form = $this->createFormBuilder($pallet)
            ->add('layout', 'choice', array(
                    'choices' => array(
                        'versq' =>'Vertical square',
                        'verint'=>'Vertical interleaved',
                        'horint'=>'Horizontal interleaved',
                        'horsq' =>'Horizontal square',
                        'horpyr'=>'Horizontal pyramid'),
                    'required'  => true,
                    'label'     =>'Pallet length',
                ))
            //->add('threed', 'text')
            ->add('rollwidth_mm', 'integer')
            ->add('diam_mm', 'integer')
            ->add('rows',             'integer', array('label'=>'How many vertical rows?'))
            ->add('plength_mm',       'integer', array('label'=>'Pallet length'))
            ->add('pwidth_mm',        'integer', array('label'=>'Pallet width'))
            ->add('maxLoadingHeight', 'integer', array('label'=>'Pallet max height(mm)'))
            ->add('maxLoadingWeight', 'integer', array('label'=>'Pallet max weight(kgs)'))
            ->add('rollkgs',          'integer', array('label'=>'Roll weight (kgs)'))
            ->add('threed', 'choice', array(
                    'choices' => array(
                        '0'=>'No',
                        '1'=>'Yes'),
                    'required' => true,
                    'label'=>'3 D effect'
                ))
            ->add('Calculate', 'submit')
            ->getForm();
        $this->debug1('form built');

        $form->handleRequest($request);
        if ($form->isValid()) {
            $pallet->makePallet($outdir, $outdirweb);
            //return new Response("Pallet: submitted layout= " . $pallet->layout
            //  . ", rollkgs=$pallet->rollkgs" );
            return $this->render('PalletBundle:Default:index.html.twig',
                array('form' => $form->createView(), 'name'=>'',
                    'image_path'=>$pallet->image_path, )
            );
        }
        //return array('form' => $form->createView);  // use Twig
        return $this->render('PalletBundle:Default:index.html.twig',
            array('form' => $form->createView(), 'name'=>'', )
        );
    }

}
