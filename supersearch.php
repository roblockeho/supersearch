<?php

/**
 * Plugin Name: Super Search
 * Description: Super Search provides a hyperfast search solution for your WordPress site.
 * Version: 1.1.0
 * Author: https://www.hi-orbit.com
 */

/**
 * Add a menu item to the admin menu
 */
add_action('admin_menu', 'supersearch_settings_menu');
function supersearch_settings_menu()
{
    add_options_page(
        'Super Search Settings',       // Page title
        'Super Search',                // Menu title
        'manage_options',              // Capability
        'supersearch-settings',        // Menu slug
        'supersearch_settings_page'    // Callback function
    );
}

/**
 * Add tabs to the admin plugin settings options page
 */
function supersearch_add_tabs() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'usage';

    ?>
    <h1>Super Search Settings</h1>
    <p>Super Search provides a hyperfast search solution for your WordPress site.</p>
    <h2 class="nav-tab-wrapper">
        <a href="?page=supersearch-settings&tab=usage" class="nav-tab <?php echo $active_tab == 'usage' ? 'nav-tab-active' : ''; ?>">Account Usage</a>
        <a href="?page=supersearch-settings&tab=credentials" class="nav-tab <?php echo $active_tab == 'credentials' ? 'nav-tab-active' : ''; ?>">API Credentials</a>
        <a href="?page=supersearch-settings&tab=sync" class="nav-tab <?php echo $active_tab == 'sync' ? 'nav-tab-active' : ''; ?>">Sync</a>
    </h2>
    <?php
}

/**
 * Display the admin plugin settings options page
 */
function supersearch_settings_page()
{
    supersearch_add_tabs();
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'usage';

    $key = 'supersearch_usage_stats';
    $stats = wp_cache_get($key);
    if (!$stats){
        $stats = json_decode(supersearch_perform_curl_request('', 'usagestats')) ?? false;
        wp_cache_set($key,$stats,'',900);
    }
    ?>
    <script>
        searchitem_progress = <?php echo $stats->data->search_item_percent ?? 0;?>;
        searchlimit_progress = <?php echo $stats->data->search_percent ?? 0;?>;
    </script>
    
    <div style="content">
        <?php if ($active_tab == 'usage') { ?>
            <img src="https://supersearch.hi-orbit.com/assets/images/supersearch_200x200.png" style="float:right;margin-left:20px;">
            <h2>Current Plan Usage</h2>
                <?php
                if ($stats){ 
                ?>
                Search count: <div style="width:300px;border: 1px solid grey;"><div id="searchlimit-progress-bar" style="width: 0%; height: 20px; background-color: green;"></div></div>
                <?php echo $stats->data->search_count; ?> of <?php echo  $stats->data->search_limit; ?>
                <br><br>
                Search index: <div style="width:300px;border: 1px solid grey;"><div id="searchitem-progress-bar" style="width: 0%; height: 20px; background-color: green;"></div></div>
                <?php echo $stats->data->search_item_count; ?> of <?php echo  $stats->data->search_item_limit; ?>
                <p><strong>Manage your plan <a href="https://supersearch.hi-orbit.com/admin/plans" target="_blank">here</a></strong></p>
                <?php
                } else {
                    echo '<p>Unable to retrieve usage stats.</p>';
                }
                ?>

        <?php } else if ($active_tab == 'credentials') { ?>
                <h2>Access Credentials</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('supersearch-settings-group');
                    do_settings_sections('supersearch-settings');
                    submit_button('Save Settings');
                    ?>
                </form>
        <?php } else if ($active_tab == 'sync') { ?>
        <h2>Sync Products, Posts, and Pages to Super Search search index</h2>
        <p>We track when products, posts and pages are created and updated and once a day we automatically sync changes to our search index. If you want to sync everything immediately, click the buttons below.</p>
        <button class="button button-secondary" id="start-products-process">Sync Products To Search Index</button>
        <div style="margin-top:10px;margin-bottom:10px;">
            <div style="width:300px;border: 1px solid grey;"><div id="products-progress-bar" style="width: 0%; height: 20px; background-color: green;"></div></div>
            <div id="products-progress-status">0% - not started</div>
            <div id="products-count">products sync not started</div>
        </div>
        <hr>
        <button class="button button-secondary" id="start-posts-process">Sync Posts To Search Index</button>
        <div style="margin-top:10px;margin-bottom:10px;">
            <div style="width:300px;border: 1px solid grey;"><div id="posts-progress-bar" style="width: 0%; height: 20px; background-color: green;"></div></div>
            <div id="posts-progress-status">0% - not started</div>
            <div id="posts-count">posts sync not started</div>
        </div>
        <hr>
        <button class="button button-secondary" id="start-pages-process">Sync Pages To Search Index</button>
        <div style="margin-top:10px;margin-bottom:10px;">
            <div style="width:300px;border: 1px solid grey;"><div id="pages-progress-bar" style="width: 0%; height: 20px; background-color: green;"></div></div>
            <div id="pages-progress-status">0% - not started</div>
            <div id="pages-count">pages sync not started</div>
        </div>
        <?php } ?>
        <div id='sup-type'></div>
        <p>Your data is safe! - <a href="https://hi-orbit.com/supersearch/privacy" target="_blank">read about our privacy policy and how we process your data.</a></p>
    </div>
<?php
}

