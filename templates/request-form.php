<?php
/**
 * Request form (withdrawal / claim / any registered type).
 *
 * Override: copy to yourtheme/px-wc-requests/request-form.php
 *
 * @var WC_Order $order
 * @var string   $type
 * @var array    $type_def
 * @var array    $fields
 * @var array    $values
 * @var int[]    $eligible_ids
 * @var DateTimeImmutable|null $deadline
 * @var WP_Post[] $past
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

use Pixeler\Requests\FieldSchema;
?>

<div class="pxer-form-wrap pxer-type-<?php echo esc_attr( $type ); ?>">

	<p>
		<?php esc_html_e( 'Order no.:', 'px-wc-requests' ); ?>
		<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
		&nbsp;|&nbsp;
		<?php esc_html_e( 'Created:', 'px-wc-requests' ); ?>
		<strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong>
		<?php if ( ! empty( $deadline ) ) : ?>
			&nbsp;|&nbsp;
			<?php esc_html_e( 'Deadline:', 'px-wc-requests' ); ?>
			<strong><?php echo esc_html( wp_date( get_option( 'date_format' ), $deadline->getTimestamp() ) ); ?></strong>
		<?php endif; ?>
	</p>

	<?php $pxer_notice = \Pixeler\Requests\Settings::get_legal_notice( $type ); ?>
	<?php if ( $pxer_notice ) : ?>
		<div class="pxer-legal-notice"><?php echo wp_kses_post( wpautop( $pxer_notice ) ); ?></div>
	<?php endif; ?>

	<form class="ajax-form pxer-form" method="post" enctype="multipart/form-data"
	      action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=pxer_submit_request">

		<?php wp_nonce_field( 'pxer_submit_request', '_wpnonce' ); ?>
		<?php \Pixeler\Requests\Security::render_hidden_fields(); ?>
		<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
		<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
		<input type="hidden" name="redirect" value="<?php echo esc_url( add_query_arg( 'pxer_success', '1', get_permalink() ) ); ?>">

		<div class="pxer-fields">
			<?php
			/**
			 * Inject custom HTML before the schema fields (notices, custom blocks…).
			 *
			 * @param WC_Order $order
			 * @param string   $type
			 * @param array    $type_def
			 */
			do_action( 'pxer_request_form_before_fields', $order, $type, $type_def );

			foreach ( $fields as $field ) {
				FieldSchema::render_field(
					$field,
					$values[ $field['key'] ] ?? '',
					$order,
					array( 'eligible_ids' => $eligible_ids )
				);
			}

			/**
			 * Inject custom HTML after the schema fields.
			 *
			 * @param WC_Order $order
			 * @param string   $type
			 * @param array    $type_def
			 */
			do_action( 'pxer_request_form_after_fields', $order, $type, $type_def );
			?>
		</div>

		<div class="ajax-response"></div>

		<button type="submit" class="button pxer-submit"><?php echo esc_html( \Pixeler\Requests\Settings::get_submit_label( $type ) ); ?></button>
	</form>

	<?php if ( ! empty( $past ) ) : ?>
		<div class="pxer-history">
			<h4><?php esc_html_e( 'Previous requests for this order', 'px-wc-requests' ); ?></h4>
			<ul>
				<?php foreach ( $past as $past_post ) :
					$past_request = pxer_get_request( $past_post->ID ); ?>
					<li>
						#<?php echo esc_html( $past_request->get_id() ); ?>
						– <?php echo esc_html( $past_request->get_type_label() ); ?>
						– <strong><?php echo esc_html( $past_request->get_status_label() ); ?></strong>
						<small>(<?php echo esc_html( get_the_date( get_option( 'date_format' ), $past_post ) ); ?>)</small>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

</div>
