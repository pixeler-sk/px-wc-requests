<?php
/**
 * Email wiring: registers the WC email classes, attaches request images to the
 * admin email and triggers the customer email on status change.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Emails {

	/** Email ids handled by this plugin. */
	private const EMAIL_IDS = array(
		'pxer_admin_request',
		'pxer_customer_request',
		'pxer_customer_status',
		'pxer_customer_note',
	);

	public function setup(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach_images' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );

		// Append request-form links to selected customer emails.
		add_action( 'woocommerce_email_order_meta', array( $this, 'inject_links' ), 20, 4 );

		// Customer note → e-mail. Triggered explicitly here (not from the email
		// class constructor) so it works in admin-ajax where the WC mailer is not
		// yet loaded.
		add_action( 'pxer_request_customer_note_notification', array( $this, 'on_customer_note' ), 10, 2 );

		// Populate our emails with the latest real request for the WC preview.
		add_filter( 'woocommerce_prepare_email_for_preview', array( $this, 'prepare_preview' ) );

		// E-mail delivery log belongs to the request, not to the order: record
		// every send attempt as an internal request note and keep WooCommerce
		// (10.9+ EmailLogger) from adding the same note to the order history.
		add_action( 'woocommerce_email_sent', array( $this, 'log_sent_email' ), 10, 3 );
		add_filter( 'woocommerce_email_log_add_order_note', array( $this, 'suppress_order_note' ), 10, 2 );
	}

	/**
	 * Record a sent/failed request e-mail as an internal note on the request.
	 *
	 * @param bool      $success
	 * @param string    $email_id
	 * @param \WC_Email $email
	 */
	public function log_sent_email( $success, $email_id, $email ): void {
		if ( ! in_array( (string) $email_id, self::EMAIL_IDS, true ) ) {
			return;
		}

		$request = $email->request ?? null;
		if ( ! $request instanceof Request || ! $request->exists() ) {
			return;
		}

		$recipient = (string) $email->get_recipient();
		$title     = (string) $email->get_title();

		if ( $success ) {
			$note = sprintf(
				/* translators: 1: e-mail title, 2: recipient address */
				__( 'E-mail "%1$s" sent to %2$s.', 'px-wc-requests' ),
				$title,
				$recipient
			);
		} else {
			$note = sprintf(
				/* translators: 1: e-mail title, 2: recipient address */
				__( 'E-mail "%1$s" to %2$s failed to send.', 'px-wc-requests' ),
				$title,
				$recipient
			);
		}

		RequestNotes::add_note( $request->get_id(), $note, false );
	}

	/**
	 * Keep WooCommerce from logging our e-mails into the order notes; the
	 * request history (see log_sent_email) is the single place for them.
	 *
	 * @param bool   $enabled
	 * @param string $email_id
	 * @return bool
	 */
	public function suppress_order_note( $enabled, $email_id ) {
		return in_array( (string) $email_id, self::EMAIL_IDS, true ) ? false : $enabled;
	}

	/**
	 * Send the customer-note e-mail. Forces the WC mailer to load so the email
	 * object exists regardless of request context.
	 *
	 * @param int    $request_id
	 * @param string $note
	 */
	public function on_customer_note( $request_id, $note = '' ): void {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['Pxer_Customer_Note_Email'] ) ) {
			$emails['Pxer_Customer_Note_Email']->trigger( (int) $request_id, (string) $note );
		}
	}

	/**
	 * Append a link to the request form(s) at the end of selected customer
	 * emails. Configured per type in the settings; only shown when the form page
	 * exists and the order is still eligible.
	 *
	 * @param \WC_Order $order
	 * @param bool      $sent_to_admin
	 * @param bool      $plain_text
	 * @param \WC_Email $email
	 */
	public function inject_links( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( $sent_to_admin || ! $order instanceof \WC_Order || ! is_object( $email ) ) {
			return;
		}

		$links = array();
		foreach ( RequestTypes::all() as $id => $type ) {
			$selected = Settings::get_inject_emails( $id );
			if ( ! in_array( $email->id, $selected, true ) ) {
				continue;
			}
			$page_id = Settings::get_page_id( $id );
			if ( ! $page_id ) {
				continue;
			}
			if ( is_wp_error( Eligibility::gate( $order, $id ) ) ) {
				continue;
			}
			$page = get_permalink( $page_id );
			if ( ! $page ) {
				continue;
			}
			$links[] = array(
				'label' => Settings::get_button_label( $id ),
				'url'   => add_query_arg( 'key', $order->get_order_key(), $page ),
			);
		}

		if ( ! $links ) {
			return;
		}

		$intro = __( 'Need to return or claim items from this order?', 'px-wc-requests' );

		if ( $plain_text ) {
			echo "\n" . esc_html( $intro ) . "\n";
			foreach ( $links as $link ) {
				echo esc_html( $link['label'] ) . ': ' . esc_url_raw( $link['url'] ) . "\n";
			}

			return;
		}

		echo '<p style="margin-top:16px">' . esc_html( $intro ) . '<br>';
		foreach ( $links as $link ) {
			echo '<a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a> &nbsp; ';
		}
		echo '</p>';
	}

	/**
	 * When WooCommerce renders an email preview there is no request object, so
	 * inject the most recent real one. If none exists the template falls back to
	 * a sample summary, so the preview is never empty.
	 *
	 * @param \WC_Email $email
	 *
	 * @return \WC_Email
	 */
	public function prepare_preview( $email ) {
		if ( ! is_object( $email ) || ! in_array( $email->id, self::EMAIL_IDS, true ) ) {
			return $email;
		}

		$latest = get_posts( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'numberposts' => 1,
			'post_status' => 'any',
			'fields'      => 'ids',
		) );

		if ( ! $latest ) {
			return $email;
		}

		$request = pxer_get_request( (int) $latest[0] );
		if ( ! $request->exists() ) {
			return $email;
		}

		$email->request = $request;
		$email->object  = $request->get_order();

		if ( property_exists( $email, 'new_status_label' ) ) {
			$email->new_status_label = $request->get_status_label();
		}
		if ( property_exists( $email, 'custom_content' ) ) {
			$email->custom_content = Settings::get_email_text(
				$request->get_type(),
				'pxer_customer_status' === $email->id ? $request->get_status() : ''
			);
		}
		if ( property_exists( $email, 'customer_note' ) ) {
			$email->customer_note = __( 'Sample note text shown in the preview.', 'px-wc-requests' );
		}

		return $email;
	}

	public function register_emails( array $classes ): array {
		$classes['Pxer_Admin_Request_Email']    = include PXER_PATH . 'inc/emails/class-admin-request-email.php';
		$classes['Pxer_Customer_Request_Email'] = include PXER_PATH . 'inc/emails/class-customer-request-email.php';
		$classes['Pxer_Customer_Status_Email']  = include PXER_PATH . 'inc/emails/class-customer-status-email.php';
		$classes['Pxer_Customer_Note_Email']    = include PXER_PATH . 'inc/emails/class-customer-note-email.php';

		return $classes;
	}

	/**
	 * Attach request images to the admin notification email.
	 */
	public function attach_images( array $attachments, ?string $email_id, $object, $email = null ): array {
		if ( 'pxer_admin_request' !== $email_id || ! isset( $email->request ) ) {
			return $attachments;
		}

		foreach ( $email->request->get_images() as $image_id ) {
			$file = get_attached_file( $image_id );
			if ( $file ) {
				$attachments[] = $file;
			}
		}

		return $attachments;
	}

	/**
	 * Notify the customer when a request changes status.
	 */
	public function on_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( RequestPostType::POST_TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		// Only notify on real transitions between request statuses. Creation
		// (old status is not a request status) is covered by the confirmation
		// email triggered in RequestController::after_success().
		$statuses = RequestTypes::all_statuses();
		if ( ! isset( $statuses[ $new_status ] ) || ! isset( $statuses[ $old_status ] ) ) {
			return;
		}

		$request = pxer_get_request( $post->ID );
		$type    = $request->get_type();
		$data    = $request->get_data();

		if ( empty( $data['email'] ) || ! RequestTypes::exists( $type ) ) {
			return;
		}

		$type_def = RequestTypes::get( $type );
		if ( empty( $type_def['emails']['customer_status_change'] ) ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['Pxer_Customer_Status_Email'] ) ) {
			$emails['Pxer_Customer_Status_Email']->trigger( $post->ID, $new_status );
		}
	}
}
