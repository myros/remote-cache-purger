= Remote Cache Purger =
Contributors: myros
Tags: nginx, cache, purge, kubernetes
Requires at least: 4.7
Tested up to: 5.6
Stable tag: 1.0.0
Requires PHP: 5.6
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Plugin will empty remote server's cache content (only NGINX at this moment).

= Plugin Features =

* Purge cache on multiple NGINX servers
* Cache can be purged on page/post, url or whole site (*)

<strong>This plugin <em>does not</em> install nor configure a cache proxy. It acts as an clear service for cache.</strong>

== Description ==

One common method of caching content for websites is via the use of reverse proxy caching. Common examples of this are <a href="https://www.varnish-cache.org/">Varnish</a> and <a href="https://www.nginx.com/">Nginx</a>. These systems allow a website to update content and have the visitor's experience cached without the need for complex plugins storing the files locally and using up a user's disk space.

A reverse proxy cache is installed in front of a server and reviews requests. If the page being requested is already cached, it delivers the cached content. Otherwise it generates the page and the cache on demand.

The Remote Cache Purger plugin sends a request to delete (aka flush) the cached data of a page or post to remote server(s).

= How It Works =

Manually

== Installation ==

No special instructions apply.

If you have a 3rd party proxy service (such as Sucuri or Cloudflare) you will need to add an IP address on the <em>Proxy Cache -> Settings</em> page. Alternatively you can add a define to your `wp-config.php` file: `define('VHP_VARNISH_IP','123.45.67.89');`

When using Nginx based proxies, your IP will likely be `localhost`.


= Requirements =

* A server based proxy cache service (only Nginx for now)

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/remote-cache-purger)**


= Privacy Policy =

By default, no data is tracked. If you use the site scanner/debugging tool, your domain and IP address will access [a remote service hosted on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt). No personally identifying transaction data is recorded or stored, only overall usage. IP addresses of the website making the request may be recorded by the service, but there is no way to access them and use it to correspond with individuals or processes.

Use of this service is required for the cache checking in order to provide up to date compatibility checks on plugins and themes that may conflict with running a server based cache without needing to update the plugin every day.

<em>No visitor information from your site is tracked.</em>

== Upgrade Notice ==

There is no need to upgrade just yet.

== Screenshots ==

There are no screenshots yet.