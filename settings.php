<?php
/**
 * Settings Code
 * @package remote-cache-purge
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Status Class
 *
 * @since 4.0
 */
class RemoteCacheSettings {
	/**
	 * Construct
	 * Fires when class is constructed, adds init hook
	 *
	 * @since 4.0
	 */
	public function __construct() {
		add_action('init', array(&$this, 'init'), 11);
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_filter( 'admin_footer_text', array( &$this, 'admin_footer' ), 1, 2 );
	}

	/**
	 * Admin init Callback
	 *
	 * @since 4.0
	 */
	public function admin_init() {
		$this->register_settings();
		$this->register_check_caching();
	}

	public function init() {
		
		if (isset($_GET['action']) && isset($_GET['post_id']) && ($_GET['action'] == 'purge_post' || $_GET['action'] == 'purge_page') && check_admin_referer($this->plugin)) {
				$this->purge_post($_GET['post_id']);
				$_SESSION['vcaching_note'] = $this->noticeMessage;
				$referer = str_replace('purge_varnish_cache=1', '', wp_get_referer());
				wp_redirect($referer . (strpos($referer, '?') ? '&' : '?') . 'vcaching_note=' . $_GET['action']);
		}
		if (isset($_GET['vcaching_note']) && ($_GET['vcaching_note'] == 'purge_post' || $_GET['vcaching_note'] == 'purge_page')) {
				add_action('admin_notices' , array($this, 'purge_post_page'));
		}
		
	}

	public function purge_post_page()
	{
			if (isset($_SESSION['vcaching_note'])) {
					echo '<div id="message" class="updated fade"><p><strong>' . __('Varnish Caching', $this->plugin) . '</strong><br /><br />' . $_SESSION['vcaching_note'] . '</p></div>';
					unset ($_SESSION['vcaching_note']);
			}
	}
		
	/**
	 * Admin Menu Callback
	 *
	 * @since 4.0
	 */
	public function admin_menu() {
		add_menu_page( __( 'Remote Cache Purger', 'rc-menu-settings' ), __( 'Remote Cache', 'remote-cache-purge' ), 'manage_options', 'rc-menu-settings', array( &$this, 'settings_page' ), RCTools::get_icon_svg( true, '#82878c' ), 75 );
		// add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $function = '', int $position = null )
		add_submenu_page( 'rc-menu-settings', __( 'Remote Cache Purger', 'remote-cache-purge' ), __( 'Settings', 'remote-cache-purge' ), 'manage_options', 'rc-menu-settings', array( &$this, 'settings_page' ) );
		add_submenu_page( 'rc-menu-settings', __( 'Check Caching1', 'remote-cache-purge' ), __( 'Check Caching1', 'remote-cache-purge' ), 'manage_options', 'rc-menu-cache', array( &$this, 'check_caching_page' ) );
		add_submenu_page( "rc-menu-settings", __( 'Demo', 'remote-cache-purge' ), __( 'Demo', 'remote-cache-purge' ), "manage_options", 'rc-menu-demo', array( &$this, 'demo_page' ) );
	}

	function demo_checkbox_display()
	{
		?>
					<!-- Here we are comparing stored value with 1. Stored value is 1 if user checks the checkbox otherwise empty string. -->
					<input type="checkbox" name="demo-checkbox" value="1" <?php checked(1, get_option('demo-checkbox'), true); ?> />
		<?php
	}

	public function demo_page() {
		?>
		<div class="wrap">

			<?php settings_errors(); ?>
			<h1><?php esc_html_e( 'Is Caching Working?', 'remote-cache-purge' ); ?></h1>

			<form method="post" action="admin.php?page=rc-menu-demo">
			<?php
				settings_fields( 'remote-cache-purge-url' );
				do_settings_sections( 'remote-cache-url-settings' );
				submit_button( __( 'Check URL', 'remote-cache-purge' ), 'primary' );
			?>
			</form>

		</div>
		<?php
	}

	function wp_custom_setting_section_cb() {
        esc_html_e('Page limit is 10','text-domain');
		}
		
