<?php
/**
 * GDPR integration: registers a personal-data exporter and eraser so customer
 * requests (including IBAN/contact data) are included in WordPress privacy
 * export and erasure tools.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Privacy {

	const GROUP = 'pxer_requests';

	public function setup(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function register_exporter( array $exporters ): array {
		$exporters['pxer-requests'] = array(
			'exporter_friendly_name' => __( 'Customer requests', 'px-wc-requests' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	public function register_eraser( array $erasers ): array {
		$erasers['pxer-requests'] = array(
			'eraser_friendly_name' => __( 'Customer requests', 'px-wc-requests' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * @return \WP_Post[]
	 */
	private function find_by_email( string $email ): array {
		return get_posts( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'meta_key'    => '_pxer_email',
			'meta_value'  => $email,
		) );
	}

	public function export( string $email, int $page = 1 ): array {
		$items = array();

		foreach ( $this->find_by_email( $email ) as $post ) {
			$request = pxer_get_request( $post->ID );
			$data    = $request->get_data();

			$fields = array(
				array( 'name' => __( 'Type', 'px-wc-requests' ), 'value' => $request->get_type_label() ),
				array( 'name' => __( 'Request number', 'px-wc-requests' ), 'value' => $request->get_id() ),
				array( 'name' => __( 'Status', 'px-wc-requests' ), 'value' => $request->get_status_label() ),
				array( 'name' => __( 'Order number', 'px-wc-requests' ), 'value' => $request->get_order_id() ),
			);

			foreach ( array( 'firstname', 'lastname', 'email', 'phone', 'address', 'postcode', 'city', 'account_name', 'iban' ) as $key ) {
				if ( ! empty( $data[ $key ] ) ) {
					$fields[] = array( 'name' => $key, 'value' => $data[ $key ] );
				}
			}

			$items[] = array(
				'group_id'    => self::GROUP,
				'group_label' => __( 'Customer requests', 'px-wc-requests' ),
				'item_id'     => 'pxer-' . $post->ID,
				'data'        => $fields,
			);
		}

		return array( 'data' => $items, 'done' => true );
	}

	public function erase( string $email, int $page = 1 ): array {
		$removed = false;

		foreach ( $this->find_by_email( $email ) as $post ) {
			$data = get_post_meta( $post->ID, '_pxer_data', true );
			if ( is_array( $data ) ) {
				foreach ( array( 'firstname', 'lastname', 'email', 'phone', 'address', 'postcode', 'city', 'account_name', 'iban' ) as $key ) {
					if ( isset( $data[ $key ] ) ) {
						$data[ $key ] = '';
					}
				}
				update_post_meta( $post->ID, '_pxer_data', $data );
			}
			delete_post_meta( $post->ID, '_pxer_email' );
			$removed = true;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
