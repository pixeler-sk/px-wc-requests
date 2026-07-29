<?php
/**
 * Template loading with theme override support:
 * copy a template into `yourtheme/px-wc-requests/<name>` to override it.
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme-overridable subdirectory name.
 */
function pxer_template_theme_path(): string {
	return 'px-wc-requests/';
}

/**
 * Render a template to string.
 */
function pxer_get_template_html( string $template_name, array $args = array() ): string {
	return wc_get_template_html( $template_name, $args, pxer_template_theme_path(), PXER_TEMPLATE_PATH );
}

/**
 * Output a template.
 */
function pxer_get_template( string $template_name, array $args = array() ): void {
	wc_get_template( $template_name, $args, pxer_template_theme_path(), PXER_TEMPLATE_PATH );
}