	/**
	 * Register Settings
	 *
	 * @since 4.0.2
	 */
	public function register_settings() {
		// register a new setting for "reading" page
		add_settings_section('eg_setting_section', 'Example settings section in reading', array(&$this,'wp_custom_setting_section_cb'), 'rc-menu-demo');
		// add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() )
		add_settings_field("demo-checkbox", "Demo Checkbox", array( &$this, 'demo_checkbox_display' ), "rc-menu-demo", "eg_setting_section");  
		register_setting('rc-menu-settings', 'page_limit');

		add_settings_section("rcp_section_1", "Section", null, array( &$this, 'rc-menu-demo'));
    add_settings_field("demo-checkbox", "Demo Checkbox", array( &$this, 'demo_checkbox_display' ), "demo", "rcp_section_1");  
		register_setting("section",  array( &$this, 'demo_checkbox_display' ));
		
		// // Enabled settings.
		// register_setting( 'rcp-settings-enabled', 'rcp_enabled', array( &$this, 'settings_enabled_sanitize' ) );
		// add_settings_section( 'rcp-enabled-section', __( 'Enabled settings', 'remote-cache-purge' ), array( &$this, 'options_settings_enabled' ), 'rcp-enabled-settings' );
		// add_settings_field( 'rcp_enabled', __( 'Enabled', 'remote-cache-purge' ), array( &$this, 'settings_enabled_callback' ), 'rcp-enabled-settings', 'rcp-enabled-section' );
		// add_settings_field( 'rcp_enabled1', __( 'Enabled1', 'remote-cache-purge' ), array( &$this, 'settings_enabled_callback1' ), 'rcp-enabled-settings1', 'rcp-enabled-section' );
		
		// // IP Settings.
		register_setting( 'vhp-settings-ip', 'rcp_ips', array( &$this, 'settings_ip_sanitize' ) );
		add_settings_section( 'rcp-settings-ips-section', __( 'Configure Custom IP', 'remote-cache-purge' ), array( &$this, 'options_settings_ip' ), 'rcp-ips-settings' );
		add_settings_field( 'varnish_ip', __( 'Set Custom IP', 'remote-cache-purge' ), array( &$this, 'settings_ip_callback' ), 'rcp-ips-settings', 'rcp-settings-ips-section' );
		
		// register_setting( 'rcp-settings-enabled1', 'rcp_enabled1', array( &$this, 'settings_enabled_sanitize' ) );
		// add_settings_section(RemoteCachePurger::$prefix . 'options', __('Settings', RemoteCachePurger::$plugin), null, 'rc_setting_option');
		// add_settings_field(RemoteCachePurger::$prefix . "enable", __("Enable" , RemoteCachePurger::$plugin), array($this, 'rc_setting_enabled'), 'rc_setting_enabled', 'rc_setting_enabled');
				
	}

	public function rc_setting_option()
	{
			?>
					<input type="checkbox" name="varnish_caching_enabled" value="1" <?php checked(1, get_option('rc_setting_enabled'), true); ?> />
					<p class="description"><?=__('Enable Varnish Caching', $this->plugin)?></p>
			<?php
	}
	public function rc_setting_enabled()
	{
			?>
					<input type="checkbox" name="varnish_caching_enabled" value="1" <?php checked(1, get_option($this->prefix . 'enable'), true); ?> />
					<p class="description"><?=__('Enable Varnish Caching', $this->plugin)?></p>
			<?php
	}
	
	/**
	 * Options Settings - Enabled
	 *
	 * @since 4.6
	 */
	public function options_settings_enabled() {
		?>
		<p><a name="#configure_enabled"></a><?php esc_html_e( 'In Development Mode, WordPress will prevent visitors from seeing cached content on your site. You can enable this for 24 hours, after which it will automatically disable itself. This will make your site run slower, so please use with caution.', 'remote-cache-purge' ); ?></p>
		<p><?php echo wp_kses_post( __( 'If you need to activate development mode for extended periods of time, you can add <code>define( \'VHP_DEVMODE\', true );</code> in your wp-config file.', 'remote-cache-purge' ) ); ?></p>
		<?php
	}

