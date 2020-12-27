<?php
/**
* @since 1.0
*/
namespace RemoteCachePurger;

/**
* @since 1.0
*/
class Queue {
  const NAME = 'remote-cache-purger-queue';

  private $plugin = null;

  private $purgeAll = false;
  private $queue = array();
  private $responses = array();
  private $version = '1.0.3';
  private $userAgent = 'cURL WP Remote Cache Purger ';
  
  // public $noticeMessage = '';

  /**
   * @since 1.0.1
  */
  public function __construct() {
    $this->plugin = Main::getInstance();
  }

  /**
	 * Add new URL to purge queue. URLs in queue will be purged on purge method call
   * 
	 * @param string $URL
	 *
	 * @return $this
   * 
   * @since 1.0.1
  */
  public function addURL($url){
    
    if ( home_url() . '/.*' == $url || '/.*' == $url ) {
      $this->queue    = array();
			$this->purgeAll = true;
    }
   
    $this->writeLog('addURL', 'Added URL => ' . $url );

    $this->queue[] = $url;

  }

  /**
	 * Execute queue
   * 
	 * @param string $URL
	 *
	 * @return $this
   * 
   * @since 1.0.1
  */
  public function commitPurge($serverIP) {

    $this->writeLog('commitPurge', 'Start purging server ' . $serverIP);

    if ( ! $this->queue ) {
      return false;
    }

    $ip = trim($serverIP);
    $mh = curl_multi_init(); // cURL multi-handle
    $requests = array(); // This will hold cURLS requests for each file
    $this->responses = [];

    $options = array(
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER    => true, 
        CURLOPT_USERAGENT      => $this->userAgent(),
        // CURLOPT_HEADER         => true,
        // CURLOPT_NOBODY          => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 0,
        CURLOPT_TIMEOUT => 10, //timeout in seconds
        CURLOPT_VERBOSE => false
    );
    
    $this->queue = array_unique($this->queue);
    sort($this->queue);

    foreach($this->queue as $key => $url) {
      $this->writeLog('commitPurge', 'URL ' . $url);
      $parsedUrl = $this->parseUrl($url);
      $port = $parsedUrl['port'];
      $host = $parsedUrl['host'];
        
      // $fullUrl = $parsedUrl['fullUrl']; # $schema . '://' . $host . $this->plugin->optPurgePath . $path;
      
      $this->writeLog('commitPurge', 'Calling URL ' . $parsedUrl['fullUrl']);

      $handle = curl_init($parsedUrl['fullUrl']);
      $array_key = (int) $handle;
      $requests[$array_key]['curl_handle'] = $handle;

      $requests[$array_key]['url'] = $parsedUrl['url'];
      $requests[$array_key]['method'] = 'GET';

      $this->responses[$array_key] = array(
          'url' => $parsedUrl['fullUrl'],
          'ip' => $ip
      );
      
      // Set cURL object options
      curl_setopt_array($handle, $options);
      
      if ($this->plugin->optUsePurgeMethod) {
        $requests[$array_key]['method'] = 'PURGE';
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "PURGE");
      }

      curl_setopt($handle, CURLOPT_RESOLVE, array(
          "{$host}:{$port}:{$ip}",
      ));

      if ($this->plugin->optResponseCountHeader) {
        curl_setopt($handle, CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));
      }

