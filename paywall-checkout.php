<?php

/**
 * Plugin Name: PayWall Checkout
 * Description: Supports for PayWall Checkout in Woocommerce
 * Author: PayWall
 * Author URI: http://paywall.app/
 * Version: 1.0.0
 */


// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Require the Stripe PHP library
require_once dirname(__FILE__) . '/vendor/stripe/stripe-php/init.php';
//Pre define constant variables.
if (!defined("PAYWALL_PLUGIN_DIR")) define("PAYWALL_PLUGIN_DIR", plugin_dir_path(__FILE__));
if (!defined("PAYWALL_PLUGIN_URL")) define("PAYWALL_PLUGIN_URL", plugins_url('', __FILE__));

add_filter('woocommerce_payment_gateways', 'add_stripe_card_element_gateway');

// Add the Stripe Card Element gateway to WooCommerce
function add_stripe_card_element_gateway($methods)
{
    $methods[] = 'WC_Stripe_Card_Element_Gateway';
    return $methods;
}

add_action('plugins_loaded', 'init_stripe_card_element_gateway');

// Initialize the Stripe Card Element gateway
function init_stripe_card_element_gateway()
{

    class WC_Stripe_Card_Element_Gateway extends WC_Payment_Gateway
    {
        // Constructor
        public function __construct()
        {
            $this->id                 = 'stripe_card_element';
            // $this->icon               = plugins_url('images/paywall.png', __FILE__);
            $this->has_fields         = true;
            $this->method_title       = __('PayWall Card Payments', 'woocommerce');
            $this->method_description = __('Accept payments with PayWall.', 'woocommerce');

            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // Load the form fields
            $this->init_form_fields();
            // Load the settings
            $this->init_settings();
            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->testmode = $this->get_option('testmode') === 'yes' ? true : false;
            $paywall_account_id = $this->testmode ? $this->get_option('paywall_test_account_id') : $this->get_option('paywall_account_id');
            try {
                $pwUrl = $this->testmode ? 'https://teamdev.paywall.app/api/team-fee/' . esc_attr($paywall_account_id) : 'https://team.paywall.app/api/team-fee/' . esc_attr($paywall_account_id);
                $response = wp_remote_get($pwUrl);
                if (is_wp_error($response)) {
                    // handle the error and return an appropriate response
                    wc_add_notice(__('Error #1011, Sorry there was an issue processing the checkout. Please try again.', 'woocommerce'), 'error');
                    return;
                }
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $this->publishable_key = $data['pk'];
            } catch (Exception $e) {
                wc_add_notice(__('Error #1012, Sorry there was an issue processing the checkout. Please try again.', 'woocommerce'), 'error');
                return;
            }
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        }

        // Initialize form fields
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable', 'woocommerce'),
                    'label'       => __('Enable the Paywall Card Element', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default'     => __('Credit Card Payment', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default'     => __('Pay with your credit card via Paywall.', 'woocommerce'),
                ),
                'paywall_account_id' => array(
                    'title' => __('PayWall Account ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('PayWall Live Account ID', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'testmode' => array(
                    'title'       => __('Test mode', 'woocommerce'),
                    'label'       => __('Enable Test Mode', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => __('Place the payment gateway in test mode using test API keys.', 'woocommerce'),
                    'default'     => false,
                    'desc_tip'    => true,
                ),
                'paywall_test_account_id' => array(
                    'title' => __('PayWall Test Account ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('PayWall Test Account ID', 'woocommerce'),
                    'desc_tip' => true,
                ),

            );
        }

        // Payment scripts
        public function payment_scripts()
        {
            wp_enqueue_script('stripe-card-element', 'https://js.stripe.com/v3/', array(), null, true);
            wp_enqueue_script('paywall-script', PAYWALL_PLUGIN_URL . '/paywall-stripe.js', array(), time(), 'all');
            $paywall_params = array('site_url' => site_url(), 'publishable_key' => $this->publishable_key);
            wp_localize_script('paywall-script', 'paywall', $paywall_params);
        }

        // Process the payment
        public function process_payment($order_id)
        {
            if (isset($_POST)) {
                //    save  $_POST to file
                $file = fopen("test.txt", "w");
                fwrite($file, print_r($_POST, true));
                fclose($file);
            }

            global $woocommerce;

            $paywall_account_id = $this->testmode ? $this->get_option('paywall_test_account_id') : $this->get_option('paywall_account_id');

            try {
                $pwUrl = $this->testmode ? 'http://teamdev.paywall.app/api/team-fee/' . esc_attr($paywall_account_id ?? '-') : 'http://team.paywall.app/api/team-fee/' . esc_attr($paywall_account_id ?? '-');
                $response = wp_remote_get($pwUrl);
                if (is_wp_error($response)) {
                    // handle the error and return an appropriate response
                    wc_add_notice(__('Error #1011, Sorry there was an issue processing the checkout. Please try again.', 'woocommerce'), 'error');
                    return;
                }
                $data = json_decode(wp_remote_retrieve_body($response), true);
            } catch (Exception $e) {
                wc_add_notice(__('Error #1012, Sorry there was an issue processing the checkout. Please try again.', 'woocommerce'), 'error');
                return;
            }

            if (isset($data['fee_percentage']) && is_numeric($data['fee_percentage']) && isset($data['fee_fixed']) && is_numeric($data['fee_fixed'])) {
                $fee_percentage = $data['fee_percentage'];
                $fee_fixed = $data['fee_fixed'];
                $pk = $data['pk'];
                $sk = $data['sk'];
            } else {
                // handle the error and return an appropriate response
                wc_add_notice(__('Error #1013, Sorry there was an issue processing the checkout. Please try again.', 'woocommerce'), 'error');
                return;
            }
            \Stripe\Stripe::setApiKey($sk);
            $order = wc_get_order($order_id);
            $orderTotal = $order->get_total() * 100;
            $paywall_Application_fee = ($order->get_total()) * ($fee_percentage / 100);
            $destination_amount = (int)(($order->get_total()) - ($paywall_Application_fee + $fee_fixed)) * 100;

            // Try to get the customer from stripe using the email
            $customerId = null;
            try {
                $customerExists = \Stripe\Customer::all([
                    'limit' => 1,
                    'email' => $order->get_billing_email()
                ]);
                // attach source to customer
                if (isset($customerExists['data'][0]['id'])) {
                    $customer = \Stripe\Customer::retrieve($customerExists['data'][0]['id']);

                    // Retrieve customer's existing sources
                    $sources = \Stripe\Customer::retrieve($customerExists['data'][0]['id'])->sources->data;

                    // Iterate through the sources
                    foreach ($sources as $source) {
                        if ($source->last4 == $_POST['paywall_last4']) {
                            // Card already exists, delete it
                            $source->delete();
                        }
                    }
                    // Create a new source
                    try {
                        $source = \Stripe\Customer::createSource(
                            $customerExists['data'][0]['id'],
                            ['source' => $_POST['paywall_stripe_token']]
                        );
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        // handle the error and return an appropriate response
                        wc_add_notice(__($e->getMessage(), 'woocommerce'), 'error');
                        return;
                    }
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                // handle the error and return an appropriate response
                wc_add_notice(__($e->getMessage(), 'woocommerce'), 'error');
            }
            if (isset($customerExists['data'][0]['id'])) {
                $customer = \Stripe\Customer::retrieve($customerExists['data'][0]['id']);
            } else {
                // Create customer in Stripe
                $customer = \Stripe\Customer::create(array(
                    'email' => $order->get_billing_email(),
                    'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'source' => $_POST['paywall_stripe_token'],
                ));
            }
            try {
                $charge = \Stripe\Charge::create(array(
                    'amount'      => $order->get_total() * 100,
                    'customer'    => $customer->id,
                    'currency'    => strtolower($order->get_currency()),
                    'source'      => $source->id,
                    'description' => sprintf(__('%s - Order %s', 'woocommerce'), esc_html(get_bloginfo('name')), $order->get_order_number()),
                    'transfer_data' => [
                        'destination' => $paywall_account_id,
                        'amount' => $destination_amount,
                    ],
                ));

                if ($charge->status == 'succeeded') {
                    $order->add_order_note(__('Paywall Payment completed', 'woocommerce'));
                    $order->payment_complete();
                    $woocommerce->cart->empty_cart();

                    // Add charge ID to order meta data
                    $charge_id = $charge->id;
                    $order->update_meta_data('_charge_id', $charge_id);
                    $order->save();

                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    $order->add_order_note(__('Paywall Payment failed', 'woocommerce'));
                    wc_add_notice(__('Payment error:', 'woocommerce') . $charge->failure_message, 'error');
                    return;
                }
            } catch (Exception $e) {
                wc_add_notice(__('Payment error:', 'woocommerce') . $e->getMessage(), 'error');
                return;
            }
        }

        // Check if the gateway is available for use
        public function is_available()
        {
            if ($this->enabled == 'no') {
                return false;
            }
            return true;
        }

        // Payment form on checkout page
        public function payment_fields()
        {
?>
            <div style="font-size: 14px">
                <?php echo $this->description;; ?>
            </div>
            <div class="form-group my-4" style="background-color: #f0f0f0; padding: 1rem; border-radius: 5px">
                <div id="card-element" style="border-radius: 5px"></div>
            </div>
            <script src='https://js.stripe.com/v3/' id='stripe-card-element-js'></script>
            <script>
                var stripe = Stripe('<?php echo $this->publishable_key; ?>');
                var elements = stripe.elements();
                var card = elements.create("card", {
                    hidePostalCode: true,
                });
                card.mount("#card-element");
            </script>

<?php
        }
    }
}