	/**
	 * Settings Enabled Callback
	 *
	 * @since 4.0
	 */
	public function settings_enabled_callback() {

		$enabled = get_site_option( 'rcp_enabled', RemoteCachePurger::$enabled );
		$active  = ( isset( $enabled['active'] ) ) ? $enabled['active'] : false;
		$active  = ( RCP_ENABLED ) ? true : $active;
		?>
		<input type="hidden" name="rcp_enabled[expire]" value="<?php $expire; ?>" />
		<input type="checkbox" name="rcp_enabled[active]" value="true" <?php disabled( RCP_ENABLED ); ?> <?php checked( $active, true ); ?> />
		<label for="rcp_enabled['active']">
			<?php
			if ( $active && isset( $enabled['expire'] ) && ! RCP_ENABLED ) {
			} elseif ( RCP_ENABLED ) {
				esc_attr_e( 'Enabled is activated through wp-config.', 'remote-cache-purge' );
			} else {
				esc_attr_e( 'Enabled', 'remote-cache-purge' );
			}
			?>
		</label>
		<?php
	}

	/**
	 * Sanitization and validation for Enabled
	 *
	 * @param mixed $input - the input to be sanitized.
	 * @since 1.0
	 */
	public function settings_enabled_sanitize( $input ) {

		$output      = array();
		$expire      = current_time( 'timestamp' ) + DAY_IN_SECONDS;
		$set_message = __( 'Something has gone wrong!', 'remote-cache-purge' );
		$set_type    = 'error';

		if ( empty( $input ) ) {
			return; // do nothing.
		} else {
			$output['active'] = ( isset( $input['active'] ) ) ? $input['active'] : false;
			$output['expire'] = ( isset( $input['expire'] ) && is_int( $input['expire'] ) ) ? $input['expire'] : $expire;
			$set_message      = ( $output['active'] ) ? __( 'Development Mode activated for the next 24 hours.', 'remote-cache-purge' ) : __( 'Development Mode dectivated.', 'remote-cache-purge' );
			$set_type         = 'updated';
		}

		// If it's true then we're activating so let's kill the cache.
		if ( $output['active'] ) {
			RemoteCachePurger::purge_url( RemoteCachePurger::the_home_url() . '/?vhp-regex' );
		}

		add_settings_error( 'rcp_enabled', 'varnish-devmode', $set_message, $set_type );
		return $output;
	}

	/**
	 * Options Settings - IP Address
	 *
	 * @since 4.0
	 */
	public function options_settings_ip() {
		?>
		<p><a name="#configureip"></a><?php esc_html_e( 'There are cases when a custom IP Address is needed to for the plugin to properly communicate with the cache service. If you\'re using a CDN like Cloudflare or a Firewall Proxy like Sucuri, or your cache is Nginx based, you may need to customize this setting.', 'remote-cache-purge' ); ?></p>
		<p><?php esc_html_e( 'Normally your Proxy Cache IP is the IP address of the server where your caching service (i.e. Varnish or Nginx) is installed. It must an address used by your cache service. If you use multiple IPs, or have customized your ACLs, you\'ll need to pick one that doesn\'t conflict with your other settings. For example, if you have Varnish listening on a public and private IP, pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1 or localhost).', 'remote-cache-purge' ); ?></p>
		<p><?php esc_html_e( 'If your webhost set the service up for you, as is the case with DreamPress or WP Engine, ask them for the specifics.', 'remote-cache-purge' ); ?></p>
		<p><strong><?php esc_html_e( 'If you aren\'t sure what to do, contact your webhost or server admin before making any changes.', 'remote-cache-purge' ); ?></strong></p>
		<?php
	}

