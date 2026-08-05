<?php
/**
 * WooCommerce settings tab: maps each request type to the page that hosts its
 * form shortcode and configures the legal period (length + starting order
 * statuses). Settings auto-expand for every registered type.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Settings {

	const TAB_ID = 'pxer_requests';

	/** Nonce action / AJAX action for the one-click "create form page" tool. */
	const CREATE_PAGE_NONCE = 'pxer_create_form_page';

	public function setup(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_tab' ), 50 );
		add_filter( 'plugin_action_links_' . plugin_basename( PXER_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'woocommerce_settings_tabs_' . self::TAB_ID, array( $this, 'render_tab' ) );
		add_action( 'woocommerce_update_options_' . self::TAB_ID, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
		add_action( 'wp_ajax_' . self::CREATE_PAGE_NONCE, array( $this, 'ajax_create_page' ) );
		add_filter( 'display_post_states', array( $this, 'add_post_state' ), 10, 2 );
		add_action( 'woocommerce_admin_field_pxer_editor', array( $this, 'render_editor_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'sanitize_editor_option' ), 10, 3 );
	}

	/**
	 * HTML allowed in pxer_editor settings — basic formatting, no images.
	 */
	private const EDITOR_ALLOWED_HTML = array(
		'a'          => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
		'p'          => array( 'style' => true ),
		'br'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		'del'        => array(),
		'span'       => array( 'style' => true ),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'blockquote' => array(),
	);

	/**
	 * Render the `pxer_editor` settings field type: a minimal WYSIWYG editor
	 * (teeny toolbar, no media buttons).
	 *
	 * @param array $field WC settings field definition.
	 */
	public function render_editor_field( array $field ): void {
		$value = \WC_Admin_Settings::get_option( $field['id'], (string) ( $field['default'] ?? '' ) );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['title'] ?? '' ); ?></label>
			</th>
			<td class="forminp forminp-pxer_editor">
				<?php
				wp_editor(
					$value,
					sanitize_key( $field['id'] ),
					array(
						'textarea_name' => $field['id'],
						'textarea_rows' => 6,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => false,
					)
				);
				?>
				<?php if ( ! empty( $field['desc'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $field['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sanitize `pxer_editor` fields on save — WC's default for unknown types is
	 * wc_clean(), which would strip all HTML.
	 *
	 * @param mixed $value     Sanitized value so far.
	 * @param array $option    Field definition.
	 * @param mixed $raw_value Raw submitted value.
	 * @return mixed
	 */
	public function sanitize_editor_option( $value, $option, $raw_value ) {
		if ( ( $option['type'] ?? '' ) !== 'pxer_editor' ) {
			return $value;
		}

		return wp_kses( (string) $raw_value, self::EDITOR_ALLOWED_HTML );
	}

	/**
	 * Mark each type's form page with a recognizable label in the Pages list —
	 * the same "post state" mechanism WooCommerce uses for Shop/Cart/Checkout.
	 *
	 * @param array    $states Existing post states.
	 * @param \WP_Post $post   The post being listed.
	 *
	 * @return array
	 */
	public function add_post_state( array $states, $post ): array {
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
			return $states;
		}

		foreach ( RequestTypes::all() as $id => $type ) {
			if ( (int) $post->ID === self::get_page_id( $id ) ) {
				/* translators: %s: request type label */
				$states[ 'pxer_' . $id ] = sprintf( __( 'Request form: %s', 'px-wc-requests' ), $type['label'] );
				break;
			}
		}

		return $states;
	}

	/**
	 * "Settings" link on the Plugins screen, pointing straight to the
	 * WooCommerce → Settings → Requests tab.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function plugin_action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB_ID );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'px-wc-requests' ) . '</a>' );

		return $links;
	}

	public function add_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = __( 'Customer requests', 'px-wc-requests' );

		return $tabs;
	}

	public function render_tab(): void {
		woocommerce_admin_fields( $this->get_settings() );
		$this->render_shortcode_help();
	}

	public function save(): void {
		woocommerce_update_options( $this->get_settings() );
	}

	private function get_settings(): array {
		$pages    = $this->get_pages_options();
		$statuses = $this->get_order_status_options();

		$settings = array(
			array(
				'title' => __( 'Form pages', 'px-wc-requests' ),
				'type'  => 'title',
				'desc'  => __( 'Assign the page that contains the shortcode of each request type.', 'px-wc-requests' ),
				'id'    => 'pxer_section_pages',
			),
		);

		foreach ( RequestTypes::all() as $id => $type ) {
			$settings[] = array(
				/* translators: %s: type label */
				'title'    => sprintf( __( 'Page: %s', 'px-wc-requests' ), $type['label_plural'] ),
				/* translators: %s: shortcode */
				'desc'     => sprintf( __( 'Page containing the shortcode %s', 'px-wc-requests' ), '[pxer_request_form type="' . esc_attr( $id ) . '"]' ),
				'id'       => 'pxer_' . $id . '_page_id',
				'type'     => 'select',
				'options'  => $pages,
				'default'  => 0,
				'class'    => 'wc-enhanced-select',
				'desc_tip' => true,
			);
		}

		$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_pages' );

		// Period / deadline section (only for types that enforce a period).
		$period_types = array_filter( RequestTypes::all(), static fn( $t ) => ! empty( $t['period']['enabled'] ) );

		if ( $period_types ) {
			$settings[] = array(
				'title' => __( 'Periods & deadlines', 'px-wc-requests' ),
				'type'  => 'title',
				'desc'  => __( 'How long a request stays open, counted from the order completion date.', 'px-wc-requests' ),
				'id'    => 'pxer_section_periods',
			);

			foreach ( $period_types as $id => $type ) {
				$unit_label = 'months' === $type['period']['unit']
					? __( 'months', 'px-wc-requests' )
					: __( 'days', 'px-wc-requests' );

				$settings[] = array(
					/* translators: 1: type label, 2: unit (days/months) */
					'title'             => sprintf( __( 'Period: %1$s (in %2$s)', 'px-wc-requests' ), $type['label'], $unit_label ),
					'id'                => 'pxer_' . $id . '_period_amount',
					'type'              => 'number',
					'default'           => $type['period']['amount'],
					'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
				);

				$settings[] = array(
					/* translators: %s: type label */
					'title'    => sprintf( __( 'Start counting from order status: %s', 'px-wc-requests' ), $type['label'] ),
					'desc'     => __( 'Order statuses that start the clock.', 'px-wc-requests' ),
					'id'       => 'pxer_' . $id . '_period_start_statuses',
					'type'     => 'multiselect',
					'class'    => 'wc-enhanced-select',
					'options'  => $statuses,
					'default'  => $type['period']['start_statuses'],
					'desc_tip' => true,
				);
			}

			$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_periods' );
		}

		// Automatic refund record on request status (never touches the gateway).
		$refund_types = array_filter( RequestTypes::all(), static fn( $t ) => ! empty( $t['refund']['enabled'] ) );

		if ( $refund_types ) {
			$settings[] = array(
				'title' => __( 'Automatic refunds', 'px-wc-requests' ),
				'type'  => 'title',
				'desc'  => __( 'When a request reaches the selected status, a refund for the requested items is recorded on the order (amounts after discounts, tax, reports, order status). Money is never sent to the payment gateway — transfer it manually, e.g. to the IBAN from the request.', 'px-wc-requests' ),
				'id'    => 'pxer_section_refunds',
			);

			foreach ( $refund_types as $id => $type ) {
				$settings[] = array(
					/* translators: %s: type label */
					'title'    => sprintf( __( 'Create refund: %s', 'px-wc-requests' ), $type['label'] ),
					'desc'     => __( 'Request status that records the refund. Each request creates at most one refund.', 'px-wc-requests' ),
					'id'       => 'pxer_' . $id . '_refund_status',
					'type'     => 'select',
					'options'  => array( '' => __( '— Disabled —', 'px-wc-requests' ) ) + $type['statuses'],
					'default'  => '',
					'desc_tip' => true,
				);
			}

			$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_refunds' );
		}

		// Legal notice shown on each form.
		$settings[] = array(
			'title' => __( 'Legal notice on forms', 'px-wc-requests' ),
			'type'  => 'title',
			'desc'  => __( 'Informational text shown on each request form (e.g. statutory withdrawal information). Have it reviewed by a lawyer.', 'px-wc-requests' ),
			'id'    => 'pxer_section_legal',
		);
		foreach ( RequestTypes::all() as $id => $type ) {
			$settings[] = array(
				/* translators: %s: type label */
				'title'   => sprintf( __( 'Notice: %s', 'px-wc-requests' ), $type['label'] ),
				'id'      => 'pxer_' . $id . '_legal_notice',
				'type'    => 'pxer_editor',
				'default' => $type['legal_notice'],
			);
		}
		$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_legal' );

		// Custom text appended to the plugin's own customer e-mails.
		$settings[] = array(
			'title' => __( 'E-mail texts', 'px-wc-requests' ),
			'type'  => 'title',
			'desc'  => sprintf(
				/* translators: %s: list of available placeholders */
				__( 'Extra text added to the customer e-mails — typically the address the goods should be returned to, or instructions for the next step. Leave empty to add nothing. Placeholders: %s', 'px-wc-requests' ),
				'<code>{request_type}</code>, <code>{request_number}</code>, <code>{order_number}</code>, <code>{request_status}</code>'
			),
			'id'    => 'pxer_section_email_texts',
		);

		foreach ( RequestTypes::all() as $id => $type ) {
			$settings[] = array(
				/* translators: %s: type label */
				'title'   => sprintf( __( 'Confirmation: %s', 'px-wc-requests' ), $type['label'] ),
				'desc'    => __( 'Added to the e-mail sent right after the request is submitted.', 'px-wc-requests' ),
				'id'      => self::email_text_option( $id ),
				'type'    => 'pxer_editor',
				'default' => '',
			);

			foreach ( $type['statuses'] as $slug => $label ) {
				$settings[] = array(
					/* translators: 1: type label, 2: status label */
					'title'   => sprintf( __( '%1$s → status “%2$s”', 'px-wc-requests' ), $type['label'], $label ),
					'desc'    => __( 'Added to the e-mail sent when the request changes to this status.', 'px-wc-requests' ),
					'id'      => self::email_text_option( $id, $slug ),
					'type'    => 'pxer_editor',
					'default' => '',
				);
			}
		}

		$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_email_texts' );

		// Form links in customer emails.
		$email_options = $this->get_customer_email_options();
		if ( $email_options ) {
			$settings[] = array(
				'title' => __( 'Form links in e-mails', 'px-wc-requests' ),
				'type'  => 'title',
				'desc'  => __( 'Append a link to the request form at the end of the selected customer e-mails.', 'px-wc-requests' ),
				'id'    => 'pxer_section_email_links',
			);
			foreach ( RequestTypes::all() as $id => $type ) {
				$settings[] = array(
					/* translators: %s: type label */
					'title'    => sprintf( __( 'Add “%s” link to e-mails', 'px-wc-requests' ), $type['label'] ),
					'id'       => 'pxer_' . $id . '_inject_emails',
					'type'     => 'multiselect',
					'class'    => 'wc-enhanced-select',
					'options'  => $email_options,
					'default'  => Settings::default_inject_emails(),
				);
			}
			$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_email_links' );
		}

		// Button texts.
		$settings[] = array(
			'title' => __( 'Button texts', 'px-wc-requests' ),
			'type'  => 'title',
			'desc'  => __( 'Customise the request button labels.', 'px-wc-requests' ),
			'id'    => 'pxer_section_buttons',
		);
		foreach ( RequestTypes::all() as $id => $type ) {
			$settings[] = array(
				/* translators: %s: type label */
				'title'       => sprintf( __( 'Action button: %s', 'px-wc-requests' ), $type['label'] ),
				'desc'        => __( 'Shown in My Account orders and e-mail links.', 'px-wc-requests' ),
				'desc_tip'    => true,
				'id'          => 'pxer_' . $id . '_button_label',
				'type'        => 'text',
				'placeholder' => $type['button_label'] ?: $type['label'],
				'default'     => $type['button_label'] ?: $type['label'],
			);
			$settings[] = array(
				/* translators: %s: type label */
				'title'       => sprintf( __( 'Submit button: %s', 'px-wc-requests' ), $type['label'] ),
				'id'          => 'pxer_' . $id . '_submit_label',
				'type'        => 'text',
				'placeholder' => __( 'Submit request', 'px-wc-requests' ),
				'default'     => __( 'Submit request', 'px-wc-requests' ),
			);
		}
		$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_buttons' );

		// Security & anti-spam.
		$settings[] = array(
			'title' => __( 'Security & anti-spam', 'px-wc-requests' ),
			'type'  => 'title',
			'desc'  => __( 'Protect the public form from bots and abuse. Set 0 to disable a limit.', 'px-wc-requests' ),
			'id'    => 'pxer_section_security',
		);
		$settings[] = array(
			'title'             => __( 'Max requests per order', 'px-wc-requests' ),
			'id'                => 'pxer_max_requests_per_order',
			'type'              => 'number',
			'default'           => 5,
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
		);
		$settings[] = array(
			'title'             => __( 'Minimum form fill time (seconds)', 'px-wc-requests' ),
			'desc'              => __( 'Submissions faster than this are treated as bots.', 'px-wc-requests' ),
			'id'                => 'pxer_min_fill_seconds',
			'type'              => 'number',
			'default'           => 4,
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			'desc_tip'          => true,
		);
		$settings[] = array(
			'title'             => __( 'Rate limit per IP / hour', 'px-wc-requests' ),
			'id'                => 'pxer_rate_limit_ip_hour',
			'type'              => 'number',
			'default'           => 15,
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
		);
		$settings[] = array(
			'title'             => __( 'Block duplicate requests within (hours)', 'px-wc-requests' ),
			'id'                => 'pxer_duplicate_window_hours',
			'type'              => 'number',
			'default'           => 24,
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
		);
		$settings[] = array( 'type' => 'sectionend', 'id' => 'pxer_section_security' );

		return apply_filters( 'pxer_settings', $settings );
	}

	/**
	 * Customer-facing WooCommerce emails (excluding this plugin's own emails).
	 *
	 * @return array<string,string> email id => title
	 */
	private function get_customer_email_options(): array {
		$options = array();
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return $options;
		}
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( method_exists( $email, 'is_customer_email' ) && $email->is_customer_email() && 0 !== strpos( $email->id, 'pxer_' ) ) {
				$options[ $email->id ] = $email->get_title();
			}
		}

		return $options;
	}

	private function get_pages_options(): array {
		$options = array( 0 => __( '— Select a page —', 'px-wc-requests' ) );
		foreach ( get_pages( array( 'sort_column' => 'post_title' ) ) as $page ) {
			$options[ $page->ID ] = $page->post_title;
		}

		return $options;
	}

	/**
	 * Order statuses keyed without the `wc-` prefix (to match get_status()).
	 */
	private function get_order_status_options(): array {
		$options = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			$options[ preg_replace( '/^wc-/', '', $key ) ] = $label;
		}

		return $options;
	}

	/**
	 * Load the settings-tab script that injects a "Create page" button under each
	 * form-page selector and wires the AJAX create + auto-select behaviour.
	 */
	public function enqueue_settings_assets( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || self::TAB_ID !== sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			return;
		}

		wp_enqueue_script( 'pxer-admin-settings', PXER_URL . 'assets/admin-settings.js', array( 'jquery' ), PXER_VERSION, true );

		$types = array();
		foreach ( RequestTypes::all() as $id => $type ) {
			$page_id = self::get_page_id( $id );
			$page    = $page_id ? get_post( $page_id ) : null;
			$valid   = $page && 'page' === $page->post_type && 'trash' !== $page->post_status;

			$types[ $id ] = array(
				'label'    => $type['label'],
				'page_id'  => $valid ? (int) $page_id : 0,
				'edit_url' => $valid ? (string) get_edit_post_link( $page_id, 'raw' ) : '',
				'view_url' => $valid ? (string) get_permalink( $page_id ) : '',
			);
		}

		wp_localize_script( 'pxer-admin-settings', 'pxer_settings', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::CREATE_PAGE_NONCE ),
			'types'    => $types,
			'i18n'     => array(
				'create_for' => __( 'Create page for %s', 'px-wc-requests' ),
				'creating'   => __( 'Creating…', 'px-wc-requests' ),
				'created'    => __( 'Form page created and selected.', 'px-wc-requests' ),
				'failed'     => __( 'Could not create the form page.', 'px-wc-requests' ),
				'edit'       => __( 'Edit page', 'px-wc-requests' ),
				'view'       => __( 'View', 'px-wc-requests' ),
			),
		) );
	}

	/**
	 * AJAX: create a published page holding the type's form shortcode, assign it
	 * to the type (persist the option) and return its data so the JS can inject
	 * and auto-select it in the page dropdown.
	 */
	public function ajax_create_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( self::CREATE_PAGE_NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'px-wc-requests' ) ), 403 );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$def  = RequestTypes::get( $type );
		if ( ! $def ) {
			wp_send_json_error( array( 'message' => __( 'Unknown request type.', 'px-wc-requests' ) ) );
		}

		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $this->page_title( $type, $def ),
			'post_content' => '[pxer_request_form type="' . $type . '"]',
		), true );

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create the form page.', 'px-wc-requests' ) ) );
		}

		update_option( 'pxer_' . $type . '_page_id', (int) $page_id );

		wp_send_json_success( array(
			'page_id'  => (int) $page_id,
			'title'    => (string) get_the_title( $page_id ),
			'edit_url' => (string) get_edit_post_link( $page_id, 'raw' ),
			'view_url' => (string) get_permalink( $page_id ),
			'message'  => __( 'Form page created and selected.', 'px-wc-requests' ),
		) );
	}

	/**
	 * Default title for the auto-created form page of a type. Built-in types get
	 * a natural, form-oriented name; custom types fall back to their plural label.
	 * Override via the `pxer_form_page_title` filter.
	 */
	private function page_title( string $type, array $def ): string {
		$defaults = array(
			'withdrawal' => __( 'Withdrawal from contract', 'px-wc-requests' ),
			'claim'      => __( 'Claim form', 'px-wc-requests' ),
		);
		$title = $defaults[ $type ] ?? $def['label_plural'];

		return (string) apply_filters( 'pxer_form_page_title', $title, $type, $def );
	}

	private function render_shortcode_help(): void {
		echo '<hr style="margin:30px 0">';
		echo '<h2>' . esc_html__( 'Available shortcodes', 'px-wc-requests' ) . '</h2>';
		echo '<table class="widefat" style="max-width:640px"><tbody>';
		foreach ( RequestTypes::all() as $id => $type ) {
			printf(
				'<tr><td><code>[pxer_request_form type="%s"]</code></td><td>%s</td></tr>',
				esc_attr( $id ),
				esc_html( $type['label_plural'] )
			);
		}
		echo '</tbody></table>';
	}

	// =====================================================================
	// Static accessors
	// =====================================================================

	public static function get_page_id( string $type ): int {
		return (int) get_option( 'pxer_' . $type . '_page_id', 0 );
	}

	/**
	 * Action button label (My Account orders + e-mail links).
	 */
	public static function get_button_label( string $type ): string {
		$def     = RequestTypes::get( $type );
		$default = $def ? ( $def['button_label'] ?: $def['label'] ) : $type;
		$value   = (string) get_option( 'pxer_' . $type . '_button_label', $default );

		return $value !== '' ? $value : $default;
	}

	/**
	 * Form submit button label.
	 */
	public static function get_submit_label( string $type ): string {
		$default = __( 'Submit request', 'px-wc-requests' );
		$value   = (string) get_option( 'pxer_' . $type . '_submit_label', $default );

		return $value !== '' ? $value : $default;
	}

	/**
	 * Legal/info notice text for a type (admin override or type default).
	 */
	public static function get_legal_notice( string $type ): string {
		$default = RequestTypes::get( $type )['legal_notice'] ?? '';

		return (string) get_option( 'pxer_' . $type . '_legal_notice', $default );
	}

	/**
	 * Request status that triggers the automatic refund record for a type.
	 * Empty string = the feature is off.
	 */
	public static function get_refund_status( string $type ): string {
		return (string) get_option( 'pxer_' . $type . '_refund_status', '' );
	}

	/**
	 * Option name holding the custom e-mail text of a type — either for the
	 * confirmation e-mail (no status) or for one status-change e-mail.
	 *
	 * @param string $type   Request type id.
	 * @param string $status Request status slug, empty for the confirmation mail.
	 */
	public static function email_text_option( string $type, string $status = '' ): string {
		$name = 'pxer_' . sanitize_key( $type ) . '_email_text';

		return $status ? $name . '_' . sanitize_key( $status ) : $name;
	}

	/**
	 * Custom e-mail text configured for a type (and optionally a status).
	 */
	public static function get_email_text( string $type, string $status = '' ): string {
		return (string) get_option( self::email_text_option( $type, $status ), '' );
	}

	/**
	 * Emails that get the form link by default (until the admin changes it).
	 */
	public static function default_inject_emails(): array {
		return array( 'customer_completed_order' );
	}

	/**
	 * Email ids configured to receive the form link for a type.
	 */
	public static function get_inject_emails( string $type ): array {
		$value = get_option( 'pxer_' . $type . '_inject_emails', null );

		// Not set yet → use the default; otherwise honour the saved value
		// (including an empty array when the admin deselected everything).
		if ( null === $value ) {
			return self::default_inject_emails();
		}

		return array_filter( (array) $value );
	}

	/**
	 * Build a deep link to the form page prefilled with the order search params.
	 */
	public static function get_form_url( string $type, int $order_id, string $email ): string {
		$page_id = self::get_page_id( $type );
		if ( ! $page_id ) {
			return '';
		}
		$base = get_permalink( $page_id );
		if ( ! $base ) {
			return '';
		}

		return add_query_arg( array(
			'order_number' => $order_id,
			'email'        => rawurlencode( $email ),
		), $base );
	}
}
