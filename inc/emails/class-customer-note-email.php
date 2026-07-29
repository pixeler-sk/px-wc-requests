<?php
/**
 * Customer notification: a note was added to their request.
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Pxer_Customer_Note_Email' ) ) :

	class Pxer_Customer_Note_Email extends WC_Email {

		/** @var \Pixeler\Requests\Request|null */
		public $request = null;

		/** @var string */
		public $customer_note = '';

		public function __construct() {
			$this->id             = 'pxer_customer_note';
			$this->customer_email = true;
			$this->title          = __( 'Request note', 'px-wc-requests' );
			$this->description    = __( 'Sent to the customer when you add a note addressed to them.', 'px-wc-requests' );
			// Named request-note (not customer-note) to avoid clashing with the WC
			// core template of the same name in the pxee template-override map.
			$this->template_html  = 'emails/request-note.php';
			$this->template_plain = 'emails/plain/request-note.php';
			$this->template_base  = PXER_TEMPLATE_PATH;
			$this->placeholders   = array(
				'{request_type}'   => '',
				'{request_number}' => '',
				'{order_number}'   => '',
			);

			// Triggered explicitly from Emails::on_customer_note() so it also works
			// in admin-ajax, where the WC mailer is not yet loaded.
			parent::__construct();
		}

		public function get_default_subject() {
			return __( 'A note has been added to your request {request_type} no. {request_number}', 'px-wc-requests' );
		}

		public function get_default_heading() {
			return __( 'A note regarding your request', 'px-wc-requests' );
		}

		public function trigger( $request_id, $note = '' ) {
			$this->setup_locale();

			$request = pxer_get_request( (int) $request_id );

			if ( $request->exists() ) {
				$order                                  = $request->get_order();
				$data                                   = $request->get_data();
				$this->request                          = $request;
				$this->object                           = $order;
				$this->customer_note                    = $note;
				$this->recipient                        = $data['email'] ?? '';
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
				'customer_note' => $this->customer_note,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			) );
		}

		public function get_content_plain() {
			return pxer_get_template_html( $this->template_plain, array(
				'request'       => $this->request,
				'customer_note' => $this->customer_note,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
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

return new Pxer_Customer_Note_Email();
