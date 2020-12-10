<?php
/*
 * Add my new menu to the Admin Control Panel
 */
 
// Hook the 'admin_menu' action hook, run the function named 'mfp_Add_My_Admin_Link()'
// add_action( 'admin_menu', 'cc_Add_My_Admin_Link' );
 
// // Add a new top level menu link to the ACP
// function cc_Add_My_Admin_Link()
// {    
  
//   $page_title = 'WordPress Extra Post Info';   
//   $menu_title = 'Extra Post Info';   
//   $capability = 'manage_options';   
//   $menu_slug  = 'cc-admin-page';   
//   $function   = 'optionPageContent';   
//   $icon_url   = 'dashicons-media-code';
//   $position   = 4; 

//   add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position ); 
//   // add_menu_page("Plugin AJAX", "Plugin AJAX","manage_options", "myplugin", "employeeList",plugins_url('/pluginajax/img/icon.png'));

// }

// function optionPageContent() {
// ?>
// <h2>Plugin Name</h2>
// <p>These are the settings for your plugin.</p>
// <form method=post action="options.php">
//   <?php
//     settings_fields('ccClearCache');
//   ?>
//   <table class="form-table">
//     <tbody>
//       <tr>
//         <th>NGINX IPS</th>
//         <td>
//           <input type="text" name="rcpServersIPS" id="rcpServersIPS" value="<?php echo get_option('rcpServersIPS');?>"><br><span class="description"> NGINX servers ips, comma delimited</span>
//         </td>
//       </tr>
//       <tr>
//         <th>Setting 2</th>
//         <td>
//           <input type="text" name="setting2" id="setting2" value="<?php echo get_option('ccClearAfterSave');?>"><br><span class="description"> Enter your description for the setting 2 here.</span>
//         </td>
//       </tr>                    
//     </tbody>
//   </table>
//   <?php 
//     submit_button();
//   ?>
// </form>
// <?php
// }

// add_action('admin_init', 'registerPluginSettings');

// function registerPluginSettings() {
//   register_setting('ccClearCache', 'rcpServersIPS');
//   register_setting('ccClearCache', 'ccClearAfterSave');
// }

// // Hook the 'admin_menu' action hook, run the function named 'mfp_Add_My_Admin_Link()'
// add_action( 'save_post', 'cc_clear_cache', 10, 3 );
 
// // Add a new top level menu link to the ACP
// // curl --head --resolve localhost:80:172.19.0.5 http://localhost/sample-page
// // curl --silent --head --resolve myros.net:443:10.244.0.153 https://myros.net

// function cc_clear_cache( $post_id, $post, $update )
// {    
//   $ips = get_option('rcpServersIPS');

//   if ( is_null($ips) ) {
//     $ips = getenv('CACHE_SERVER_IPS');
//   }

//   $ips = explode(',', $ips);

//   if(!$ips)
//   {
//     echo 'Invalid IPS';
//     die();
//   }

//   // TODO: CHECK IF IPS EMPTY
//   // TODO: key?
//   $slug = $post->post_name;

//   $cache_path = '/tmp/cache/';
//   $url = parse_url(get_permalink($post));
  
//   if(!$url)
//   {
//     echo 'Invalid URL entered';
//     die();
//   }
  
//   $scheme = $url['scheme'];
//   $port = ($url['scheme'] == 'https') ? '443' : '80';
//   $host = $url['host'];
//   $requesturi = $url['path'];
  
//   // TODO: get post data
//   foreach ($ips as $ip) {

//     $ip = trim($ip);
//     // "$scheme$request_method$host$request_uri";
//     $hash = md5($scheme.'GET'.$ip.$requesturi);
//     // echo 'dump: ' . $cache_path . ' * uri: ' . $requesturi . ' *** HOST: ' . $host . '<br>';

//     // location
//     // echo '</br>dump: ' . $cache_path . substr($hash, -1) . '/' . substr($hash,-3,2) . '/' . $hash . ' IP:' . $ip;
    
//     $url = "http://{$ip}{$requesturi}";

//     // echo $hash;
//     // echo var_dumpunlink($cache_path . substr($hash, -1) . '/' . substr($hash,-3,2) . '/' . $hash);
//     // echo $host;
  
//     $headers = [
//       "cache-key: {$hash}",
//       "X-Cache-Purge: 1",
//       "Host: {$host}",
//       "Location: {$host}"
//     ];

//     $ch = curl_init("{$host}{$requesturi}");
//     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//     curl_setopt($ch, CURLOPT_RESOLVE, array(
//         "{$host}:{$port}:{$ip}",
//     ));
//     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_VERBOSE, true);
//     $result = curl_exec($ch);
    
//     curl_close($ch);

//     print $host;
    
//     // echo json_decode($result);
//     //TODO: collect responses and if all are ok, green message, otherwise red with server which didn't respond properly
//   }
  
//   // wp_die();
//   // return true;
// }

// add_action( 'admin_notices', array( 'admin_message_purge' ) );

// /**
//  * Purge Message
//  * Informs of a succcessful purge
//  *
//  * @since 4.6
// */
// function admin_message_purge() {
//   echo '<div id="message" class="notice notice-success fade is-dismissible"><p><strong>' . esc_html__( 'Cache emptied!', 'varnish-http-purge' ) . '</strong></p></div>';
// }

// add_filter('post_updated_messages', 'your_message');

// function your_message(){
//   $msg = 'Is this un update? ';
//   $msg .= $update ? 'Yes.' : 'No.';
//   wp_die( $msg );
// }

// <?php
// /*
//  * Add my new menu to the Admin Control Panel
//  */
 
// // Add a new top level menu link to the ACP
// function mfp_Add_My_Admin_Link()
// {
//       add_menu_page(
//         'My First Page', // Title of the page
//         'My First Plugin', // Text to show on the menu link
//         'manage_options', // Capability requirement to see the link
//         'includes/mfp-first-acp-page.php' // The 'slug' - file to display when clicking the link
//     );
// }