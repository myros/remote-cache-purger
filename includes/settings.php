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

  private $plugin = null;

  /**
   * @since 1.0.1
  */
  public function __construct() {
    $this->plugin = Main::getInstance();

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