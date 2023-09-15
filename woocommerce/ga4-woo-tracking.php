<?php

class Ga4_Woo_Tracking {

    /**
     * GA4 ID
     * @var string
     */
    protected $ga4_id = null;

    /**
     * GA4 Ad Conversion ID
     */
    protected $conversion_id = null;

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
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'customize_add_to_cart_button'], 30);
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

    function customize_add_to_cart_button($html) {
        global $product;
        
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
        
            ?>
            <script>
                jQuery(document).ready(function($) {
                    $(document).on("click", ".single_add_to_cart_button", function() {
                        const productData = <?php echo json_encode(array(
                            'id' => $this->get_product_identifier($product),
                            'name' => $product->get_name(),
                            'price' => $product->get_price(),
                            'category' => $this->product_get_category_line( $product ),
                            'default_quantity' => 1,
                        )) ?>;
                        const product_quantity = $("input.qty").val() || productData.default_quantity;
                        <?php echo $this->gtag('add_to_cart', "{
                            items: [
                                {
                                    id: productData.id,
                                    name: productData.name,
                                    price: productData.price,
                                    category: productData.category,
                                    quantity: product_quantity
                                }
                            ]
                        }"); ?>
                    });
                });
            </script>
    
           <?php
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
                    
                    $(document).on('click', '.checkout-button,a[href*="/checkout/"]', function(event) {
                        <?php echo $this->gtag('begin_checkout', "{
                            value: cart_total,
                            items: cart_items
                        }");?>
                    });
                });
            </script>
        <?php

    }
        

    function ga4_woo_remove_from_cart_event() {
        if ( ! is_cart() || !function_exists('WC')) {
            return;
        }
        
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

                    <?php echo $this->gtag('remove_from_cart', "{
                        items: [
                            {
                                item_id: product_id,
                                item_name: product_name,
                                price: product_price,
                                quantity: product_quantity,
                                category: product_category
                            }
                        ]
                    }");?>
                });
            });
        </script>
       <?php
    }

    function ga4_woo_add_to_cart_item_event() {

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

                    <?php echo $this->gtag('add_to_cart', "{
                        items: [
                            {
                                id: product_id,
                                name: product_name,
                                price: product_price,
                                category: product_category,
                                quantity: product_quantity
                            }
                        ]
                    }"); ?>
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
        
        echo $this->gtag('purchase', "{
            'transaction_id': '$transaction_id',
            'affiliation': '$store_name',
            'currency': '$currency',
            'value': $value,
            'discount': $discount,
            'tax': $tax,
            'shipping': $shipping,
            'coupon': '$coupon_codes',
            'items': ".json_encode($items_array)."
        }", true);
    }


    function ga4_woo_product_view_event() {
        global $product;
        $product_id = $this->get_product_identifier($product);
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        $currency = get_woocommerce_currency();

    ?>
        <script>
            <?php echo $this->gtag('view_item', "{
                'items': [{
                    'id': '$product_id',
                    'name': '$product_name',
                    'price': '$product_price',
                    'currency': '$currency',
                    'category': '".$this->product_get_category_line( $product )."',
                }]
            }");?>
        </script>
    <?php
    }

    /**
	 * Get item identifier from product data
	 *
	 * @param  WC_Product $product WC_Product Object
	 * @return string
	 */
	public function get_product_identifier( $product ) {
		if ( ! empty( $product->get_sku() ) ) {
			return esc_js( $product->get_sku() );
		} else {
			return esc_js( '#' . ( $product->is_type( 'variation' ) ? $product->get_parent_id() :  $product->get_id() ) );
		}
	}

    /**
	 * Returns a list of category names the product is atttributed to
	 *
	 * @param  WC_Product $product Product to generate category line for
	 * @return string
	 */
	public function product_get_category_line( $product ) {
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
	public function product_get_variant_line( $_product ) {
		$out            = '';
		$variation_data = $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : false;

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$out = "'" . esc_js( wc_get_formatted_variation( $variation_data, true ) ) . "',";
		}

		return $out;
	}

    function gtag($eventName, $paramsJson, $withScript=false) {
        // Initialize JS code string
        $jsCode = "";

        if($withScript) $jsCode .= "<script>";

        // Add the GA4 event tracking code
        $jsCode .= "gtag('event', '{$eventName}', {$paramsJson});\n";

        // Initialize Google Ads parameters
        $adsParamsJs = $paramsJson;

        // Check if conversion_id exists within the function
        if (isset($this->conversion_id)) {
            // Fetch the conversion label from the database and remove 'AW-' prefix if it exists
            $conversion_label = preg_replace('/^AW-/', '', get_option("conversion_{$eventName}_label"));

            // Determine the send_to value based on available data
            $send_to = $conversion_label ? esc_js($this->conversion_id) . '/' . esc_js($conversion_label) : esc_js($this->conversion_id);

            // Append the send_to parameter
            $adsParamsJs = "{\nsend_to: 'AW-{$send_to}'," . substr($adsParamsJs, 1);

            // Add the Google Ads conversion tracking code
            $jsCode .= "gtag('event', '{$eventName}', {$adsParamsJs});\n";

        }

        if($withScript) $jsCode .= "</script>";

        return $jsCode;
    }
}

// Initialize the class
new Ga4_Woo_Tracking();