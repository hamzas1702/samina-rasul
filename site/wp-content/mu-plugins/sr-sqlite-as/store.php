<?php
/**
 * SQLite-compatible Action Scheduler store.
 *
 * Loaded on demand by sr-sqlite-action-scheduler.php (kept out of the mu-plugins
 * root so WordPress does not auto-load it before Action Scheduler's classes exist).
 *
 * @package samina-rasul
 */

defined( 'ABSPATH' ) || exit;

/**
 * Custom-table store whose batch claim works on SQLite.
 *
 * Mirrors ActionScheduler_DBStore::claim_actions() selection semantics exactly —
 * same WHERE clause, same claim filters, same order-by filter — but performs the
 * claim as SELECT-then-UPDATE instead of MySQL's UPDATE ... JOIN ( ... FOR UPDATE ).
 */
class SR_SQLite_ActionScheduler_Store extends ActionScheduler_DBStore {

	/**
	 * Mark actions claimed.
	 *
	 * @param string        $claim_id    Claim Id.
	 * @param int           $limit       Number of action to include in claim.
	 * @param DateTime|null $before_date Should use UTC timezone.
	 * @param array         $hooks       Hooks to filter for.
	 * @param string        $group       Group to filter for.
	 *
	 * @return int The number of actions that were claimed.
	 * @throws InvalidArgumentException When the group is invalid.
	 * @throws RuntimeException When there is a database error.
	 */
	protected function claim_actions( $claim_id, $limit, ?DateTime $before_date = null, $hooks = array(), $group = '' ) {
		/**
		 * Global WordPress database object.
		 *
		 * @var wpdb $wpdb
		 */
		global $wpdb;

		$now  = as_get_datetime_object();
		$date = is_null( $before_date ) ? $now : clone $before_date;

		// Set claim filters.
		if ( ! empty( $hooks ) ) {
			$this->set_claim_filter( 'hooks', $hooks );
		} else {
			$hooks = $this->get_claim_filter( 'hooks' );
		}
		if ( ! empty( $group ) ) {
			$this->set_claim_filter( 'group', $group );
		} else {
			$group = $this->get_claim_filter( 'group' );
		}

		$where        = 'WHERE claim_id = 0 AND scheduled_date_gmt <= %s AND status = %s';
		$where_params = array(
			$date->format( 'Y-m-d H:i:s' ),
			self::STATUS_PENDING,
		);

		if ( ! empty( $hooks ) ) {
			$placeholders  = array_fill( 0, count( $hooks ), '%s' );
			$where        .= ' AND hook IN (' . join( ', ', $placeholders ) . ')';
			$where_params  = array_merge( $where_params, array_values( $hooks ) );
		}

		$group_operator = 'IN';
		if ( empty( $group ) ) {
			$group          = $this->get_claim_filter( 'exclude-groups' );
			$group_operator = 'NOT IN';
		}

		if ( ! empty( $group ) ) {
			$group_ids = $this->get_group_ids( $group, false );

			// Throw exception if no matching group(s) found, matching ActionScheduler_DBStore's behaviour.
			if ( empty( $group_ids ) ) {
				throw new InvalidArgumentException(
					sprintf(
						/* translators: %s: group name(s) */
						_n(
							'The group "%s" does not exist.',
							'The groups "%s" do not exist.',
							is_array( $group ) ? count( $group ) : 1,
							'woocommerce'
						),
						is_array( $group ) ? implode( ', ', $group ) : $group
					)
				);
			}

			$id_list = implode( ',', array_map( 'intval', $group_ids ) );
			$where  .= " AND group_id {$group_operator} ( $id_list )";
		}

		/** This filter is documented in Action Scheduler's ActionScheduler_DBStore::claim_actions(). */
		$order = apply_filters( 'action_scheduler_claim_actions_order_by', 'ORDER BY priority ASC, attempts ASC, scheduled_date_gmt ASC, action_id ASC', $claim_id, $hooks );

		// SQLite has no row-level locking to skip, so select the candidate ids first...
		$action_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT action_id FROM {$wpdb->actionscheduler_actions} {$where} {$order} LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $where_params, array( $limit ) )
			)
		);

		if ( ! is_array( $action_ids ) ) {
			throw new RuntimeException( $this->claim_error_message() );
		}

		if ( empty( $action_ids ) ) {
			return 0;
		}

		// ...then stake the claim on exactly those rows, re-checking claim_id to stay safe
		// against a concurrent runner that claimed them between the two statements.
		$id_list = implode( ',', array_map( 'intval', $action_ids ) );

		$rows_affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->actionscheduler_actions} SET claim_id = %d, last_attempt_gmt = %s, last_attempt_local = %s WHERE claim_id = 0 AND action_id IN ( $id_list )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$claim_id,
				$now->format( 'Y-m-d H:i:s' ),
				current_time( 'mysql' )
			)
		);

		if ( false === $rows_affected ) {
			throw new RuntimeException( $this->claim_error_message() );
		}

		return (int) $rows_affected;
	}

	/**
	 * Build the claim failure message from the last database error.
	 *
	 * @return string
	 */
	private function claim_error_message() {
		global $wpdb;

		$error = empty( $wpdb->last_error )
			? _x( 'unknown', 'database error', 'woocommerce' )
			: $wpdb->last_error;

		/* translators: %s database error. */
		return sprintf( __( 'Unable to claim actions. Database error: %s.', 'woocommerce' ), $error );
	}
}
