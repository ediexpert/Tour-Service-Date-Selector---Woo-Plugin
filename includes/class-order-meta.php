<?php
/**
 * Order meta class.
 *
 * @package TSDS
 */

declare( strict_types=1 );

namespace TSDS;

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
	private const ORDER_DATE_KEY = 'tsds_booking_date';
	private const ORDER_TIME_KEY = 'tsds_booking_time';

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
		$date = $item->get_meta( self::ORDER_DATE_KEY );
		$time = $item->get_meta( self::ORDER_TIME_KEY );

		if ( ! $date && ! $time ) {
			return;
		}

		echo '<div class="tsds-order-meta">';

		if ( $date ) {
			printf(
				'<p class="tsds-order-meta__row"><strong>%s:</strong> %s</p>',
				esc_html__( 'Booking Date', 'tour-service-date-selector' ),
				esc_html( $date )
			);
		}

		if ( $time ) {
			printf(
				'<p class="tsds-order-meta__row"><strong>%s:</strong> %s</p>',
				esc_html__( 'Booking Time', 'tour-service-date-selector' ),
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
		$date = $item->get_meta( self::ORDER_DATE_KEY );
		$time = $item->get_meta( self::ORDER_TIME_KEY );

		if ( $date ) {
			$meta          = new \stdClass();
			$meta->key     = self::ORDER_DATE_KEY;
			$meta->label   = __( 'Booking Date', 'tour-service-date-selector' );
			$meta->value   = esc_html( $date );
			$meta->display_key   = __( 'Booking Date', 'tour-service-date-selector' );
			$meta->display_value = esc_html( $date );
			$formatted_meta[]    = $meta;
		}

		if ( $time ) {
			$meta          = new \stdClass();
			$meta->key     = self::ORDER_TIME_KEY;
			$meta->label   = __( 'Booking Time', 'tour-service-date-selector' );
			$meta->value   = esc_html( $time );
			$meta->display_key   = __( 'Booking Time', 'tour-service-date-selector' );
			$meta->display_value = esc_html( $time );
			$formatted_meta[]    = $meta;
		}

		return $formatted_meta;
	}
}
