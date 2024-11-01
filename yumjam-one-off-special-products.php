<?php

/* YumJam One-off Special Products for WooCommerce
 *
 * @package     YumJamRequireLogin
 * @author      Matt Burnett
 * @copyright   2016 YumJam
 * @license     GPL-2.0+
 * 
 * @wordpress-plugin
 * Plugin Name:    YumJam One-off Special Products for WooCommerce
 * Plugin URI:     https://www.yumjam.co.uk/yumjam-wordpress-plugins/yumjam-one-off-special-products-for-woocommerce/
 * Description:    The YumJam One-Off Specials plugin extends Woocommerce allow support for products that are one-off or bespoke
 * Author:         YumJam
 * Author URI:     https://www.yumjam.co.uk
 * Version:        1.0.3
 * Text Domain:    oneoffspecials
 * License:        GPL-2.0+
 * License URI:    http://www.gnu.org/licenses/gpl-2.0.txt
 * Tags:           WooCommerce, One-Off, One off, Bespoke, Special Products, Product Enquiry, Out of Stock, product inquiry, product enquiry
 * Requires at least: 4.0
 * Tested up to:   4.9.7
 * Stable tag:     4.9.7
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('YumJamOneOffSpecials')) {

    class YumJamOneOffSpecials {

        public function __construct() {
            define('YJOFS_PLUGIN_PATH', __DIR__);
            define('YJOFS_PLUGIN_URL', plugin_dir_url(__FILE__));

            $this->oneoffs_hooks();
        }

        /**
         * Hooks
         */
        public function oneoffs_hooks() {
            //WP Filters
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'oneoffs_plugin_action_links'));

            //WC Filters
            add_filter('woocommerce_get_stock_html', array($this, 'oneoffs_out_of_stock_display'), 10, 2);
            add_filter('woocommerce_get_sections_products', array($this, 'oneoffs_settings_section'), 10, 1);
            add_filter('woocommerce_get_settings_products', array($this, 'oneoffs_settings'), 10, 2);

            //WC Actions
            add_action('woocommerce_product_options_stock_status', array($this, 'oneoffs_stock_status'));
            add_action('woocommerce_process_product_meta', array($this, 'oneoffs_add_custom_linked_field_save'));
        }

        /**
         * Add extra links to plugins page, by active/deactivate link
         * @param array $links
         * @return array
         */
        public function oneoffs_plugin_action_links($links) {
            $links[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=products&section=oneoffs')) . '">Settings</a>';
            $links[] = '<a href="https://www.yumjam.co.uk" target="_blank">More by YumJam</a>';

            return $links;
        }

        /**
         * Add new Checkbox to WC Product Inventory tab
         * @global object $post
         * @param int $post_id
         */
        public function oneoffs_stock_status($post_id) {
            global $post;

            $oneoff = get_post_meta($post->ID, '_special_oneoff');

            woocommerce_wp_checkbox(array(
                'id' => '_special_oneoff',
                'value' => $oneoff[0],
                'wrapper_class' => '',
                'label' => __('Special one off', 'woocommerce'),
                'description' => __('Special one off product, dont show out of stock when sold', 'woocommerce'),
            ));
        }

        /**
         * Handle Saving of new Check box
         * @param int $post_id
         * @return boolean
         */
        public function oneoffs_add_custom_linked_field_save($post_id) {

            if (!( isset($_POST['woocommerce_meta_nonce'], $_POST['_special_oneoff']) || wp_verify_nonce(sanitize_key($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data') )) {
                return false;
            }

            $oneoff = sanitize_text_field(wp_unslash($_POST['_special_oneoff']));

            update_post_meta(
                    $post_id, '_special_oneoff', esc_attr($oneoff)
            );
        }

        /**
         * Replace out of stock message using setting content
         * 
         * @param string $html - incoming unmodified html
         * @param object $product - WooCommerce product object
         *
         * @return string
         */
        public function oneoffs_out_of_stock_display($html, $product) {
            $allowed_tags = wp_kses_allowed_html('post');

            $one_off = get_post_meta($product->get_id(), '_special_oneoff', true);
            $oos = $product->is_in_stock();

            if (!empty($one_off) && $one_off == 'yes' && isset($oos) && $oos == false) {
                $enabled = get_option('oneoffs_enable');
                $enable_button = get_option('oneoffs_enable_button');
                $message_text = get_option('oneoffs_message_text');
                $button_text = get_option('oneoffs_button_text');
                $popup_type = get_option('oneoffs_popup_type');
                $popup_title = get_option('oneoffs_popup_title');
                $popup_content = get_option('oneoffs_popup_content');
                $popup_height = get_option('oneoffs_popup_height');
                $popup_width = get_option('oneoffs_popup_width');

                if (!empty($enabled) && $enabled == 'yes' && (!empty($button_text) || !empty($message_text))) {
                    $output = '';
                                        
                    if (!empty($message_text)) {
                        $output .= $message_text;
                    }
                    
                    if (!empty($enable_button) && $enable_button == 'yes') {
                        //load modal support for popup
                        add_thickbox();
                        $output .= "<a name='" . esc_attr($popup_title) . "' class='button thickbox' href='#TB_inline?width=" . esc_attr($popup_width) . "&height=" . esc_attr($popup_height) . "&inlineId=one_off_popup'>" . esc_html($button_text) . "</a></br>";

                        //popup window
                        switch ($popup_type) {
                            case 'shortcode':
                                $output .= '<div id="one_off_popup" style="display:none;"><p>' . do_shortcode($popup_content) . '</p></div>';
                                break;
                            case 'html':
                                $output .= '<div id="one_off_popup" style="display:none;"><p>' . $popup_content . '</p></div>';
                            default:

                                break;
                        }
                    }
                    
                    return $output;
                }
            }

            return $html;
        }

        /**
         * Create the section beneath the settings products tab 
         * 
         * @param array $sections
         * @return array
         */
        public function oneoffs_settings_section($sections) {
            $sections['oneoffs'] = __('One Off Specials', 'oneoffspecials');
            return $sections;
        }

        /**
         * Populate settings section on products tab 
         * 
         * @param array $settings
         * @param string $current_section
         * @return array
         */
        public function oneoffs_settings($settings, $current_section) {
            if ($current_section == 'oneoffs') {
                return array(
                    array('name' => __('One Off Special Settings', 'oneoffspecials'),
                        'type' => 'title',
                        'desc' => __('The following options are used to configure the Out of Stock Override for One Off Special Products', 'oneoffspecials'),
                        'id' => 'oneoffs'
                    ),
                    array(
                        'name' => __('Override out of stock message', 'oneoffspecials'),
                        'desc_tip' => __('This will enable Override of the out of stock message on a product page', 'text-domain'),
                        'id' => 'oneoffs_enable',
                        'type' => 'checkbox',
                        'css' => 'min-width:300px;',
                        'desc' => __('Enable Override', 'oneoffspecials'),
                    ),
                    array(
                        'name' => __('Out of Stock message text', 'oneoffspecials'),
                        'desc' => __('Replace the out of stock messgae with the following text, or leave blank to ommit', 'oneoffspecials'),
                        'id' => 'oneoffs_message_text',
                        'default' => '',
                        'placeholder' => 'We\'ve sold this product',
                        'type' => 'textarea',
                    ),    
                    array(
                        'name' => __('Enable Button with Popup window', 'oneoffspecials'),
                        'desc_tip' => __('Show a button below the message text which triggers a popup window', 'text-domain'),
                        'id' => 'oneoffs_enable_button',
                        'type' => 'checkbox',
                        'css' => 'min-width:300px;',
                        'desc' => __('Enable Button', 'oneoffspecials'),
                    ),                    
                    array(
                        'name' => __('Button Text', 'oneoffspecials'),
                        'desc_tip' => __('The text to display on the Button', 'oneoffspecials'),
                        'id' => 'oneoffs_button_text',
                        'default' => 'Looking for something similar?',
                        'type' => 'text',
                    ),
                    array(
                        'name' => __('Popup Content Type', 'oneoffspecials'),
                        'desc' => __('Select the type of content to show in the Popup', 'oneoffspecials'),
                        'id' => 'oneoffs_popup_type',
                        'default' => 'html',
                        'type' => 'radio',
                        'options' => array(
                            'html' => __('Html', 'oneoffspecials'),
                            'shortcode' => __('Shortcode e.g. [gforms id="99"]', 'oneoffspecials'),
                        ),
                        'desc_tip' => true,
                    ),
                    array(
                        'name' => __('Popup Windows Title', 'oneoffspecials'),
                        'desc_tip' => __('Title of the Popup Window', 'oneoffspecials'),
                        'id' => 'oneoffs_popup_title',
                        'default' => 'One Off Special',
                        'placeholder' => 'Popup Window Title',
                        'type' => 'text',
                    ),
                    array(
                        'name' => __('Popup Content', 'oneoffspecials'),
                        'desc_tip' => __('What to display inside the Popup', 'oneoffspecials'),
                        'id' => 'oneoffs_popup_content',
                        'default' => '',
                        'placeholder' => 'Enter some Popup content here',
                        'type' => 'textarea',
                    ),
                    array(
                        'name' => __('Popup Height', 'oneoffspecials'),
                        'desc_tip' => __('Height of the Popup window in px', 'oneoffspecials'),
                        'id' => 'oneoffs_popup_height',
                        'default' => '400',
                        'desc' => 'px',
                        'type' => 'number',
                    ),
                    array(
                        'name' => __('Popup Width', 'oneoffspecials'),
                        'desc_tip' => __('Width of the Popup window in px', 'oneoffspecials'),
                        'id' => 'oneoffs_popup_width',
                        'default' => '300',
                        'desc' => 'px',
                        'type' => 'number',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'wcslider'
                    )
                );
            } else {
                return $settings;
            }
        }
    }
}

return new YumJamOneOffSpecials();
