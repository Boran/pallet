<?php

namespace bpp\Test2Bundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/test/{name}")
     * @Template()
     */
    public function indexAction($name)
    {
        return $this->render('bppTest2Bundle:Default:index.html.twig', array('name' => $name));
    }
}
