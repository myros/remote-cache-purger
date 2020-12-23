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
class Admin {
  const NAME = 'remote-cache-purger-admin';

  private $plugin = null;

  /**
   * @since 1.0.1
  */
  private function __construct() {
    $this->plugin = Main::getInstance();


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

}