      // Add cURL object to multi-handle
      curl_multi_add_handle($mh, $handle);
    }
      
    // Do while all request have been completed
    do {
      curl_multi_exec($mh, $running);
    } while ($running);
      
    $this->plugin->noticeMessage .= '<br/>SERVER: ' . $ip . '<br/>';
      
    foreach ($requests as $key => $request) {
      $this->responses[$key]['HTTP_CODE'] = curl_getinfo($request['curl_handle'], CURLINFO_HTTP_CODE);
      curl_multi_remove_handle($mh, $request['curl_handle']); //assuming we're being responsible about our resource management
    }
      
    curl_multi_close($mh);

    if ($this->plugin->optTruncateNotice) {
      $this->plugin->noticeMessage .= 'Purged';
    }
    else {
      foreach($this->responses as $key => $response) {

        $headerPresent = isset($response['headers'][$this->plugin->optResponseCountHeader]);

        if ($headerPresent) {
          $purgedCount = $response['headers'][$this->plugin->optResponseCountHeader];
        }
        $isCleared = $headerPresent && isset($purgedCount) && $purgedCount > 0;

        if($isCleared) {
          $this->plugin->noticeMessage .= '<strong>';
        }

        $this->plugin->noticeMessage .= $requests[$key]['method'];
        
        if($headerPresent) {
          $this->plugin->noticeMessage .= '(' . $response['headers'][$this->plugin->optResponseCountHeader] . ')';
        }

        $this->plugin->noticeMessage .= ' | ' . $response['HTTP_CODE'] . ' | ' .  $response['url'];

        if($isCleared) {
          $this->plugin->noticeMessage .= '</strong>';
        }

        $this->plugin->noticeMessage .= '<br/>';

      }
    }

    $this->writeLog('commitPurge', 'End');

    return true;
  }

  /**
	 * Purge URL
   * 
	 * @param string $URL
	 *
	 * @return $this
   * 
   * @since 1.0.1
  */
  public function purge($url) {

  }

  /**
	 * Parse URL
   * 
	 * @param string $URL
	 *
	 * @return parseUrl
   * 
   * @since 1.0.1
  */
  private function parseUrl($url) {
    $parsedUrl = wp_parse_url( $url );
    $parsedHome  = wp_parse_url( home_url() );

		if ( ! isset( $parsedUrl['scheme'] ) or ! $parsedUrl['scheme'] ) {
			$parsedUrl['scheme'] = $parsedHome['scheme'];
    }
    
    if ( ! isset( $parsedHome['path'] ) or ! $parsedHome['path'] ) {
			$parsedHome['path'] = '/';
		}
    
		if ( ! isset( $parsedUrl['path'] ) or ! $parsedUrl['path'] ) {
      $parsedUrl['path'] = $parsedHome['path'];
		}
    
    if ( ! isset( $parsedUrl['port'] ) or ! $parsedUrl['port'] ) {
      $parsedUrl['port'] = $parsedHome['scheme'] == 'https' ? '443' : '80';
    }

    $query = ( isset( $parsedUrl['query'] ) and $parsedUrl['query'] ) ? '?' . $parsedUrl['query'] : '';
    
    $fullUrl = $parsedUrl['scheme'] . '://' . $parsedHome['host'];
    $fullUrl .= ($this->plugin->optUsePurgeMethod) ? $parsedUrl['path'] : $this->plugin->optPurgePath . $parsedUrl['path'];
    $fullUrl .= $query;


    $this->writeLog('addURL', 'Added URL => ' . $url );

    return [
      'url' => $url,
      'fullUrl' => $fullUrl,
      'host' => $parsedHome['host'],
      'path' => $parsedUrl['path'],
      'port' => $parsedUrl['port'],
      'scheme' => $parsedUrl['scheme'],
      'query' => $query
    ];
  }

  /**
  * @since 1.0.1
  */
  public function headerCallback($ch, $header_line)
  {
      $_header = trim($header_line);
      $colonPos= strpos($_header, ':');
      if($colonPos > 0)
      {
          $key = substr($_header, 0, $colonPos);
          $val = preg_replace('/^\W+/','',substr($_header, $colonPos));
          $this->responses[$this->getKey($ch)]['headers'][$key] = $val;
      }
      return strlen($header_line);
  }
  
  /**
  * @since 1.0.1
  */
  public function getKey($ch)
  {
      return (int)$ch;
  }

  /**
   * @since 1.0.1
  */
  private function writeLog($method, $message) {
      $this->plugin->write_log('Queue', $method, $message);
  }

  /**
  * @since 1.0
  */
  private function userAgent()
  {
      return $this->userAgent . "1.0.4.4";
  }
}