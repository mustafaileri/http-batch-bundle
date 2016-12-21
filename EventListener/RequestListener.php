<?php
namespace Hezarfen\HttpBatchBundle\EventListener;


use Hezarfen\HttpBatchBundle\Annotation\BatchRequest;
use Hezarfen\HttpBatchBundle\Handler;
use Doctrine\Common\Annotations\AnnotationReader;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RequestListener
{

    /** @var  AnnotationReader */
    private $annotationReader;

    /** @var  Handler */
    private $batchRequestHandler;

    public function __construct(AnnotationReader $annotationReader, Handler $batchRequestHandler)
    {
        $this->annotationReader = $annotationReader;
        $this->batchRequestHandler = $batchRequestHandler;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if ($event->isMasterRequest()) {
            $controller = $event->getController();

            list($controllerObject, $methodName) = $controller;
            $reflectionOfMethod = new \ReflectionMethod($controllerObject, $methodName);
            $hasBatchRequestAnnotation = $this->annotationReader->getMethodAnnotation($reflectionOfMethod, BatchRequest::class);
            if ($hasBatchRequestAnnotation) {
                if ($event->getRequest()->getMethod() != Request::METHOD_POST) {
                    throw new MethodNotAllowedHttpException([Request::METHOD_POST],
                        $event->getRequest()->getMethod() . " method not allowed for batch request."
                        . PHP_EOL . "Allowed method:" . Request::METHOD_POST);
                }

                $event->setController(function () use ($event) {
                    return $this->batchRequestHandler->handle($event->getRequest());

                });
            }
        }
        return;

    }
}