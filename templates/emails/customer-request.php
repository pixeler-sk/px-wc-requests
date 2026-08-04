<?php
/**
 * Customer confirmation email (HTML).
 *
 * @var \Pixeler\Requests\Request $request
 * @var string $custom_content
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
			esc_html__( 'We have received your request %1$s no. %2$d and will get back to you soon.', 'px-wc-requests' ),
			esc_html( $request->get_type_label() ),
			(int) $request->get_id()
		);
		?>
	</p>

	<?php pxer_render_email_custom_content( $custom_content ?? '' ); ?>

	<?php pxer_render_request_summary( $request ); ?>
<?php else : ?>
	<p><?php esc_html_e( 'This is a sample preview of the email.', 'px-wc-requests' ); ?></p>
	<?php pxer_render_request_summary_sample(); ?>
<?php endif;

do_action( 'woocommerce_email_footer', $email );
