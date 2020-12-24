<?php
/**
     * Plugin Name: Remote Cache Purger
     * Description: Clearing cache on remote NGINX servers (Kubernetes)
     * Author: Myros
     * Author URI: https://www.myros.net/
     * Version: 1.0.1
     
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
     * 
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
     * 
*/

namespace RemoteCachePurger;

require_once __DIR__ . '/includes/queue.php';

class Main {

    const NAME = 'remote-cache-purger';

    private static $instance = null;
    private $queue = null;
    private $admin = null;
    
    // general

    protected $blogId; 
    protected $plugin = 'cache-purger';
    protected $prefix = 'remote_cache_'; // remote cache purger

    protected $serverIPS = array();
    protected $getParam = 'purge_remote_cache';
    protected $postTypes = array('page', 'post');
    
    public $noticeMessage = '';
    protected $truncateNotice = false;
    protected $truncateCount = 0;
    
    // settings
    protected $debug = 0;
    protected $enabled = false;
    protected $optServersIP = [];

    // future
    // protected $optDomains = null;
    // protected $optPurgeOnMenuSave = false;
    // protected $purgeKey = null;
    protected $usePurgeMethod = true;
    protected $purgePath = '';
    // protected $responseHeader = null;

    private $registeredEvents = array(
        'publish_future_post',
        'save_post', 
        'deleted_post',
        'trashed_post',
        'edit_post',
        'delete_attachment',
        'switch_theme',
    );

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

