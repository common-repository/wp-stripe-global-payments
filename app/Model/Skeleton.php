<?php

namespace ChinaPayments\Model;

use Exception;

class Skeleton {

	public static $table;
	public static $fields;
	public static $identifier;
	public static $identifier_any = false;
	public static $timestamps     = array( 'created_at' );

	/**
	 * @param $data
	 * @return static
	 * @throws Exception
	 */
	public static function findOrCreate( $data ) {
		$model = new static( $data );

		$model->get();

		if ( null === $model->id ) {
			$model->insert();
		}

		return $model;
	}

	/**
	 * @param $data
	 * @return static
	 * @throws Exception
	 */
	public static function findOrFail( $data ) {
		$model = new static( $data );
		$model->get();

		if ( ! $model->exists() ) {
			throw new Exception( sprintf( __( 'Failed to find %s.', 'china-payments' ), static::$table ) );
		}

		return $model;
	}

	/**
	 * @param array $where
	 * @return static[]
	 */
	public static function listWhere( array $where ): array {
		$query = 'SELECT * FROM ' . china_payments_wpdb()->prefix . static::$table;

		if ( ! empty( $where ) ) {
			$query .= ' WHERE 1 = 1 ';

			foreach ( $where as $k => $v ) {
				if ( ! is_array( $v ) ) {
					$query .= ' AND ' . $k . ' = ' . china_payments_wpdb()->prepare( '%s', $v );

					continue;
				}

				foreach ( $v as $compare => $value ) {
					if ( is_array( $value ) && ! empty( $value ) ) {
						$value = china_payments_wpdb()->_escape( $value );

						$value = '("' . implode( '","', $value ) . '")';
					} else {
						$value = china_payments_wpdb()->prepare( '%s', $value );
					}

					$query .= ' AND ' . $k . ' ' . $compare . ' ' . $value;
				}
			}
		}

		$entries = china_payments_wpdb()->get_results( $query, ARRAY_A );

		if ( empty( $entries ) ) {
			return array();
		}

		$response = array();

		foreach ( $entries as $entry ) {
			$response[] = new static( $entry );
		}

		return $response;
	}

