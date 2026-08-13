<?php
/**
 * Plugin Name: Samina Rasul — Action Scheduler SQLite compatibility
 * Description: Makes Action Scheduler's claim query work on the local SQLite dev stack. Inert on MySQL/MariaDB.
 * Author: Samina Rasul build
 *
 * Action Scheduler's ActionScheduler_DBStore::claim_actions() issues a MySQL-only
 * "UPDATE ... JOIN ( SELECT ... FOR UPDATE SKIP LOCKED ) ..." statement. The SQLite
 * driver cannot translate it, so no batch is ever claimed, no action ever runs, and
 * past-due actions pile up forever. This swaps in a store that performs the same
 * selection as a plain SELECT followed by an UPDATE ... WHERE action_id IN (...).
 *
 * Row locking is dropped deliberately: SQLite serialises writers at the database
 * level, so the FOR UPDATE / SKIP LOCKED semantics have no equivalent and no
 * purpose here. Production runs MySQL, where this file does nothing at all.
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the site is running on the SQLite database integration drop-in.
 *
 * @return bool
 */
function sr_is_sqlite_db() {
	global $wpdb;

	return $wpdb instanceof WP_SQLite_DB;
}

/**
 * Point Action Scheduler at the SQLite-compatible store.
 *
 * Registered below priority 100 so Action Scheduler's own migration controller
 * (which hooks at 100) sees this as a custom data store, uses it directly, and
 * skips the wpPost -> custom-table migration that cannot complete here either.
 *
 * @param string $class Store class name.
 * @return string
 */
function sr_action_scheduler_sqlite_store( $class ) {
	if ( ! sr_is_sqlite_db() || ! class_exists( 'ActionScheduler_DBStore' ) ) {
		return $class;
	}

	// Only substitute for the stock stores; respect any other custom store.
	if ( ! in_array( $class, array( 'ActionScheduler_DBStore', 'ActionScheduler_wpPostStore', 'ActionScheduler_HybridStore' ), true ) ) {
		return $class;
	}

	require_once __DIR__ . '/sr-sqlite-as/store.php';

	return 'SR_SQLite_ActionScheduler_Store';
}
add_filter( 'action_scheduler_store_class', 'sr_action_scheduler_sqlite_store', 20 );
