<?php

if ( ! function_exists( 'china_payments_format_content' ) ) {

	/**
	 * @param $content
	 * @return string
	 */
	function china_payments_format_content( $content ) {
		return do_shortcode( wpautop( $content ) );
	}

}

if ( ! function_exists( 'china_payments_remove_url_protocol' ) ) {

	/**
	 * @param $url
	 * @return string
	 */
	function china_payments_remove_url_protocol( $url ): string {
		return str_replace( array( 'http://', 'https://' ), '', $url );
	}

}

if ( ! function_exists( 'china_payments_username_from_details' ) ) {

	function china_payments_username_from_details( $email_address, $first_name = '', $last_name = '' ): string {
		if ( $first_name === '' && $last_name !== '' ) {
			$first_name = $last_name;
			$last_name  = '';
		}

		if ( $first_name !== '' ) {
			$username = china_payments_label_to_alias( $first_name );

			if ( validate_username( $username ) && ! username_exists( $username ) ) {
				return $username;
			}

			for ( $i = 1; $i <= 5; $i++ ) {
				$current_username_test = $username . '_' . rand( 1, 1000 );

				if ( validate_username( $current_username_test ) && ! username_exists( $current_username_test ) ) {
					return $current_username_test;
				}
			}

			$username = china_payments_label_to_alias( $first_name . ' ' . $last_name );

			if ( validate_username( $username ) && ! username_exists( $username ) ) {
				return $username;
			}

			for ( $i = 1; $i <= 5; $i++ ) {
				$current_username_test = $username . '_' . rand( 1, 1000 );

				if ( validate_username( $current_username_test ) && ! username_exists( $current_username_test ) ) {
					return $current_username_test;
				}
			}
		}

		$username = substr( $email_address, 0, strpos( $email_address, '@' ) );

		if ( validate_username( $username ) && ! username_exists( $username ) ) {
			return $username;
		}

		for ( $i = 1; $i <= 5; $i++ ) {
			$current_username_test = $username . '_' . rand( 1, 1000 );

			if ( validate_username( $current_username_test ) && ! username_exists( $current_username_test ) ) {
				return $current_username_test;
			}
		}

		return $email_address;
	}

}

if ( ! function_exists( 'china_payments_generate_random_token' ) ) {

	function china_payments_generate_random_token( int $length = 13 ) {
		if ( function_exists( 'random_bytes' ) ) {
			$bytes = random_bytes( ceil( $length / 2 ) );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$bytes = openssl_random_pseudo_bytes( ceil( $length / 2 ) );
		} else {
			exit( 'no cryptographically secure random function available' );
		}

		return substr( bin2hex( $bytes ), 0, $length );
	}

}

if ( ! function_exists( 'china_payments_http_user_agent' ) ) {

	/**
	 * @return string
	 */
	function china_payments_http_user_agent(): string {
		return $_SERVER['HTTP_USER_AGENT'];
	}

}

if ( ! function_exists( 'china_payments_http_ip_address' ) ) {

	function china_payments_http_ip_address(): string {
		return $_SERVER['REMOTE_ADDR'];
	}

}

if ( ! function_exists( 'china_payments_debug_dump' ) ) {

	/**
	 * Debug helper function.  This is a wrapper for var_dump() that adds
	 * the <pre /> tags, cleans up newlines and indents, and runs
	 * htmlentities() before output.
	 *
	 * @param  mixed  $var   The variable to dump.
	 * @param  string $label OPTIONAL Label to prepend to output.
	 * @param  bool   $echo  OPTIONAL Echo output if true.
	 * @return string
	 */
	function china_payments_debug_dump( $var, $label = null, $echo = true ) {
		$label = ( $label === null ) ? '' : rtrim( $label ) . ' ';

		ob_start();

		var_dump( $var );

		$output = ob_get_clean();
		// neaten the newlines and indents
		$output = preg_replace( "/\]\=\>\n(\s+)/m", '] => ', $output );

		if ( ! extension_loaded( 'xdebug' ) ) {
			$output = htmlspecialchars( $output, ENT_QUOTES );
		}

		$output = '<pre>' . $label . $output . '</pre>';

		if ( $echo ) {
			echo $output;
		}

		return $output;
	}

}

