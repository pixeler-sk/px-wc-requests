<?php
/**
 * Anti-spam / abuse protection for the public submission form:
 * honeypot, time-trap, per-IP rate limit, max requests per order and
 * duplicate-content detection. Inspired by WPify Woo's spam layers.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Security {

	const HONEYPOT  = 'pxer_homepage';
	const TIMETRAP  = 'pxer_tt';

	// =====================================================================
	// Frontend hidden fields
	// =====================================================================

	public static function render_hidden_fields(): void {
		$ts    = time();
		$token = $ts . '.' . hash_hmac( 'sha256', (string) $ts, wp_salt( 'auth' ) );
		?>
		<input type="text" name="<?php echo esc_attr( self::HONEYPOT ); ?>" value="" tabindex="-1" autocomplete="off"
		       aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden">
		<input type="hidden" name="<?php echo esc_attr( self::TIMETRAP ); ?>" value="<?php echo esc_attr( $token ); ?>">
		<?php
	}

	// =====================================================================
	// Bot checks (silent rejection)
	// =====================================================================

	/**
	 * Returns true when the submission looks like a bot (honeypot filled or the
	 * form was submitted impossibly fast / with a tampered time token).
	 */
	public static function is_bot( array $post ): bool {
		if ( ! empty( $post[ self::HONEYPOT ] ) ) {
			return true;
		}

		$token = isset( $post[ self::TIMETRAP ] ) ? (string) wp_unslash( $post[ self::TIMETRAP ] ) : '';
		if ( ! str_contains( $token, '.' ) ) {
			return true;
		}

		list( $ts, $hmac ) = explode( '.', $token, 2 );
		$expected          = hash_hmac( 'sha256', $ts, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $hmac ) ) {
			return true;
		}

		$elapsed = time() - (int) $ts;
		$min     = max( 0, (int) get_option( 'pxer_min_fill_seconds', 4 ) );

		// Too fast, or a stale token (> 3 hours old).
		return $elapsed < $min || $elapsed > 3 * HOUR_IN_SECONDS;
	}

	// =====================================================================
	// Throttling & abuse limits
	// =====================================================================

	/**
	 * @return true|\WP_Error
	 */
	public static function check_limits( \WC_Order $order, string $type, array $data ) {
		// Per-IP rate limit.
		$limit = (int) get_option( 'pxer_rate_limit_ip_hour', 15 );
		if ( $limit > 0 ) {
			$key   = 'pxer_rl_' . md5( self::get_client_ip() );
			$count = (int) get_transient( $key );
			if ( $count >= $limit ) {
				return new \WP_Error( 'rate_limited', __( 'Too many attempts. Please try again later.', 'px-wc-requests' ) );
			}
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}

		// Max requests per order.
		$max      = (int) get_option( 'pxer_max_requests_per_order', 5 );
		$existing = pxer_get_requests_by_order_id( $order->get_id() );
		if ( $max > 0 && count( $existing ) >= $max ) {
			return new \WP_Error( 'max_per_order', __( 'The maximum number of requests for this order has been reached.', 'px-wc-requests' ) );
		}

		// Duplicate content within a time window.
		$window = (int) get_option( 'pxer_duplicate_window_hours', 24 );
		if ( $window > 0 ) {
			$fingerprint = self::fingerprint( $type, (int) $order->get_id(), $data );
			$threshold   = time() - $window * HOUR_IN_SECONDS;
			foreach ( $existing as $post ) {
				if ( strtotime( $post->post_date_gmt . ' UTC' ) < $threshold ) {
					continue;
				}
				$d = get_post_meta( $post->ID, '_pxer_data', true );
				$t = (string) get_post_meta( $post->ID, '_pxer_type', true );
				if ( is_array( $d ) && self::fingerprint( $t, (int) $order->get_id(), $d ) === $fingerprint ) {
					return new \WP_Error( 'duplicate', __( 'You have already submitted an identical request recently.', 'px-wc-requests' ) );
				}
			}
		}

		return true;
	}

	private static function fingerprint( string $type, int $order_id, array $data ): string {
		return sha1( $type . '|' . $order_id . '|' . wp_json_encode( $data['items'] ?? array() ) );
	}

	public static function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
