= Remote Cache Purger =
Contributors: myros
Tags: nginx, cache, purge, kubernetes
Requires at least: 4.7
Tested up to: 5.6
Stable tag: 1.0.4
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Source Code: https://github.com/myros/remote-cache-purger


Plugin will empty remote server's cache content (only NGINX at this moment).

= Plugin Features =

* Purge cache on multiple NGINX servers
* Cache can be purged on page/post, url or whole site (*)

<strong>This plugin <em>does not</em> install nor configure a cache proxy. It acts as an clear service for cache.</strong>

== Description ==

One common method of caching content for websites is via the use of reverse proxy caching. Common examples of this are <a href="https://www.varnish-cache.org/">Varnish</a> and <a href="https://www.nginx.com/">Nginx</a>. These systems allow a website to update content and have the visitor's experience cached without the need for complex plugins storing the files locally and using up a user's disk space.

The Remote Cache Purger plugin sends a request to delete (aka flush) the cached data of a page or post to remote server(s).

= How It Works =

Manually

== Installation ==

No special instructions apply.

= Requirements =

* A server based proxy cache service (only Nginx for now) which responds to PURGE request.

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/remote-cache-purger)**

= Can I delete the entire cache? =

Yes and you can do it in multiple ways.

1. Click the Purge ALL Remote Cache in Top Bar
2. On Remote Caching Settings page, Console tab. Enter / or * and whole site cache will be purged

== Screenshots ==

There are no screenshots yet.