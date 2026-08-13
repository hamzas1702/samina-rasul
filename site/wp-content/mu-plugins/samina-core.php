<?php
/**
 * Plugin Name: Samina Rasul Core
 * Description: Store business logic — Collection taxonomy, delivery-time field, fabric add-ons, Bridals inquiry flow. Theme-independent.
 * Version: 0.1.0
 * Author: Samina Rasul build
 */

defined( 'ABSPATH' ) || exit;

define( 'SR_CORE_DIR', __DIR__ . '/samina-core' );

/*
 * Load every module in samina-core/, alphabetically.
 *
 * This loader lives in the mu-plugins root, which the deploy pipeline does not
 * sync (only samina-core/ itself is rsynced), so a hardcoded require list would
 * go stale on live the moment a module is added. Globbing keeps the two in step.
 *
 * Modules must be order-independent: register hooks, run nothing at load time.
 */
$sr_core_modules = glob( SR_CORE_DIR . '/*.php' );

if ( is_array( $sr_core_modules ) ) {
	sort( $sr_core_modules, SORT_STRING );

	foreach ( $sr_core_modules as $sr_core_module ) {
		require_once $sr_core_module;
	}
}

unset( $sr_core_modules, $sr_core_module );