        add_action('admin_enqueue_scripts', array( $this, 'add_scripts') );
        add_action('wp_ajax_remote_cache_purge_all', array( $this, 'purgejs' ) );
        add_action('wp_ajax_remote_cache_purge_item', array( $this, 'purgejs_item' ) );
        add_action('wp_ajax_remote_cache_purge_url', array( $this, 'purgejs_url' ) );

        
    }

    /**
    * @since 1.0
    */
    public function init()
    {
      $this->write_log('Init', 'Start');

      load_plugin_textdomain( self::NAME, false, self::NAME . '/languages' );

      if ( is_admin() ) {
        // do nothing for now
      }
      
      $this->queue = new Queue();

      $this->postTypes = get_post_types(array('show_in_rest' => true));

      $this->loadOptions();
      $this->admin_menu();

      // register events to purge post
      foreach ($this->registeredEvents as $event) {
          add_action($event, array($this, 'addPost'), 10, 2);
      }

      // purge post/page cache from post/page actions
      if ($this->check_if_purgeable()) {
        require_once __DIR__ . '/includes/settings.php';
        $this->admin = new Settings();
        add_action('admin_bar_menu', array($this, 'purge_cache_from_adminbar'), 100);
        add_filter('post_row_actions', array(&$this, 'post_row_actions'), 0, 2);
        add_filter('page_row_actions', array(&$this, 'page_row_actions'), 0, 2);
      }

      $this->currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
      $this->write_log('Init', 'End');
    }

    /**
    * @since 1.0.1
    */
    public function purgejs() {

      if ( wp_verify_nonce( $_POST['wp_nonce'], self::NAME . '-purge-wp-nonce' ) && $this->purgeAll() ) {
        echo json_encode( array(
          'success' => true,
          'message' => __( $this->noticeMessage, 'remote-cache-purger' )
        ) );

      } else {
        echo json_encode( array(
          'success' => false,
          'message' => __( 'The Remote Cache could not be purged!', 'remote-cache-purger' )
        ) );
      }

      exit();
    }

    /**
    * @since 1.0.1
    */
    public function purgejs_item($postid) {
        $this->write_log('purge item');

        $id = $_POST['id'];

        if ( wp_verify_nonce( $_POST['wp_nonce'], self::NAME . '-purge-wp-nonce' )) {
            if ($this->purgeItem($id)) {
              echo json_encode( array(
                'success' => true,
                'message' => __( $this->noticeMessage, 'remote-cache-purger' )
              ) );

              exit();
            }
        }

        echo json_encode( array(
          'success' => false,
          'message' => __( 'Remote Cache could not be purged!', 'remote-cache-purger' )
        ) );

        exit();
    }

    /**
    * @since 1.0.1
    */
    public function purgejs_url($url) {
        $this->write_log('purge item');

        $url = $_POST['url'];

        if ( wp_verify_nonce( $_POST['wp_nonce'], self::NAME . '-purge-wp-nonce' )) {
            if ($this->purgeUrl($url)) {
                echo json_encode( array(
                    'success' => true,
                    'message' => __( $this->noticeMessage, 'remote-cache-purger' )
                ) );

                exit();
            }
        }

        echo json_encode( array(
            'success' => false,
            'message' => __( 'Remote URL could not be purged!', 'remote-cache-purger' )
        ) );

        exit();
    }
    
    /**
    * @since 1.0.1
    */
    public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
    }
    
    /**
    * @since 1.0.1
    */
    public function add_scripts() {
      wp_register_script( self::NAME, plugins_url('assets/js/admin.js', __FILE__ ) );
      wp_enqueue_script( self::NAME );
    }
    
    /**
    * @since 1.0.1
    */
    public function addAll() {
		$this->queue->addURL( home_url() . '/.*' );
        
		return $this;
    }
    
    /**
    * @since 1.0
    */
    protected function loadOptions()
    {
        $this->truncateNotice = get_option($this->prefix . 'truncate_notice');
        $this->debug = get_option($this->prefix . 'debug');
        $this->optServersIP = get_option($this->prefix . 'ips');
        $this->optDomains = get_option($this->prefix . 'domains');
        $this->optUsePurgeMethod = get_option($this->prefix . 'use_purge_method');
        $this->optPurgePath = get_option($this->prefix . 'purge_path');

        $serverIPS = explode(',', $this->optServersIP);
        foreach ($serverIPS as $key => $ip) {
            $this->serverIPS[] = trim($ip);
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
    public function purge_cache_from_adminbar($admin_bar)
    {
        $admin_bar->add_menu(array(
            'id'    => 'purge-all-remote-cache',
            'title' => __('Purge Cache', $this->plugin),
            'href'  => 'javascript:;',
            'meta'  => array(
                'title' => __('Purge Cache', $this->plugin),
            )
        ));

        add_action( 'admin_footer', array( $this, 'embed_wp_nonce' ) );
        add_action( 'admin_notices', array( $this, 'embed_admin_notices' ) );
    }

    /**
     * 
     * dashboard purge link
     * 
     * @since 1.0
    */
    public function remote_purger_glance()
    {
        $data = '';
        $nopermission = '';
        if ($this->optServersIP == null) {
            $intro .= sprintf(__('Please setup Remote Host IPs to be able to use <a href="%1$s">Remote Cache Purger</a>.', $this->plugin), 'http://wordpress.org/plugins/remote-cache-purger/');
        } else {
            $data .= sprintf(__('<a href="%1$s">Remote Cache Purge</a> automatically purges your posts when published or updated. Sometimes you need a manual flush.', $this->plugin), 'http://wordpress.org/plugins/remote-cache-purger/');
            $data .=  __('Press the button below to force it to purge your entire cache.', $this->plugin);
            $data .= '</p><p><span class="button"><a href=javascript:;><strong>';
            $data .= __('Purge ALL Remote Cache', $this->plugin);
            $data .= '</strong></a></span>';
            $nopermission .=  __('You do not have permission to purge the cache for the whole site. Please contact your adminstrator.', $this->plugin);
        }
        if ($this->check_if_purgeable()) {
            $text = $data;
        } else {
            $text = $intro . ' ' . $nopermission;
        }
        echo '<div class="remote-purger-glance" id="wp-glance-purge-all-remote-cache">' . $text . '</div>';
    }

    /**
    * @since 1.0
    */
    public function purgeUrl($url)
    {
        $this->write_log('Main', 'purgeUrl', 'Purging URL: ' . $url);
        
        // is it root path => /
        $is_root = $url == '*' || untrailingslashit($url) == get_site_url();

        if ($is_root){
            $urls_to_purge = array('*');

            foreach ($this->serverIPS as $server) {
                $this->responses = [];
                $this->purgeServer($server, $urls_to_purge, false);
            }
        } else {
            $postid = url_to_postid($url);

            if ($postid > 0) {
                $this->addPost($postid);
            } else {
                $this->queue->addURL($url);
                foreach ($this->serverIPS as $serverIP) {
                    $this->queue->commitPurge($serverIP);
                }
            }
        }

        return true;
    }

    /**
    * @since 1.0
    */
    public function addPost($postId, $post=null)
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
                $this->queue->addURL($url);
            }
        }
    }

    /**
     * Purge all pages
     * @since 1.0.1
    */
    public function purgeAll()
    {
        $this->queue->addURL( home_url() . '/.*' );

        foreach ($this->serverIPS as $serverIP) {
            $this->queue->commitPurge($serverIP);
        }
        
        return true;
    }

    /**
     * Purge one post/page
     * 
     * @since 1.0.1
    */
    public function purgeItem($postID)
    {
        $this->addPost($postID);

        foreach ($this->serverIPS as $serverIP) {
            $this->queue->commitPurge($serverIP);
        }

        return true;
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
            add_menu_page(__('Remote Cache Purger', $this->plugin), __('Remote Cache Purger', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), 'dashicons-sos', 99);
        }
    }

    /**
    * @since 1.0
    */
    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?=__('Remote Cache Purger', $this->plugin)?></h1>

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
        <?php elseif($this->currentTab == 'console'): ?>
            <form method="post" action="admin.php?page=<?=$this->plugin?>-plugin&amp;tab=console">
                <?php
                    settings_fields($this->prefix . 'console');
                    do_settings_sections($this->prefix . 'console');
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
        // add_settings_field($this->prefix . "additional_domains", __("Additional domains", $this->plugin), array($this, $this->prefix . "ips"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "purge_key", __("Purge key", $this->plugin), array($this, $this->prefix . "purge_key"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "truncate_notice", __("Truncate notice message", $this->plugin), array($this, $this->prefix . "truncate_notice"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "use_purge_method", __("Use PURGE method", $this->plugin), array($this, $this->prefix . "use_purge_method"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "purge_path", __("PURGE path", $this->plugin), array($this, $this->prefix . "purge_path"), $this->prefix . 'settings', $this->prefix . 'settings');

        // add_settings_section($this->prefix . 'settings_use_purge', __('Purge Settings', $this->plugin), null, $this->prefix . 'settings');

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'settings') {
            // register_setting($this->prefix . 'settings', $this->prefix . "enabled");
            register_setting($this->prefix . 'settings', $this->prefix . "ips");
            // register_setting($this->prefix . 'settings', $this->prefix . "purge_key");
            // register_setting($this->prefix . 'settings', $this->prefix . "truncate_notice");
            register_setting($this->prefix . 'settings', $this->prefix . "use_purge_method", true);
            register_setting($this->prefix . 'settings', $this->prefix . "purge_path");
            // register_setting($this->prefix . 'settings', $this->prefix . "additional_domains");
            // register_setting($this->prefix . 'settings', $this->prefix . "debug");
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
            <p class="description"><?=__('Comma separated ip/ip:port. Example: 192.168.0.2,192.168.0.3:8080', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_additional_domains()
    {
        ?>
            <input type="checkbox" name="remote_cache_additional_domains" value="1" <?php checked(1, get_option($this->prefix . 'additional_domains'), true); ?> />
            <p class="description">
                <?=__('If empty, uses the $_SERVER[\'HTTP_HOST\'] as hash for Remote Server. This means the purge cache action will work on the domain you\'re on.<br />Do not use this option if you use only one domain.', $this->plugin)?>
            </p>
        <?php
    }

    /**
    * @since 1.0.1
    */
    public function remote_cache_use_purge_method()
    {
        ?>
            <input type="checkbox" name="remote_cache_use_purge_method" value="1" <?php checked(1, get_option($this->prefix . 'use_purge_method'), true); ?> />
            <p class="description">
                <?=__('Use PURGE http method or /purge(/.*) path', $this->plugin)?>
            </p>

            <script type="text/javascript">
                function togglePurge() {
                    jQuery('#' + id).val(outStr);
                }
            </script>
        <?php
    }

    /**
    * @since 1.0.1
    */
    public function remote_cache_purge_path()
    {
        ?>
            <input type="text" name="remote_cache_purge_path" id="remote_cache_purge_path" size="100" value="<?php echo get_option($this->prefix . 'purge_path'); ?>" />
            <p class="description"><?=__('If your\'re using purge path enter your path here, based on your server configuration, Example: /purge', $this->plugin)?></p>
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
            <a href="javascript:;" id="remote-cache-purger-purge-link">Purge</a>
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
                'rcpurger_purge_post' => sprintf('<a href="%s" data-item-id=' . $post->ID . '>' . __('Purge cache', $this->plugin) . '</a>', sprintf('javascript:;'), $this->plugin)
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
                'rcpurger_purge_post' => sprintf('<a href="%s" data-item-id=' . $post->ID . '>' . __('Purge cache', $this->plugin) . '</a>', sprintf('javascript:;'), $this->plugin)
            ));
        }
        return $actions;
    }
    
    /**
    * @since 1.0.1
    */
    public function write_log($method, $part = '', $message = '') {
        if (true === WP_DEBUG) {
            if (is_array($method) || is_object($method)) {
                error_log(print_r($method . '::' . $part . ' => ' . $message, true));
            } else {
                error_log($method . '::' . $part . ' => ' . $message);
            }
        }
    }
        
    /**
    * @since 1.0.1
    */
    public function embed_wp_nonce() {
		echo '<span id="' . self::NAME . '-purge-wp-nonce' . '" class="hidden">'
		     . wp_create_nonce( self::NAME . '-purge-wp-nonce' )
		     . '</span>';
    }
    
    /**
    * @since 1.0.1
    */
    public function embed_admin_notices() {
        echo '<div id="' . self::NAME . '-admin-notices' . '" class="hidden notice"></div>';
    }
    
}

\RemoteCachePurger\Main::getInstance();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}