	/**
	 * @param array $ids
	 * @param array $args
	 * @return static[]
	 */
	public static function listByIds( array $ids, array $args = array() ): array {
		$ids = array_map( 'intval', $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$query = 'SELECT * FROM ' . china_payments_wpdb()->prefix . static::$table . ' WHERE id IN (' . implode( ',', $ids ) . ')';

		if ( isset( $args['order_by'] ) ) {
			$query .= ' ORDER BY `' . $args['order_by'] . '` ' . ( $args['order'] === 'ASC' ? 'ASC' : 'DESC' );
		}

		$entries = china_payments_wpdb()->get_results( $query, ARRAY_A );

		if ( empty( $entries ) ) {
			return array();
		}

		$response = array();

		foreach ( $entries as $entry ) {
			$response[] = new static( $entry );
		}

		return $response;
	}

	protected $_db_data;
	public $id = null;

	public function __construct( ?array $data ) {
		if ( is_array( $data ) ) {
			$this->populate( $data );
		}
	}

	/**
	 * @param $data
	 * @return $this
	 */
	public function populate( $data ) {
		foreach ( $data as $k => $v ) {
			if ( ! property_exists( $this, $k ) ) {
				continue;
			}

			if ( isset( static::$fields[ $k ] ) ) {
				if ( static::$fields[ $k ] === 'int' ) {
					$v = intval( $v );
				} elseif ( static::$fields[ $k ] === 'json' ) {
					if ( is_string( $v ) ) {
						$v = json_decode( $v, true );
					}

					if ( ! is_array( $v ) ) {
						$v = array();
					}
				}
			} elseif ( $k === 'id' ) {
				$v = intval( $v );
			}

			$this->$k = $v;
		}

		return $this;
	}

	public function get() {
		$identifier = static::$identifier;

		if ( empty( $this->id ) && ( empty( $identifier ) || ( is_string( $identifier ) && empty( $this->$identifier ) ) ) ) {
			return;
		}

		$query = 'SELECT * FROM ' . china_payments_wpdb()->prefix . static::$table;

		if ( null !== $this->id ) {
			$query .= ' WHERE id = ' . $this->id;
		} elseif ( is_array( static::$identifier ) ) {
			if ( static::$identifier_any ) {
				$query .= ' WHERE 1 = 2';

				foreach ( static::$identifier as $current_identifier ) {
					if ( ! empty( $this->$current_identifier ) ) {
						$query .= ' OR ' . $current_identifier . ' = ' . china_payments_wpdb()->prepare( '%s', $this->$current_identifier );
					}
				}
			} else {
				$query .= ' WHERE 1 = 1';

				foreach ( static::$identifier as $current_identifier ) {
					$query .= ' AND ' . $current_identifier . ' = ' . china_payments_wpdb()->prepare( '%s', $this->$current_identifier );
				}
			}
		} else {
			$query .= ' WHERE ' . static::$identifier . ' = ' . china_payments_wpdb()->prepare( '%s', $this->$identifier );
		}

		$response = china_payments_wpdb()->get_row( $query, ARRAY_A );

		if ( null === $response ) {
			return;
		}

		$this->_db_data = $response;

		$this->populate( $response );
	}

	public function exists(): bool {
		return ! empty( $this->_db_data );
	}

	/**
	 * @return $this
	 * @throws Exception
	 */
	public function insert() {
		$insert_data = array();

		foreach ( static::$fields as $field_key => $field_type ) {
			$current_field_value = $this->$field_key;

			if ( 'json' === $field_type && is_array( $current_field_value ) ) {
				$current_field_value = json_encode( $current_field_value );
			}

			$insert_data[ $field_key ] = $current_field_value;
		}

		if ( in_array( 'created_at', static::$timestamps ) ) {
			$insert_data['created_at'] = time();
		}

		if ( in_array( 'updated_at', static::$timestamps ) ) {
			$insert_data['updated_at'] = time();
		}

		if ( false !== china_payments_wpdb()->insert( china_payments_wpdb()->prefix . static::$table, $insert_data ) ) {
			$this->id = china_payments_wpdb()->insert_id;
		} else {
			throw new Exception( sprintf( __( 'Failed to insert %s.', 'china-payments' ), static::$table ) );
		}

		return $this;
	}

	/**
	 * @return $this
	 * @throws Exception
	 */
	public function save() {
		if ( $this->id === null ) {
			return $this->insert();
		}

		$update_data = array();

		foreach ( static::$fields as $field_key => $field_type ) {
			$current_field_value = $this->$field_key;

			if ( 'json' === $field_type && is_array( $current_field_value ) ) {
				$current_field_value = json_encode( $current_field_value );
			}

			$update_data[ $field_key ] = $current_field_value;
		}

		foreach ( $update_data as $k => $v ) {
			if ( ! isset( $this->_db_data[ $k ] ) ) {
				continue;
			}

			if ( $this->_db_data[ $k ] == $v ) {
				unset( $update_data[ $k ] );

				continue;
			}

			if ( static::$fields[ $k ] === 'json' ) {
				$test_db_data   = is_array( $v ) ? $v : json_decode( $v, true );
				$test_save_data = is_array( $v ) ? $v : json_decode( $v, true );

				foreach ( $test_save_data as $save_data_key => $save_data_value ) {
					if ( isset( $test_db_data[ $save_data_key ] ) ) {
						if ( is_array( $test_db_data[ $save_data_key ] ) ) {
							$test_db_data[ $save_data_key ] = json_encode( $test_db_data[ $save_data_key ] );
						}

						if ( is_array( $save_data_value ) ) {
							$save_data_value = json_encode( $save_data_value );
						}

						if ( $test_db_data[ $save_data_key ] === $save_data_value ) {
								unset( $test_db_data[ $save_data_key ] );
								unset( $test_save_data[ $save_data_key ] );
						}
					}
				}

				if ( empty( $test_save_data ) && empty( $test_db_data ) ) {
					unset( $update_data[ $k ] );
				}
			}
		}

		if ( empty( $update_data ) ) {
			return $this;
		}

		if ( in_array( 'updated_at', static::$timestamps ) ) {
			$update_data['updated_at'] = time();
		}

		if ( false === china_payments_wpdb()->update( china_payments_wpdb()->prefix . static::$table, $update_data, array( 'id' => $this->id ) ) ) {
			throw new Exception( sprintf( __( 'Failed to update %1$s for id %2$s.', 'china-payments' ), static::$table, $this->id ) );
		}

		return $this;
	}

	public function array(): array {
		$response = array();

		foreach ( static::$fields as $field_key => $field_type ) {
			$response[ $field_key ] = ( $this->$field_key ?? null );
		}

		return $response;
	}
}
