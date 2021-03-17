<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;

final class SejoliRatapay extends \SejoliSA\Payment{

    /**
     * Prevent double method calling
     * @since   1.0.0
     * @access  protected
     * @var     boolean
     */
    protected $is_called = false;

    /**
     * Construction
     */
    public function __construct() {

        $this->id             = 'ratapay';
        $this->name           = __('Ratapay', 'sejoli');
        $this->title          = __('Ratapay', 'sejoli');
        $this->description    = __('Transaksi menggunakan Ratapay payment gateway.', 'sejoli-ratapay');

        add_action( 'init',                             array($this, 'set_endpoint'),           100);
        add_filter( 'query_vars',                       array($this, 'set_query_vars'),         100);
        add_filter( 'sejoli/payment/payment-options',   array($this, 'add_payment_options'),    100);
        add_action( 'parse_query',                      array($this, 'check_parse_query'),      100);
        add_action( 'sejoli/thank-you/render',          array($this, 'check_for_redirect'),     100);

    }

    /**
     * Set callback recipient only for ratapay
     * Hooked via action init, priority 100
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
     * @since   1.3.0
     * @access  public
     * @param   array $vars
     * @return  array
     */
    public function set_query_vars($vars)
    {
        $vars[] = 'ratapay-method';

        return $vars;
    }

    /**
     * Completed an order
     * @since   1.0.0
     * @param   int    $order_id
     * @return  void
     */
    protected function complete_order( int $order_id ) {

        $response = sejolisa_get_order(array('ID' => $order_id));

        if(false !== $response['valid']) :

            $order    = $response['orders'];
            $product  = $order['product'];

            // if product is need of shipment
            if(false !== $product->shipping['active']) :
                $status = 'in-progress';
            else :
                $status = 'completed';
            endif;

            // call parent method class
            $this->update_order_status( $order['ID'] );

            $args['status'] = $status;

            do_action('sejoli/log/write', 'ratapay-update-order', $args);
        else :
            do_action('sejoli/log/write', 'ratapay-wrong-order', $args);
        endif;

    }

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
            isset($wp_query->query_vars['duitku-method']) &&
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

    /**
     * Set option in Sejoli payment options, we use CARBONFIELDS for plugin options
     * Called from parent method
     * @since   1.0.0
     * @return  array
     */
    public function get_setup_fields( ) {

        return array(

            // Read https://docs.carbonfields.net/#/ for further information on using carbon fields

            Field::make('separator', 'sep_ratapay_tranaction_setting',	__('Pengaturan Ratapay', 'sejoli')),

            Field::make('checkbox', 'ratapay_active',   __('Aktifkan pembayaran melalui ratapay', 'sejoli-ratapay')),

            Field::make('select',   'ratapay_mode',     __('Payment Mode', 'sejoli-ratapay'))
                ->set_options(array(
                    'sandbox'   => 'Sandbox',
                    'live'      => 'Live'
                ))
        );
    }

    /**
     * Display ratapay payment options in checkout page
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

    /**
     * Set order price if there is any fee need to be added
     * @since   1.0.0
     * @param   float $price
     * @param   array $order_data
     * @return  float
     */
    public function set_price(float $price, array $order_data) {

        if(0.0 !== $price ) :

            $this->order_price = $price;

            return floatval($this->order_price);

        endif;

        return $price;
    }

    /**
     * Set order meta data
     * @since   1.3.0
     * @param   array $meta_data
     * @param   array $order_data
     * @param   array $payment_subtype
     * @return  array
     */
    public function set_meta_data(array $meta_data, array $order_data, $payment_subtype) {

        $meta_data['ratapay'] = [
            'trans_id'  => '',
            'unique_key'=> substr(md5(rand(0,1000)), 0, 16),
            'method'    => $payment_subtype
        ];

        return $meta_data;
    }

    /**
     * Check if current order is using ratapay and will be redirected to ratapay payment channel options
     * Hooked via action sejoli/thank-you/render, priority 100
     * @since   1.3.0
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

    /**
     * Display payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media             email,whatsapp,sms
     * @return  string
     */
    public function display_payment_instruction($invoice_data, $media = 'email') {

        if('on-hold' !== $invoice_data['order_data']['status']) :
            return;
        endif;

        ob_start();

        // PUT TEMPLATE ON PAYMENT INSTRUCTION HERE BASED ON MEDIA

        $content = ob_get_contents();

        ob_end_clean();

        return $content;
    }

    /**
     * Display simple payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media
     * @return  string
     */
    public function display_simple_payment_instruction($invoice_data, $media = 'email') {

        if('on-hold' !== $invoice_data['order_data']['status']) :
            return;
        endif;

        $content = __('via Ratapay', 'sejoli');

        return $content;
    }

    /**
     * Set payment info to order data
     * @since   1.0.0
     * @param   array $order_data
     * @return  array
     */
    public function set_payment_info(array $order_data) {

        $trans_data = [
            'bank'  => 'Ratapay'
        ];

        return $trans_data;
    }

}
