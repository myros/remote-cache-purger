<?php
/**
 * Uninstall
 * @package remote-cache-purge
*/

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_site_option( 'rcpServersIPS' );
