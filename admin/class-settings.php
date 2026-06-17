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

		register_setting(
			'tsds_settings_group',
			Helper::OPTION_DATE_LABEL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Helper::class, 'sanitize_label' ),
				'default'           => Helper::DEFAULT_DATE_LABEL,
			)
		);

		register_setting(
			'tsds_settings_group',
			Helper::OPTION_DATE_ERROR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Helper::class, 'sanitize_label' ),
				'default'           => Helper::DEFAULT_DATE_ERROR,
			)
		);

		register_setting(
			'tsds_settings_group',
			Helper::OPTION_DELETE_DATA_ON_UNINSTALL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Helper::class, 'sanitize_yes_no' ),
				'default'           => Helper::DEFAULT_DELETE_DATA_ON_UNINSTALL,
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

		add_settings_field(
			'tsds_date_label_field',
			__( 'Date Field Label', 'tour-service-date-selector' ),
			array( $this, 'render_date_label_field' ),
			'tsds-settings',
			'tsds_general_section'
		);

		add_settings_field(
			'tsds_date_error_field',
			__( 'Date Validation Error', 'tour-service-date-selector' ),
			array( $this, 'render_date_error_field' ),
			'tsds-settings',
			'tsds_general_section'
		);

		add_settings_field(
			'tsds_delete_data_on_uninstall_field',
			__( 'Data Cleanup on Uninstall', 'tour-service-date-selector' ),
			array( $this, 'render_delete_data_on_uninstall_field' ),
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
	 * Render date label field.
	 */
	public function render_date_label_field(): void {
		$current = Helper::get_date_label();
		?>
		<input
			type="text"
			id="<?php echo esc_attr( Helper::OPTION_DATE_LABEL ); ?>"
			name="<?php echo esc_attr( Helper::OPTION_DATE_LABEL ); ?>"
			value="<?php echo esc_attr( $current ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: default label value */
				esc_html__( 'Label shown above the date picker on the product page. Default: %s', 'tour-service-date-selector' ),
				'<strong>' . esc_html( Helper::DEFAULT_DATE_LABEL ) . '</strong>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render date error message field.
	 */
	public function render_date_error_field(): void {
		$current = Helper::get_date_error();
		?>
		<input
			type="text"
			id="<?php echo esc_attr( Helper::OPTION_DATE_ERROR ); ?>"
			name="<?php echo esc_attr( Helper::OPTION_DATE_ERROR ); ?>"
			value="<?php echo esc_attr( $current ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: default error message */
				esc_html__( 'Validation error shown when customer tries to add to cart without selecting a date. Default: %s', 'tour-service-date-selector' ),
				'<strong>' . esc_html( Helper::DEFAULT_DATE_ERROR ) . '</strong>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render uninstall data cleanup field.
	 */
	public function render_delete_data_on_uninstall_field(): void {
		$enabled = Helper::should_delete_data_on_uninstall();
		?>
		<input type="hidden" name="<?php echo esc_attr( Helper::OPTION_DELETE_DATA_ON_UNINSTALL ); ?>" value="no" />
		<label for="<?php echo esc_attr( Helper::OPTION_DELETE_DATA_ON_UNINSTALL ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( Helper::OPTION_DELETE_DATA_ON_UNINSTALL ); ?>"
				name="<?php echo esc_attr( Helper::OPTION_DELETE_DATA_ON_UNINSTALL ); ?>"
				value="yes"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Delete plugin data when uninstalling this plugin.', 'tour-service-date-selector' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, plugin settings, product booking configuration, and booking order item meta will be permanently removed on uninstall.', 'tour-service-date-selector' ); ?>
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