// include js and css

/**
 * Enqueue scripts
 */
function enqueue_my_plugin_scripts()
{
    wp_enqueue_script('my-plugin-script', plugin_dir_url(__FILE__) . 'supersearch-admin.js', array('jquery'));
    wp_localize_script('my-plugin-script', 'myPlugin', array(
        'ajax_nonce' => wp_create_nonce('my_plugin_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_my_plugin_scripts');

/**
 * Enqueue styles
 */
function enqueue_my_plugin_styles()
{
    wp_enqueue_style('my-plugin-style', plugin_dir_url(__FILE__) . 'supersearch-admin.css');
}
add_action('admin_enqueue_scripts', 'enqueue_my_plugin_styles');

function process_posts_handler()
{
    check_ajax_referer('my_plugin_nonce', 'nonce');

    $batch_size = 10;

    $page = 0;
    if (isset($_POST['page'])) {
        $page = sanitize_text_field($_POST['page']);
    }
    if (isset($_POST['product_count'])) {
        $product_count = sanitize_text_field($_POST['product_count']);
    }
    if (isset($_POST['post_type'])) {
        $post_type = sanitize_text_field($_POST['post_type']);
    }


    // Get all posts
    $data = get_paginated_data($post_type, $page, $batch_size);
    $posts = $data['posts'];

    $product_count = (isset($product_count)) ? $product_count + count($posts) : count($posts);

    $locale = get_locale();
    $language_code = substr($locale, 0, 2);
    $response = supersearch_perform_curl_request($posts, 'createupdate?transform=wp&language=' . $language_code);
    if (isset($response->status_code) && $response->status_code == 508) {
        wp_send_json_error($response->message, 508);
    }

    $page = $page + 1;
    $total_posts = $data['total_items'];
    $progress = round(($page * $batch_size) / $total_posts * 100);
    wp_send_json_success(['progress' => $progress, 'page' => $page, 'product_count' => $product_count, 'total_posts' => $total_posts]);
}
add_action('wp_ajax_process_posts', 'process_posts_handler');

function get_paginated_data($post_type, $page_number = 1, $posts_per_page = 10)
{

    switch ($post_type) {
        case 'products':
            $request = new WP_REST_Request('GET', '/wc/v3/products');
            break;
        case 'posts':
            $request = new WP_REST_Request('GET', '/wp/v2/posts');
            break;
        case 'pages':
            $request = new WP_REST_Request('GET', '/wp/v2/pages');
            break;
        default:
            $request = new WP_REST_Request('GET', '/wp/v2/posts');
            break;
    }

    $request->set_query_params(['per_page' => intval($posts_per_page), 'page' => intval($page_number)]);
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);

    return array(
        'posts' => $data,
        'total_items' => $response->headers['X-WP-Total'],
        'total_pages' => $response->headers['X-WP-TotalPages']
    );
}

// Add featured image URL to API response
function add_featured_image_url_to_api_response($response)
{
    $featured_image_id = $response->data['featured_media'];
    if ($featured_image_id) {
        $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'medium');
        $response->data['featured_image_url'] = $featured_image_url;
    }
    return $response;
}
add_filter('rest_prepare_post', 'add_featured_image_url_to_api_response', 10, 3);

// Add the category information to the API response
function add_category_info_to_api_response($response, $post)
{
    $category = get_the_category($post->ID);
    $response->data['categories'][0] = ['name' => $category[0]->name, 'slug' => $category[0]->slug];
    return $response;
}
add_filter('rest_prepare_post', 'add_category_info_to_api_response', 10, 3);

function add_plain_text_excerpt_to_api_response($response)
{
    if (isset($response->data['excerpt']['rendered'])) {
        $content = strip_shortcodes($response->data['excerpt']['rendered']);
        $plain_text_excerpt = wp_strip_all_tags($content);
        $plain_text_excerpt = html_entity_decode($plain_text_excerpt);
        $plain_text_excerpt = preg_replace('/\[\/?et_pb.*?\]/', '', $plain_text_excerpt);

        $response->data['excerpt']['plain_text'] = $plain_text_excerpt;
    }
    return $response;
}
add_filter('rest_prepare_post', 'add_plain_text_excerpt_to_api_response', 10, 3);
add_filter('rest_prepare_page', 'add_plain_text_excerpt_to_api_response', 10, 3);


function query_arguments($posts_per_page, $page_number = false)
{

    $return = [
        'post_type'      => array('product'), // Add all desired post types here
        'posts_per_page' => $posts_per_page,
        'post_status'    => array('publish', 'inherit'),
        'orderby'        => 'type',
    ];

    if ($page_number) {
        $return['paged'] = $page_number;
    }

    return $return;
}

// Register settings, a section, and fields
add_action('admin_init', 'supersearch_settings_init');
function supersearch_settings_init()
{
    register_setting('supersearch-settings-group', 'public_key');
    register_setting('supersearch-settings-group', 'private_key');

    add_settings_section(
        'supersearch-settings-section',
        'API Keys',
        'supersearch_settings_section_callback',
        'supersearch-settings'
    );

    add_settings_field(
        'public-key-field',
        'Public Key',
        'supersearch_settings_public_key_field_callback',
        'supersearch-settings',
        'supersearch-settings-section'
    );
    add_settings_field(
        'private-key-field',
        'Private Key',
        'supersearch_settings_private_key_field_callback',
        'supersearch-settings',
        'supersearch-settings-section'
    );
}

function supersearch_settings_section_callback()
{
    echo '<p><strong>Get your API Keys by creating an account <a href="https://supersearch.hi-orbit.com" target="_blank">here</a></strong></p><p>Enter your API keys below.</p>';
}

function supersearch_settings_public_key_field_callback()
{
    $public_key = get_option('public_key');
    echo '<input type="text" id="public_key" name="public_key" value="' . esc_attr($public_key) . '" />';
}

function supersearch_settings_private_key_field_callback()
{
    $private_key = get_option('private_key');
    echo '<input type="text" id="private_key" name="private_key" value="' . esc_attr($private_key) . '" />';
}

/**
 * Save post event, that records the post ID of changed posts
 */
function record_changed_posts( $post_id, $post, $update ) {

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // get the saved changed posts...
    $changed_posts = get_option( 'supersearch_changed_posts', array() );

    // Add the post ID to your collection if it's not already there
    if ( ! in_array( $post_id, $changed_posts ) ) {
        $changed_posts[] = $post_id;
        update_option( 'supersearch_changed_posts', $changed_posts );
    }
}
add_action( 'save_post', 'record_changed_posts', 10, 3 );

// crontab 

/**
 * Add a cron event to run daily
 */
function supersearch_update_function(){
    supersearch_log( 'supersearch_daily_index_update - called' );
    $changed_posts = get_option( 'supersearch_changed_posts', array() );
    supersearch_log('changed_posts: ' . json_encode($changed_posts));
    if (count($changed_posts) > 0){
        $posts = get_posts([
            'post_type' => 'any',
            'post__in' => $changed_posts,
            'posts_per_page' => -1
        ]);

        $locale = get_locale();
        $language_code = substr($locale, 0, 2);

        $chunked_posts = array_chunk($posts, 10);
        foreach ($chunked_posts as $chunk){
            $response = supersearch_perform_curl_request($chunk, 'createupdate?transform=wp&language=' . $language_code);
            supersearch_log('response: ' . json_encode($response) );
        }

        update_option( 'supersearch_changed_posts', array() );
    }
}
add_action( 'supersearch_daily_index_update', 'supersearch_update_function');

/**
 * Log to a file
 */
function supersearch_log( $message ) {
    if ( true === WP_DEBUG ) {
        $log_file = plugin_dir_path( __FILE__ ) . 'supersearch.log';
        error_log( date( "Y-m-d H:i:s" ) . ": " . $message . "\n", 3, $log_file );
    }
}

/**
 * Curl request to SuperSearch API
 */
function supersearch_perform_curl_request($data, $action)
{

    $url = 'https://supersearch.hi-orbit.com/api/search/' . $action;
    //$url = 'http://supersearch.test:8081/api/search/' . $action;
    $public_key = get_option('public_key');
    $private_key = get_option('private_key');
    $token = md5($public_key . $private_key);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'public_key' => $public_key,
        'token' => $token,
        'data' => json_encode($data)
    )));

    $response = curl_exec($ch);
    curl_close($ch);

    return $response ?: 'No response from server';
}

// activate and deactivate hooks

/**
 * Activate supersearch plugin
 */
function supersearch_activate() {
    if ( ! wp_next_scheduled( 'supersearch_daily_index_update' ) ) {
        wp_schedule_event( time(), 'daily', 'supersearch_daily_index_update' );
    }
}
register_activation_hook( __FILE__, 'supersearch_activate' );

/**
 * Deactivate supersearch plugin
 */
function supersearch_deactivate() {
    $timestamp = wp_next_scheduled( 'supersearch_daily_index_update' );
    wp_unschedule_event( $timestamp, 'supersearch_daily_index_update' );
}

register_deactivation_hook( __FILE__, 'supersearch_deactivate' );

// Add a shortcode to display the search input

include 'supersearch-frontend.php';
