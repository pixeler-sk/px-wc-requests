<?php
/**
 * Automatické aktualizácie z GitHubu.
 *
 * Plugin nie je na wordpress.org — distribuuje sa z verejného repozitára
 * https://github.com/pixeler-sk/px-wc-requests. Plugin Update Checker sa pýta
 * GitHub API na najnovší release, porovná ho s `PXER_VERSION` a keď je novší,
 * objaví sa v Nástenka → Aktualizácie ako pri hociktorom inom plugine.
 *
 * Zdrojom aktualizácie je **zip priložený k releasu** (asset), nie automaticky
 * generovaný archív zdrojákov — asset skladá CI a neobsahuje vývojárske súbory.
 * Preto `REQUIRE_RELEASE_ASSETS`: keby release nemal asset, radšej sa aktualizácia
 * neponúkne, než by sa na klientske weby dostal surový obsah repozitára.
 *
 * Changelog v okne „Zobraziť podrobnosti" sa číta z `readme.txt`.
 * Postup vydania novej verzie je v `RELEASING.md`.
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Verejný repozitár, z ktorého sa ťahajú aktualizácie.
 */
const PXER_GITHUB_REPO = 'https://github.com/pixeler-sk/px-wc-requests/';

/**
 * Zapne kontrolu aktualizácií.
 *
 * Volá sa mimo `pxer_bootstrap()` a teda aj vtedy, keď WooCommerce nebeží —
 * plugin bez WooCommerce síce nič nerobí, ale updatovať sa musí dať vždy.
 */
function pxer_init_updater(): void {
	// Aktualizácie sa kontrolujú len v administrácii, v cron behu a cez WP-CLI.
	// Na frontende nie je dôvod knižnicu vôbec načítať.
	if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	require_once PXER_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

	$checker = PucFactory::buildUpdateChecker(
		PXER_GITHUB_REPO,
		PXER_FILE,
		'px-wc-requests' // Musí sedieť s názvom priečinka, inak update pristane vedľa.
	);

	// Konštanta sa číta z inštancie zámerne. Trieda Api žije v priečinku
	// pomenovanom podľa verzie knižnice (`v5p7\Vcs\Api`), takže `use` s plným
	// názvom by po upgrade Plugin Update Checkera spadol na fatal error.
	$api = $checker->getVcsApi();
	$api->enableReleaseAssets(
		'/^px-wc-requests-\d+\.\d+\.\d+\.zip$/',
		$api::REQUIRE_RELEASE_ASSETS
	);
}
