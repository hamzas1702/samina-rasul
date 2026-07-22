<?php
/**
 * Plugin Name: Samina Rasul Core
 * Description: Store business logic — Collection taxonomy, delivery-time field, fabric add-ons, Bridals inquiry flow. Theme-independent.
 * Version: 0.1.0
 * Author: Samina Rasul build
 */

defined( 'ABSPATH' ) || exit;

define( 'SR_CORE_DIR', __DIR__ . '/samina-core' );

require SR_CORE_DIR . '/taxonomy.php';
require SR_CORE_DIR . '/product-fields.php';
require SR_CORE_DIR . '/fabric-addons.php';
require SR_CORE_DIR . '/bridal-flow.php';
require SR_CORE_DIR . '/order-terms.php';
