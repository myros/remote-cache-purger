<?php
/**
 	* Plugin Name: Remote Cache Purger
	* Plugin URI: https://www.myros.net/
	* Description: Clearing cache on remote NGINX server
	* Author: Myros
	* Author URI: https://www.myros.net/
	* Version: 1.0.0

	 * License: http://www.apache.org/licenses/LICENSE-2.0
 	* Text Domain: remote-cache-purger
 	* Network: true
 	*
 	* @package remote-cache-purger
 	*
 	* Copyright 2020 Myros (email: myros@gmail.com)
 	*
 	* This file is part of Remote Cache Purger, a plugin for WordPress.
 	*
 	* Remote Cache Purger is free software: you can redistribute it and/or modify
 	* it under the terms of the Apache License 2.0 license.
	*
	* Remote Cache Purger is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
 	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

class RCPurger {
    protected $blogId;
    protected $plugin = 'rcpurger';
    protected $prefix = 'remote_cache_';
    protected $purgeUrls = array();
    protected $varnishIp = null;
    protected $varnishHost = null;
    protected $dynamicHost = null;
    protected $ipsToHosts = array();
    protected $statsJsons = array();
    protected $purgeKey = null;
    protected $getParam = 'purge_remote_cache';
    protected $postTypes = array('page', 'post');
    protected $customFields = array();
    protected $noticeMessage = '';
    protected $truncateNotice = false;
    protected $truncateNoticeShown = false;
    protected $truncateCount = 0;
    protected $debug = 0;
    protected $purgeOnMenuSave = false;
		protected $currentTab;
		
		protected $enabled = false;

    public function __construct()
    {
        global $blog_id;
        defined($this->plugin) || define($this->plugin, true);

        $this->blogId = $blog_id;
        add_action('init', array(&$this, 'init'), 11);
        add_action('activity_box_end', array($this, 'varnish_glance'), 100);
    }

    public function init()
    {
        load_plugin_textdomain($this->plugin, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

        $this->customFields = array(
            array(
                'name'          => 'ttl',
                'title'         => 'TTL',
                'description'   => __('Not required. If filled in overrides default TTL of %s seconds. 0 means no caching.', $this->plugin),
                'type'          => 'text',
                'scope'         =>  array('post', 'page'),
                'capability'    => 'manage_options'
            )
        );
        $this->useSsl = get_option($this->prefix . 'ssl');
				$this->useSsl = get_option($this->prefix . 'ssl');

        $this->postTypes = get_post_types(array('show_in_rest' => true));

        $this->setup_ips_to_hosts();
        $this->purgeKey = ($purgeKey = trim(get_option($this->prefix . 'purge_key'))) ? $purgeKey : null;
        $this->admin_menu();

        add_action('wp', array($this, 'buffer_start'), 1000000);
        add_action('shutdown', array($this, 'buffer_end'), 1000000);

        $this->truncateNotice = get_option($this->prefix . 'truncate_notice');
        $this->debug = get_option($this->prefix . 'debug');

        // send headers to varnish
        // add_action('send_headers', array($this, 'send_headers'), 1000000);

        // logged in cookie
        add_action('wp_login', array($this, 'wp_login'), 1000000);
        add_action('wp_logout', array($this, 'wp_logout'), 1000000);

        // register events to purge post
        foreach ($this->get_register_events() as $event) {
            add_action($event, array($this, 'purge_post'), 10, 2);
        }

        // purge all cache from admin bar
        if ($this->check_if_purgeable()) {
            add_action('admin_bar_menu', array($this, 'purge_varnish_cache_all_adminbar'), 100);
            if (isset($_GET[$this->getParam]) && check_admin_referer($this->plugin)) {
                if ($this->varnishIp == null) {
                    add_action('admin_notices' , array($this, 'purge_message_no_ips'));
                } else {
                    $this->purge_cache();
                }
            }
        }

        // purge post/page cache from post/page actions
        if ($this->check_if_purgeable()) {
            if(!session_id()) {
                session_start();
            }
            add_filter('post_row_actions', array(
                &$this,
                'post_row_actions'
            ), 0, 2);
            add_filter('page_row_actions', array(
                &$this,
                'page_row_actions'
            ), 0, 2);
            if (isset($_GET['action']) && isset($_GET['post_id']) && ($_GET['action'] == 'purge_post' || $_GET['action'] == 'purge_page') && check_admin_referer($this->plugin)) {
                $this->purge_post($_GET['post_id']);
                $_SESSION['rcpurger_note'] = $this->noticeMessage;
                $referer = str_replace('purge_remote_cache=1', '', wp_get_referer());
                wp_redirect($referer . (strpos($referer, '?') ? '&' : '?') . 'rcpurger_note=' . $_GET['action']);
            }
            if (isset($_GET['rcpurger_note']) && ($_GET['rcpurger_note'] == 'purge_post' || $_GET['rcpurger_note'] == 'purge_page')) {
                add_action('admin_notices' , array($this, 'purge_post_page'));
            }
        }

        // console purge
        if ($this->check_if_purgeable() && isset($_POST['remote_cache_purge_url'])) {
            $this->purge_url(home_url() . $_POST['remote_cache_purge_url']);
            add_action('admin_notices' , array($this, 'purge_message'));
        }
        $this->currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
    }

    public function override_ttl($post)
    {
        $postId = isset($GLOBALS['wp_the_query']->post->ID) ? $GLOBALS['wp_the_query']->post->ID : 0;
        if ($postId && (is_page() || is_single())) {
            $ttl = get_post_meta($postId, $this->prefix . 'ttl', true);
            if (trim($ttl) != '') {
                Header('X-VC-TTL: ' . intval($ttl), true);
            }
        }
    }

    // public function override_homepage_ttl()
    // {
    //     if (is_home() || is_front_page()) {
    //         $this->homepage_ttl = get_option($this->prefix . 'homepage_ttl');
    //         Header('X-VC-TTL: ' . intval($this->homepage_ttl), true);
    //     }
    // }

    public function buffer_callback($buffer)
    {
        return $buffer;
    }

    public function buffer_start()
    {
        ob_start(array($this, "buffer_callback"));
    }

    public function buffer_end()
    {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    protected function setup_ips_to_hosts()
    {
        $this->varnishIp = get_option($this->prefix . 'ips');
        $this->varnishHost = get_option($this->prefix . 'hosts');
        $this->dynamicHost = get_option($this->prefix . 'dynamic_host');
        $this->statsJsons = get_option($this->prefix . 'stats_json_file');
        $this->purgeOnMenuSave = get_option($this->prefix . 'purge_menu_save');
        $varnishIp = explode(',', $this->varnishIp);
        $varnishIp = apply_filters('rcpurger_varnish_ips', $varnishIp);
        $varnishHost = explode(',', $this->varnishHost);
        $varnishHost = apply_filters('rcpurger_varnish_hosts', $varnishHost);
        $statsJsons = explode(',', $this->statsJsons);
        foreach ($varnishIp as $key => $ip) {
            $this->ipsToHosts[] = array(
                'ip' => $ip,
                // 'host' => $this->dynamicHost ? $_SERVER['HTTP_HOST'] : $varnishHost[$key],
                // 'statsJson' => isset($statsJsons[$key]) ? $statsJsons[$key] : null
            );
        }
    }

    public function create_custom_fields()
    {
        if (function_exists('add_meta_box')) {
            foreach ($this->postTypes as $postType) {
                add_meta_box($this->plugin, __('Remote Caching', $this->plugin), array($this, 'display_custom_fields'), $postType, 'side', 'high');
            }
        }
    }

    public function save_custom_fields($post_id, $post)
    {
        if (!isset($_POST['vc-custom-fields_wpnonce']) || !wp_verify_nonce($_POST['vc-custom-fields_wpnonce'], 'vc-custom-fields'))
            return;
        if (!current_user_can('edit_post', $post_id))
            return;
        if (!in_array($post->post_type, $this->postTypes))
            return;
        foreach ($this->customFields as $customField) {
            if (current_user_can($customField['capability'], $post_id)) {
                if (isset($_POST[$this->prefix . $customField['name']]) && trim($_POST[$this->prefix . $customField['name']]) != '') {
                    update_post_meta($post_id, $this->prefix . $customField['name'], $_POST[$this->prefix . $customField['name']]);
                } else {
                    delete_post_meta($post_id, $this->prefix . $customField['name']);
                }
            }
        }
    }

    public function display_custom_fields()
    {
        global $post;
        wp_nonce_field('vc-custom-fields', 'vc-custom-fields_wpnonce', false, true);
        foreach ($this->customFields as $customField) {
            // Check scope
            $scope = $customField['scope'];
            $output = false;
            foreach ($scope as $scopeItem) {
                switch ($scopeItem) {
                    default: {
                        if ($post->post_type == $scopeItem)
                            $output = true;
                        break;
                    }
                }
                if ($output) break;
            }
            // Check capability
            if (!current_user_can($customField['capability'], $post->ID))
                $output = false;
            // Output if allowed
            if ($output) {
                switch ($customField['type']) {
                    case "checkbox": {
                        // Checkbox
                        echo '<p><strong>' . $customField['title'] . '</strong></p>';
                        echo '<label class="screen-reader-text" for="' . $this->prefix . $customField['name'] . '">' . $customField['title'] . '</label>';
                        echo '<p><input type="checkbox" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="yes"';
                        if (get_post_meta($post->ID, $this->prefix . $customField['name'], true ) == "yes")
                            echo ' checked="checked"';
                        echo '" style="width: auto;" /></p>';
                        break;
                    }
                    default: {
                        // Plain text field
                        echo '<p><strong>' . $customField['title'] . '</strong></p>';
                        $value = get_post_meta($post->ID, $this->prefix . $customField[ 'name' ], true);
                        echo '<p><input type="text" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="' . $value . '" /></p>';
                        break;
                    }
                }
            } else {
                echo '<p><strong>' . $customField['title'] . '</strong></p>';
                $value = get_post_meta($post->ID, $this->prefix . $customField[ 'name' ], true);
                echo '<p><input type="text" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="' . $value . '" disabled /></p>';
            }
            $default_ttl = get_option($this->prefix . 'ttl');
            if ($customField['description']) echo '<p>' . sprintf($customField['description'], $default_ttl) . '</p>';
        }
    }

    public function check_if_purgeable()
    {
        return (!is_multisite() && current_user_can('activate_plugins')) || current_user_can('manage_network') || (is_multisite() && !current_user_can('manage_network') && (SUBDOMAIN_INSTALL || (!SUBDOMAIN_INSTALL && (BLOG_ID_CURRENT_SITE != $this->blogId))));
    }

    public function purge_message()
    {
        echo '<div id="message" class="updated fade"><p><strong>' . __('Remote Caching', $this->plugin) . '</strong><br /><br />' . $this->noticeMessage . '</p></div>';
    }

    public function purge_message_no_ips()
    {
        echo '<div id="message" class="error fade"><p><strong>' . __('Please set the IPs for Varnish!', $this->plugin) . '</strong></p></div>';
    }

    public function purge_post_page()
    {
        if (isset($_SESSION['rcpurger_note'])) {
            echo '<div id="message" class="updated fade"><p><strong>' . __('Remote Caching', $this->plugin) . '</strong><br /><br />' . $_SESSION['rcpurger_note'] . '</p></div>';
            unset ($_SESSION['rcpurger_note']);
        }
    }

    public function purge_varnish_cache_all_adminbar($admin_bar)
    {
        $admin_bar->add_menu(array(
            'id'    => 'purge-all-remote-cache',
            'title' => __('Purge ALL Remote Cache', $this->plugin),
            'href'  => wp_nonce_url(add_query_arg($this->getParam, 1), $this->plugin),
            'meta'  => array(
                'title' => __('Purge ALL Remote Cache', $this->plugin),
            )
        ));
    }

    public function varnish_glance()
    {
        $url = wp_nonce_url(admin_url('?' . $this->getParam), $this->plugin);
        $button = '';
        $nopermission = '';
        $intro = '';
        if ($this->varnishIp == null) {
            $intro .= sprintf(__('Please setup Varnish IPs to be able to use <a href="%1$s">Varnish Caching</a>.', $this->plugin), 'http://wordpress.org/plugins/varnish-caching/');
        } else {
            $intro .= sprintf(__('<a href="%1$s">Varnish Caching</a> automatically purges your posts when published or updated. Sometimes you need a manual flush.', $this->plugin), 'http://wordpress.org/plugins/varnish-caching/');
            $button .=  __('Press the button below to force it to purge your entire cache.', $this->plugin);
            $button .= '</p><p><span class="button"><a href="' . $url . '"><strong>';
            $button .= __('Purge ALL Varnish Cache', $this->plugin);
            $button .= '</strong></a></span>';
            $nopermission .=  __('You do not have permission to purge the cache for the whole site. Please contact your adminstrator.', $this->plugin);
        }
        if ($this->check_if_purgeable()) {
            $text = $intro . ' ' . $button;
        } else {
            $text = $intro . ' ' . $nopermission;
        }
        echo '<p class="varnish-glance">' . $text . '</p>';
    }

    protected function get_register_events()
    {
        $actions = array(
            // 'publish_future_post',
            // 'save_post', 
            // 'deleted_post',
            // 'trashed_post',
            // 'edit_post',
            // 'delete_attachment',
            'switch_theme',
        );
        return apply_filters('rcpurger_events', $actions);
    }

    public function purge_cache()
    {
        $purgeUrls = array_unique($this->purgeUrls);

        if (empty($purgeUrls)) {
            if (isset($_GET[$this->getParam]) && $this->check_if_purgeable() && check_admin_referer($this->plugin)) {
                $this->purge_url(home_url() .'/?vc-regex');
            }
        } else {
            foreach($purgeUrls as $url) {
                $this->purge_url($url);
            }
        }
        if ($this->truncateNotice && $this->truncateNoticeShown == false) {
            $this->truncateNoticeShown = true;
            $this->noticeMessage .= '<br />' . __('Truncate message activated. Showing only first 3 messages.', $this->plugin);
        }
        add_action('admin_notices' , array($this, 'purge_message'));
    }

    public function purge_url($url)
    {
        $p = parse_url($url);

        if (isset($p['query']) && ($p['query'] == 'vc-regex')) {
            $pregex = '.*';
            $purgemethod = 'regex';
        } else {
            $pregex = '';
            $purgemethod = 'default';
        }

        if (isset($p['path'])) {
            $path = $p['path'];
        } else {
            $path = '';
        }

        $schema = apply_filters('rcpurger_schema', ($this->useSsl ? 'https://' : 'http://'));
				$port = ( $p['scheme'] == 'https' ? '443' : '80' );
				$host = $p['host'];

        foreach ($this->ipsToHosts as $key => $ipToHost) {
						$ip = trim($ipToHost['ip']);
            $purgeme = $schema . $host . $path . $pregex;

            $headers = [
                "X-Cache-Purge: 1",
                "X-Cache-Purge-Host: {$host}",
                "X-Cache-Purge-IP: {$ip}",
                "Location: {$host}"
            ];

            $ch = curl_init("{$host}{$path}");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RESOLVE, array(
                "{$host}:{$port}:{$ip}",
            ));

            
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            // curl_setopt($ch, CURLOPT_HEADER, true); 
            // curl_setopt($ch, CURLOPT_NOBODY, true);
            
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // $this->noticeMessage .= "{$host}:{$port}:{$ip}";

            curl_close($ch);
						
            if ($response instanceof WP_Error) {
                foreach ($response->errors as $error => $errors) {
                    $this->noticeMessage .= '<br />Error ' . $error . '<br />';
                    foreach ($errors as $error => $description) {
                        $this->noticeMessage .= ' - ' . $description . '<br />';
                    }
                }
            } else {
                if ($this->truncateNotice && $this->truncateCount <= 2 || $this->truncateNotice == false) {
										$this->noticeMessage .= '' . "{$ip} ({$httpcode}) => " . $purgeme;
										
                    preg_match("/<title>(.*)<\/title>/i", $response, $matches);
										// TODO
                    // $this->noticeMessage .= ' => <br /> ' . isset($matches[1]) ? " => " . $matches[1] : '';
                    $this->noticeMessage .= '<br />';
                    if ($this->debug) {
                        // $this->noticeMessage .= $response . "<br />";
                    }
                }
                $this->truncateCount++;
            }
        }

        do_action('rcpurger_after_purge_url', $url, $purgeme);
    }

    public function purge_post($postId, $post=null)
    {
        // Do not purge menu items
        if (get_post_type($post) == 'nav_menu_item' && $this->purgeOnMenuSave == false) {
            return;
        }

        // If this is a valid post we want to purge the post, the home page and any associated tags & cats
        // If not, purge everything on the site.
        $validPostStatus = array('publish', 'trash');
        $thisPostStatus  = get_post_status($postId);

        // If this is a revision, stop.
        if(get_permalink($postId) !== true && !in_array($thisPostStatus, $validPostStatus)) {
            return;
        } else {
            // array to collect all our URLs
            $listofurls = array();

            // Category purge based on Donnacha's work in WP Super Cache
            $categories = get_the_category($postId);
            if ($categories) {
                foreach ($categories as $cat) {
                    array_push($listofurls, get_category_link($cat->term_id));
                }
            }
            // Tag purge based on Donnacha's work in WP Super Cache
            $tags = get_the_tags($postId);
            if ($tags) {
                foreach ($tags as $tag) {
                    array_push($listofurls, get_tag_link($tag->term_id));
                }
            }

            // Author URL
            array_push($listofurls,
                get_author_posts_url(get_post_field('post_author', $postId)),
                get_author_feed_link(get_post_field('post_author', $postId))
            );

            // Archives and their feeds
            $archiveurls = array();
            if (get_post_type_archive_link(get_post_type($postId)) == true) {
                array_push($listofurls,
                    get_post_type_archive_link( get_post_type($postId)),
                    get_post_type_archive_feed_link( get_post_type($postId))
                );
            }

            // Post URL
            array_push($listofurls, get_permalink($postId));

            // Feeds
            array_push($listofurls,
                get_bloginfo_rss('rdf_url') ,
                get_bloginfo_rss('rss_url') ,
                get_bloginfo_rss('rss2_url'),
                get_bloginfo_rss('atom_url'),
                get_bloginfo_rss('comments_rss2_url'),
                get_post_comments_feed_link($postId)
            );

            // Home Page and (if used) posts page
            array_push($listofurls, home_url('/'));
            if (get_option('show_on_front') == 'page') {
                array_push($listofurls, get_permalink(get_option('page_for_posts')));
            }

            // If Automattic's AMP is installed, add AMP permalink
            if (function_exists('amp_get_permalink')) {
                array_push($listofurls, amp_get_permalink($postId));
            }

            // Now flush all the URLs we've collected
            foreach ($listofurls as $url) {
                array_push($this->purgeUrls, $url) ;
            }
        }
        // Filter to add or remove urls to the array of purged urls
        // @param array $purgeUrls the urls (paths) to be purged
        // @param int $postId the id of the new/edited post
        $this->purgeUrls = apply_filters('rcpurger_purge_urls', $this->purgeUrls, $postId);
        $this->purge_cache();
    }

    public function wp_login()
    {
        $cookie = get_option($this->prefix . 'cookie');
        if (!empty($cookie)) {
            setcookie($cookie, 1, time()+3600*24*100, COOKIEPATH, COOKIE_DOMAIN, false, true);
        }
    }

    public function wp_logout()
    {
        $cookie = get_option($this->prefix . 'cookie');
        if (!empty($cookie)) {
            setcookie($cookie, null, time()-3600*24*100, COOKIEPATH, COOKIE_DOMAIN, false, true);
        }
    }

    public function admin_menu()
    {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_init', array($this, 'options_page_fields'));
        add_action('admin_init', array($this, 'console_page_fields'));
    }

    public function add_menu_item()
    {
        if ($this->check_if_purgeable()) {
            add_menu_page(__('Remote Caching', $this->plugin), __('Remote Caching', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), plugins_url() . '/' . $this->plugin . '/icon.png', 99);
        }
    }

    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?=__('Remote Caching', $this->plugin)?></h1>

        <h2 class="nav-tab-wrapper">
            <a class="nav-tab <?php if($this->currentTab == 'settings'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=settings"><?=__('Settings', $this->plugin)?></a>
            <?php if ($this->check_if_purgeable()): ?>
                <a class="nav-tab <?php if($this->currentTab == 'console'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=console"><?=__('Console', $this->plugin)?></a>
            <?php endif; ?>
        </h2>

        <?php if($this->currentTab == 'settings'): ?>
            <form method="post" action="options.php">
                <?php
                    settings_fields($this->prefix . 'settings');
                    do_settings_sections($this->prefix . 'settings');
                    submit_button();
                ?>
            </form>
            <script type="text/javascript">
                function generateHash(length, bits, id) {
                    bits = bits || 36;
                    var outStr = "", newStr;
                    while (outStr.length < length)
                    {
                        newStr = Math.random().toString(bits).slice(2);
                        outStr += newStr.slice(0, Math.min(newStr.length, (length - outStr.length)));
                    }
                    jQuery('#' + id).val(outStr);
                }
            </script>
        <?php elseif($this->currentTab == 'console'): ?>
            <form method="post" action="admin.php?page=<?=$this->plugin?>-plugin&amp;tab=console">
                <?php
                    settings_fields($this->prefix . 'console');
                    do_settings_sections($this->prefix . 'console');
                    submit_button(__('Purge', $this->plugin));
                ?>
            </form>
        <?php endif; ?>
        </div>
    <?php
    }

    public function options_page_fields()
    {
        add_settings_section($this->prefix . 'settings', __('Settings', $this->plugin), null, $this->prefix . 'settings');

        add_settings_field($this->prefix . "enabled", __("Enable" , $this->plugin), array($this, $this->prefix . "enabled"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "ips", __("IPs", $this->plugin), array($this, $this->prefix . "ips"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "purge_key", __("Purge key", $this->plugin), array($this, $this->prefix . "purge_key"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "truncate_notice", __("Truncate notice message", $this->plugin), array($this, $this->prefix . "truncate_notice"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "purge_menu_save", __("Purge on save menu", $this->plugin), array($this, $this->prefix . "purge_menu_save"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->prefix . 'settings', $this->prefix . 'settings');

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'settings') {
            register_setting($this->prefix . 'settings', $this->prefix . "enabled");
            register_setting($this->prefix . 'settings', $this->prefix . "ips");
            register_setting($this->prefix . 'settings', $this->prefix . "purge_key");
            register_setting($this->prefix . 'settings', $this->prefix . "truncate_notice");
            register_setting($this->prefix . 'settings', $this->prefix . "purge_menu_save");
            register_setting($this->prefix . 'settings', $this->prefix . "debug");
        }
    }

    public function remote_cache_enabled()
    {
        ?>
            <input type="checkbox" name="remote_cache_enabled" value="1" <?php checked(1, get_option($this->prefix . 'enabled'), true); ?> />
            <p class="description"><?=__('Enable Remote Cache Purge', $this->plugin)?></p>
        <?php
    }

    public function remote_cache_ips()
    {
        ?>
            <input type="text" name="remote_cache_ips" id="remote_cache_ips" size="100" value="<?php echo get_option($this->prefix . 'ips'); ?>" />
            <p class="description"><?=__('Comma separated ip/ip:port. Example : 192.168.0.2,192.168.0.3:8080', $this->plugin)?></p>
        <?php
    }

    public function remote_cache_dynamic_host()
    {
        ?>
            <input type="checkbox" name="remote_cache_dynamic_host" value="1" <?php checked(1, get_option($this->prefix . 'dynamic_host'), true); ?> />
            <p class="description">
                <?=__('Uses the $_SERVER[\'HTTP_HOST\'] as hash for Varnish. This means the purge cache action will work on the domain you\'re on.<br />Use this option if you use only one domain.', $this->plugin)?>
            </p>
        <?php
    }

    public function remote_cache_hosts()
    {
        ?>
            <input type="text" name="remote_cache_hosts" id="remote_cache_hosts" size="100" value="<?php echo get_option($this->prefix . 'hosts'); ?>" />
            <p class="description">
                <?=__('Comma separated hostnames. Varnish uses the hostname to create the cache hash. For each IP, you must set a hostname.<br />Use this option if you use multiple domains.', $this->plugin)?>
            </p>
        <?php
    }

    public function remote_cache_purge_key()
    {
        ?>
            <input type="text" name="remote_cache_purge_key" id="remote_cache_purge_key" size="100" maxlength="64" value="<?php echo get_option($this->prefix . 'purge_key'); ?>" />
            <span onclick="generateHash(64, 0, 'remote_cache_purge_key'); return false;" class="dashicons dashicons-image-rotate" title="<?=__('Generate')?>"></span>
            <p class="description">
                <?=__('Key used to purge Varnish cache. It is sent to Varnish as X-VC-Purge-Key header. Use a SHA-256 hash.<br />If you can\'t use ACL\'s, use this option. You can set the `purge key` in lib/purge.vcl.<br />Search the default value ff93c3cb929cee86901c7eefc8088e9511c005492c6502a930360c02221cf8f4 to find where to replace it.', $this->plugin)?>
            </p>
        <?php
    }

    public function remote_cache_truncate_notice()
    {
        ?>
            <input type="checkbox" name="remote_cache_truncate_notice" value="1" <?php checked(1, get_option($this->prefix . 'truncate_notice'), true); ?> />
            <p class="description">
                <?=__('When using multiple Varnish Cache servers, RCPurger shows too many `Trying to purge URL` messages. Check this option to truncate that message.', $this->plugin)?>
            </p>
        <?php
    }

    public function remote_cache_purge_menu_save()
    {
        ?>
            <input type="checkbox" name="remote_cache_purge_menu_save" value="1" <?php checked(1, get_option($this->prefix . 'purge_menu_save'), true); ?> />
            <p class="description">
                <?=__('Purge menu related pages when a menu is saved.', $this->plugin)?>
            </p>
        <?php
    }

    public function remote_cache_debug()
    {
        ?>
            <input type="checkbox" name="remote_cache_debug" value="1" <?php checked(1, get_option($this->prefix . 'debug'), true); ?> />
            <p class="description">
                <?=__('Send all debugging headers to the client. Also shows complete response from Varnish on purge all.', $this->plugin)?>
            </p>
        <?php
    }

		// console
    public function console_page_fields()
    {
        add_settings_section('console', __("Console", $this->plugin), null, $this->prefix . 'console');

        add_settings_field($this->prefix . "purge_url", __("URL", $this->plugin), array($this, $this->prefix . "purge_url"), $this->prefix . 'console', "console");
    }

    public function remote_cache_purge_url()
    {
        ?>
            <input type="text" name="remote_cache_purge_url" size="100" id="remote_cache_purge_url" value="" />
            <p class="description"><?=__('Relative URL to purge. Example : /simple-post or /uncategorized/hello-world. It will clear that URL page (and related pages) cache on ALL reported servers', $this->plugin)?></p>
        <?php
    }
		// end of console

    public function post_row_actions($actions, $post)
    {
        if ($this->check_if_purgeable()) {
            $actions = array_merge($actions, array(
                'rcpurger_purge_post' => sprintf('<a href="%s">' . __('Refresh cache', $this->plugin) . '</a>', wp_nonce_url(sprintf('admin.php?page=rcpurger-plugin&tab=settings&action=purge_post&post_id=%d', $post->ID), $this->plugin))
            ));
        }
        return $actions;
    }

    public function page_row_actions($actions, $post)
    {
        if ($this->check_if_purgeable()) {
            $actions = array_merge($actions, array(
                'rcpurger_purge_page' => sprintf('<a href="%s">' . __('Refresh cache', $this->plugin) . '</a>', wp_nonce_url(sprintf('admin.php?page=rcpurger-plugin&tab=settings&action=purge_page&post_id=%d', $post->ID), $this->plugin))
            ));
        }
        return $actions;
    }
}

$rcpurger = new RCPurger();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}
