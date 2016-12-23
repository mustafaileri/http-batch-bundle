<?php

namespace Ideasoft\HttpBatchBundle\Controller;

use Ideasoft\HttpBatchBundle\Annotation\BatchRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/batch", name="http_batch")
     * @Method({"POST"})
     * @BatchRequest
     * @param Request $request
     */
    public function indexAction(Request $request)
    {
    }
}
