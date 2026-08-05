<?php
/**
 * Plugin bootstrap — wires the components together.
 *
 * @package Pixeler\Requests
 */

namespace Pixeler\Requests;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		( new RequestPostType() )->setup();
		( new RequestNotes() )->setup();
		( new RequestController() )->setup();
		( new Emails() )->setup();
		( new EmptyEmailsBridge() )->setup();
		( new Settings() )->setup();
		( new ProductSettings() )->setup();
		( new MyAccount() )->setup();
		( new PrivateFiles() )->setup();
		( new Privacy() )->setup();
		( new RestApi() )->setup();
		( new Migrator() )->setup();
		( new Shortcodes() )->setup();
		( new OrderList() )->setup();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Internal data-schema version. Bump to re-run the backfill below.
	 */
	private const DATA_SCHEMA = '3';

	/**
	 * One-time data backfill: (re)compute the owner id and e-mail meta on every
	 * request so the "My requests" tab and GDPR lookups work for legacy rows.
	 * Runs once per schema version (cheap option check afterwards).
	 */
	public function maybe_upgrade(): void {
		if ( get_option( 'pxer_data_version' ) === self::DATA_SCHEMA ) {
			return;
		}

		$ids = get_posts( array(
			'post_type'   => RequestPostType::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		foreach ( $ids as $id ) {
			$order_id = (int) get_post_meta( $id, '_pxer_order_id', true );
			$order    = $order_id ? wc_get_order( $order_id ) : null;
			$data     = get_post_meta( $id, '_pxer_data', true );
			$email    = is_array( $data ) && ! empty( $data['email'] ) ? (string) $data['email'] : '';

			update_post_meta( $id, '_pxer_user_id', pxer_resolve_owner_id( $order, $email ) );
			if ( $email ) {
				update_post_meta( $id, '_pxer_email', $email );
			}
		}

		update_option( 'pxer_data_version', self::DATA_SCHEMA );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'px-wc-requests', false, dirname( plugin_basename( PXER_FILE ) ) . '/languages' );
	}

	public function register_assets(): void {
		wp_register_style( 'pxer-form', PXER_URL . 'assets/ajax-form.css', array(), PXER_VERSION );
		wp_register_script( 'pxer-form', PXER_URL . 'assets/ajax-form.js', array( 'jquery' ), PXER_VERSION, true );
		wp_localize_script( 'pxer-form', 'pxer_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		) );
	}
}
