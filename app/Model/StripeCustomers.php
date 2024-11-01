<?php

namespace ChinaPayments\Model;

class StripeCustomers extends Skeleton {

	public static $table      = CHINA_PAYMENTS_TABLE_STRIPE_CUSTOMERS;
	public static $fields     = array(
		'email_address'     => 'string',
		'stripe_id'         => 'string',
		'stripe_account_id' => 'string',
		'is_live'           => 'int',
	);
	public static $identifier = array( 'email_address', 'stripe_account_id', 'is_live' );
	public static $timestamps = array( 'created_at', 'updated_at' );

	public $email_address     = '';
	public $stripe_id         = '';
	public $stripe_account_id = '';
	public $is_live           = 0;
}
