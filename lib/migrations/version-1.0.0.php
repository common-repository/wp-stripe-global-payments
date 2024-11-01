<?php

ChinaPayments\Migration::instance()->fix_table_structure(true);

echo '<p>' . __( "Database Migration.", "china-payments" ) . '</p>';