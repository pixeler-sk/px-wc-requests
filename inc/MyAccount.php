<?php
/**
 * My Account integration: adds per-type action buttons (e.g. "Return",
 * "File a claim") to the orders list, but only for orders where the request is
 * still possible according to the configured period.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class MyAccount {

	const ENDPOINT = 'requests'; // default slug

	/**
	 * Configured My Account endpoint slug (option override or default).
	 */
	public static function endpoint(): string {
		$slug = sanitize_title( (string) get_option( 'pxer_account_endpoint', self::ENDPOINT ) );

		return $slug ?: self::ENDPOINT;
	}

	public function setup(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_actions' ), 10, 2 );

		// "My requests" account tab.
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 11 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::endpoint() . '_title', array( $this, 'endpoint_title' ) );
		add_action( 'woocommerce_account_' . self::endpoint() . '_endpoint', array( $this, 'render_endpoint' ) );

		// Pull in the page builder's My Account widget styles on our endpoint.
		// Builders like Elementor load that CSS conditionally — only on the
		// endpoints their widget renders — so on our custom endpoint the wrapper
		// styles (.woocommerce-MyAccount-content-wrapper) would otherwise be missing.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_account_assets' ), 20 );

		// Expose the slug in WooCommerce → Settings → Advanced → Account endpoints.
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'add_account_endpoint_setting' ), 10, 2 );
	}

	/**
	 * On our account endpoint, enqueue the page builder's My Account widget
	 * styles if they are registered but not loaded (conditional asset loading).
	 */
	public function enqueue_account_assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		global $wp_query;
		if ( ! isset( $wp_query ) || ! array_key_exists( self::endpoint(), (array) $wp_query->query_vars ) ) {
			return;
		}

		// Elementor (and others) register per-widget styles with these handles.
		$handles = apply_filters( 'pxer_account_style_handles', array( 'widget-woocommerce-my-account' ) );
		foreach ( $handles as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
				wp_enqueue_style( $handle );
			}
		}
	}

	/**
	 * Add our endpoint to WooCommerce's native "Account endpoints" list, next to
	 * Orders, Downloads, Addresses, etc.
	 *
	 * @param array  $settings
	 * @param string $current_section
	 *
	 * @return array
	 */
	public function add_account_endpoint_setting( array $settings, string $current_section ): array {
		if ( '' !== $current_section ) {
			return $settings; // Account endpoints live in the default "Page setup" section.
		}

		$field = array(
			'title'    => __( 'My requests', 'px-wc-requests' ),
			'desc'     => __( 'Endpoint for the customer requests page.', 'px-wc-requests' ),
			'id'       => 'pxer_account_endpoint',
			'type'     => 'text',
			'default'  => self::ENDPOINT,
			'desc_tip' => true,
		);

		$out      = array();
		$inserted = false;
		foreach ( $settings as $setting ) {
			if ( ! $inserted && 'sectionend' === ( $setting['type'] ?? '' ) && 'account_endpoint_options' === ( $setting['id'] ?? '' ) ) {
				$out[]    = $field;
				$inserted = true;
			}
			$out[] = $setting;
		}
		if ( ! $inserted ) {
			$out[] = $field;
		}

		return $out;
	}

	public static function register_endpoint(): void {
		add_rewrite_endpoint( self::endpoint(), EP_ROOT | EP_PAGES );
	}

	/**
	 * Flush rewrite rules when the plugin version OR the endpoint slug changes,
	 * so the account tab works without a manual permalink re-save.
	 */
	public static function maybe_flush(): void {
		$signature = PXER_VERSION . '|' . self::endpoint();
		if ( get_option( 'pxer_rewrite_version' ) !== $signature ) {
			flush_rewrite_rules( false );
			update_option( 'pxer_rewrite_version', $signature );
		}
	}

	public function query_vars( array $vars ): array {
		$vars[ self::endpoint() ] = self::endpoint();

		return $vars;
	}

	/**
	 * Page title for our account endpoint.
	 *
	 * WC_Query::get_endpoint_title() only knows WooCommerce's own endpoints and
	 * returns '' for anything else, so wc_page_endpoint_title() fell back to the
	 * My Account page title - every request screen was headed "My account" while
	 * the sibling tabs showed their own name.
	 *
	 * The filter's $action argument is WooCommerce's own $_GET['action'], not
	 * our endpoint value, so the detail view's request id has to come from the
	 * query var - the same read render_endpoint() does.
	 *
	 * @param string $title Title so far (empty for a custom endpoint).
	 */
	public function endpoint_title( $title ): string {
		$value = trim( (string) get_query_var( self::endpoint() ) );

		if ( '' !== $value && ctype_digit( $value ) ) {
			/* translators: %s: request number. */
			return sprintf( __( 'Request #%s', 'px-wc-requests' ), $value );
		}

		return __( 'My requests', 'px-wc-requests' );
	}

	/**
	 * Insert the menu item before "Logout".
	 */
	public function menu_item( array $items ): array {
		$logout = array();
		if ( isset( $items['customer-logout'] ) ) {
			$logout = array( 'customer-logout' => $items['customer-logout'] );
			unset( $items['customer-logout'] );
		}

		$items[ self::endpoint() ] = __( 'My requests', 'px-wc-requests' );

		return array_merge( $items, $logout );
	}

	public function render_endpoint(): void {
		// Elementor's My Account widget scopes its content-wrapper styling to its
		// own endpoints (orders/downloads/edit-account/…) via `.e-my-account-tab__{slug}`.
		// Our custom endpoint gets `.e-my-account-tab__{slug}`, which the
		// compiled CSS doesn't list, so the configured design never applies.
		// Borrow the "orders" tab class so the existing styling matches. The
		// wrapper div itself is added by the builder (woocommerce_account_content),
		// so we must not output it here. No-op when the builder isn't present.
		self::borrow_account_tab_class();

		// Detail view: .../my-account/{endpoint}/{id}/
		$value = trim( (string) get_query_var( self::endpoint() ) );
		if ( '' !== $value && ctype_digit( $value ) ) {
			$this->render_detail( (int) $value );

			return;
		}

		$user = wp_get_current_user();

		$base = array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
		);

		// Cheap path: match by stored owner id or the account e-mail.
		$requests = get_posts( $base + array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_pxer_user_id', 'value' => $user->ID ),
				array( 'key' => '_pxer_email', 'value' => $user->user_email ),
			),
		) );

		// Fallback (only when the cheap path is empty): match by the account's
		// own orders. Covers requests whose stored owner/e-mail differs.
		if ( empty( $requests ) ) {
			$order_ids = wc_get_orders( array(
				'customer' => $user->ID,
				'limit'    => 50,
				'return'   => 'ids',
			) );
			if ( ! empty( $order_ids ) ) {
				$requests = get_posts( $base + array(
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_pxer_order_id',
							'value'   => $order_ids,
							'compare' => 'IN',
						),
					),
				) );
			}
		}

		pxer_get_template( 'myaccount/requests.php', array( 'requests' => $requests ) );
	}

	/**
	 * Add a builder-recognised My Account tab class to our endpoint wrapper so
	 * its content styling (Elementor scopes it per known endpoint) applies here
	 * too. Inline so it runs in place; degrades to nothing without the markup.
	 *
	 * The class can be changed/disabled via the `pxer_account_tab_class` filter.
	 */
	private static function borrow_account_tab_class(): void {
		$class = apply_filters( 'pxer_account_tab_class', 'e-my-account-tab__orders' );
		if ( ! $class ) {
			return;
		}
		?>
		<script>
		( function () {
			var s = document.currentScript;
			var tab = s && s.closest ? s.closest( '.e-my-account-tab' ) : null;
			if ( tab && ! tab.classList.contains( <?php echo wp_json_encode( $class ); ?> ) ) {
				tab.classList.add( <?php echo wp_json_encode( $class ); ?> );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Render a single request detail (data + customer-visible history).
	 */
	private function render_detail( int $request_id ): void {
		$request = pxer_get_request( $request_id );

		if ( ! $request->exists() || ! self::user_owns_request( $request ) ) {
			echo wc_print_notice( __( 'Request not found.', 'px-wc-requests' ), 'error', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		pxer_get_template( 'myaccount/request-detail.php', array(
			'request' => $request,
			'notes'   => RequestNotes::get_notes( $request_id, true ),
		) );
	}

	/**
	 * Ownership check for the current logged-in user.
	 */
	public static function user_owns_request( \Pixeler\Requests\Request $request ): bool {
		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return false;
		}
		if ( (int) $request->get_meta( '_pxer_user_id' ) === (int) $user->ID ) {
			return true;
		}
		if ( $user->user_email && $request->get_meta( '_pxer_email' ) === $user->user_email ) {
			return true;
		}
		$order = $request->get_order();

		return $order && (int) $order->get_customer_id() === (int) $user->ID;
	}

	/**
	 * URL of a request detail in the account area.
	 */
	public static function detail_url( int $request_id ): string {
		return wc_get_endpoint_url( self::endpoint(), (string) $request_id, wc_get_page_permalink( 'myaccount' ) );
	}

	/**
	 * @param array     $actions
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	public function add_actions( array $actions, $order ): array {
		if ( ! $order instanceof \WC_Order ) {
			return $actions;
		}

		foreach ( RequestTypes::all() as $id => $type ) {
			$page_id = Settings::get_page_id( $id );
			if ( ! $page_id ) {
				continue; // No form page configured for this type.
			}

			// Only when the request is still open for this order (period + items).
			if ( is_wp_error( Eligibility::gate( $order, $id ) ) ) {
				continue;
			}

			$page_url = get_permalink( $page_id );
			if ( ! $page_url ) {
				continue;
			}

			// Logged-in owner → deep-link with the order key to skip the search step.
			$url   = add_query_arg( 'key', $order->get_order_key(), $page_url );
			$label = Settings::get_button_label( $id );

			$action = array(
				'url'  => $url,
				'name' => $label,
			);

			/**
			 * Filter a single My Account request action button.
			 *
			 * @param array     $action ['url'=>, 'name'=>]
			 * @param \WC_Order $order
			 * @param string    $id     Request type id.
			 */
			$actions[ 'pxer_' . $id ] = apply_filters( 'pxer_my_account_action', $action, $order, $id );
		}

		return $actions;
	}
}
