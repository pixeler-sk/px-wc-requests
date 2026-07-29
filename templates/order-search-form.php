<?php
/**
 * Order search form (step 1 of the request flow).
 *
 * Override: copy to yourtheme/px-wc-requests/order-search-form.php
 *
 * @var string $type
 * @var array  $type_def
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="pxer-search-wrap pxer-type-<?php echo esc_attr( $type ); ?>">

	<?php
	if ( function_exists( 'wc_print_notice' ) && isset( $_GET['pxer_success'] ) ) {
		wc_print_notice( __( 'Your request has been submitted successfully.', 'px-wc-requests' ), 'success' );
	}
	if ( function_exists( 'wc_print_notices' ) ) {
		wc_print_notices();
	}
	?>

	<p><?php esc_html_e( 'To continue, enter your e-mail and order number.', 'px-wc-requests' ); ?></p>

	<form class="pxer-search-form" action="" method="get">
		<p class="pxer-field pxer-field-full">
			<label for="pxer-search-email"><?php esc_html_e( 'E-mail', 'px-wc-requests' ); ?></label>
			<input id="pxer-search-email" type="email" name="email" required
			       value="<?php echo isset( $_REQUEST['email'] ) ? esc_attr( sanitize_email( wp_unslash( $_REQUEST['email'] ) ) ) : ''; ?>">
		</p>
		<p class="pxer-field pxer-field-full">
			<label for="pxer-search-order"><?php esc_html_e( 'Order number', 'px-wc-requests' ); ?></label>
			<input id="pxer-search-order" type="text" name="order_number" required
			       value="<?php echo isset( $_REQUEST['order_number'] ) ? esc_attr( absint( $_REQUEST['order_number'] ) ) : ''; ?>">
		</p>
		<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Continue', 'px-wc-requests' ); ?></button>
	</form>

</div>
