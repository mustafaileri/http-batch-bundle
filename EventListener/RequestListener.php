<?php

namespace Ideasoft\HttpBatchBundle\EventListener;

use Doctrine\Common\Annotations\AnnotationReader;
use Ideasoft\HttpBatchBundle\Annotation\BatchRequest;
use Ideasoft\HttpBatchBundle\Handler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class RequestListener
{
    /** @var  AnnotationReader */
    private $annotationReader;

    /** @var  Handler */
    private $batchRequestHandler;

    /**
     * RequestListener constructor.
     * @param AnnotationReader $annotationReader
     * @param Handler $batchRequestHandler
     */
    public function __construct(AnnotationReader $annotationReader, Handler $batchRequestHandler)
    {
        $this->annotationReader = $annotationReader;
        $this->batchRequestHandler = $batchRequestHandler;
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if ($event->isMasterRequest()) {
            $controller = $event->getController();

            list($controllerObject, $methodName) = $controller;
            $reflectionOfMethod = new \ReflectionMethod($controllerObject, $methodName);
            $hasBatchRequestAnnotation = $this->annotationReader->getMethodAnnotation(
                $reflectionOfMethod,
                BatchRequest::class
            );

            if ($hasBatchRequestAnnotation) {
                if ($event->getRequest()->getMethod() != Request::METHOD_POST) {
                    throw new MethodNotAllowedHttpException(
                        [Request::METHOD_POST],
                        sprintf(
                            '%s method not allowed for batch request. %s Allowed method: %s',
                            $event->getRequest()->getMethod(),
                            PHP_EOL,
                            Request::METHOD_POST
                        )
                    );
                }

                $event->setController(
                    function () use ($event) {
                        return $this->batchRequestHandler->handle($event->getRequest());
                    }
                );
            }
        }

        return;
    }
}
