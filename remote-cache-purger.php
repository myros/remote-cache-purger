<?php
/**
 	* Plugin Name: Remote Cache Purger
	* Description: Clearing cache on remote NGINX server (Kubernetes)
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
     * 
     * TODO:
     * multiple domains
     * cache purge key
     * dynamic response header for cleared items count
     * purge on save (option)
     * debug
*/

class RCPurger {

    // general
    protected $version = '1.0.0';
    protected $userAgent = 'cURL WP Remote Cache Purger ';
    protected $blogId; 
    protected $plugin = 'cache-purger';
    protected $prefix = 'remote_cache_'; // remote cache purger

    protected $purgeUrls = array();
    protected $ipsToHosts = array();
    protected $getParam = 'purge_remote_cache';
    protected $postTypes = array('page', 'post');
    protected $responses = array();
    
    protected $noticeMessage = '';
    protected $truncateNotice = false;
    protected $truncateNoticeShown = false;
    protected $truncateCount = 0;
    
    // settings
    protected $debug = 0;
    protected $enabled = false;
    protected $optServersIP = [];

    // future
    // protected $optDomains = null;
    // protected $optPurgeOnMenuSave = false;
    // protected $purgeKey = null;
    // protected $responseHeader = null;

    /**
    * Constructor.
    *
    * @since 1.0
    */
    public function __construct()
    {
        global $blog_id;
        defined($this->plugin) || define($this->plugin, true);

        $this->blogId = $blog_id;
        add_action('init', array(&$this, 'init'), 11);
        add_action('activity_box_end', array($this, 'remote_purger_glance'), 100);
    }

