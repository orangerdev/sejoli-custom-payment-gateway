# Custom payment gateway for Sejoli Membership Plugin
The plugin is an example for Sejoli custom payment gateway. send me any email for further information: orangerdigiart@gmail.com

At least you need :
1. a PHP file to set filter hook to declare the payment gateway class
2. a class that extends class \\SejoliSA\\Payment, with CarbonFields loaded

We need CarbonFields to set plugin options.

Hook filter that you need to use is *sejoli/payment/available-libraries*.

For payment gateway class, at least you need these properties :

1. $id *string*, set the payment gateway ID, alphanumeric with dash only.
2. $name *string*, set the payment gateway name.
3. $title *string*, set the payment gateway title.
4. $description *string*, set the payment gateway description.

and for methods, you need these :

1. get_setup_fields(), return array with CarbonFields value
2. set_price(), return with price, if you need to charge with payment gateway fee
3. set_meta_data(), return with an array of payment info
4. display_payment_instruction()
5. display_simple_payment_instruction()
6. set_payment_info()

If the payment gateway needs custom link to receive notification, you need to setup

```php
/**
 * Set callback recipient only for ratapay
 * Hooked via action init
 * @since   1.0.0
 * @return  void
 */
public function set_endpoint() {

    add_rewrite_rule( '^ratapay/([^/]*)/?',		'index.php?ratapay-method=1&action=$matches[1]','top');

    flush_rewrite_rules();
}

/**
 * Set custom query vars
 * Hooked via filter query_vars, priority 100
 * @since   1.0.0
 * @access  public
 * @param   array $vars
 * @return  array
 */
public function set_query_vars($vars)
{
    $vars[] = 'ratapay-method';

    return $vars;
}
```

you can change *ratapay* or *ratapay-method* to anything you desire.

and you need to use hook *parse_query* to do anything with the notification

```php
/**
 * Check parse query and if ratapay-method exists and do the proses
 * Hooked via action parse_query, priority 999
 * @since 1.0.0
 * @access public
 * @return void
 */
public function check_parse_query() {

    global $wp_query;

    if(is_admin() || $this->is_called ) :
        return;
    endif;

    if(
        isset($wp_query->query_vars['ratapay-method']) &&
        isset($wp_query->query_vars['action']) && !empty($wp_query->query_vars['action'])
    ) :

        // EXAMPLE ONLY!!!
        $order_id = $_GET['order_id'];
        $action   = strtolower($wp_query->query_vars['action']);

        if('completed' === $action ) :
            $this->complete_order( $order_id );
        else :
            // ELSE ACTION
        endif;

    endif;

    $this->is_called = true; // PREVENT DOUBLE CALLED
}
```

to display payment channel in checkout page, you need this method, hook with action *sejoli/payment/payment-options*

```php
/**
 * Display ratapay payment options in checkout page
 * Hooked via filter sejoli/payment/payment-options, priority 100
 * @since   1.0.0
 * @param   array $options
 * @return  array
 */
public function add_payment_options( array $options ) {

    $active = boolval( carbon_get_theme_option('ratapay_active') );

    if( true === $active ) :

        // EXAMPLE!!
        // Listing available payment channels from your payment gateways
        $methods = array(
            'va-mandiri',
            'cc',
            'gopay'
        );

        foreach($methods as $method_id) :

            // MUST PUT ::: after payment ID
            $key = 'ratapay:::' . $method_id;

            switch($method_id) :

                case 'va-mandiri' :

                    $options[$key] = [
                        'label' => __('Transaksi via Bank Mandiri', 'sejoli-ratapay'),
                        'image' => plugin_dir_url( __FILE__ ) . 'img/MANDIRI.png'
                    ];

                    break;

                case 'cc' :

                    $options[$key] = [
                        'label' => __('Transaksi via Kartu Kredit', 'sejoli-ratapay'),
                        'image' => plugin_dir_url( __FILE__ ) . 'img/CC.png'
                    ];

                    break;

                case 'gopay' :

                    $options[$key] = [
                        'label' => __('Transaksi via Gopay', 'sejoli-ratapay'),
                        'image' => plugin_dir_url( __FILE__ ) . 'img/GOPAY.png'
                    ];

                    break;

            endswitch;

        endforeach;

    endif;

    return $options;

}
```

if your payment gateway need customer to do something in payment gateway website / redirect after invoice created, use action hook *sejoli/thank-you/render*

```php
/**
 * Check if current order is using ratapay and will be redirected to ratapay payment channel options
 * Hooked via action sejoli/thank-you/render, priority 100
 * @since   1.0.0
 * @param   array  $order Order data
 * @return  void
 */
public function check_for_redirect(array $order) {

    if(
        isset($order['payment_info']['bank']) &&
        'RATAPAY' === strtoupper($order['payment_info']['bank'])
    ) :

        if('on-hold' === $order['status']) :

            // PUT ANY REQUEST CODE HERE!
            // EXAMPLE IF WE NEED TO REDIRECT TO OUTSIDE

            $response_url = 'https://google.com';

            wp_redirect( $response_url );
            exit;

        elseif(in_array($order['status'], array('refunded', 'cancelled'))) :

            $title = __('Order telah dibatalkan', 'sejoli');
            require SEJOLISA_DIR . 'template/checkout/order-cancelled.php';

        else :

            $title = __('Order sudah diproses', 'sejoli');
            require SEJOLISA_DIR . 'template/checkout/order-processed.php';

        endif;

        exit;

    endif;
}
```
