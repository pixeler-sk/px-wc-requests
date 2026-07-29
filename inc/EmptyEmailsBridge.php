<?php
/**
 * Integration with px-wc-empty-emails: registers request-related placeholders
 * ({<type>_form_url} for every registered request type + {request_data}) and
 * maps this plugin's email templates so the empty template can override them.
 *
 * All hooks are pxee filters — with px-wc-empty-emails inactive they simply
 * never fire, so the bridge is always safe to load.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class EmptyEmailsBridge {

	/** This plugin's email templates → WC email ids. */
	private const TEMPLATE_MAP = array(
		'emails/admin-request.php'    => 'pxer_admin_request',
		'emails/customer-request.php' => 'pxer_customer_request',
		'emails/customer-status.php'  => 'pxer_customer_status',
		'emails/request-note.php'     => 'pxer_customer_note',
	);

	public function setup(): void {
		add_filter( 'pxee_email_placeholders', array( $this, 'add_placeholders' ), 10, 3 );
		add_filter( 'pxee_available_placeholders', array( $this, 'list_placeholders' ), 10, 2 );
		add_filter( 'pxee_template_to_email_map', array( $this, 'extend_template_map' ) );
	}

	/**
	 * Provide {<type>_form_url} deep links (e.g. {claim_form_url},
	 * {withdrawal_form_url}) and {request_data} — the request summary table —
	 * for emails that carry a request (this plugin's own emails).
	 *
	 * @param array<string,string> $placeholders
	 * @param \WC_Order            $order
	 * @param \WC_Email            $email
	 *
	 * @return array<string,string>
	 */
	public function add_placeholders( array $placeholders, $order, $email ): array {
		if ( $order instanceof \WC_Order ) {
			foreach ( array_keys( RequestTypes::all() ) as $type_id ) {
				$placeholders[ '{' . $type_id . '_form_url}' ] = Settings::get_form_url( $type_id, $order->get_id(), $order->get_billing_email() );
			}
		}

		$placeholders['{request_data}'] = '';

		$request = is_object( $email ) && isset( $email->request ) ? $email->request : null;
		if ( $request instanceof Request && $request->exists() ) {
			ob_start();
			pxer_render_request_summary( $request );
			$placeholders['{request_data}'] = (string) ob_get_clean();
		}

		return $placeholders;
	}

	/**
	 * Surface the placeholders in the pxee admin settings help text. The
	 * {<type>_form_url} links work in any order email; the request placeholders
	 * are listed only on this plugin's own emails, where they get filled.
	 *
	 * @param array<int,string> $placeholders
	 * @param string            $email_id WC email id the settings screen belongs to.
	 *
	 * @return array<int,string>
	 */
	public function list_placeholders( array $placeholders, string $email_id = '' ): array {
		$tags = array_map(
			static fn( string $type_id ): string => '{' . $type_id . '_form_url}',
			array_keys( RequestTypes::all() )
		);

		if ( str_starts_with( $email_id, 'pxer_' ) ) {
			$tags[] = '{request_data}';
			$tags[] = '{request_type}';
			$tags[] = '{request_number}';
			$tags[] = '{order_number}';
			if ( 'pxer_customer_status' === $email_id ) {
				$tags[] = '{request_status}';
			}
		}

		foreach ( $tags as $tag ) {
			if ( ! in_array( $tag, $placeholders, true ) ) {
				$placeholders[] = $tag;
			}
		}

		return $placeholders;
	}

	/**
	 * Let the pxee empty template override this plugin's email templates too
	 * (per-email "enabled_empty" toggle in the WC email settings).
	 *
	 * @param array<string,string> $map
	 *
	 * @return array<string,string>
	 */
	public function extend_template_map( array $map ): array {
		return array_merge( $map, self::TEMPLATE_MAP );
	}
}
