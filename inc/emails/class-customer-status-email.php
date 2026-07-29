<?php
/**
 * Customer notification: request status has changed.
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Pxer_Customer_Status_Email' ) ) :

	class Pxer_Customer_Status_Email extends WC_Email {

		/** @var \Pixeler\Requests\Request|null */
		public $request = null;

		/** @var string */
		public $new_status_label = '';

		public function __construct() {
			$this->id             = 'pxer_customer_status';
			$this->customer_email = true;
			$this->title          = __( 'Request status change', 'px-wc-requests' );
			$this->description    = __( 'Customer notification when their request status changes.', 'px-wc-requests' );
			$this->template_html  = 'emails/customer-status.php';
			$this->template_plain = 'emails/plain/customer-status.php';
			$this->template_base  = PXER_TEMPLATE_PATH;
			$this->placeholders   = array(
				'{request_type}'   => '',
				'{request_number}' => '',
				'{order_number}'   => '',
				'{request_status}' => '',
			);

			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'Status of request {request_type} no. {request_number} has changed', 'px-wc-requests' );
		}

		public function get_default_heading() {
			return __( 'Status of request {request_type} no. {request_number} has changed', 'px-wc-requests' );
		}

		public function trigger( $request_id, $new_status = '' ) {
			$this->setup_locale();

			$request = pxer_get_request( (int) $request_id );

			if ( $request->exists() ) {
				$order                                  = $request->get_order();
				$data                                   = $request->get_data();
				$this->request                          = $request;
				$this->object                           = $order;
				$this->new_status_label                 = $new_status ? \Pixeler\Requests\RequestTypes::status_label( $new_status ) : $request->get_status_label();
				$this->recipient                        = $data['email'] ?? '';
				$this->placeholders['{request_type}']   = $request->get_type_label();
				$this->placeholders['{request_number}'] = $request->get_id();
				$this->placeholders['{order_number}']   = $order ? $order->get_order_number() : '';
				$this->placeholders['{request_status}'] = $this->new_status_label;
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		public function get_content_html() {
			return pxer_get_template_html( $this->template_html, array(
				'request'          => $this->request,
				'new_status_label' => $this->new_status_label,
				'email_heading'    => $this->get_heading(),
				'sent_to_admin'    => false,
				'plain_text'       => false,
				'email'            => $this,
			) );
		}

		public function get_content_plain() {
			return pxer_get_template_html( $this->template_plain, array(
				'request'          => $this->request,
				'new_status_label' => $this->new_status_label,
				'email_heading'    => $this->get_heading(),
				'sent_to_admin'    => false,
				'plain_text'       => true,
				'email'            => $this,
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

return new Pxer_Customer_Status_Email();
