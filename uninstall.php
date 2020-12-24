<?php
/**
 * Uninstall
 * @package remote-cache-purge
*/

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_site_option( 'remote_cache_use_purge_method' );
delete_site_option( 'remote_cache_enabled' );
delete_site_option( 'remote_cache_ips' );
delete_site_option( 'remote_cache_truncate_notice' );
delete_site_option( 'remote_cache_purge_menu_save' );
delete_site_option( 'remote_cache_debug' );
delete_site_option( 'remote_cache_purge_path' );
delete_site_option( 'remote_cache_additional_domains' );
