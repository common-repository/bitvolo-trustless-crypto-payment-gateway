<?php
/*
Plugin Name: Bitvolo trustless crypto payment gateway
Plugin URI: https://bitvolo.com/woocommerce-trustless-crypto-payments/
Description: This plugin integrates Bitvolo.com trustless cryptocurrency payments (IOTA / Stellar XLM / EOS / TELOS / WAX / Ripple XRP) into WooCommerce checkout. Before using it, you'll need to create an account at bitvolo.com. Please see <a href='https://bitvolo.com/woocommerce-trustless-crypto-payments/'>https://bitvolo.com/woocommerce-trustless-crypto-payments/</a> for more info.
Version: 1.0
Author: Xtreeme GmbH
Author URI: https://bitvolo.com/
*/

/*  Copyright 2018 Xtreeme GmbH  (email : planyo@xtreeme.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_action( 'plugins_loaded', 'init_bitvolo_class' );

function add_bitvolo_class( $methods ) {
  $methods[] = 'WC_Gateway_Bitvolo'; 
  return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_bitvolo_class' );

function init_bitvolo_class() {

/**
 * WC_Gateway_Bitvolo Class.
 */
class WC_Gateway_Bitvolo extends WC_Payment_Gateway {

  /** @var bool Whether or not logging is enabled */
  public static $log_enabled = false;

  /** @var WC_Logger Logger instance */
  public static $log = false;

  /**
   * Constructor for the gateway.
   */
  public function __construct() {
    $this->id                 = 'bitvolo';
    $this->icon               = "https://bitvolo.com/images/bitvolo-paynow.png";
    $this->method_title       = __( 'Bitvolo', 'woocommerce' );

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.
    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
    $this->secret_key = $this->get_option( 'secret_key' );
    $this->account_id = $this->get_option( 'account_id' );

    self::$log_enabled    = $this->debug;

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    add_action('woocommerce_thankyou_bitvolo', array( $this, 'show_payment_fields'), 1, 1);
    add_action( 'woocommerce_api_wc_gateway_bitvolo', array( $this, 'process_ipn_response' ) );

    if ( ! $this->is_valid_for_use() ) {
      $this->enabled = 'no';
    } else {
      // ipn
    }
  }

  /**
   * Logging method.
   *
   * @param string $message Log message.
   * @param string $level   Optional. Default 'info'.
   *     emergency|alert|critical|error|warning|notice|info|debug
   */
  public static function log( $message, $level = 'info' ) {
    if ( self::$log_enabled ) {
      if ( empty( self::$log ) ) {
        self::$log = wc_get_logger();
      }
      self::$log->log( $level, $message, array( 'source' => 'bitvolo' ) );
    }
  }

    /**
     * Check if this gateway is enabled and available in the user's country.
     * @return bool
     */
    public function is_valid_for_use() {
      return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'AUD', 'BGN', 'BRL', 'CAD', 'BTC', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'IOTA', 'MIOTA', 'ISK', 'JPY', 'EOS', 'TLOS', 'WAX', 'XRP', 'XLM', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TRY', 'USD', 'ZAR') ) );
    }

