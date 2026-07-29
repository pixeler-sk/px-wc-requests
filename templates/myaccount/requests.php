<?php
/**
 * "My requests" account tab.
 *
 * Override: copy to yourtheme/px-wc-requests/myaccount/requests.php
 *
 * @var WP_Post[] $requests
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( empty( $requests ) ) : ?>
	<p><?php esc_html_e( 'You have no requests yet.', 'px-wc-requests' ); ?></p>
<?php else : ?>
	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
		<thead>
		<tr>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-request-number"><span class="nobr"><?php esc_html_e( 'Request', 'px-wc-requests' ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-request-type"><span class="nobr"><?php esc_html_e( 'Type', 'px-wc-requests' ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-request-date"><span class="nobr"><?php esc_html_e( 'Date', 'px-wc-requests' ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-request-status"><span class="nobr"><?php esc_html_e( 'Status', 'px-wc-requests' ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-request-order"><span class="nobr"><?php esc_html_e( 'Order', 'px-wc-requests' ); ?></span></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $requests as $post ) :
			$request = pxer_get_request( $post->ID );
			$order   = $request->get_order();
			?>
			<tr class="woocommerce-orders-table__row order">
				<th class="woocommerce-orders-table__cell woocommerce-orders-table__cell-request-number" data-title="<?php esc_attr_e( 'Request', 'px-wc-requests' ); ?>" scope="row">
					<a href="<?php echo esc_url( \Pixeler\Requests\MyAccount::detail_url( $request->get_id() ) ); ?>">#<?php echo esc_html( $request->get_id() ); ?></a>
				</th>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-request-type" data-title="<?php esc_attr_e( 'Type', 'px-wc-requests' ); ?>"><?php echo esc_html( $request->get_type_label() ); ?></td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-request-date" data-title="<?php esc_attr_e( 'Date', 'px-wc-requests' ); ?>"><?php echo esc_html( get_the_date( get_option( 'date_format' ), $post ) ); ?></td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-request-status" data-title="<?php esc_attr_e( 'Status', 'px-wc-requests' ); ?>"><?php echo esc_html( $request->get_status_label() ); ?></td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-request-order" data-title="<?php esc_attr_e( 'Order', 'px-wc-requests' ); ?>">
					<?php if ( $order ) : ?>
						<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $request->get_order_id() ); ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
