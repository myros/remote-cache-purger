<?php
/**
 * @since 1.0.1
 * 
 * will contain admin pages and actions
 * 
*/
namespace RemoteCachePurger;

/**
* @since 1.0.1
*/
class Settings {
  const NAME = 'remote-cache-purger-settings';

  private $main = null;
  private $plugin = null;
  private $prefix = null;

  /**
   * @since 1.0.1
  */
  public function __construct() {
    $this->main = Main::getInstance();

    $this->plugin = $this->main->plugin;
    $this->prefix = $this->main->prefix;

    $this->admin_menu();
    
    $this->currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';

    add_filter('admin_footer_text', array( &$this, 'admin_footer' ), 1, 2 );
  }

  /**
   * JS for admin pages
   * 
   * @since 1.0.1
  */
  public function addScripts() {
    
  }

  /**
   * Styles for admin page
   * 
   * @since 1.0.1
  */
  public function addStyles() {

  }

  /**
   * Action for purge button
   * 
   * @since 1.0.1
  */
  public function purge() {

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
        if ($this->main->check_if_purgeable()) {
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
            <?php if ($this->main->check_if_purgeable()): ?>
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
     * OPTIONS TAB
     * 
     * @since 1.0
    */
    public function options_page_fields()
    {
        add_settings_section($this->prefix . 'settings', __('Settings', $this->plugin), null, $this->prefix . 'settings');

        add_settings_field($this->prefix . "enabled", __("Enable" , $this->plugin), array($this, $this->prefix . "enabled"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "ips", __("Servers IPs", $this->plugin), array($this, $this->prefix . "ips"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "additional_domains", __("Additional domains", $this->plugin), array($this, $this->prefix . "additional_domains"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "response_count_header", __("Response Count Header", $this->plugin), array($this, $this->prefix . "response_count_header"), $this->prefix . 'settings', $this->prefix . 'settings');
        // add_settings_field($this->prefix . "purge_key", __("Purge key", $this->plugin), array($this, $this->prefix . "purge_key"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "truncate_notice", __("Truncate notice", $this->plugin), array($this, $this->prefix . "truncate_notice"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "use_purge_method", __("Use PURGE HTTP method", $this->plugin), array($this, $this->prefix . "use_purge_method"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "purge_path", __("PURGE path", $this->plugin), array($this, $this->prefix . "purge_path"), $this->prefix . 'settings', $this->prefix . 'settings');
        add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->prefix . 'settings', $this->prefix . 'settings');

        // add_settings_section($this->prefix . 'settings_use_purge', __('Purge Settings', $this->plugin), null, $this->prefix . 'settings');

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'settings') {
            register_setting($this->prefix . 'settings', $this->prefix . "enabled");
            register_setting($this->prefix . 'settings', $this->prefix . "ips");
            // register_setting($this->prefix . 'settings', $this->prefix . "purge_key");
            register_setting($this->prefix . 'settings', $this->prefix . "truncate_notice");
            register_setting($this->prefix . 'settings', $this->prefix . "use_purge_method", true);
            register_setting($this->prefix . 'settings', $this->prefix . "purge_path");
            register_setting($this->prefix . 'settings', $this->prefix . "additional_domains");
            register_setting($this->prefix . 'settings', $this->prefix . "response_count_header");
            register_setting($this->prefix . 'settings', $this->prefix . "debug");
        }

    }
    
    /**
    * @since 1.0
    */
    public function remote_cache_enabled()
    {
        ?>
        <label for="remote_cache_enabled">
            <input name="remote_cache_enabled" type="checkbox" id="remote_cache_enabled" value="1" <?php checked(1, get_option($this->prefix . 'enabled'), true); ?> />
	        <?=__('Enable Remote Cache Purge', $this->plugin)?>
        </label>
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
    public function remote_cache_ips()
    {
        ?>
            <input type="text" name="remote_cache_ips" id="remote_cache_ips" size="100" value="<?php echo get_option($this->prefix . 'ips'); ?>" />
            <p class="description"><?=__('Comma separated ip/ip:port. Example: 192.168.0.2,192.168.0.3:8080', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0.2
    */
    public function remote_cache_additional_domains()
    {
        ?>
            <input type="text" name="remote_cache_additional_domains" id="remote_cache_additional_domains" size="100" value="<?php echo get_option($this->prefix . 'additional_domains'); ?>" />
            <p class="description"><?=__('BETA!!! Other domains which use this blog. They will be added to purge. Comma separated, Example: www.example.com, example.co', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0.3
    */
    public function remote_cache_response_count_header()
    {
        ?>
            <input type="text" name="remote_cache_response_count_header" id="remote_cache_response_count_header" size="50" value="<?php echo get_option($this->prefix . 'response_count_header'); ?>" />
            <p class="description"><?=__('BETA!!! Response header that holds number of purged items. Example: X-PURGED-COUNT', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0
    */
    public function remote_cache_debug()
    {
        ?>
        <label for="remote_cache_debug">
            <input name="remote_cache_debug" type="checkbox" id="remote_cache_debug" value="1" <?php checked(1, get_option($this->prefix . 'debug'), true); ?> />
	        <?=__('Send logging data to WP debug.log.', $this->plugin)?>
        </label>
        <?php
    }

    
    /**
    * @since 1.0.1
    */
    public function remote_cache_use_purge_method()
    {
        ?>
        <label for="remote_cache_use_purge_method">
            <input name="remote_cache_use_purge_method" type="checkbox" id="remote_cache_use_purge_method" value="1" <?php checked(1, get_option($this->prefix . 'use_purge_method'), true); ?> />
	        <?=__('Use PURGE http method', $this->plugin)?>
        </label>
        <p class="description"><?=__('Requests will be send using -X PURGE method. If not checked, GET method and definfed purge path will be used. Choose right value based on your server config', $this->plugin)?></p>

        <?php
    }

    /**
    * @since 1.0.1
    */
    public function remote_cache_purge_path()
    {
        ?>
            <input type="text" name="remote_cache_purge_path" id="remote_cache_purge_path" size="50" value="<?php echo get_option($this->prefix . 'purge_path'); ?>" />
            <p class="description"><?=__('If your\'re using purge path enter your path here, based on your server configuration, Example: /purge', $this->plugin)?></p>
        <?php
    }

    /**
    * @since 1.0.3
    */
    public function remote_cache_truncate_notice()
    {
        ?>
        <label for="remote_cache_truncate_notice">
            <input name="remote_cache_truncate_notice" type="checkbox" id="remote_cache_truncate_notice" value="1" <?php checked(1, get_option($this->prefix . 'truncate_notice'), true); ?> />
	        <?=__('When using multiple Cache servers, RCPurger shows too many purging messages. Check this option to truncate that message.', $this->plugin)?>
        </label>
        <?php
    }

    /**
     * END OF OPTIONS TAB
    */

    /**
     *  
     * CONSOLE TAB
     * 
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

    /**
     * END OF CONSOLE TAB
    */

    /**
    * @since 1.0.1
    */
	public function admin_footer( $text ) {

    global $current_screen;

    $review_url  = 'https://wordpress.org/support/plugin/remote-cache-purger/reviews/?filter=5#new-post';
    $dream_url   = 'https://myros.net/';
    $footer_text = sprintf(
        wp_kses(
            __( 'Brought to you by <a href="%1$s" target="_blank" rel="noopener noreferrer">Myros</a>. Please rate %2$s <a href="%3$s" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%4$s" target="_blank" rel="noopener">WordPress.org</a> to help us spread the word.', 'varnish-http-purge' ),
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

    return $text;
	}

}