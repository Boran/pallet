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
     * @Route("/")
     * @Template()
     *     will look for index.html.twig automatically
     * TODO: add parameters like @Route("/{param1}")
     */
    public function indexAction(Request $request)
    {
        $this->debug1("indexAction ");
        $params = $this->getRequest()->request->all();
        //print_r($params);   ['_route_params']   attributes()->get()
        //echo "<pre>"; \Doctrine\Common\Util\Debug::dump($request->parameters()); echo "</pre>";
        //echo "<pre>"; \Doctrine\Common\Util\Debug::dump($request); echo "</pre>";
        // @todo: pull values optionally for get/post arguments
        //$this->debug1( $request->query->get('file') );  // get /pallet/foo?file=ss

        //$this->debug1('__FILE__=' . dirname(__FILE__) . ', REQUEST_URI=' . dirname($_SERVER['REQUEST_URI'])
        //   . ", getRootDir=" . $this->get('kernel')->getRootDir()
        //   . " getBasePath=" . $request->getBasePath()
        //);
        $outdirweb = $request->getBasePath() . '/out/';  // /pallet/web/out : must get from request

        // create a Pallet object with default values, and a form
        $pallet = new Pallet($outdirweb);
        $form = $this->createFormBuilder($pallet)
            ->add('layout', 'choice', array(
                    'choices' => array(
                        'versq' =>'Vertical square',
                        'verint'=>'Vertical interleaved',
                        'horint'=>'Horizontal interleaved',
                        'horsq' =>'Horizontal square',
                        'horpyr'=>'Horizontal pyramid'),
                    'required'  => true,
                    'label'     =>'Pallet layout',
                ))
            //->add('threed', 'text')
            ->add('rollwidth_mm', 'integer',  array('label'=>'Roll width mm') )
            ->add('diam_mm', 'integer',  array('label'=>'Roll diameter mm'))
            ->add('rows',             'integer', array('label'=>'Pallet vertical rows'))
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
            $pallet->makePallet();
            //return new Response("Pallet: submitted layout= " . $pallet->layout
            //  . ", rollkgs=$pallet->rollkgs" );
            return $this->render('PalletBundle:Default:index.html.twig',
                array('form' => $form->createView(),
                    'name'=>'',
                    'image_path'=>$pallet->image_path,
                    'pallet' => $pallet
                )
            );
        }
        //return array('form' => $form->createView);  // use Twig
        return $this->render('PalletBundle:Default:index.html.twig',
            array('form' => $form->createView(), 'name'=>'', )
        );
    }

}
