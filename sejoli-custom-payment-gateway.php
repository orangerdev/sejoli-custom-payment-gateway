<?php

/**
 *
 * @link              https://ridwan-arifandi.com
 * @since             1.0.0
 * @package           Sejoli
 *
 * @wordpress-plugin
 * Plugin Name:       Sejoli - Custom Payment Gateway
 * Plugin URI:        https://sejoli.co.id
 * Description:       Example on how creating custom payment gateway for Sejoli premium membership
 * Version:           1.0.0
 * Requires PHP: 	  7.2.1
 * Author:            Sejoli
 * Author URI:        https://sejoli.co.id
 * Text Domain:       sejoli-ratapay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_filter('sejoli/payment/available-libraries', function( array $libraries ){

    require_once ( plugin_dir_path( __FILE__ ) . '/class-payment-gateway.php' );

    $libraries['ratapay'] = new \SejoliRatapay();

    return $libraries;
});
