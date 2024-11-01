<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div style="max-width:420px;margin:20px auto;">
  <?php
    require_once __DIR__ . '/woocommerce-payment-handler.php';
  ?>
</div>

<?php wp_footer(); ?>
</body>
</html>