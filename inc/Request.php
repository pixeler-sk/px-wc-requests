<?php
/**
 * Request model — thin wrapper around a pxer_request post.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class Request {

	public int $id;
	private ?\WP_Post $post;

	public function __construct( int $id ) {
		$this->id   = $id;
		$post       = get_post( $id );
		$this->post = ( $post && RequestPostType::POST_TYPE === $post->post_type ) ? $post : null;
	}

	public function exists(): bool {
		return null !== $this->post;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_title(): string {
		return $this->post ? $this->post->post_title : '';
	}

	public function get_status(): string {
		return $this->post ? $this->post->post_status : '';
	}

	public function get_status_label(): string {
		return RequestTypes::status_label( $this->get_status() );
	}

	public function get_meta( string $key, bool $single = true ) {
		return get_post_meta( $this->id, $key, $single );
	}

	public function get_type(): string {
		return (string) $this->get_meta( '_pxer_type' );
	}

	public function get_type_label( bool $plural = false ): string {
		return RequestTypes::get_label( $this->get_type(), $plural );
	}

	public function get_order_id(): int {
		return (int) $this->get_meta( '_pxer_order_id' );
	}

	public function get_order(): ?\WC_Order {
		$order = wc_get_order( $this->get_order_id() );

		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * @return array
	 */
	public function get_data(): array {
		$data = $this->get_meta( '_pxer_data' );

		return is_array( $data ) ? $data : array();
	}

	public function get_field( string $key, $default = '' ) {
		$data = $this->get_data();

		return $data[ $key ] ?? $default;
	}

	/**
	 * @return int[] Attachment IDs.
	 */
	public function get_images(): array {
		$images = $this->get_meta( '_pxer_images' );

		return is_array( $images ) ? $images : array();
	}
}