if ( ! function_exists( 'china_payments_format_content_autoembed' ) ) {

	function china_payments_format_content_autoembed( $content ) {
		if ( ! isset( $GLOBALS['wp_embed'] ) ) {
			return $content;
		}

		$usecache_status = $GLOBALS['wp_embed']->usecache;

		$GLOBALS['wp_embed']->usecache = false;

		$autoembed = $GLOBALS['wp_embed']->autoembed( $content );

		$GLOBALS['wp_embed']->usecache = $usecache_status;

		return $autoembed;
	}

}

if ( ! function_exists( 'china_payments_redirect' ) ) {

	function china_payments_redirect( $url ) {
		if ( ! did_action( 'wp_head' ) ) {
			wp_redirect( $url );

			return;
		}

		echo '<script type="text/javascript">document.location = "' . $url . '"</script>';
	}

}

if ( ! function_exists( 'china_payments_alias_to_label' ) ) {

	/**
	 * @param $alias
	 * @return string
	 */
	function china_payments_alias_to_label( $alias ) {
		if ( $alias === 'api' ) {
			return 'API';
		}

		$response = str_replace( array( '_', '-', '.' ), ' ', $alias );

		$response = preg_replace( array( '/\s{2,}/', '/[\t\n]/' ), ' ', $response );

		return ucwords( $response );
	}

}

if ( ! function_exists( 'china_payments_label_to_alias' ) ) {

	/**
	 * @param $label
	 * @return string
	 */
	function china_payments_label_to_alias( $label ) {
		return preg_replace( '/\W+/', '', strtolower( str_replace( ' ', '_', $label ) ) );
	}

}

if ( ! function_exists( 'china_payments_label_to_slug' ) ) {

	/**
	 * @param $label
	 * @return string
	 */
	function china_payments_label_to_slug( $label ) {
		$response = str_replace( '_', '-', preg_replace( '/\W+/', '', strtolower( str_replace( ' ', '_', $label ) ) ) );

		$response = str_replace( '--', '-', $response );

		return $response;
	}

}

if ( ! function_exists( 'china_payments_label_to_prefix' ) ) {

	/**
	 * @param $label
	 * @return string
	 */
	function china_payments_label_to_prefix( $label ) {
		$response = '';
		$tokens   = explode( '_', china_payments_label_to_alias( $label ) );

		if ( count( $tokens ) <= 1 ) {
			return str_replace( '_', '-', china_payments_label_to_alias( $label ) );
		}

		foreach ( $tokens as $token ) {
			if ( ! empty( $token ) ) {
				$response .= $token[0];
			}
		}

		return $response;
	}

}

if ( ! function_exists( 'china_payments_utility_selected' ) ) {

	/**
	 * @param $selected
	 * @param bool     $current
	 * @param bool     $echo
	 * @return string
	 */
	function china_payments_utility_selected( $selected, $current = true, $echo = true ) {
		if ( is_array( $selected ) ) {
			return selected( 1, in_array( $current, $selected ), $echo );
		}

		return selected( $selected, $current, $echo );
	}

}

if ( ! function_exists( 'china_payments_utility_checked' ) ) {

	function china_payments_utility_checked( $checked, $current = true, $echo = true ) {
		if ( is_array( $checked ) ) {
			return checked( 1, in_array( $current, $checked ), $echo );
		}

		return checked( $checked, $current, $echo );
	}

}

if ( ! function_exists( 'china_payments_utilities_map_object' ) ) {

	function china_payments_utilities_map_object( $array, $param = 'ID', $multiple = false ) {
		$ret = array();

		foreach ( $array as $a ) {
			if ( ! $multiple ) {
				$ret[ $a->$param ] = $a;
				continue;
			}

			if ( ! isset( $ret[ $a->$param ] ) ) {
				$ret[ $a->$param ] = array();
			}

			$ret[ $a->$param ][] = $a;
		}

		return $ret;
	}

}

if ( ! function_exists( 'china_payments_utilities_map_array' ) ) {

	function china_payments_utilities_map_array( $array, $param = 'ID', $multiple = false ) {
		$ret = array();

		foreach ( $array as $a ) {
			if ( $multiple === false ) {
				$ret[ $a[ $param ] ] = $a;
				continue;
			}

			if ( ! isset( $ret[ $a[ $param ] ] ) ) {
				$ret[ $a[ $param ] ] = array();
			}

			if ( $multiple === true ) {
				$ret[ $a[ $param ] ][] = $a;
			} else {
				$ret[ $a[ $param ] ][ $a[ $multiple ] ] = $a;
			}
		}

		return $ret;
	}

}

