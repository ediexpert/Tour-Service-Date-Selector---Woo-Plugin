<?php
/**
 * Compatibility class.
 *
 * @package TSDS
 */

declare( strict_types=1 );

namespace TSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Compatibility
 *
 * Handles compatibility declarations with WooCommerce features.
 */
class Compatibility {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Add plugin row meta links.
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add links to the plugin row meta.
	 *
	 * @param string[] $links Existing links.
	 * @param string   $file  Plugin file.
	 * @return string[]
	 */
	public function plugin_row_meta( array $links, string $file ): array {
		if ( TSDS_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( 'https://example.com/docs/tour-service-date-selector' ),
			esc_html__( 'Documentation', 'tour-service-date-selector' )
		);

		return $links;
	}
}
