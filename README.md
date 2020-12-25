Contributors: myros

Tags: nginx, cache, purge, kubernetes

Requires at least: 4.7

Tested up to: 5.6

Stable tag: 1.0.0

Requires PHP

Source Code: https://github.com/myros/remote-cache-purger

License URI: http://www.gnu.org/licenses/gpl-3.0.html

### Wordpress Plugin - remote-cache-purger

#### Flushing Remote Cache On Multiple Servers

Remote Cache Purger will help you flushing cache on local and remote NGINX servers. 

It will work with:
* Host NGINX install
* Docker
* Docker Swarm
* Kubernetes

and any other accessible NGINX server

Prerequisite:


## Settings

### Enabled

Self explanatory

### Server IPs


Comma separated list of NGINX cache servers (their IP addresses). They can be publicly accessible addresses or internal IPs (Docker, Kubernentes, MetalLB).

Example:

205.204.88.xx, 178.21.23.xxx, 208.67.222.xxx

Internal:
10.10.10.6, 172.19.0.4

### Additional Domains

Some 
## Response

Normal responses are 200 and 412. If you're using GET method with purge path, in case cache is cleared, response status will be 200. If there is nothing to purge, response status will be 412 (NGINX).

## Purge Notice

If notice is not truncated, it will be displayed as this:

| Method       | Counter     | Url     |
| :-------------: | :----------: | ----------- |
|  PURGE | (X)   | http://localhost    |
|  PURGE | (X)   | http://localhost/hello-world    |
|  PURGE | (X)   | http://localhost/hello-world/feed    |

| Method       | Counter     | Url     |
| :-------------: | :----------: | ----------- |
|  GET | (X)   | http://localhost    |
|  GET | (X)   | http://localhost/hello-world    |
|  GET | (X)   | http://localhost/hello-world/feed    |


#### Explanation
* Method represents http mehtod used. It can be PURGE or GET, so if you're not using PURGE method, then GET method and purge path will be used.
* If there is return header set, X will contain number of cleared files. Won't be displayed if there is no response count header 
* URL -> url that has been purged

### What's next

If you have a additional idea or have a problem with plugin, report an issue.

In case you need help setting whole caching system send me an email ***myros.net at gmail.com*** or visit [myros.net](https://myros.net)