    /**
    * @since 1.0
    */
    public function init()
    {
        $this->postTypes = get_post_types(array('show_in_rest' => true));

        $this->setup_ips_to_hosts();
        // $this->purgeKey = ($purgeKey = trim(get_option($this->prefix . 'purge_key'))) ? $purgeKey : null;
        $this->admin_menu();

        add_action('wp', array($this, 'buffer_start'), 1000000);
        add_action('shutdown', array($this, 'buffer_end'), 1000000);

        $this->truncateNotice = get_option($this->prefix . 'truncate_notice');
        $this->debug = get_option($this->prefix . 'debug');

        // register events to purge post
        foreach ($this->get_register_events() as $event) {
            add_action($event, array($this, 'purge_post'), 10, 2);
        }

        // purge all cache from admin bar
        if ($rcp_tools->check_if_purgeable()) {
            add_action('admin_bar_menu', array($this, 'purge_cache_all_adminbar'), 100);
            if (isset($_GET[$this->getParam]) && check_admin_referer($this->plugin)) {
                if ($this->optServersIP == null) {
                    add_action('admin_notices' , array($this, 'purge_message_no_ips'));
                } else {
                    $this->purge_cache();
                }
            }
        }

        // purge post/page cache from post/page actions
        if ($this->check_if_purgeable()) {
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

    /**
    * @since 1.0
    */
    public function buffer_callback($buffer)
    {
        return $buffer;
    }

    /**
    * @since 1.0
    */
    public function buffer_start()
    {
        ob_start(array($this, "buffer_callback"));
    }

    /**
    * @since 1.0
    */
    public function buffer_end()
    {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
    * @since 1.0
    */
    protected function setup_ips_to_hosts()
    {
        $this->optServersIP = get_option($this->prefix . 'ips');
        $this->optDomains = get_option($this->prefix . 'domains');
        $this->optPurgeOnMenuSave = get_option($this->prefix . 'purge_menu_save');

        $serverIPS = explode(',', $this->optServersIP);
        foreach ($serverIPS as $key => $ip) {
            $this->ipsToHosts[] = trim($ip);
        }
    }

    /**
    * @since 1.0
    */
    public function check_if_purgeable()
    {
        return (!is_multisite() && current_user_can('activate_plugins')) || current_user_can('manage_network') || (is_multisite() && !current_user_can('manage_network') && (SUBDOMAIN_INSTALL || (!SUBDOMAIN_INSTALL && (BLOG_ID_CURRENT_SITE != $this->blogId))));
    }

    /**
    * @since 1.0
    */
    public function purge_message()
    {
        echo '<div id="message" class="updated fade"><p><strong>' . __('Remote Caching', $this->plugin) . '</strong><br /><br />' . $this->noticeMessage . '</p></div>';
    }

    /**
    * @since 1.0
    */
    public function purge_message_no_ips()
    {
        echo '<div id="message" class="error fade"><p><strong>' . __('Please set the IPs for remote server(s)!', $this->plugin) . '</strong></p></div>';
    }

    /**
    * @since 1.0
    */
    public function purge_post_page()
    {
        if (isset($_SESSION['rcpurger_note'])) {
            echo '<div id="message" class="updated fade"><p><strong>' . __('Remote Caching', $this->plugin) . '</strong><br /><br />' . $_SESSION['rcpurger_note'] . '</p></div>';
            unset ($_SESSION['rcpurger_note']);
        }
    }

    /**
    * @since 1.0
    */
    public function purge_cache_all_adminbar($admin_bar)
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

    /**
     * 
     * dashboard purge link
     * 
     * @since 1.0
    */
    public function remote_purger_glance()
    {
        $url = wp_nonce_url(admin_url('?' . $this->getParam), $this->plugin);
        $button = '';
        $nopermission = '';
        $intro = '';
        if ($this->optServersIP == null) {
            $intro .= sprintf(__('Please setup Remote Host IPs to be able to use <a href="%1$s">Remote Cache Purger</a>.', $this->plugin), 'http://wordpress.org/plugins/remote-cache-purger/');
        } else {
            $intro .= sprintf(__('<a href="%1$s">Remote Cache Purge</a> automatically purges your posts when published or updated. Sometimes you need a manual flush.', $this->plugin), 'http://wordpress.org/plugins/remote-cache-purger/');
            $button .=  __('Press the button below to force it to purge your entire cache.', $this->plugin);
            $button .= '</p><p><span class="button"><a href="' . $url . '"><strong>';
            $button .= __('Purge ALL Remote Cache', $this->plugin);
            $button .= '</strong></a></span>';
            $nopermission .=  __('You do not have permission to purge the cache for the whole site. Please contact your adminstrator.', $this->plugin);
        }
        if ($this->check_if_purgeable()) {
            $text = $intro . ' ' . $button;
        } else {
            $text = $intro . ' ' . $nopermission;
        }
        echo '<p class="remote-purger-glance">' . $text . '</p>';
    }

    /**
    * @since 1.0
    */
    protected function get_register_events()
    {
        $actions = array(
            'publish_future_post',
            'save_post', 
            'deleted_post',
            'trashed_post',
            'edit_post',
            'delete_attachment',
            'switch_theme',
        );
        return apply_filters('rcpurger_events', $actions);
    }

    /**
    * @since 1.0
    */
    public function purge_url($url)
    {
        // is it root path => /
        $is_root = $url == '*' || untrailingslashit($url) == get_site_url();

        if ($is_root){
            $urls_to_purge = array('*');

            foreach ($this->ipsToHosts as $server) {
                $this->responses = [];
                $this->purge_server($server, $urls_to_purge, false);
            }
        } else {
            $postid = url_to_postid($url);

            if ($postid > 0) {
                $this->purge_post($postid);
            } else {
                $urls_to_purge = [];
                array_push($urls_to_purge, $url);

                foreach ($this->ipsToHosts as $server) {
                    $this->responses = [];
                    $this->purge_server($server, $urls_to_purge);
                }
            }
        }

        if ($this->truncateNotice && $this->truncateNoticeShown == false) {
            $this->truncateNoticeShown = true;
            $this->noticeMessage .= '<br />' . __('Truncate message activated. Showing only first 3 messages.', $this->plugin);
        }

        add_action('admin_notices' , array($this, 'purge_message'));
    }

    /**
    * @since 1.0
    */
    public function purge_post($postId, $post=null)
    {
        // Do not purge menu items
        if (get_post_type($post) == 'nav_menu_item' && $this->optPurgeOnMenuSave == false) {
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
        $this->purgeUrls = apply_filters('rcpurger_purge_urls', $this->purgeUrls, $postId);
        $this->purge_cache();
    }

    /**
    * @since 1.0
    */
    public function purge_cache()
    {
        $purgeUrls = array_unique($this->purgeUrls);

        if (empty($purgeUrls)) {
            if (isset($_GET[$this->getParam]) && $this->check_if_purgeable() && check_admin_referer($this->plugin)) {
                $this->purge_url(home_url());
            }
        } else {
            
            $urls_to_purge = [];  
            foreach($purgeUrls as $key => $url) {
                array_push($urls_to_purge, $url);
            }

            foreach ($this->ipsToHosts as $server) {
                $this->purge_server($server, $purgeUrls);
            }
        }
        
        if ($this->truncateNotice && $this->truncateNoticeShown == false) {
            $this->truncateNoticeShown = true;
            $this->noticeMessage .= '<br />' . __('Truncate message activated. Showing only first 3 messages.', $this->plugin);
        }

        add_action('admin_notices' , array($this, 'purge_message'));
    }
    
    /**
    * @since 1.0
    */
    public function purge_server($server_ip, $urls_to_purge, $parse = true)
    {
        // $responses = $this->responses;
        $r = array();
        $ip = trim($server_ip);
        $mh = curl_multi_init(); // cURL multi-handle
        $requests = array(); // This will hold cURLS requests for each file
         $this->responses = [];

        $options = array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true, 
            CURLOPT_USERAGENT      => $this->userAgent,
            // CURLOPT_HEADER         => true,
            // CURLOPT_NOBODY          => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PURGE",
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_TIMEOUT => 10, //timeout in seconds
            CURLOPT_VERBOSE => true
        );
        
        foreach(array_unique($urls_to_purge) as $key => $url) {
            
            if ($url == '*') {
                $url = trailingslashit(get_site_url()) . '*'; // force url => *
            }
            
            $parsedUrl = parse_url($url);
            $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
            $schema = $parsedUrl['scheme'];
            $port = ( $parsedUrl['scheme'] == 'https' ? '443' : '80' );
            $host = $parsedUrl['host'];
            
            $fullUrl = $schema . '://' . $host . '/' . $path;
            
            $handle = curl_init($url);
            $array_key = (int) $handle;
            $requests[$array_key]['curl_handle'] = $handle;

            $requests[$array_key]['url'] = $url;

            $this->responses[$array_key] = array(
                'url' => $url,
                'ip' => $ip
            );
            
            // Set cURL object options
            curl_setopt_array($requests[$array_key]['curl_handle'], $options);
            curl_setopt($requests[$array_key]['curl_handle'], CURLOPT_RESOLVE, array(
                "{$host}:{$port}:{$ip}",
            ));
            curl_setopt($requests[$array_key]['curl_handle'], CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));

            // Add cURL object to multi-handle
            curl_multi_add_handle($mh, $requests[$array_key]['curl_handle']);
        }
        
        // Do while all request have been completed
        do {
            curl_multi_exec($mh, $running);
        } while ($running);
        
        $this->noticeMessage .= '<br/>SERVER: ' . $ip . '<br/>';
        
        foreach ($requests as $key => $request) {
            $this->responses[$key]['HTTP_CODE'] = curl_getinfo($request['curl_handle'], CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $request['curl_handle']); //assuming we're being responsible about our resource management
        }
        
        curl_multi_close($mh);

        foreach($this->responses as $key => $response) {
            if(isset($response['headers']['x-purged-count'] )) {
                $this->noticeMessage .= 'PURGE: (' . $response['headers']['x-purged-count'] . ')';
            } else {
                $this->noticeMessage .= 'PURGE (0)' ;
            }

            $this->noticeMessage .= ' | ' . $response['HTTP_CODE'] . ' | ' .  $response['url'];

            $this->noticeMessage .= '<br/>';
        }
    }

    /**
    * @since 1.0
    */
    private function headerCallback($ch, $header)
    {
        $_header = trim($header);
        $colonPos= strpos($_header, ':');
        if($colonPos > 0)
        {
            $key = substr($_header, 0, $colonPos);
            $val = preg_replace('/^\W+/','',substr($_header, $colonPos));
            $this->responses[$this->getKey($ch)]['headers'][$key] = $val;
        }
        return strlen($header);
    }
    
    /**
    * @since 1.0
    */
    public function getKey($ch)
    {
        return (int)$ch;
    }

    /**
    * @since 1.0
    */
    public function admin_menu()
    {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_init', array($this, 'options_page_fields'));
        add_action('admin_init', array($this, 'console_page_fields'));
    }

    /**
    * @since 1.0
    */
    public function add_menu_item()
    {
        if ($this->check_if_purgeable()) {
            add_menu_page(__('Remote Caching', $this->plugin), __('Remote Caching', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), 'dashicons-sos', 99);
        }
    }

    /**
    * @since 1.0
    */
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

    /**
    * @since 1.0
    */
    public function options_page_fields()
    {
        add_settings_section($this->prefix . 'settings', __('Settings', $this->plugin), null, $this->prefix . 'settings');

        // add_settings_field($this->prefix . "enabled", __("Enable" , $this->plugin), array($this, $this->prefix . "enabled"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "ips", __("IPs", $this->plugin), array($this, $this->prefix . "ips"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "purge_key", __("Purge key", $this->plugin), array($this, $this->prefix . "purge_key"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "truncate_notice", __("Truncate notice message", $this->plugin), array($this, $this->prefix . "truncate_notice"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "purge_menu_save", __("Purge on save menu", $this->plugin), array($this, $this->prefix . "purge_menu_save"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->prefix . 'settings', $this->prefix . 'settings');

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'settings') {
            register_setting($this->prefix . 'settings', $this->prefix . "enabled");
            register_setting($this->prefix . 'settings', $this->prefix . "ips");
            register_setting($this->prefix . 'settings', $this->prefix . "purge_key");
            register_setting($this->prefix . 'settings', $this->prefix . "truncate_notice");
            register_setting($this->prefix . 'settings', $this->prefix . "purge_menu_save");
            register_setting($this->prefix . 'settings', $this->prefix . "debug");
        }
    }

    /**
    * @since 1.0
    */
    public function remote_cache_enabled()
    {
        ?>
            <input type="checkbox" name="remote_cache_enabled" value="1" <?php checked(1, get_option($this->prefix . 'enabled'), true); ?> />
            <p class="description"><?=__('Enable Remote Cache Purge', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_ips()
    {
        ?>
            <input type="text" name="remote_cache_ips" id="remote_cache_ips" size="100" value="<?php echo get_option($this->prefix . 'ips'); ?>" />
            <p class="description"><?=__('Comma separated ip/ip:port. Example : 192.168.0.2,192.168.0.3:8080', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_dynamic_host()
    {
        ?>
            <input type="checkbox" name="remote_cache_dynamic_hosts" value="1" <?php checked(1, get_option($this->prefix . 'dynamic_hosts'), true); ?> />
            <p class="description">
                <?=__('If empty, uses the $_SERVER[\'HTTP_HOST\'] as hash for Remote Server. This means the purge cache action will work on the domain you\'re on.<br />Do not use this option if you use only one domain.', $this->plugin)?>
            </p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_purge_key()
    {
        ?>
            <input type="text" name="remote_cache_purge_key" id="remote_cache_purge_key" size="100" maxlength="64" value="<?php echo get_option($this->prefix . 'purge_key'); ?>" />
            <span onclick="generateHash(64, 0, 'remote_cache_purge_key'); return false;" class="dashicons dashicons-image-rotate" title="<?=__('Generate')?>"></span>
            <p class="description">
                <?=__('Key used to purge remote cache. It is sent to Remote Cache Servers as X-VC-Purge-Key header. Use a SHA-256 hash.<br />If you can\'t use ACL\'s, use this option. You can set the `purge key` in lib/purge.vcl.<br />Search the default value ff93c3cb929cee86901c7eefc8088e9511c005492c6502a930360c02221cf8f4 to find where to replace it.', $this->plugin)?>
            </p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_truncate_notice()
    {
        ?>
            <input type="checkbox" name="remote_cache_truncate_notice" value="1" <?php checked(1, get_option($this->prefix . 'truncate_notice'), true); ?> />
            <p class="description">
                <?=__('When using multiple Cache servers, RCPurger shows too many `Trying to purge URL` messages. Check this option to truncate that message.', $this->plugin)?>
            </p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_purge_menu_save()
    {
        ?>
            <input type="checkbox" name="remote_cache_purge_menu_save" value="1" <?php checked(1, get_option($this->prefix . 'purge_menu_save'), true); ?> />
            <p class="description">
                <?=__('Purge menu related pages when a menu is saved.', $this->plugin)?>
            </p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_debug()
    {
        ?>
            <input type="checkbox" name="remote_cache_debug" value="1" <?php checked(1, get_option($this->prefix . 'debug'), true); ?> />
            <p class="description">
                <?=__('Send all debugging headers to the client. Also shows complete response from Remote Server on purge all.', $this->plugin)?>
            </p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function console_page_fields()
    {
        add_settings_section('console', __("Console", $this->plugin), null, $this->prefix . 'console');

        add_settings_field($this->prefix . "purge_url", __("URL", $this->plugin), array($this, $this->prefix . "purge_url"), $this->prefix . 'console', "console");
    }

    /**
    * @since 1.0
    */
    public function remote_cache_purge_url()
    {
        ?>
            <input type="text" name="remote_cache_purge_url" size="100" id="remote_cache_purge_url" value="" />
            <p class="description"><?=__('Relative URL to purge. Example : /simple-post or /uncategorized/hello-world. It will clear that URL page (and related pages) cache on ALL reported servers', $this->plugin)?></p>
        <?php
    }
    // end of console

    /**
    * @since 1.0
    */
    public function post_row_actions($actions, $post)
    {
        if ($this->check_if_purgeable()) {
            $actions = array_merge($actions, array(
                'rcpurger_purge_post' => sprintf('<a href="%s">' . __('Refresh cache', $this->plugin) . '</a>', wp_nonce_url(sprintf('admin.php?page=rcpurger-plugin&tab=settings&action=purge_post&post_id=%d', $post->ID), $this->plugin))
            ));
        }
        return $actions;
    }

    /**
    * @since 1.0
    */
    public function page_row_actions($actions, $post)
    {
        if ($this->check_if_purgeable()) {
            $actions = array_merge($actions, array(
                'rcpurger_purge_page' => sprintf('<a href="%s">' . __('Refresh cache', $this->plugin) . '</a>', wp_nonce_url(sprintf('admin.php?page=rcpurger-plugin&tab=settings&action=purge_page&post_id=%d', $post->ID), $this->plugin))
            ));
        }
        return $actions;
    }

    /**
    * @since 1.0
    */
    public function get_user_agent()
    {
        return $this->$userAgent . $this->$version;
    }
}

$rcpurger = new RCPurger();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}
