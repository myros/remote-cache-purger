# remote-cache-purger
Contributors: myros

Tags: nginx, cache, purge, kubernetes

Requires at least: 4.7

Tested up to: 5.6

Stable tag: 1.0.0

Requires PHP: 5.6

Source Code: https://github.com/myros/remote-cache-purger

License URI: http://www.gnu.org/licenses/gpl-3.0.html


### Wordpress Plugin - Flushing Remote Cache 


## Response

Normal responses are 200 and 412. If you're using GET method with purge path, in case cache is cleared, response status will be 200. If there is no cache to clean, response status will be 412.

## Purge Notice

If notice is not truncated, it will be displayed as this:

| Method       | Counter     | Url     |
| :------------- | :----------: | ----------- |
|  PURGE | (X)   | http://localhost    |
|  PURGE | (X)   | http://localhost/hello-world    |
|  PURGE | (X)   | http://localhost/hello-world/feed    |


#### Explanation
* Purge represents PURGE http mehtod. If you're not using PURGE method, then purge path will be used.
* If there is return header set, X will contain number of cleared files
* URL -> url that has been purged