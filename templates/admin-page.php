<?php
/**
 * Admin page template.
 *
 * @var array{items: array<int,object>, total: int} $result
 * @var array<string,int> $counts
 * @var int $total
 * @var int $total_pages
 * @var int $page
 * @var string $status
 * @var string $search
 */

defined( 'ABSPATH' ) || exit;

$base_url = admin_url( 'admin.php?page=doec-draft-emails' );
?>
<div class="wrap doec-wrap">
	<h1><?php esc_html_e( 'Draft Order Emails', 'draft-orders-get-email-customers' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Emails captured from customers who started checkout but did not complete their order. WooCommerce deletes draft orders after ~24 hours, so this plugin saves them here for your email marketing.', 'draft-orders-get-email-customers' ); ?>
	</p>

	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Lead deleted.', 'draft-orders-get-email-customers' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['synced'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: 1: drafts scanned, 2: emails captured, 3: drafts without email */
					esc_html__( 'Sync complete: %1$s draft orders scanned, %2$s emails captured, %3$s drafts had no email entered.', 'draft-orders-get-email-customers' ),
					esc_html( isset( $_GET['doec_scanned'] ) ? (string) (int) $_GET['doec_scanned'] : '0' ),
					esc_html( isset( $_GET['doec_captured'] ) ? (string) (int) $_GET['doec_captured'] : '0' ),
					esc_html( isset( $_GET['doec_skipped'] ) ? (string) (int) $_GET['doec_skipped'] : '0' )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="doec-stats">
		<div class="doec-stat-card">
			<span class="doec-stat-number"><?php echo esc_html( (string) $counts['abandoned'] ); ?></span>
			<span class="doec-stat-label"><?php esc_html_e( 'Abandoned', 'draft-orders-get-email-customers' ); ?></span>
		</div>
		<div class="doec-stat-card">
			<span class="doec-stat-number"><?php echo esc_html( (string) $counts['converted'] ); ?></span>
			<span class="doec-stat-label"><?php esc_html_e( 'Later Completed', 'draft-orders-get-email-customers' ); ?></span>
		</div>
		<div class="doec-stat-card">
			<span class="doec-stat-number"><?php echo esc_html( (string) $counts['total'] ); ?></span>
			<span class="doec-stat-label"><?php esc_html_e( 'Total Captured', 'draft-orders-get-email-customers' ); ?></span>
		</div>
	</div>

	<div class="doec-toolbar">
		<form method="get" class="doec-search-form">
			<input type="hidden" name="page" value="doec-draft-emails" />
			<?php if ( $status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
			<?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search email or name…', 'draft-orders-get-email-customers' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'draft-orders-get-email-customers' ); ?></button>
		</form>

		<div class="doec-actions">
			<a href="<?php echo esc_url( add_query_arg( array( 'status' => '' ), $base_url ) ); ?>" class="button <?php echo '' === $status ? 'button-primary' : ''; ?>">
				<?php esc_html_e( 'All', 'draft-orders-get-email-customers' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( array( 'status' => 'abandoned' ), $base_url ) ); ?>" class="button <?php echo 'abandoned' === $status ? 'button-primary' : ''; ?>">
				<?php esc_html_e( 'Abandoned only', 'draft-orders-get-email-customers' ); ?>
			</a>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'doec_sync_now' ); ?>
				<input type="hidden" name="action" value="doec_sync_now" />
				<button type="submit" class="button"><?php esc_html_e( 'Sync All Draft Orders', 'draft-orders-get-email-customers' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'doec_export_csv' ); ?>
				<input type="hidden" name="action" value="doec_export_csv" />
				<?php if ( $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
				<?php endif; ?>
				<?php if ( $search ) : ?>
					<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />
				<?php endif; ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Export CSV', 'draft-orders-get-email-customers' ); ?></button>
			</form>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped doec-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Email', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Name', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Order', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Total', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Status', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Captured', 'draft-orders-get-email-customers' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'draft-orders-get-email-customers' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr>
					<td colspan="8">
						<?php esc_html_e( 'No leads captured yet. They will appear when customers enter their email on checkout (block or classic). Use "Sync Draft Orders Now" to import existing drafts.', 'draft-orders-get-email-customers' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $lead ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $lead->email ); ?></strong></td>
						<td><?php echo esc_html( trim( $lead->first_name . ' ' . $lead->last_name ) ); ?></td>
						<td><?php echo esc_html( $lead->phone ); ?></td>
						<td>
							<?php if ( $lead->order_id ) : ?>
								<a href="<?php echo esc_url( DOEC_Admin::get_order_edit_url( (int) $lead->order_id ) ); ?>">
									#<?php echo esc_html( (string) $lead->order_id ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( function_exists( 'wc_price' ) && $lead->currency ) {
								echo wp_kses_post( wc_price( (float) $lead->order_total, array( 'currency' => $lead->currency ) ) );
							} else {
								echo esc_html( (string) $lead->order_total );
							}
							?>
						</td>
						<td>
							<span class="doec-badge doec-badge--<?php echo esc_attr( $lead->status ); ?>">
								<?php echo esc_html( ucfirst( $lead->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->captured_at ) ); ?></td>
						<td>
							<a class="doec-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=doec_delete_lead&lead_id=' . (int) $lead->id ), 'doec_delete_lead' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this lead?', 'draft-orders-get-email-customers' ) ); ?>');">
								<?php esc_html_e( 'Delete', 'draft-orders-get-email-customers' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %d: number of leads */
						esc_html( _n( '%d item', '%d items', $total, 'draft-orders-get-email-customers' ) ),
						(int) $total
					);
					?>
				</span>
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $page,
							'add_args'  => array_filter(
								array(
									'status' => $status ?: null,
									's'      => $search ?: null,
								)
							),
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
