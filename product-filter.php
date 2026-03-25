<?php
/**
 * Plugin Name: WooCommerce Product Category Filter
 * Description: Filters products on shop and search pages based on selected categories.
 * Version: 1.1
 * Author: Faisal
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter product queries
 */
function filter_product_queries($query) {

    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!is_search() && !is_shop()) {
        return;
    }

    // Force product post type
    $query->set('post_type', 'product');

    $selected_categories = get_option('product_filter_categories', array());

    if (!empty($selected_categories)) {

        // Convert slugs → IDs efficiently
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'slug'     => $selected_categories,
            'fields'   => 'ids',
        ));

        if (!empty($terms) && !is_wp_error($terms)) {

            $tax_query = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $terms,
                    'operator' => 'IN',
                ),
            );

            $existing_tax_query = $query->get('tax_query');

            if (!empty($existing_tax_query) && is_array($existing_tax_query)) {
                $tax_query = array_merge($existing_tax_query, $tax_query);
            }

            $query->set('tax_query', $tax_query);
        }
    }
}
add_action('pre_get_posts', 'filter_product_queries');


/**
 * Add settings page
 */
function product_filter_settings_menu() {
    add_options_page(
        'Product Filter Settings',
        'Product Filter',
        'manage_options',
        'product-filter-settings',
        'product_filter_settings_page'
    );
}
add_action('admin_menu', 'product_filter_settings_menu');


/**
 * Settings page UI
 */
function product_filter_settings_page() {
    ?>
    <div class="wrap">
        <h1>Product Filter Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('product_filter_settings_group');
            do_settings_sections('product-filter-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}


/**
 * Register settings
 */
function product_filter_settings_init() {

    register_setting(
        'product_filter_settings_group',
        'product_filter_categories',
        array(
            'sanitize_callback' => 'sanitize_product_filter_categories',
            'default' => array(),
        )
    );

    add_settings_section(
        'product_filter_settings_section',
        'Select Categories',
        null,
        'product-filter-settings'
    );

    add_settings_field(
        'product_filter_categories',
        'Categories',
        'product_filter_categories_callback',
        'product-filter-settings',
        'product_filter_settings_section'
    );
}
add_action('admin_init', 'product_filter_settings_init');


/**
 * Sanitize input
 */
function sanitize_product_filter_categories($input) {
    if (!is_array($input)) {
        return array();
    }

    return array_map('sanitize_text_field', $input);
}


/**
 * Categories dropdown
 */
function product_filter_categories_callback() {

    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ));

    $selected_categories = get_option('product_filter_categories', array());

    echo '<select id="product_filter_categories" name="product_filter_categories[]" multiple="multiple" style="width:100%;">';

    foreach ($categories as $category) {
        $selected = in_array($category->slug, $selected_categories) ? 'selected' : '';
        echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
    }

    echo '</select>';
}


/**
 * Select2 scripts
 */
function enqueue_select2_admin_scripts($hook) {

    if ($hook !== 'settings_page_product-filter-settings') {
        return;
    }

    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0/js/select2.min.js', array('jquery'), null, true);

    wp_add_inline_script('select2', '
        jQuery(document).ready(function($) {
            $("#product_filter_categories").select2();
        });
    ');
}
add_action('admin_enqueue_scripts', 'enqueue_select2_admin_scripts');