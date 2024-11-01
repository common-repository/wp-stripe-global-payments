<?php

namespace ChinaPayments;

/**
 * Class Request
 *
 * @author Robert Rusu
 */
class Request {

	/**
	 * @var Request|null
	 */
	protected static $instance = null;

	public static function instance(): ?Request {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Used to store any key -> value based type information while handling the current request.
	 *
	 * @var array
	 */
	protected $_current_request_setting = array();

	private $_current_post_id   = null;
	private $_current_post_type = null;
	private $_url_to_post_id    = array();

	/**
	 * @param string $type : admin|ajax|cron|frontend
	 * @return bool|null
	 */
	public function is_request_type( string $type ) {
		if ( $type == 'admin' ) {
			return is_admin() && ! defined( 'DOING_AJAX' );
		}

		if ( $type == 'ajax' ) {
			return defined( 'DOING_AJAX' );
		}

		if ( $type == 'cron' ) {
			return defined( 'DOING_CRON' );
		}

		if ( $type == 'frontend' ) {
			return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}

		return null;
	}

	/**
	 * @param int|string $post_id
	 */
	public function set_current_post_id( $post_id ) {
		$this->_current_post_id = intval( $post_id );
	}

	/**
	 * @return int
	 */
	public function get_current_post_id(): int {
		if ( $this->_current_post_id === null ) {

			if ( $this->is_request_type( 'admin' ) ) {
				$this->_current_post_id = 0;

				return $this->_current_post_id;
			}

			$current_url = $this->get_current_url();

			$this->_current_post_id = $this->get_post_id_by_url( $current_url );
		}

		return $this->_current_post_id;
	}

	public function get_current_post_type() {
		if ( $this->_current_post_type === null ) {
			$post_id = $this->get_current_post_id();

			if ( $post_id == 0 ) {
				$this->_current_post_type = false;

				return $this->_current_post_type;
			}

			$this->_current_post_type = get_post_type( $post_id );
		}

		return $this->_current_post_type;
	}

	/**
	 * @param $current_url
	 * @return int
	 */
	public function get_post_id_by_url( $current_url ): int {
		if ( isset( $this->_url_to_post_id[ $current_url ] ) ) {
			return $this->_url_to_post_id[ $current_url ];
		}

		$post_id = url_to_postid( $current_url );

		if ( $post_id === 0 && strpos( $current_url, '?' ) !== false ) {
			$current_url = strtok( $current_url, '?' );

			$post_id = url_to_postid( $current_url );
		}

		$this->_url_to_post_id[ $current_url ] = intval( $post_id );

		return $this->_url_to_post_id[ $current_url ];
	}

	/**
	 * @return null|string
	 */
	public function get_current_url() {
		if ( ! isset( $_SERVER['HTTP_HOST'] ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		return ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	/**
	 * @param $key
	 * @param $value
	 */
	public function set_request_setting( $key, $value ) {
		$this->_current_request_setting[ $key ] = $value;
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	public function get_request_setting( $key ) {
		return ( $this->_current_request_setting[ $key ] ?? null );
	}
}
