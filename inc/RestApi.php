<?php
/**
 * REST API (namespace `px-wc-requests/v1`) for headless / JS frontends.
 *
 * Endpoints:
 *   GET  /eligibility            ?order_key|order_id & type  → eligible items + deadline
 *   POST /requests               submit a request (JSON or multipart)
 *   GET  /requests               current user's requests (auth)
 *   GET  /requests/{id}          request detail + customer-visible history (auth + owner)
 *
 * Guest access uses the order key (a secret) as 2FA; logged-in users are matched
 * by ownership. The submit reuses the same sanitise/validate/limit/create core
 * as the AJAX flow (RequestController::submit).
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class RestApi {

	const NS = 'px-wc-requests/v1';

	private RequestController $controller;

	public function __construct() {
		$this->controller = new RequestController();
	}

	public function setup(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/eligibility', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'get_eligibility' ),
		) );

		register_rest_route( self::NS, '/requests', array(
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'create_request' ),
			),
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'require_login' ),
				'callback'            => array( $this, 'list_requests' ),
			),
		) );

		register_rest_route( self::NS, '/requests/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'permission_callback' => array( $this, 'require_login' ),
			'callback'            => array( $this, 'get_request' ),
		) );
	}

	public function require_login(): bool {
		return is_user_logged_in();
	}

	// =====================================================================
	// Helpers
	// =====================================================================

	/**
	 * Resolve the order securely: by order key (guest 2FA) or by an order id the
	 * logged-in user owns. Never trusts a bare order id from the client.
	 */
	private function resolve_order( array $params ): ?\WC_Order {
		$key = isset( $params['order_key'] ) ? sanitize_text_field( $params['order_key'] ) : '';
		if ( $key ) {
			$order_id = wc_get_order_id_by_order_key( $key );
			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				return $order instanceof \WC_Order ? $order : null;
			}
		}

		$order_id = isset( $params['order_id'] ) ? absint( $params['order_id'] ) : 0;
		if ( $order_id && is_user_logged_in() ) {
			$order = wc_get_order( $order_id );
			if ( $order && (int) $order->get_customer_id() === get_current_user_id() ) {
				return $order;
			}
		}

		return null;
	}

	private function format_request( \Pixeler\Requests\Request $request ): array {
		$order = $request->get_order();

		return array(
			'id'           => $request->get_id(),
			'type'         => $request->get_type(),
			'type_label'   => $request->get_type_label(),
			'status'       => $request->get_status(),
			'status_label' => $request->get_status_label(),
			'date'         => get_the_date( 'c', $request->get_id() ),
			'order_id'     => $request->get_order_id(),
			'order_number' => $order ? $order->get_order_number() : (string) $request->get_order_id(),
		);
	}

	// =====================================================================
	// Endpoints
	// =====================================================================

	public function get_eligibility( \WP_REST_Request $request ) {
		$type = sanitize_text_field( (string) $request->get_param( 'type' ) );
		if ( ! RequestTypes::exists( $type ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid request type.', 'px-wc-requests' ), array( 'status' => 400 ) );
		}

		$order = $this->resolve_order( $request->get_params() );
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', __( 'Order not found.', 'px-wc-requests' ), array( 'status' => 404 ) );
		}

		$gate  = Eligibility::gate( $order, $type );
		$items = array();
		foreach ( Eligibility::eligible_items( $order, $type ) as $item_id => $item ) {
			$items[] = array(
				'line_item_id' => (int) $item_id,
				'name'         => $item->get_name(),
				'quantity'     => (int) $item->get_quantity(),
			);
		}
		$deadline = Eligibility::period_end( $order, $type );

		return new \WP_REST_Response( array(
			'type'     => $type,
			'eligible' => ! is_wp_error( $gate ),
			'reason'   => is_wp_error( $gate ) ? $gate->get_error_message() : '',
			'deadline' => $deadline ? $deadline->format( 'c' ) : null,
			'items'    => $items,
			'notice'   => Settings::get_legal_notice( $type ),
		), 200 );
	}

	public function create_request( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$type  = sanitize_text_field( (string) ( $params['type'] ?? '' ) );
		$order = $this->resolve_order( $params );
		if ( ! $order ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'Order not found or not accessible.', 'px-wc-requests' ),
			), 403 );
		}

		// Trust only the server-resolved order id.
		$params['order_id'] = $order->get_id();

		// Bot heuristics are UI-specific; REST relies on order-key + rate limit.
		$result = $this->controller->submit( wp_slash( $params ), $type, false );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			), 200 );
		}

		return new \WP_REST_Response( array( 'ok' => true, 'request_id' => $result ), 201 );
	}

	public function list_requests( \WP_REST_Request $request ) {
		$user  = wp_get_current_user();
		$posts = get_posts( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_pxer_user_id', 'value' => $user->ID ),
				array( 'key' => '_pxer_email', 'value' => $user->user_email ),
			),
		) );

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $this->format_request( pxer_get_request( $post->ID ) );
		}

		return new \WP_REST_Response( $out, 200 );
	}

	public function get_request( \WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$model   = pxer_get_request( $id );

		if ( ! $model->exists() || ! MyAccount::user_owns_request( $model ) ) {
			return new \WP_Error( 'not_found', __( 'Request not found.', 'px-wc-requests' ), array( 'status' => 404 ) );
		}

		$history = array();
		foreach ( RequestNotes::get_notes( $id, true ) as $note ) {
			$history[] = array(
				'date'      => get_comment_date( 'c', $note->comment_ID ),
				'content'   => wp_strip_all_tags( $note->comment_content ),
				'is_status' => RequestNotes::is_status_log( (int) $note->comment_ID ),
			);
		}

		$data    = $model->get_data();
		$payload = $this->format_request( $model );
		// Expose the customer's own submitted fields (their data).
		$payload['fields']  = $data;
		$payload['history'] = $history;

		return new \WP_REST_Response( $payload, 200 );
	}
}
