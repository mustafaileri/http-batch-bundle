<?php


namespace Ideasoft\HttpBatchBundle\HTTP;


/**
 * Class ContentParser
 * Handles a input stream with http messages
 * @see     https://gist.github.com/jas-/5c3fdc26fedd11cb9fb5#file-class-stream-php
 * @see     https://stackoverflow.com/questions/5483851/manually-parse-raw-multipart-form-data-data-with-php
 *
 * @package Ideasoft\HttpBatchBundle\HTTP
 */
class ContentParser {


	/**
	 * @abstract Raw input stream
	 */
	protected $input;

	/**
	 * @param string $boundary
	 * @param string $content
	 * @param array  $data stream
	 *
	 * @throws \HttpHeaderException
	 */
	private function __construct( $boundary, $content, array &$data ) {

		$this->input = $content;

		if ( strpos( $boundary, 'boundary=' ) !== false ) {
			$boundary = $this->boundary( $boundary );
		}

		$blocks = $this->split( $boundary );

		$data = $this->blocks( $blocks );

		return $data;

	} // __construct

	/**
	 * @param string $boundary
	 * @param string $content
	 *
	 * @return array
	 * @throws \HttpHeaderException
	 */
	public static function parse( $boundary, $content ) {

		$params = [];

		new self( $boundary, $content, $params );

		return $params;
	}

	/**
	 * @param string $contentType
	 *
	 * @return array
	 * @throws \HttpHeaderException
	 */
	private function boundary( $contentType ) {

		if ( ! $contentType ) {
			throw new \HttpHeaderException( "Content-type can not be found in header" );
		} // if
		$contentTypeData = explode( ";", $contentType );

		foreach ( $contentTypeData as $data ) {
			$contentTypePart = explode( "=", $data );
			if ( sizeof( $contentTypePart ) == 2 && trim( $contentTypePart[ 0 ] ) == "boundary" ) {
				$boundary = trim( $contentTypePart[ 1 ] );
				break;
			} // if
		} // foreach

		if ( isset( $boundary ) ) {
			return $boundary;
		} else {
			throw new \HttpHeaderException( "Boundary can not be found." );
		} // if

	} // boundary

	/**
	 * @param $boundary string
	 *
	 * @return array
	 */
	private function split( $boundary ) {

		$result = preg_split( "/-+$boundary/", $this->input );
		array_pop( $result );

		return $result;

	} // split

	/**
	 * @param $array array
	 *
	 * @return array
	 */
	private function blocks( $array ) {

		$results = [
			'post' => [],
			//			'file' => [],
		];

		foreach ( $array as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			} // if

			$block = $this->decide( $value );

			if ( count( $block[ 'post' ] ) > 0 ) {
				array_push( $results[ 'post' ], $block[ 'post' ] );
			} // if

//			if ( count( $block[ 'file' ] ) > 0 ) {
//				array_push( $results[ 'file' ], $block[ 'file' ] );
//			} // if
		} // foreach

		return $this->merge( $results );

	} // blocks

	/**
	 * @param $string string
	 *
	 * @return array
	 */
	private function decide( $string ) {

//		if ( strpos( $string, 'application/octet-stream' ) !== false ) {
//			return [
//				'post' => $this->file( $string ),
//				'file' => [],
//			];
//		} // if

//		if ( strpos( $string, 'filename' ) !== false ) {
//			return [
//				'post' => [],
//				'file' => $this->file_stream( $string ),
//			];
//		} // if

		return [
			'post' => $this->post( $string ),
			//			'file' => [],
		];

	} // decide

	/**
	 * @param $string
	 *
	 * @return array
	 */
//	private function file( $string ) {
//
//		preg_match( '/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $string, $match );
//
//		return [
//			$match[ 1 ] => ( ! empty( $match[ 2 ] ) ? $match[ 2 ] : '' ),
//		];
//
//	} // file

	/**
	 * @param $string
	 *
	 * @return array
	 */
//	private function file_stream( $string ) {
//
//		$data = [];
//
//		preg_match( '/name=\"([^\"]*)\"; filename=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $string, $match );
//		preg_match( '/Content-Type: (.*)?/', $match[ 3 ], $mime );
//
//		$image = preg_replace( '/Content-Type: (.*)[^\n\r]/', '', $match[ 3 ] );
//
//		$path = sys_get_temp_dir() . '/php' . substr( sha1( rand() ), 0, 6 );
//
//		$err = file_put_contents( $path, ltrim( $image ) );
//
//		if ( preg_match( '/^(.*)\[\]$/i', $match[ 1 ], $tmp ) ) {
//			$index = $tmp[ 1 ];
//		} else {
//			$index = $match[ 1 ];
//		} // if
//
//		$data[ $index ][ 'name' ][]     = $match[ 2 ];
//		$data[ $index ][ 'type' ][]     = $mime[ 1 ];
//		$data[ $index ][ 'tmp_name' ][] = $path;
//		$data[ $index ][ 'error' ][]    = ( $err === false ) ? $err : 0;
//		$data[ $index ][ 'size' ][]     = filesize( $path );
//
//		return $data;
//
//	} // file_stream

	/**
	 * @param $string
	 *
	 * @return array
	 */
	private function post( $string ) {

		$data = [];

		preg_match( '/name=\"([^\"]*)\"[\n|\r]+([^\n\r]*)?$/s', $string, $match );

		if ( preg_match( '/^(.*)\[\]$/i', $match[ 1 ], $tmp ) ) {
			$data[ $tmp[ 1 ] ][] = ( ! empty( $match[ 2 ] ) ? $match[ 2 ] : '' );
		} else {
			$data[ $match[ 1 ] ] = ( ! empty( $match[ 2 ] ) ? $match[ 2 ] : '' );
		} // if

		return $data;

	} // post

	/**
	 * @param $array array
	 *
	 * Ugly ugly ugly
	 *
	 * @return array
	 */
	private function merge( $array ) {

		$results = [
			'post' => [],
			//			'file' => [],
		];

		if ( count( $array[ 'post' ] ) > 0 ) {
			foreach ( $array[ 'post' ] as $key => $value ) {
				foreach ( $value as $k => $v ) {
					if ( is_array( $v ) ) {
						foreach ( $v as $kk => $vv ) {
							$results[ 'post' ][ $k ][] = $vv;
						} // foreach
					} else {
						$results[ 'post' ][ $k ] = $v;
					} // if
				} // foeach
			} // foeach
		} // if

//		if ( count( $array[ 'file' ] ) > 0 ) {
//			foreach ( $array[ 'file' ] as $key => $value ) {
//				foreach ( $value as $k => $v ) {
//					if ( is_array( $v ) ) {
//						foreach ( $v as $kk => $vv ) {
//							if ( is_array( $vv ) && ( count( $vv ) === 1 ) ) {
//								$results[ 'file' ][ $k ][ $kk ] = $vv[ 0 ];
//							} else {
//								$results[ 'file' ][ $k ][ $kk ][] = $vv[ 0 ];
//							} // if
//						} // foreach
//					} else {
//						$results[ 'file' ][ $k ][ $key ] = $v;
//					} // if
//				} // foreach
//			} // foreach
//		} // if

		return $results;

	} // merge

} // ContentParser