    /**
     * Admin Panel Options.
     * - Options for bits like 'title' and availability on a country-by-country basis.
     *
     * @since 1.0.0
     */
    public function admin_options() {
        if ( $this->is_valid_for_use() ) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Bitvolo does not support your store currency.', 'woocommerce' ); ?></p></div>
            <?php
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
          'enabled'               => array(
            'title'   => __( 'Enable/Disable', 'woocommerce' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable Bitvolo', 'woocommerce' ),
            'default' => 'no',
          ),
          'title'                 => array(
            'title'       => __( 'Title', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default'     => __( 'Cryptocurrencies (IOTA / XML / XRP / EOS / TLOS / WAX) via Bitvolo', 'woocommerce' ),
            'desc_tip'    => true,
          ),
          'description'           => array(
            'title'       => __( 'Description', 'woocommerce' ),
            'type'        => 'text',
            'desc_tip'    => true,
            'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
            'default'     => __( "Pay with cryptocurrencies IOTA/XLM/XRP/EOS/TLOS/WAX or SEPA bank transfer via Bitvolo.", 'woocommerce' ),
          ),
          'account_id'                 => array(
            'title'       => __( 'Bitvolo account ID', 'woocommerce' ),
            'type'        => 'number',
            'description' => __( 'Please enter your Bitvolo account ID.', 'woocommerce' ),
            'default'     => '',
          ),
          'secret_key'                 => array(
            'title'       => __( 'Bitvolo secret key', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'Please enter your Bitvolo secret key. You can find it in the Bitvolo backend on the Settings page.', 'woocommerce' ),
            'default'     => '',
          ),
          'iota'=>array(
            'title' => __('Accept IOTA', 'woocommerce'),
            'description'=>__('Select if you want to accept payments in IOTA', 'woocommerce'),
            'label'=>__('IOTA', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'xlm'=>array(
            'title' => __('Accept XLM', 'woocommerce'),
            'description'=>__('Select if you want to accept payments in XLM', 'woocommerce'),
            'label'=>__('Stellar XLM', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'stellar_tokens' => array(
            'title'       => __( 'Accepted Stellar tokens', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'If you want to accept tokens on the Stellar network (except XML), enter comma-separated token codes, e.g. MOBI, USD, PEDI', 'woocommerce' ),
            'default'     => __( '', 'woocommerce' ),
          ),
          'xrp'=>array(
            'title' => __('Accept XRP', 'woocommerce'),
            'description'=>__('Select if you want to accept payments in XRP', 'woocommerce'),
            'label'=>__('XRP', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'eos'=>array(
            'title' => __('Accept EOS', 'woocommerce'),
            'description'=>__('Select if you want to accept payments in EOS', 'woocommerce'),
            'label'=>__('EOS', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'eos_tokens' => array(
            'title'       => __( 'Accepted EOS tokens', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'If you want to accept tokens on the EOS network (except EOS), enter comma-separated token codes, e.g. PEOS', 'woocommerce' ),
            'default'     => __( '', 'woocommerce' ),
          ),
          'tlos'=>array(
            'title' => __('Accept TLOS', 'woocommerce'),
            'description'=>__('Select if you want to accept payments in TLOS', 'woocommerce'),
            'label'=>__('TLOS', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'tlos_tokens' => array(
            'title'       => __( 'Accepted TLOS tokens', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'If you want to accept tokens on the TLOS network (except TLOS), enter comma-separated token codes, e.g. SQRL', 'woocommerce' ),
            'default'     => __( '', 'woocommerce' ),
          ),
          'wax'=>array(
            'title' => __('Accept WAX', 'woocommerce'),
            'description'=>__('Select if you want to accept payments in WAX', 'woocommerce'),
            'label'=>__('WAX', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'wax_tokens' => array(
            'title'       => __( 'Accepted WAX tokens', 'woocommerce' ),
            'type'        => 'text',
            'description' => __( 'If you want to accept tokens on the WAX network (except WAX), enter comma-separated token codes, e.g. PGL', 'woocommerce' ),
            'default'     => __( '', 'woocommerce' ),
          ),
          'bank'=>array(
            'title' => __('Accept SEPA', 'woocommerce'),
            'description'=>__('Select if you want to accept SEPA bank transfers', 'woocommerce'),
            'label'=>__('SEPA', 'woocommerce'),
            'type'=> 'checkbox',
          ),
          'extra_text'           => array(
            'title'       => __( 'Extra text', 'woocommerce' ),
            'type'        => 'text',
            'default'=>'',
            'description' => __( 'You can add extra text to be displayed above the payment buttons.', 'woocommerce' ),
          ),
          'debug'                 => array(
            'title'       => __( 'Debug log', 'woocommerce' ),
            'type'        => 'checkbox',
            'label'       => __( 'Enable logging', 'woocommerce' ),
            'default'     => 'no',
            /* translators: %s: URL */
            'description' => sprintf( __( 'Log Bitvolo events, such as IPN requests, inside %s', 'woocommerce' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'bitvolo' ) . '</code>' ),
          ),
        );
    }

  /**
   * Display the payment form
   */
  function show_payment_fields($order_id){
    $order = wc_get_order($order_id);
    if($order->get_payment_method() != 'bitvolo')
      return;

    $currency = get_woocommerce_currency();
    $amount = (float)$order->get_total();
    $ref = $order_id;
    $methods_arr = array();
    if($this->get_option('iota') == 'yes')
      $methods_arr []= "'IOTA'";
    if($this->get_option('xlm') == 'yes')
      $methods_arr []= "'XLM'";
    if($this->get_option('xrp') == 'yes')
      $methods_arr []= "'XRP'";
    if($this->get_option('eos') == 'yes')
      $methods_arr []= "'EOS'";
    if($this->get_option('tlos') == 'yes')
      $methods_arr []= "'TLOS'";
    if($this->get_option('wax') == 'yes')
      $methods_arr []= "'WAX'";
    if($this->get_option('bank') == 'yes')
      $methods_arr []= "'BANK'";
    if($this->get_option('stellar_tokens')) {
      $tokens = explode(",", $this->get_option('stellar_tokens'));
      if($tokens && count($tokens) > 0) {
        foreach($tokens as $t) {
          $t = strtoupper(trim($t));
          if($t != 'XML')
            $methods_arr []= ((strpos($t, '/') === false) ? "Stellar/" : "") . $t;
        }
      }
    }
    if($this->get_option('eos_tokens')) {
      $tokens = explode(",", $this->get_option('eos_tokens'));
      if($tokens && count($tokens) > 0) {
        foreach($tokens as $t) {
          $t = strtoupper(trim($t));
          if($t != 'EOS')
            $methods_arr []= ((strpos($t, '/') === false) ? "EOS/" : "") . $t;
        }
      }
    }
    if($this->get_option('tlos_tokens')) {
      $tokens = explode(",", $this->get_option('tlos_tokens'));
      if($tokens && count($tokens) > 0) {
        foreach($tokens as $t) {
          $t = strtoupper(trim($t));
          if($t != 'TLOS')
            $methods_arr []= ((strpos($t, '/') === false) ? "TLOS/" : "") . $t;
        }
      }
    }
    if($this->get_option('wax_tokens')) {
      $tokens = explode(",", $this->get_option('wax_tokens'));
      if($tokens && count($tokens) > 0) {
        foreach($tokens as $t) {
          $t = strtoupper(trim($t));
          if($t != 'WAX')
            $methods_arr []= ((strpos($t, '/') === false) ? "WAX/" : "") . $t;
        }
      }
    }
    $methods = implode(",", $methods_arr);

    wp_enqueue_style('woocommerce_bitvolo_css', "https://bitvolo.com/bitvolo.css");
    wp_enqueue_script('woocommerce_bitvolo_js', "https://bitvolo.com/bitvolo.js");
?>
    <div class='wc_bitvolo_extra_text'><?php echo $this->get_option('extra_text');?></div>
    <div id='wc_bitvolo_buttons'></div>
    <div id='wc_bitvolo_after' style='margin-bottom:30px'></div>
<?php
    wp_add_inline_script('woocommerce_bitvolo_js', "window.addEventListener('load', init_bitvolo_buttons); function init_bitvolo_buttons() {var bitvolo = new Bitvolo(); bitvolo.setup('wc_bitvolo_buttons', ".(int)$this->account_id.", $ref, '".sha1($this->secret_key . $ref . $currency . $amount)."', ".$amount.", '".esc_attr($currency)."', [".$methods."], {callback_url: '".WC()->api_request_url('WC_Gateway_Bitvolo')."', });}");
  }

  /**
   * Process the payment and return the result.
   * @param  int $order_id
   * @return array
   */
  public function process_payment( $order_id ) {
    global $woocommerce;
    $order = wc_get_order( $order_id );

    $order->update_status('on-hold', __( 'Awaiting payment', 'woocommerce' ));

    $woocommerce->cart->empty_cart();

    return array(
      'result' => 'success',
      'redirect'=> $this->get_return_url( $order ),
    );
  }

  public function process_ipn_response() {
    global $woocommerce;
    $this->log("IPN call received. Order id ".wp_strip_all_tags(esc_html($_REQUEST['reference']))." Transaction ".wp_strip_all_tags(esc_html($_REQUEST['transaction_id']))." Hash ".wp_strip_all_tags(esc_html($_REQUEST['hash']))." Amount ".((float)$_REQUEST['amount']). " ".($_REQUEST['success'] ? "Success" : ($_REQUEST['failure'] ? "Failure" : "")));
    $order_id = wp_strip_all_tags(esc_html($_REQUEST['reference']));
    $order = wc_get_order($order_id);
    if($_REQUEST['success']) {
      $this->log("Marking order ".$order_id. " as completed");
      $order->payment_complete();
      $order->add_order_note(__('Payment completed', 'woocommerce'));
    }
    else if($_REQUEST['failure']) {
      $this->log("Marking payment for order ".$order_id. " as failed");
      $order->update_status('failed', __( 'Payment not received', 'woocommerce' ));
    }
    return $order;
  }

}
}
