<?php
/**
 * Customer "note added" email (HTML).
 *
 * @var \Pixeler\Requests\Request $request
 * @var string $customer_note
 * @var string $email_heading
 * @var WC_Email $email
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( $request && $request->exists() ) : ?>
	<p>
		<?php
		printf(
			/* translators: 1: type label, 2: request id */
			esc_html__( 'A note has been added to your request %1$s no. %2$d:', 'px-wc-requests' ),
			esc_html( $request->get_type_label() ),
			(int) $request->get_id()
		);
		?>
	</p>

	<blockquote><?php echo wp_kses_post( wpautop( wptexturize( $customer_note ) ) ); ?></blockquote>

	<?php pxer_render_request_summary( $request ); ?>
<?php else : ?>
	<p><?php esc_html_e( 'This is a sample preview of the email.', 'px-wc-requests' ); ?></p>
	<blockquote><?php esc_html_e( 'Sample note text shown in the preview.', 'px-wc-requests' ); ?></blockquote>
	<?php pxer_render_request_summary_sample(); ?>
<?php endif;

do_action( 'woocommerce_email_footer', $email );