	/**
	 * Settings IP Callback
	 *
	 * @since 4.0
	 */
	public function settings_ip_callback() {

		$disabled = false;
		if ( false !== VHP_VARNISH_IP ) {
			$disabled  = true;
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_site_option( 'rcp_ips' );
		}

		echo '<input type="text" id="rcp_ips" name="rcp_ips" value="' . esc_attr( $varniship ) . '" size="25" ' . disabled( $disabled, true ) . '/>';
		echo '<label for="rcp_ips">';

		if ( $disabled ) {
			esc_html_e( 'A Proxy Cache IP has been defined in your wp-config file, so it is not editable in settings.', 'remote-cache-purge' );
		} else {
			esc_html_e( 'Example: ', 'remote-cache-purge' );
			echo '<code>123.45.67.89</code> or <code>localhost</code>';
		}

		echo '</label>';
	}

	/**
	 * Sanitization and validation for IP
	 *
	 * @param mixed $input - the input to be sanitized.
	 * @since 4.0
	 */
	public function settings_ip_sanitize( $input ) {

		$output      = '';
		$set_message = __( 'You have entered an invalid IP address.', 'remote-cache-purge' );
		$set_type    = 'error';

		if ( empty( $input ) ) {
			return; // do nothing.
		} elseif ( 'localhost' === $input || filter_var( $input, FILTER_VALIDATE_IP ) ) {
			$set_message = 'Proxy Cache IP Updated.';
			$set_type    = 'updated';
			$output      = filter_var( $input, FILTER_VALIDATE_IP );
		}

		add_settings_error( 'rcp_ips', 'varnish-ip', $set_message, $set_type );
		return $output;
	}

	/**
	 * Register Check Caching
	 *
	 * @since 4.0
	 */
	public function register_check_caching() {
		register_setting( 'remote-cache-purge-url', 'rcp_server_url', array( &$this, 'varnish_url_sanitize' ) );
		add_settings_section( 'varnish-url-settings-section', __( 'Check Caching Status', 'remote-cache-purge' ), array( &$this, 'options_check_caching_scan' ), 'remote-cache-url-settings' );
		add_settings_field( 'varnish_url', __( 'Check A URL On Your Site: ', 'remote-cache-purge' ), array( &$this, 'check_caching_callback' ), 'remote-cache-url-settings', 'varnish-url-settings-section' );
	}

