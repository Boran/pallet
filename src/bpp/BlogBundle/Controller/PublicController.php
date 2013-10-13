<?php
/**
 * Created by PhpStorm.
 * User: TGDBOSE1
 * Date: 11.10.13
 * Time: 19:27
 */
namespace bpp\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class PublicController extends Controller
{
    public function indexAction($name='foo')
    {
        return new Response(" Acceuil du blog:_ $name");
        //array('name' => $name);
    }
}
