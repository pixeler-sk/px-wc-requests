<?php
/**
 * Admin notification: a new request was submitted.
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Pxer_Admin_Request_Email' ) ) :

	class Pxer_Admin_Request_Email extends WC_Email {

		/** @var \Pixeler\Requests\Request|null */
		public $request = null;

		public function __construct() {
			$this->id             = 'pxer_admin_request';
			$this->title          = __( 'New request (admin)', 'px-wc-requests' );
			$this->description    = __( 'Admin notification about a new customer request (withdrawal / claim).', 'px-wc-requests' );
			$this->template_html  = 'emails/admin-request.php';
			$this->template_plain = 'emails/plain/admin-request.php';
			$this->template_base  = PXER_TEMPLATE_PATH;
			$this->placeholders   = array(
				'{request_type}'   => '',
				'{request_number}' => '',
				'{order_number}'   => '',
			);

			parent::__construct();

			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		public function get_default_subject() {
			return __( 'New request: {request_type} no. {request_number}', 'px-wc-requests' );
		}

		public function get_default_heading() {
			return __( 'New request: {request_type} no. {request_number}', 'px-wc-requests' );
		}

		public function trigger( $request_id ) {
			$this->setup_locale();

			$request = pxer_get_request( (int) $request_id );

			if ( $request->exists() ) {
				$order                                  = $request->get_order();
				$this->request                          = $request;
				$this->object                           = $order;
				$this->placeholders['{request_type}']   = $request->get_type_label();
				$this->placeholders['{request_number}'] = $request->get_id();
				$this->placeholders['{order_number}']   = $order ? $order->get_order_number() : '';
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		public function get_content_html() {
			return pxer_get_template_html( $this->template_html, array(
				'request'       => $this->request,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => false,
				'email'         => $this,
			) );
		}

		public function get_content_plain() {
			return pxer_get_template_html( $this->template_plain, array(
				'request'       => $this->request,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => true,
				'email'         => $this,
			) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'px-wc-requests' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'px-wc-requests' ),
					'default' => 'yes',
				),
				'recipient'  => array(
					'title'       => __( 'Recipient(s)', 'px-wc-requests' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Comma-separated emails. Defaults to %s.', 'px-wc-requests' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'    => array(
					'title'       => __( 'Subject', 'px-wc-requests' ),
					'type'        => 'text',
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'    => array(
					'title'       => __( 'Email heading', 'px-wc-requests' ),
					'type'        => 'text',
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'email_type' => array(
					'title'   => __( 'Email type', 'px-wc-requests' ),
					'type'    => 'select',
					'default' => 'html',
					'class'   => 'email_type wc-enhanced-select',
					'options' => $this->get_email_type_options(),
				),
			);
		}
	}

endif;

return new Pxer_Admin_Request_Email();