	/**
	 * Options Callback - URL Scanner
	 *
	 * @since 4.0
	 */
	public function options_check_caching_scan() {
		?>
		<p><?php esc_html_e( 'This feature performs a check of the most common issues that prevents your site from caching properly. This feature is provided to help you in resolve potential conflicts on your own. When filing an issue with your web-host, we recommend you include the output in your ticket.', 'remote-cache-purge' ); ?></p>
		<h4><?php esc_html_e( 'Privacy Note', 'remote-cache-purge' ); ?></h4>
		<p>
		<?php
			// translators: %s is a link to the readme for the detection service.
			printf( wp_kses_post( __( '<strong>This check uses <a href="%s">a remote service hosted on DreamObjects</a></strong>.', 'remote-cache-purge' ) ), 'https://remote-cache-purge.objects-us-east-1.dream.io/readme.txt' );
		?>
		</p>
		<p><?php esc_html_e( 'The service used only for providing up to date compatibility checks on plugins and themes that may conflict with running a server based cache. No personally identifying information regarding persons running this check, nor the plugins and themes in use on this site will be transmitted. The bare minimum of usage information is collected, concerning only IPs and domains making requests of the service. If you do not wish to use this service, please do not use this feature.', 'remote-cache-purge' ); ?></p>
		<?php

		// If there's no post made, let's not...
		// @codingStandardsIgnoreStart
		if ( ! isset( $_REQUEST['settings-updated'] ) || ! $_REQUEST['settings-updated'] ) {
			return;
		}
		// @codingStandardsIgnoreEnd

		// Set icons.
		$icons = array(
			'awesome' => '<span class="dashicons dashicons-heart" style="color:#46B450;"></span>',
			'good'    => '<span class="dashicons dashicons-thumbs-up" style="color:#00A0D2;"></span>',
			'warning' => '<span class="dashicons dashicons-warning" style="color:#FFB900"></span>',
			'notice'  => '<span class="dashicons dashicons-flag" style="color:#826EB4;">',
			'bad'     => '<span class="dashicons dashicons-thumbs-down" style="color:#DC3232;"></span>',
		);

		// Get the base URL to start.
		$url        = esc_url( RemoteCachePurger::the_home_url() );
		$varnishurl = get_site_option( 'rcp_server_url', $url );

		// Is this a good URL?
		$valid_url = RemoteCacheDebug::is_url_valid( $varnishurl );
		if ( 'valid' === $valid_url ) {
			// Get the response and headers.
			$remote_get = RemoteCacheDebug::remote_get( $varnishurl );
			$headers    = wp_remote_retrieve_headers( $remote_get );

			// Preflight checklist.
			$preflight = RemoteCacheDebug::preflight( $remote_get );

			// Check for Remote IP.
			$remote_ip = RemoteCacheDebug::remote_ip( $headers );

			// Get the IP.
			if ( false !== VHP_VARNISH_IP ) {
				$varniship = VHP_VARNISH_IP;
			} else {
				$varniship = get_site_option( 'rcp_ips' );
			}
			?>

			<h4>
			<?php
				// translators: %s is the URL someone asked to scan.
				printf( esc_html__( 'Results for %s ', 'remote-cache-purge' ), esc_url_raw( $varnishurl ) );
			?>
			</h4>

			<table class="wp-list-table widefat fixed posts">

			<?php
			// If we failed the preflight checks, we fail.
			if ( ! $preflight['preflight'] ) {
				?>
				<tr>
					<td width="40px"><?php echo wp_kses_post( $icons['bad'] ); ?></td>
					<td><?php echo wp_kses_post( $preflight['message'] ); ?></td>
				</tr>
				<?php
			} else {
				// We passed the checks, let's get the data!
				$output = RemoteCacheDebug::get_all_the_results( $headers, $remote_ip, $varniship );

				foreach ( $output as $subject => $item ) {
					if ( $item && is_array( $item ) ) {
						?>
							<tr>
								<td width="20px"><?php echo wp_kses_post( $icons[ $item['icon'] ] ); ?></td>
								<td width="180px"><strong><?php echo wp_kses_post( $subject ); ?></strong></td>
								<td><?php echo wp_kses_post( $item['message'] ); ?></td>
							</tr>
							<?php
					}
				}
			}
			?>
			</table>

			<?php
			if ( false !== $preflight['preflight'] ) {
				?>
				<h4><?php esc_html_e( 'Technical Details', 'remote-cache-purge' ); ?></h4>
				<table class="wp-list-table widefat fixed posts">
					<?php
					if ( ! empty( $headers[0] ) ) {
						echo '<tr><td width="200px">&nbsp;</td><td>' . wp_kses_post( $headers[0] ) . '</td></tr>';
					}
					foreach ( $headers as $header => $key ) {
						if ( '0' !== $header ) {
							if ( is_array( $key ) ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$content = print_r( $key, true );
							} else {
								$content = wp_kses_post( $key );
							}
							echo '<tr><td width="200px" style="text-align:right;">' . wp_kses_post( ucfirst( $header ) ) . ':</td><td>' . wp_kses_post( $content ) . '</td></tr>';
						}
					}
					?>
				</table>
				<?php
			}
		}
	}

	/**
	 * URL Callback
	 *
	 * @since 4.0
	 */
	public function check_caching_callback() {
		$url        = esc_url( RemoteCachePurger::the_home_url() );
		$varnishurl = get_site_option( 'rcp_server_url', $url );
		echo '<input type="text" id="rcp_server_url" name="rcp_server_url" value="' . esc_url( $varnishurl ) . '" size="50" />';
	}

