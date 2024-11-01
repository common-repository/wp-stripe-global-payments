<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe\Structure;

use WC_Order;
use Automattic\WooCommerce\Blocks\StoreApi\Routes\RouteException;

interface Subscription {

	public function order_charge_using_setup_intent( \WC_Order $order );
}
