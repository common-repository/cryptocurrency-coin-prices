<?php
/*
Plugin Name: Crypto Coin Market Prices
Plugin URI: http://wordpress.org/plugins/cryptocurrency-coin-prices
Description: Easy to use option for setting up a bitcoin and altcoin exchange rate.
Text Domain: cryptocurrency-coin-prices
Domain Path: /languages
Version: 1.0.1
Author: MyBitcoin
Author URI: https://www.mybitcoin.com
License: GPLv2 or later (if another license is not provided)


*/

include( "main.php" );

// #######################################################################

register_activation_hook( __FILE__, 'cryptocurrency_prices\\OnActivate' );
register_deactivation_hook( __FILE__, 'cryptocurrency_prices\\OnDeactivate' );
//register_uninstall_hook( __FILE__, 'cryptocurrency_prices\\OnUninstall' );

// #######################################################################
// #######################################################################