	/**
	 * Sanitization and validation for URL
	 *
	 * @param mixed $input - the input to be sanitized.
	 * @since 4.0
	 */
	public function varnish_url_sanitize( $input ) {

		// Defaults values.
		$output   = esc_url( RemoteCachePurger::the_home_url() );
		$set_type = 'error';

		if ( empty( $input ) ) {
			$set_message = __( 'You must enter a URL from your own domain to scan.', 'remote-cache-purge' );
		} else {
			$valid_url = RemoteCacheDebug::is_url_valid( esc_url( $input ) );

			switch ( $valid_url ) {
				case 'empty':
				case 'domain':
					$set_message = __( 'You must provide a URL on your own domain to scan.', 'remote-cache-purge' );
					break;
				case 'invalid':
					$set_message = __( 'You have entered an invalid URL address.', 'remote-cache-purge' );
					break;
				case 'valid':
					$set_type    = 'updated';
					$set_message = __( 'URL Scanned.', 'remote-cache-purge' );
					$output      = esc_url( $input );
					break;
				default:
					$set_message = __( 'An unknown error has occurred.', 'remote-cache-purge' );
					break;
			}
		}

		if ( isset( $set_message ) ) {
			add_settings_error( 'rcp_server_url', 'rcp-url', $set_message, $set_type );
		}
		return $output;
	}

	/**
	 * Call settings page
	 *
	 * @since 4.0
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<?php settings_errors(); ?>
			<h1><?php esc_html_e( 'Remote Cache Purger Settings', 'remote-cache-purge' ); ?></h1>

			<p><?php esc_html_e( 'Remote Cache Purger can empty the cache for different server based caching systems, including Varnish and nginx. For most users, there should be no configuration necessary as the plugin is intended to work silently, behind the scenes.', 'remote-cache-purge' ); ?></p>

			<?php
			if ( ! is_multisite() ) {
				?>
				<form action="options.php" method="POST" >
				<?php
					settings_fields( 'rcp-settings-enabled' );
					do_settings_sections( 'rcp-enabled-settings' );
					submit_button( __( 'Save Settings', 'remote-cache-purge' ), 'primary' );
				?>
				</form>

				<form action="options.php" method="POST" >
				<?php
					settings_fields( 'vhp-settings-ip' );
					do_settings_sections( 'rcp-ips-settings' );
					submit_button( __( 'Save IP', 'remote-cache-purge' ), 'secondary' );
				?>
				</form>
				<?php
			} else {
				?>
				<p><?php esc_html_e( 'Editing these settings via the Dashboard is disabled on Multisite as incorrect edits can prevent your network from loading entirely. You can toggle debug mode globally using the admin toolbar option, and you should define your Proxy IP directly into your wp-config file for best results.', 'remote-cache-purge' ); ?></p>
				<p><?php esc_html_e( 'The cache check page remains available to assist you in determining if pages on your site are properly cached by your server.', 'remote-cache-purge' ); ?></p>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Call the Check Caching
	 *
	 * @since 1.0.0
	 */
	public function check_caching_page() {
		?>
		<div class="wrap">

			<?php settings_errors(); ?>
			<h1><?php esc_html_e( 'Is Caching Working?', 'remote-cache-purge' ); ?></h1>

			<form action="options.php" method="POST" >
			<?php
				settings_fields( 'remote-cache-purge-url' );
				do_settings_sections( 'remote-cache-url-settings' );
				submit_button( __( 'Check URL', 'remote-cache-purge' ), 'primary' );
			?>
			</form>

		</div>
		<?php
	}

	/**
	 * When user is on one of our admin pages, display footer text
	 * that graciously asks them to rate us.
	 *
	 * @since 4.6.4
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function admin_footer( $text ) {

		global $current_screen;

		if ( ! empty( $current_screen->parent_base ) && strpos( $current_screen->parent_base, 'remote-cache-plugin' ) !== false ) {
			$review_url  = 'https://wordpress.org/support/plugin/remote-cache-purge/reviews/?filter=5#new-post';
			$dream_url   = 'https://dreamhost.com/dreampress/';
			$footer_text = sprintf(
				wp_kses(
					/* translators: $1$s - DreamHost URL; $2$s - plugin name; $3$s - WP.org review link; $4$s - WP.org review link. */
					__( 'Please rate %2$s <a href="%3$s" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%4$s" target="_blank" rel="noopener">WordPress.org</a> to help us spread the word.', 'remote-cache-purge' ),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				$dream_url,
				'<strong>Remote Cache Purger</strong>',
				$review_url,
				$review_url
			);
			$text = $footer_text;
		}

		return $text;
	}
}

new RemoteCacheSettings();
