<?php
/**
 * Admin Settings class.
 *
 * @package TSDS\Admin
 */

declare( strict_types=1 );

namespace TSDS\Admin;

defined( 'ABSPATH' ) || exit;

use TSDS\Helper;

/**
 * Class Settings
 *
 * Provides a central plugin settings page in WooCommerce menu.
 */
class Settings {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register plugin settings and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'tsds_settings_group',
			Helper::OPTION_DATE_FORMAT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Helper::class, 'sanitize_date_format' ),
				'default'           => Helper::DEFAULT_DATE_FORMAT,
			)
		);

		add_settings_section(
			'tsds_general_section',
			__( 'General Settings', 'tour-service-date-selector' ),
			function (): void {
				echo '<p>' . esc_html__( 'Configure global booking display preferences for all products.', 'tour-service-date-selector' ) . '</p>';
			},
			'tsds-settings'
		);

		add_settings_field(
			'tsds_date_format_field',
			__( 'Date Format', 'tour-service-date-selector' ),
			array( $this, 'render_date_format_field' ),
			'tsds-settings',
			'tsds_general_section'
		);
	}

	/**
	 * Register submenu page under WooCommerce.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Tour Service Settings', 'tour-service-date-selector' ),
			__( 'Tour Service Settings', 'tour-service-date-selector' ),
			'manage_woocommerce',
			'tsds-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render date format field.
	 */
	public function render_date_format_field(): void {
		$current = Helper::get_date_format();
		?>
		<select
			id="<?php echo esc_attr( Helper::OPTION_DATE_FORMAT ); ?>"
			name="<?php echo esc_attr( Helper::OPTION_DATE_FORMAT ); ?>"
		>
			<?php foreach ( Helper::date_format_labels() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'This format is applied to booking dates across all products, cart, checkout, emails, and order details.', 'tour-service-date-selector' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page markup.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Service Date Selector Settings', 'tour-service-date-selector' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'tsds_settings_group' );
				do_settings_sections( 'tsds-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
