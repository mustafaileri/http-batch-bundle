<?php


namespace Ideasoft\HttpBatchBundle\Message;


use Symfony\Component\HttpFoundation\Request;


/**
 * Class Transaction used as parameter object
 *
 * @package Ideasoft\HttpBatchBundle\Message
 */
class Transaction {

	/** @var Request */
	public $request;

	/** @var \Symfony\Component\HttpFoundation\Response */
	public $response;

	/** @var string */
	public $content_id;

} // Transaction