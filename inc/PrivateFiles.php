<?php
/**
 * Private storage for request attachments (e.g. claim photos).
 *
 * Files are uploaded into wp-content/uploads/pxer-private/ which is protected
 * by an .htaccess deny rule, and served only through a capability-checked
 * admin endpoint. Email attachments keep working because they use the
 * server-side file path, not a public URL.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

class PrivateFiles {

	const SUBDIR = 'pxer-private';

	public function setup(): void {
		add_action( 'admin_post_pxer_view_file', array( $this, 'serve' ) );
	}

	// =====================================================================
	// Protected directory
	// =====================================================================

	public static function base_path(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
	}

	public static function protect_dir(): void {
		$dir = self::base_path();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
		}
	}

	// =====================================================================
	// Upload redirection (used around media_handle_upload)
	// =====================================================================

	public static function begin_private_upload(): void {
		self::protect_dir();
		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
	}

	public static function end_private_upload(): void {
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
	}

	public static function filter_upload_dir( array $dirs ): array {
		$dirs['subdir'] = '/' . self::SUBDIR . $dirs['subdir'];
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

		return $dirs;
	}

	public static function mark_private( int $attachment_id ): void {
		update_post_meta( $attachment_id, '_pxer_private', 1 );
	}

	public static function is_private( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, '_pxer_private', true );
	}

	// =====================================================================
	// Gated viewer
	// =====================================================================

	public static function view_url( int $attachment_id ): string {
		return add_query_arg(
			array(
				'action'   => 'pxer_view_file',
				'id'       => $attachment_id,
				'_wpnonce' => wp_create_nonce( 'pxer_view_file_' . $attachment_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public function serve(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'pxer_view_file_' . $id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'px-wc-requests' ), '', array( 'response' => 403 ) );
		}

		$attachment = get_post( $id );
		$parent     = $attachment ? (int) $attachment->post_parent : 0;

		if ( ! $attachment || 'attachment' !== $attachment->post_type || ! current_user_can( 'edit_post', $parent ) ) {
			wp_die( esc_html__( 'You are not allowed to view this file.', 'px-wc-requests' ), '', array( 'response' => 403 ) );
		}

		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			wp_die( esc_html__( 'File not found.', 'px-wc-requests' ), '', array( 'response' => 404 ) );
		}

		$mime = get_post_mime_type( $id ) ?: 'application/octet-stream';
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'Content-Disposition: inline; filename="' . basename( $file ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file ); // phpcs:ignore
		exit;
	}
}
