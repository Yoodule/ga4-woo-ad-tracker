<?php

class Ga4_Woo_Tracking {

    /**
     * GA4 ID
     * @var string
     */
    private $ga4_id = null;

    /**
     * GA4 Ad Conversion ID
     */
    private $conversion_id = null;

    public function __construct() {

        $ga4_id = get_option('ga4_id');
        $conversion_id = preg_replace('/^AW-/', '', get_option('conversion_id'));


        if($ga4_id) {
            $this->ga4_id = $ga4_id;
            $this->conversion_id = $conversion_id;

            // register after woocommerce has been loaded
            add_action('woocommerce_loaded', [$this, 'register_woocommerce_ga4_events']);
        }
    }

    public function register_woocommerce_ga4_events() {

        // add product info to cart item remove button
        add_filter('woocommerce_cart_item_remove_link', [$this, 'add_data_attributes_to_remove_button'], 10, 2);
        // send "remove_from_cart" event
        add_action('wp_head', [$this, 'ga4_woo_remove_from_cart_event']);

        // send "view_item" event
        add_action('woocommerce_after_single_product', [$this, 'ga4_woo_product_view_event']);

        // add product info to loop product item cart button
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'customize_add_to_cart_button'], 10, 3);
        // send "add_to_cart" event for loop item add to cart
        add_action('wp_head', [$this, 'ga4_woo_add_to_cart_item_event']);
        
        // send "add_to_cart" event for single_add_to_cart_button
        add_action('woocommerce_before_add_to_cart_button', [$this, 'ga4_woo_add_to_cart_event'], 10, 6);

        // send "begin_checkout" event
        add_filter('wp_head', [$this, 'ga4_woo_begin_checkout_event']);

        // On Order confirmation
        add_action('woocommerce_thankyou', [$this, 'ga4_woo_purchase_event'], 10, 1);

    }

    function add_data_attributes_to_remove_button($link, $cart_item_key) {
        if ( ! is_cart() || !function_exists('WC')) {
            return;
        }
        
        global $woocommerce;
        $cart = $woocommerce->cart->get_cart();
        $cart_item = $cart[$cart_item_key];
    
        $product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);
        $product_identifier = $this->get_product_identifier($product);
        $product_name = $product->get_name();
        $product_category = $this->product_get_category_line($product);
        $product_price = $product->get_price();
        $product_quantity = $cart_item['quantity'];
    
        $new_link = str_replace('<a ', '<a data-product_identifier="' . $product_identifier . '" data-product_name="' . $product_name . '" data-product_price="' . $product_price . '" data-product_quantity="' . $product_quantity . '" data-product_category="' . $product_category . '" ', $link);
    
        return $new_link;
    }

    function customize_add_to_cart_button($html, $product, $args) {
        $product_id = $this->get_product_identifier($product);
        $product_category = $this->product_get_category_line($product);
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        $product_quantity = 1;

        $html = str_replace(' href=', ' data-product_identifier="' . $product_id . '" data-product_name="' . $product_name . '" data-product_price="' . $product_price . '" data-product_quantity="' . $product_quantity . '" data-product_category="' . $product_category . '" href=', $html);
    
        return $html;
    }



    function ga4_woo_add_to_cart_event() {
        global $product;

        // Only run this on single product pages
        if ($product) {

            wp_enqueue_script('jquery');
    
            // Prepare product data
            $product_data = array(
                'id' => $this->get_product_identifier($product),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'category' => $this->product_get_category_line( $product ),
                'default_quantity' => 1,
            );
    
            // Localize the script with the product data
            wp_localize_script('jquery', 'productData', $product_data);
    
            $inline_script = '
                jQuery(document).ready(function($) {
                    $("body").on("click", ".single_add_to_cart_button", function() {
                        const product_quantity = $("input.qty").val() || productData.default_quantity;
                        gtag("event", "add_to_cart", {
                            items: [
                                {
                                    id: productData.id,
                                    name: productData.name,
                                    price: productData.price,
                                    category: productData.category,
                                    quantity: product_quantity
                                }
                            ]
                        });
                    });
                });
            ';
    
            wp_add_inline_script('jquery', $inline_script);
        }
    }   
    
    function ga4_woo_begin_checkout_event() {
       
        if ( ! is_cart() || !function_exists('WC')) {
            return;
        }
        
        $cart = WC()->cart->get_cart();
        $cart_items = array();
        
        foreach ($cart as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $variant = $this->product_get_variant_line($product);
            $data = array(
                'id' => $this->get_product_identifier($product),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'category' => $this->product_get_category_line($product),
                'quantity' => $cart_item['quantity'],
            );

            if($variant) {
                $data['variant'] = $variant;
            }

            $cart_items[] = $data;
        }
        
        // Enqueue the dependency script first
        wp_enqueue_script('jquery');

        ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var cart_total = "<?php echo wp_strip_all_tags(preg_replace( '/&#?[a-z0-9]+;/i', '', WC()->cart->get_cart_total())); ?>";
                    var cart_items = <?php echo json_encode($cart_items); ?>;
                    
                    $(document).on('click', '.checkout-button', function(event) {
                        gtag('event', 'begin_checkout', {
                            value: cart_total,
                            items: cart_items
                        });
                    });
                });
            </script>
        <?php

    }
        

    function ga4_woo_remove_from_cart_event() {
        // Enqueue the dependency script first
        wp_enqueue_script('jquery');

       ?>
        <script>
            // Check if this is the cart page
            jQuery(document).ready(function($) {
                $('body').on('click', '.product-remove .remove', function() {
                    const product_id = $(this).data('product_identifier');
                    const product_name = $(this).data('product_name');
                    const product_price = $(this).data('product_price');
                    const product_quantity = $(this).data('product_quantity');
                    const product_category = $(this).data('product_category');

                    gtag('event', 'remove_from_cart', {
                        items: [
                            {
                                item_id: product_id,
                                item_name: product_name,
                                price: product_price,
                                quantity: product_quantity,
                                category: product_category
                            }
                        ]
                    });
                });
            });
        </script>
       <?php
    }

    function ga4_woo_add_to_cart_item_event() {
        if ( ! is_shop()) {
            return;
        }
        

        // Enqueue the dependency script first
        wp_enqueue_script('jquery');

       ?>
        <script>
            // Check if this is the cart page
            jQuery(document).ready(function($) {
                $('body').on('click', '.ajax_add_to_cart', function(e) {
                    
                    const product_id = $(this).data('product_identifier');
                    const product_name = $(this).data('product_name');
                    const product_price = $(this).data('product_price');
                    const product_quantity = $(this).data('product_quantity');
                    const product_category = $(this).data('product_category');

                    gtag('event', 'add_to_cart', {
                        items: [
                            {
                                id: product_id,
                                name: product_name,
                                price: product_price,
                                category: product_category,
                                quantity: product_quantity
                            }
                        ]
                    });
                });
            });
        </script>
        <?php
    }


    function ga4_woo_purchase_event($order_id) {
        $order = wc_get_order($order_id);
        
        // Transaction details
        $transaction_id = $order->get_order_number();
        $currency = $order->get_currency();
        $value = $order->get_total();
        $discount = $order->get_total_discount();
        $tax = $order->get_total_tax();
        $shipping = $order->get_shipping_total();
        $store_name = get_bloginfo('name');
        $coupon_codes = implode(", ", $order->get_coupon_codes());
        
        // Items array
        $items_array = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $variant = $this->product_get_variant_line($product);
            $data = [
                'quantity' => $item->get_quantity(),
                'price' => $product->get_price(),
                'name' => $product->get_name(),
                'category' => implode(', ', wc_get_product_terms($product->get_id(), 'product_cat', ['fields' => 'names'])),
                'id' => $this->get_product_identifier($product),
            ];

            if($variant) {
                $data['variant'] = $variant;
            }

            $items_array[] = $data;
        }
        
        $items_json = json_encode($items_array);
        ?>

        <script>
            gtag('event', 'purchase', {
                'transaction_id': '<?php echo $transaction_id; ?>',
                'affiliation': '<?php echo $store_name ?>',
                'currency': '<?php echo $currency; ?>',
                'value': <?php echo $value; ?>,
                'discount': <?php echo $discount; ?>,
                'tax': <?php echo $tax; ?>,
                'shipping': <?php echo $shipping; ?>,
                'coupon': '<?php echo $coupon_codes; ?>',
                'items': <?php echo $items_json; ?>
            });
        </script>
        <?php 
    }


    function ga4_woo_product_view_event() {
        global $product;
        $product_id = $this->get_product_identifier($product);
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        $currency = get_woocommerce_currency();

    ?>
        <script>
            gtag('event', 'view_item', {
                'items': [{
                    'id': '<?php echo $product_id; ?>',
                    'name': '<?php echo $product_name; ?>',
                    'price': '<?php echo $product_price; ?>',
                    'currency': '<?php echo $currency; ?>',
                    'category': '<?php echo $this->product_get_category_line( $product ) ?>',
                }]
            });
        </script>
    <?php
    }

    /**
	 * Get item identifier from product data
	 *
	 * @param  WC_Product $product WC_Product Object
	 * @return string
	 */
	public static function get_product_identifier( $product ) {
		if ( ! empty( $product->get_sku() ) ) {
			return esc_js( $product->get_sku() );
		} else {
			return esc_js( '#' . ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $this->get_product_identifier($product) ) );
		}
	}

    /**
	 * Returns a list of category names the product is atttributed to
	 *
	 * @param  WC_Product $product Product to generate category line for
	 * @return string
	 */
	public static function product_get_category_line( $product ) {
		$category_names = array();
		$categories     = get_the_terms( $product->get_id(), 'product_cat' );

		$variation_data = $product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $product->get_id() ) : false;
		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$categories = get_the_terms( $product->get_parent_id(), 'product_cat' );
		}

		if ( false !== $categories && ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				$category_names[] = $category->name;
			}
		}

		return join( '/', $category_names );
	}

    /**
	 * Returns a 'variant' JSON line based on $product
	 *
	 * @param  WC_Product $_product  Product to pull info for
	 * @return string                Line of JSON
	 */
	public static function product_get_variant_line( $_product ) {
		$out            = '';
		$variation_data = $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : false;

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$out = "'" . esc_js( wc_get_formatted_variation( $variation_data, true ) ) . "',";
		}

		return $out;
	}
}

// Initialize the class
new Ga4_Woo_Tracking();