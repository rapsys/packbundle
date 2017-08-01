<?php

namespace Rapsys\PackBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('RapsysPackBundle:Default:index.html.twig');
    }
}