if ( ! function_exists( 'china_payments_utility_map_to_array_assoc' ) ) {

	function china_payments_utility_map_to_array_assoc( $array, $key_param = 'key', $value_param = 'value', $merge_existent_indexes = false ) {
		if ( ! is_array( $array ) ) {
			return array();
		}

		$ret = array();

		foreach ( $array as $a ) {
			if ( is_object( $a ) ) {
				$a = get_object_vars( $a );
			}

			$current_value_param = $a[ $value_param ];

			if ( is_array( $key_param ) ) {
				$current_key_param = '';

				foreach ( $key_param as $key_param_token ) {
					$current_key_param .= ( isset( $a[ $key_param_token ] ) ? $a[ $key_param_token ] : $key_param_token );
				}
			} else {
				$current_key_param = $a[ $key_param ];
			}

			if ( isset( $ret[ $current_key_param ] ) && $merge_existent_indexes ) {
				if ( ! is_array( $ret[ $current_key_param ] ) ) {
					$ret[ $current_key_param ] = array( $ret[ $current_key_param ] );
				}

				$ret[ $current_key_param ][] = $current_value_param;

				continue;
			}

			$ret[ $current_key_param ] = $current_value_param;
		}

		return $ret;
	}

}

if ( ! function_exists( 'china_payments_utility_array_trim' ) ) {

	function china_payments_utility_array_trim( $array, $charlist = " \t\n\r\0\x0B" ) {
		foreach ( $array as $k => $v ) {
			$array[ $k ] = is_string( $v ) ? trim( $v, $charlist ) : $v;
		}

		return $array;
	}

}

if ( ! function_exists( 'china_payments_utility_map_to_object_assoc' ) ) {

	function china_payments_utility_map_to_object_assoc( $object, $key_param = 'key', $value_param = 'value' ) {
		if ( ! is_array( $object ) ) {
			return new stdClass();
		}

		$ret = new stdClass();

		foreach ( $object as $o ) {
			$index       = $o->$key_param;
			$ret->$index = $o->$value_param;
		}

		return $ret;
	}

}

if ( ! function_exists( 'china_payments_permalink_extend' ) ) {

	function china_payments_permalink_extend( $permalink, $param, $value ) {
		$permalink = remove_query_arg( $param, $permalink );

		$permalink = add_query_arg( $param, $value, $permalink );

		return $permalink;
	}

}

if ( ! function_exists( 'china_payments_link_is_404' ) ) {

	function china_payments_link_is_404( $url ) {
		$http_code = china_payments_link_http_code( $url );

		if ( $http_code == 0 && strpos( $url, get_site_url() ) === 0 ) {
			return false;
		}

		if ( in_array( $http_code, array( 302, 403 ) ) ) {
			return false;
		}

		if ( $http_code >= 200 && $http_code < 300 ) {
			return false;
		} else {
			return true;
		}
	}

}

if ( ! function_exists( 'china_payments_link_http_code' ) ) {

	function china_payments_link_http_code( $url ) {
		$handle = curl_init( $url );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		$response  = curl_exec( $handle );
		$http_code = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
		curl_close( $handle );

		return $http_code;
	}

}

function china_payments_content_allowed_html_tags() {
	return array(
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'alt'    => array(),
		),
		'br'     => array(),
		'video'  => array(
			'width'  => array(),
			'height' => array(),
		),
		'source' => array(
			'src'  => array(),
			'type' => array(),
		),
		'strong' => array( 'style' => array() ),
		'sub'    => array( 'style' => array() ),
		'sup'    => array( 'style' => array() ),
		's'      => array( 'style' => array() ),
		'i'      => array( 'style' => array() ),
		'u'      => array( 'style' => array() ),
		'span'   => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'h1'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'h2'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'h3'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'ol'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'ul'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'li'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'em'     => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'hr'     => array(),
		'p'      => array(
			'align' => array(),
			'class' => array(),
			'type'  => array(),
			'id'    => array(),
			'style' => array(),
			'data'  => array(),
		),
		'img'    => array(
			'align'  => array(),
			'class'  => array(),
			'type'   => array(),
			'id'     => array(),
			'style'  => array(),
			'src'    => array(),
			'alt'    => array(),
			'href'   => array(),
			'rel'    => array(),
			'target' => array(),
			'value'  => array(),
			'name'   => array(),
			'width'  => array(),
			'height' => array(),
			'data'   => array(),
			'title'  => array(),
		),
	);
}
