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

/**
 * Purge Class
 *
 * @since 1.0
 */
class RemoteCachePurger {

	/**
	 * Version Number
	 * @var string
	 */
	public static $version = '1.0.0';

	/**
	 * List of URLs to be purged
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access protected
	 */
	protected $purge_urls = array();

	/**
	 * Devmode options
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 * @static
	 */
	public static $devmode = array();
	
	/**
	 * Init
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {

		add_action( 'admin_notices', array( $this, 'require_wp_version_notice' ) );

		// Load everything
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		// add_action( 'import_start', array( &$this, 'import_start' ) );
		// add_action( 'import_end', array( &$this, 'import_end' ) );

		// Check if there's an upgrade
		// add_action( 'upgrader_process_complete', array( &$this, 'check_upgrades' ), 10, 2 );

	}
	
	/**
	 * Admin Init
	 *
	 * @since 1.0
	 * @access public
	*/
	public function admin_init() {
		// If WordPress.com Master Bar is active, show the activity box.
		if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'masterbar' ) ) {
			add_action( 'activity_box_end', array( $this, 'cc_remote_cache_rightnow' ), 100 );
		}

		// Failure: Pre WP 4.7.
		if ( version_compare( get_bloginfo( 'version' ), '4.7', '<=' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', array( $this, 'require_wp_version_notice' ) );
			return;
		}

		// Admin notices.
		if ( current_user_can( 'manage_options' ) ) {

			// Warning: Debug is active.
			// REMOVE: 
			// if ( RemoteCacheDebug::devmode_check() ) {
				add_action( 'admin_notices', array( $this, 'devmode_is_active_notice' ) );
			// }

		}
	}

	/**
	 * Plugin Init
	 *
	 * @since 1.0
	 * @access public
	*/
	public function init() {

		// get events after which cache will be cleared
		$events       = $this->get_register_events();
		$no_id_events = $this->get_no_id_events();

		// make sure we have our events
		if ( ! empty( $events ) && ! empty( $no_id_events ) ) {

			// force needed?
			$events       = (array) $events;
			$no_id_events = (array) $no_id_events;

			// Add the action for each event.
			foreach ( $events as $event ) {
				if ( in_array( $event, $no_id_events, true ) ) {
					// Events without an ID will be forced to clear full cache
					add_action( $event, array( $this, 'rc_purge_full' ) );
				} else {
					add_action( $event, array( $this, 'rc_purge_post' ), 10, 2 );
				}
			}
		}

		add_action( 'shutdown', array( $this, 'execute_purge' ) );

		// ON Success: Admin notice when purging.
		if ( ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) ||
			( isset( $_GET['vhp_flush_do'] ) && check_admin_referer( 'vhp-flush-do' ) ) ) {
			if ( 'devmode' === $_GET['vhp_flush_do'] && isset( $_GET['vhp_set_devmode'] ) ) {
				// RemoteCacheDebug::devmode_toggle( esc_attr( $_GET['vhp_set_devmode'] ) );
				add_action( 'admin_notices', array( $this, 'admin_message_devmode' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'admin_message_purge' ) );
			}
		}

		// Add Admin Bar.
		add_action( 'admin_bar_menu', array( $this, 'remote_purger_rightnow_adminbar' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'custom_css' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'custom_css' ) );

	}

	/**
	 * Warning: Development Mode
	 * Checks if DevMode is active
	 *
	 * @since 4.6.0
	 */
	public function devmode_is_active_notice() {
		$message = __( 'Proxy Cache Purge Development Mode has been activated via wp-config.', 'varnish-http-purge' );

		// Only echo if there's actually a message
		if ( isset( $message ) ) {
			echo '<div class="notice notice-warning"><p>' . wp_kses_post( $message ) . '</p></div>';
		}
	}
	
	/**
	 * Custom CSS to allow for coloring.
	 *
	 * @since 1.0
	 */
	public function custom_css() {
		if ( is_user_logged_in() && is_admin_bar_showing() ) {
			wp_register_style( 'varnish_http_purge', plugins_url( 'style.css', __FILE__ ), false, self::$version );
			wp_enqueue_style( 'varnish_http_purge' );
		}
	}


	/**
	 * Purge Button in the Admin Bar
	 *
	 * @access public
	 * @param mixed $admin_bar - data passed back from admin bar.
	 * @return void
	 * 
	 * @since 1.0
	 */
	public function cc_remote_cache_rightnow( $admin_bar ) {
		global $wp;

		$can_purge    = true;
		$cache_active = true; // ( RemoteCacheDebug::devmode_check() ) ? __( 'Inactive', 'remote-cache-purger' ) : __( 'Active', 'remote-cache-purger' );
		
		$cache_titled = sprintf( __( 'Cache (%s)', 'remote-cache-purger' ), $cache_active );

		if ( ( ! is_admin() && get_post() !== false && current_user_can( 'edit_published_posts' ) ) || current_user_can( 'activate_plugins' ) ) {
			// Main Array.
			$args      = array(
				array(
					'id'    => 'remote-cache-purger',
					'title' => '<span class="ab-icon" style="background-image: url(' . self::get_icon_svg() . ') !important;"></span><span class="ab-label">' . $cache_titled . '</span>',
					'meta'  => array(
						'class' => 'remote-cache-purger',
					),
				),
			);
			$can_purge = true;
		}

		// Checking user permissions for who can and cannot use the all flush.
		if (
			// SingleSite - admins can always purge.
			( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge.
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1.
			( is_multisite() && current_user_can( 'activate_plugins' ) && ( SUBDOMAIN_INSTALL || ( ! SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE !== $blog_id ) ) ) )
			) {

			$args[] = array(
				'parent' => 'remote-cache-purger',
				'id'     => 'remote-cache-purger-all',
				'title'  => __( 'Purge Cache (All Pages)', 'remote-cache-purger' ),
				'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', 'all' ), 'vhp-flush-do' ),
				'meta'   => array(
					'title' => __( 'Purge Cache (All Pages)', 'remote-cache-purger' ),
				),
			);

			// If a memcached file is found, we can do this too.
			if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
				$args[] = array(
					'parent' => 'remote-cache-purger',
					'id'     => 'remote-cache-purger-db',
					'title'  => __( 'Purge Database Cache', 'remote-cache-purger' ),
					'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', 'object' ), 'vhp-flush-do' ),
					'meta'   => array(
						'title' => __( 'Purge Database Cache', 'remote-cache-purger' ),
					),
				);
			}

			// If we're on a front end page and the current user can edit published posts, then they can do this.
			if ( ! is_admin() && get_post() !== false && current_user_can( 'edit_published_posts' ) ) {
				$page_url = esc_url( home_url( $wp->request ) );
				$args[]   = array(
					'parent' => 'remote-cache-purger',
					'id'     => 'remote-cache-purger-this',
					'title'  => __( 'Purge Cache (This Page)', 'remote-cache-purger' ),
					'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', $page_url . '/' ), 'vhp-flush-do' ),
					'meta'   => array(
						'title' => __( 'Purge Cache (This Page)', 'remote-cache-purger' ),
					),
				);
			}

			// If Devmode is in the config, don't allow it to be disabled.
			if ( ! VHP_DEVMODE ) {
				// Populate enable/disable cache button.
				if ( RemoteCacheDebug::devmode_check() ) {
					$purge_devmode_title = __( 'Restart Cache', 'remote-cache-purger' );
					$vhp_add_query_arg   = array(
						'vhp_flush_do'    => 'devmode',
						'vhp_set_devmode' => 'dectivate',
					);
				} else {
					$purge_devmode_title = __( 'Pause Cache (24h)', 'remote-cache-purger' );
					$vhp_add_query_arg   = array(
						'vhp_flush_do'    => 'devmode',
						'vhp_set_devmode' => 'activate',
					);
				}

				$args[] = array(
					'parent' => 'remote-cache-purger',
					'id'     => 'remote-cache-purger-devmode',
					'title'  => $purge_devmode_title,
					'href'   => wp_nonce_url( add_query_arg( $vhp_add_query_arg ), 'vhp-flush-do' ),
					'meta'   => array(
						'title' => $purge_devmode_title,
					),
				);
			}
		}

		if ( $can_purge ) {
			foreach ( $args as $arg ) {
				$admin_bar->add_node( $arg );
			}
		}
	}


	/**
	 * Purge Message
	 * Informs of a succcessful purge
	 *
	 * @since 1.0
	 */
	public function admin_message_purge() {
		echo '<div id="message" class="notice notice-success fade is-dismissible"><p><strong>' . esc_html__( 'Cache emptied!', 'remote-cache-purger' ) . '</strong></p></div>';
	}
}
	
/*
 * Settings Pages
 *
 * @since 1.0
 */
// The settings PAGES aren't needed on the network admin page
if ( ! is_network_admin() ) {
	// require_once 'settings.php';
}
// require_once 'debug.php';

// $rcpurger = new RemoteCachePurger();
