<?php

namespace Ideasoft\HttpBatchBundle;

use Ideasoft\HttpBatchBundle\HTTP\ContentParser;
use Ideasoft\HttpBatchBundle\Message\Response;
use Ideasoft\HttpBatchBundle\Message\Transaction;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class Handler {


	/**
	 * @var string
	 */
	private $boundary;

	/**
	 * @var Kernel $kernel
	 */
	private $kernel;

	/**
	 * @var Request $batchRequest
	 */
	private $batchRequest;

	/**
	 * @var integer
	 */
	private $max_calls;

	/**
	 * @param HttpKernelInterface $kernel
	 */
	public function __construct( HttpKernelInterface $kernel, $max_calls ) {

		$this->kernel = $kernel;

		$this->max_calls = $max_calls;

	}

	/**
	 * @param Request $request
	 *
	 * @return Response
	 * @throws \HttpHeaderException
	 */
	public function handle( Request $request ) {

		$this->batchRequest = $request;

		return $this->parseRequest( $request );

	}

	/**
	 * @param Request $request
	 *
	 * @return Response
	 * @throws \HttpHeaderException
	 */
	private function parseRequest( Request $request ) {

		$this->getBatchHeader( $request );
		$transactions = $this->getTransactions( $request );
		try {
			$transactions = $this->getTransactions( $request );
		}
		catch ( HttpException $e ) {
			return new JsonResponse( array(
				                         'result' => 'error',
				                         'errors' => array(
					                         array(
						                         'message' => $e->getMessage(),
						                         'type'    => 'client_error',
					                         ),
				                         ),
			                         ), $e->getStatusCode() );
		}

		return $this->getBatchRequestResponse( $transactions );

	}

	/**
	 * @param Request $request
	 *
	 * @return mixed
	 * @throws \HttpHeaderException
	 */
	private function getBatchHeader( Request $request ) {

		$headers        = $request->headers->all();
		$contentType    = $request->headers->get( "content-type" );
		$this->boundary = $this->parseBoundary( $contentType );

		return $headers;

	}

	/**
	 * @param string $contentType
	 *
	 * @return string
	 * @throws \HttpHeaderException
	 */
	private function parseBoundary( $contentType ) {

		if ( ! $contentType ) {
			throw new \HttpHeaderException( "Content-type can not be found in header" );
		}
		$contentTypeData = explode( ";", $contentType );

		foreach ( $contentTypeData as $data ) {
			$contentTypePart = explode( "=", $data );
			if ( sizeof( $contentTypePart ) == 2 && trim( $contentTypePart[ 0 ] ) == "boundary" ) {
				$boundary = trim( $contentTypePart[ 1 ] );
				break;
			}
		}
		if ( isset( $boundary ) ) {
			return $boundary;
		} else {
			throw new BadRequestHttpException( "Boundary can not be found." );
		}

	}

	private function parseContentId( $request_header ) {

		if ( ! $request_header ) {
			throw new \HttpHeaderException( "Subrequest header can not be found" );
		}
		$request_header_data = explode( PHP_EOL, $request_header );

		foreach ( $request_header_data as $data ) {
			$item = explode( ':', $data, 2 );
			array_walk( $item,
				function ( &$value ) {

					$value = trim( strtolower( $value ) );
				} );

			if ( $item[ 0 ] === 'content-id' ) {
				return $item[ 1 ];
			} // if
		} // foreach

		throw new \HttpHeaderException( "Content-id can not be found in subrequest header" );

	} // perseContentId

	/**
	 * @param Request $request
	 *
	 * @return array
	 * @throws \HttpHeaderException
	 */
	private function getTransactions( Request $request ) {

		$transactions        = [];
		$content             = explode( "--" . $this->boundary . "--", $request->getContent() )[ 0 ];
		$subRequestsAsString = explode( "--" . $this->boundary, $content );
		array_map( function ( $data ) {

			trim( $data );
		},
			$subRequestsAsString );
		$subRequestsAsString = array_filter( $subRequestsAsString,
			function ( $data ) {

				return strlen( $data ) > 0;
			} );

		if ( count( $subRequestsAsString ) > $this->max_calls ) {
			throw new HttpException( Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
			                         sprintf( "Maximum call limit exceeded (found %d). Please consider to send only %d calls per request.",
			                                  count( $subRequestsAsString ),
			                                  $this->max_calls ) );
		}

		$subRequestsAsString = array_values( $subRequestsAsString );
		foreach ( $subRequestsAsString as $item ) {
			$item          = explode( PHP_EOL . PHP_EOL, $item, 2 );
			$requestHeader = $item[ 0 ];
			$requestString = $item[ 1 ];

			$transaction             = $this->convertGuzzleRequestToTransactions(
				\GuzzleHttp\Psr7\parse_request( $requestString )
			);
			$transaction->content_id = $this->parseContentId( $requestHeader );

			$transactions[] = $transaction;
		}

		return $transactions;

	}

	/**
	 * @param \GuzzleHttp\Psr7\Request $guzzleRequest
	 *
	 * @return Transaction
	 * @throws \HttpHeaderException
	 */
	private function convertGuzzleRequestToTransactions( \GuzzleHttp\Psr7\Request $guzzleRequest ) {

		$url     = $guzzleRequest->getUri()->getPath();
		$method  = $guzzleRequest->getMethod();
		$cookies = [];
		$files   = [];
		$server  = $this->batchRequest->server->all();
		$content = null;
		$params  = ContentParser::parse( $guzzleRequest->getHeader( 'content-type' )[ 0 ],
		                                 $guzzleRequest->getBody() );

		$transaction = new Transaction();

		$transaction->request = Request::create( $url,
		                                         $method,
		                                         $params[ 'post' ],
		                                         $cookies,
		                                         $files,
		                                         $server,
		                                         $content );

		foreach ( $guzzleRequest->getHeaders() as $key => $value ) {
			$transaction->request->headers->set( $key, $value );
		}

		return $transaction;

	}

	/**
	 * @param Transaction[] $transactions
	 *
	 * @return Response
	 * @throws \Exception
	 */
	private function getBatchRequestResponse( array $transactions ) {

		foreach ( $transactions as $transaction ) {
			$transaction->response = $this->kernel->handle( $transaction->request, HttpKernelInterface::SUB_REQUEST );
		}

		return $this->generateBatchResponseFromSubResponses( $transactions );

	}

	/**
	 * @param Transaction[] $transactions
	 *
	 * @return Response
	 */
	private function generateBatchResponseFromSubResponses( $transactions ) {

		$response    = new Response();
		$version     = $response->getProtocolVersion();
		$contentType = 'multipart/batch;type="application/http;type=' . $version . '";boundary=' . $this->boundary;
		$response->headers->set( "Content-Type", $contentType );

		$contentForSubResponses = [];
		foreach ( $transactions as $transaction ) {
			$contentForSubResponses[] = $this->generateSubResponseFromContent( $transaction );
		}
		$content = "--" . $this->boundary . PHP_EOL;
		$content .= implode( PHP_EOL . "--" . $this->boundary . PHP_EOL, $contentForSubResponses ) . PHP_EOL;
		$content .= "--" . $this->boundary . "--" . PHP_EOL;
		$response->setContent( $content );

		return $response;

	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return string
	 */
	private function generateSubResponseFromContent( Transaction $transaction ) {

		$content = '';
		$content .= 'Content-Type: application/http;version=' . $transaction->response->getProtocolVersion() . PHP_EOL;
		$content .= 'Content-Transfer-Encoding: binary' . PHP_EOL;
		$content .= 'In-Reply-To: ' . $transaction->content_id . PHP_EOL;
		$content .= PHP_EOL;
		$content .= "HTTP/" . $transaction->response->getProtocolVersion() . " " .
		            $transaction->response->getStatusCode() .
		            " " .
		            Response::$statusTexts[ $transaction->response->getStatusCode() ] . PHP_EOL;
		foreach ( $transaction->response->headers->allPreserveCase() as $key => $value ) {
			$content .= sprintf( "%s:%s" . PHP_EOL, $key, implode( ",", $value ) );
		}
		$content .= PHP_EOL;
		$content .= trim( $transaction->response->getContent() );

		return $content;

	}
}
