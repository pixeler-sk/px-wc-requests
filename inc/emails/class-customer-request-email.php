<?php
/**
 * Customer confirmation: sent right after a request is submitted.
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Pxer_Customer_Request_Email' ) ) :

	class Pxer_Customer_Request_Email extends WC_Email {

		/** @var \Pixeler\Requests\Request|null */
		public $request = null;

		/** @var string Admin-configured extra text (per request type). */
		public $custom_content = '';

		public function __construct() {
			$this->id             = 'pxer_customer_request';
			$this->customer_email = true;
			$this->title          = __( 'Request confirmation', 'px-wc-requests' );
			$this->description    = __( 'Sent to the customer right after they submit a request.', 'px-wc-requests' );
			$this->template_html  = 'emails/customer-request.php';
			$this->template_plain = 'emails/plain/customer-request.php';
			$this->template_base  = PXER_TEMPLATE_PATH;
			$this->placeholders   = array(
				'{request_type}'   => '',
				'{request_number}' => '',
				'{order_number}'   => '',
			);

			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'We have received your request {request_type} no. {request_number}', 'px-wc-requests' );
		}

		public function get_default_heading() {
			return __( 'We have received your request', 'px-wc-requests' );
		}

		public function trigger( $request_id ) {
			$this->setup_locale();

			$request = pxer_get_request( (int) $request_id );

			if ( $request->exists() ) {
				$order                                  = $request->get_order();
				$data                                   = $request->get_data();
				$this->request                          = $request;
				$this->object                           = $order;
				$this->recipient                        = $data['email'] ?? '';
				$this->placeholders['{request_type}']   = $request->get_type_label();
				$this->placeholders['{request_number}'] = $request->get_id();
				$this->placeholders['{order_number}']   = $order ? $order->get_order_number() : '';
				$this->custom_content                   = \Pixeler\Requests\Settings::get_email_text( $request->get_type() );
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		public function get_content_html() {
			return pxer_get_template_html( $this->template_html, array(
				'request'        => $this->request,
				'custom_content' => $this->get_custom_content(),
				'email_heading'  => $this->get_heading(),
				'sent_to_admin'  => false,
				'plain_text'     => false,
				'email'          => $this,
			) );
		}

		public function get_content_plain() {
			return pxer_get_template_html( $this->template_plain, array(
				'request'        => $this->request,
				'custom_content' => $this->get_custom_content(),
				'email_heading'  => $this->get_heading(),
				'sent_to_admin'  => false,
				'plain_text'     => true,
				'email'          => $this,
			) );
		}

		/**
		 * Custom text with the e-mail placeholders resolved.
		 */
		public function get_custom_content(): string {
			return $this->custom_content ? $this->format_string( $this->custom_content ) : '';
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'px-wc-requests' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'px-wc-requests' ),
					'default' => 'yes',
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

return new Pxer_Customer_Request_Email();
