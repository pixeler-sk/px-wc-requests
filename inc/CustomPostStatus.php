<?php
/**
 * Generic custom post-status registrar with admin dropdown + bulk actions.
 * Ported and generalised from pixeler-eshop-addons.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class CustomPostStatus {

	public string $slug;
	public array $args;
	public array $post_types;

	public function __construct( string $slug, array $args = array(), array $post_types = array() ) {
		$this->slug = $slug;

		$defaults = array(
			'_show_in_state' => false,
			'_bulk_edit'     => true,
			'_submitbox'     => true, // inject the status into the WP publish box
		);

		$this->args       = wp_parse_args( $args, $defaults );
		$this->post_types = ! empty( $post_types ) ? $post_types : array();

		$this->hooks();
	}

	private function hooks(): void {
		add_action( 'init', array( $this, 'register_status' ) );

		if ( ! empty( $this->args['_submitbox'] ) ) {
			add_action( 'post_submitbox_misc_actions', array( $this, 'add_to_post_status_dropdown' ) );
		}

		if ( ! empty( $this->args['_show_in_state'] ) ) {
			add_filter( 'display_post_states', array( $this, 'add_to_post_states' ) );
		}

		foreach ( $this->post_types as $post_type ) {
			if ( ! empty( $this->args['_bulk_edit'] ) ) {
				add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'status_bulk_actions_edit' ), 20, 1 );
				add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'status_handle_bulk_action_edit' ), 10, 3 );
			}
		}

		if ( ! empty( $this->args['_bulk_edit'] ) ) {
			add_action( 'admin_notices', array( $this, 'status_bulk_action_admin_notice' ) );
		}
	}

	public function register_status(): void {
		register_post_status( $this->slug, $this->args );
	}

	public function add_to_post_status_dropdown(): void {
		global $post;
		if ( ! $post || ! in_array( $post->post_type, $this->post_types, true ) ) {
			return;
		}
		?>
		<script>
			jQuery(function ($) {
				$('select[name="post_status"]').append('<option value="<?php echo esc_js( $this->slug ); ?>"><?php echo esc_js( $this->args['label'] ); ?></option>');
				<?php if ( $post->post_status === $this->slug ) : ?>
				$('#post-status-display').text('<?php echo esc_js( $this->args['label'] ); ?>');
				$('select[name="post_status"]').val('<?php echo esc_js( $this->slug ); ?>');
				<?php endif; ?>
			});
		</script>
		<?php
	}

	public function add_to_post_states( array $states ): array {
		global $post;
		$arg = get_query_var( 'post_status' );

		if ( $arg !== $this->slug && $post && $post->post_status === $this->slug ) {
			return array( $this->args['label'] );
		}

		return $states;
	}

	public function status_bulk_actions_edit( array $actions ): array {
		/* translators: %s: status label */
		$actions[ 'mark_' . $this->slug ] = sprintf( __( 'Change status to: %s', 'px-wc-requests' ), $this->args['label'] );

		return $actions;
	}

	public function status_handle_bulk_action_edit( string $redirect_to, string $action, array $post_ids ): string {
		if ( $action !== 'mark_' . $this->slug ) {
			return $redirect_to;
		}

		$processed = 0;
		foreach ( $post_ids as $post_id ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => $this->slug,
			) );
			$processed++;
		}

		return add_query_arg( array( 'marked_' . $this->slug => $processed ), $redirect_to );
	}

	public function status_bulk_action_admin_notice(): void {
		$key = 'marked_' . $this->slug;
		if ( empty( $_REQUEST[ $key ] ) ) {
			return;
		}

		$count = absint( $_REQUEST[ $key ] );
		printf(
			'<div id="message" class="updated fade"><p>%s</p></div>',
			esc_html( sprintf( _n( '%s request processed.', '%s requests processed.', $count, 'px-wc-requests' ), $count ) )
		);
	}
}
