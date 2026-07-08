<?php
/**
 * Order meta class.
 *
 * @package INTSDS
 */

declare( strict_types=1 );

namespace INTSDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Order_Meta
 *
 * Copies booking data from cart to order line items
 * and displays it in the admin, emails, and My Account.
 */
class Order_Meta {

	/**
	 * Order item meta keys.
	 */
	private const ORDER_DATE_KEY = 'intsds_booking_date';
	private const ORDER_TIME_KEY = 'intsds_booking_time';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Copy cart meta to order item meta.
		add_action(
			'woocommerce_checkout_create_order_line_item',
			array( $this, 'copy_cart_meta_to_order' ),
			10,
			4
		);

		// Display in admin order screen and customer emails.
		add_action(
			'woocommerce_before_order_itemmeta',
			array( $this, 'display_order_item_meta_admin' ),
			10,
			3
		);

		// Ensure meta displays in customer-facing views via formatted meta.
		add_filter(
			'woocommerce_order_item_get_formatted_meta_data',
			array( $this, 'format_order_item_meta' ),
			10,
			2
		);

		// Hide raw booking meta keys in admin item meta list.
		add_filter(
			'woocommerce_hidden_order_itemmeta',
			array( $this, 'hide_raw_order_item_meta' )
		);
	}

	/**
	 * Copy booking data from cart item to order line item.
	 *
	 * @param \WC_Order_Item_Product $item          Order line item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array<string,mixed>    $values        Cart item data.
	 * @param \WC_Order              $order         The order.
	 */
	public function copy_cart_meta_to_order(
		\WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		\WC_Order $order
	): void {
		if ( ! empty( $values[ Helper::CART_DATE_KEY ] ) ) {
			$item->add_meta_data(
				self::ORDER_DATE_KEY,
				sanitize_text_field( $values[ Helper::CART_DATE_KEY ] ),
				true
			);
		}

		if ( ! empty( $values[ Helper::CART_TIME_KEY ] ) ) {
			$item->add_meta_data(
				self::ORDER_TIME_KEY,
				sanitize_text_field( $values[ Helper::CART_TIME_KEY ] ),
				true
			);
		}
	}

	/**
	 * Display booking meta in the WooCommerce admin order screen.
	 *
	 * @param int                    $item_id Order item ID.
	 * @param \WC_Order_Item_Product $item    Order item object.
	 * @param \WC_Product|false      $product Product object.
	 */
	public function display_order_item_meta_admin(
		int $item_id,
		\WC_Order_Item_Product $item,
		\WC_Product|false $product
	): void {
		unset( $item_id, $product );

		$date = $item->get_meta( self::ORDER_DATE_KEY );
		$time = $item->get_meta( self::ORDER_TIME_KEY );
		$date_label = Helper::get_date_label();

		$display_date = $date
			? Helper::format_booking_date_for_display(
				(string) $date,
				Helper::get_date_format()
			)
			: '';

		if ( ! $date && ! $time ) {
			return;
		}

		echo '<div class="intsds-order-meta">';

		if ( $display_date ) {
			printf(
				'<p class="intsds-order-meta__row"><strong>%s:</strong> %s</p>',
				esc_html( $date_label ),
				esc_html( $display_date )
			);
		}

		if ( $time ) {
			printf(
				'<p class="intsds-order-meta__row"><strong>%s:</strong> %s</p>',
				esc_html__( 'Booking Time', 'ints-tour-service-date-selector' ),
				esc_html( $time )
			);
		}

		echo '</div>';
	}

	/**
	 * Format order item meta for customer-facing display (emails, My Account).
	 *
	 * @param \stdClass[]            $formatted_meta Array of formatted meta objects.
	 * @param \WC_Order_Item_Product $item           Order item.
	 * @return \stdClass[]
	 */
	public function format_order_item_meta( array $formatted_meta, \WC_Order_Item_Product $item ): array {
		// Remove raw meta rows so only curated labels/values are shown to customers.
		foreach ( $formatted_meta as $index => $meta ) {
			if ( ! isset( $meta->key ) ) {
				continue;
			}

			if ( self::ORDER_DATE_KEY === $meta->key ) {
				unset( $formatted_meta[ $index ] );
			}
		}

		$date = $item->get_meta( self::ORDER_DATE_KEY );
		$time = $item->get_meta( self::ORDER_TIME_KEY );
		$date_label = Helper::get_date_label();

		$display_date = $date
			? Helper::format_booking_date_for_display(
				(string) $date,
				Helper::get_date_format()
			)
			: '';

		if ( $display_date ) {
			$meta          = new \stdClass();
			$meta->key     = self::ORDER_DATE_KEY;
			$meta->label   = $date_label;
			$meta->value   = esc_html( $display_date );
			$meta->display_key   = $date_label;
			$meta->display_value = esc_html( $display_date );
			$formatted_meta[]    = $meta;
		}

		if ( $time ) {
			$meta          = new \stdClass();
			$meta->key     = self::ORDER_TIME_KEY;
			$meta->label   = __( 'Booking Time', 'ints-tour-service-date-selector' );
			$meta->value   = esc_html( $time );
			$meta->display_key   = __( 'Booking Time', 'ints-tour-service-date-selector' );
			$meta->display_value = esc_html( $time );
			$formatted_meta[]    = $meta;
		}

		return array_values( $formatted_meta );
	}

	/**
	 * Hide raw booking meta keys in WooCommerce admin order item meta display.
	 *
	 * @param string[] $hidden_meta_keys Existing hidden order item meta keys.
	 * @return string[]
	 */
	public function hide_raw_order_item_meta( array $hidden_meta_keys ): array {
		if ( ! in_array( self::ORDER_DATE_KEY, $hidden_meta_keys, true ) ) {
			$hidden_meta_keys[] = self::ORDER_DATE_KEY;
		}

		if ( ! in_array( self::ORDER_TIME_KEY, $hidden_meta_keys, true ) ) {
			$hidden_meta_keys[] = self::ORDER_TIME_KEY;
		}

		return $hidden_meta_keys;
	}
}
