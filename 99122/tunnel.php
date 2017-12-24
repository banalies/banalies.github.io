<?php
ini_set('display_errors', 0);
//error_reporting(E_ALL & ~E_NOTICE);

require_once "lib/code_8.php";

class GTunnel {
	
	var $cookiePath;
	var $cookieFile;
	var $userAgent;
	var $timeout = 120;
	var $cookies = array();
	var $returnHeader = false;
	var $ip;
	
	function setUserAgent($userAgent) {
		$this->userAgent = $userAgent;
	}
	
	function getDefaultUserAgent() {
		return 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1) Gecko/20090624 Firefox/3.5';
	}
	
	function setReturnHeader($return) {
		$this->returnHeader = $return;
	}
	
	function setIP($ip) {
		$this->ip = $ip;
	}
	
	function getIP() {
		return $this->ip;
	}
	
	function getCookieFilename($userAgent) {
		
		$cookiePath = dirname(__FILE__).'/data/cookies';
		$cookieFile = 'cookie.txt';
		
		if(!file_exists($cookiePath)) {
			@mkdir($cookiePath);
		}
		
		if(file_exists($cookiePath) && is_writable($cookiePath)) {
			$cookieFile = $cookiePath.'/'.$cookieFile;
		} elseif(is_writable(dirname(__FILE__).'/data/'.$cookieFile)) {
			$cookieFile = dirname(__FILE__).'/data/'.$cookieFile;
		} else {
			$cookieFile = null;
		}
		return $cookieFile;
	}
	
	function init() {
		$this->userAgent = $this->userAgent ? $this->userAgent : $this->getDefaultUserAgent();
		$this->cookieFile = $this->getCookieFilename($this->userAgent);
	}
	
	function run() {
		
		$url = $this->getQueryParam("PGrabberGETURL", true);
		$returnHeader = $this->getQueryParam("PGrabberReturnHeader", true);
		
		if($this->getQueryParam("PGrabberCanSaveCookie", true)) {
			$cookieFilename = $this->getCookieFilename($_SERVER['HTTP_USER_AGENT']);
			echo $cookieFilename ? "YES" : "NO";
			exit();
		}
		
		$this->setUserAgent($_SERVER['HTTP_USER_AGENT']);
		$this->setReturnHeader($returnHeader ? true : false);
		$this->init();
		
		if($this->getQueryParam("is_cookie_writable", true)) {
			echo is_writable($this->cookieFile) ? 'YES' : 'NO';
			exit();
		}
		
		if($this->getQueryParam("reset_cookie", true)) {
			file_put_contents($this->cookieFile, "");
			exit();
		}
		
		if(!empty($url)) {
			if($this->isPost()) {
				$content = $this->request($url, $_POST);
			} else {
				$content = $this->request($url);
			}
			echo $content;
		} else {
			header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
		}
	}
	
	function isPost() {
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}
	
	function getQueryParam($key, $clean = false) {
		if(isset($_POST[$key])) {
			$value = $_POST[$key];
			if($clean) {
				unset($_POST[$key]);
			}
		} elseif(isset($_GET[$key])) {
			$value =  $_GET[$key];
			if($clean) {
				unset($_GET[$key]);
			}
		} else {
			$value = null;
		}
		return $value;
	}
	
	
	function arrayToString($array, $key = null) {
		if(is_array($array)) {
			$data = '';
			foreach ($array as $nkey=>$value) {
				if(!empty($key)) {
					$data .= $this->arrayToString($value, $key."[".$nkey."]");
				} else {
					$data .= $this->arrayToString($value, $nkey);
				}
			}
			return $data;
		}
		else {
			return $key."=".urlencode($array)."&";
		}
	}
	
	function curl_request($url, $cookieFile, $userAgent, $post = null ) {
		
		$current = curl_init ($url);

		curl_setopt($current, CURLOPT_URL, $url);
		curl_setopt($current, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($current, CURLOPT_HEADER, 1);
		curl_setopt($current, CURLOPT_USERAGENT, $userAgent);
		
		$host = strtolower(preg_replace('/^(?:http:\/\/)?(?:www\.)?([^\/]+).*$/', '\\1', $url));
		if($cookieFile && $host != 'google.ru' && $host != 'google.com') {
			curl_setopt($current, CURLOPT_COOKIEFILE, $cookieFile);
			curl_setopt($current, CURLOPT_COOKIEJAR, $cookieFile);
		}
		
		curl_setopt($current, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($current, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($current, CURLOPT_TIMEOUT, $this->timeout);
			
		if(!empty($post) && is_array($post)) {
			curl_setopt($current, CURLOPT_POST, 1);
			curl_setopt($current, CURLOPT_POSTFIELDS, $post);
		}
		
		if(($ip = $this->getIP()) != null) {
			curl_setopt($current, CURLOPT_INTERFACE, $ip);
		}
		
		$content = curl_exec($current);
		curl_close($current);
		
		return $content;
	}
	
	function socket_request($url, $userAgent, $post = null) {
		
		$response = null;
		
		//из $url надо вытащить имя хоста, имя дириктории, GET параметры
		if(preg_match('/^(?:http:\/\/)?((?:www\.)?[^\/?]+)([^?]*)?(.*)/i', $url, $matches)) {
			
			$host = $matches[1];
			$path = empty($matches[2]) ? '/' : $matches[2];
			$query =$matches[3];
			
			$fp = @fsockopen($host, 80, $errno, $errstr, $this->timeout);
            if ($fp) {
                if(!empty($post)) {
                	$data = $this->arrayToString($post);
					$length = strlen($data)-1;
					$data = substr($data, 0, $length);
					$header  = "POST ".$path.$query." HTTP/1.0\r\n";
					$header .= "Host: {$host}\r\n";
                	$header .= "User-Agent: ".$userAgent."\r\n";
                	$header .= "Accept: text/html\r\n";
                	$header .= "Connection: close\r\n";
					$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
					$header .= "Content-Length: ".$length."\r\n\r\n";
					$header .= $data."\r\n\r\n";
                } else {
                	$header  = "GET ".$path.$query." HTTP/1.0\r\n";
                	$header .= "Host: {$host}\r\n";
                	$header .= "User-Agent: {$userAgent}\r\n";
                	$header .= "Accept: text/html\r\n";
                	$header .= "Connection: close\r\n";
                	
                	//добавим Кукисы
                	$host = strtolower($host);
                	
                	if(empty($this->cookies) && ($host != 'google.ru') && ($host != 'google.com')) {
                		$this->cookies = $this->socket_get_cookie($url);
                	}
                	
                	if(!empty($this->cookies)) {
                		$cookiesTemplate = "Cookie: {cookie}\r\n";
                		$cookiesTEXT = "";
                		$cookiesLength = count($this->cookies);
                		$i = 1;
                		foreach ($this->cookies as $name=>$value) {
                			$cookiesTEXT .= $name.'='.urlencode($value);
                			if($i != $cookiesLength) {
                				$cookiesTEXT .= '; ';
                			}
                			$i++;
                		}
                		$header .= str_replace("{cookie}", $cookiesTEXT, $cookiesTemplate);
                	}
                	/* не забываем дописать в конце запроса \r\n, чтоб запрос заканчивался на \r\n\r\n,
                	 * иначе некоторые сервера будут ждать, пока в поток не запишут эти недостающие \r\n
                	 */
                	$header .= "\r\n";
                }
                //записываем данные
                @fputs($fp, $header, strlen($header));
                
                //и получаем ответ:
                while (!feof($fp)) {
                    $response .= fread($fp, 1024);
                }
                fclose($fp);
            }
		}
		if(strpos($response, "Set-Cookie:") !== false) {
			$this->socket_save_cookie($response,$host);
		}
		return $response;
	}
	
	function socket_get_cookie($url) {
		
		$cookie = null;
		
		if(preg_match('/^(?:http:\/\/)?((?:www\.)?[^\/?]+)([^?]*)?(.*)/i', $url, $matches)) {
			$host = $matches[1];
			$path = empty($matches[2]) ? '/' : $matches[2];
			$query =$matches[3];

			if(file_exists($this->cookieFile)) {
				$content = file_get_contents($this->cookieFile);
				$lines = explode("\r\n", $content);
				$cookiesArray = array();
				foreach ($lines as $line) {
					if(!empty($line) && $line[0] !== '#') {
						$cookiesArray[] = explode("\t", $line);
					}
				}
				
				foreach($cookiesArray as $key=>$data) {
					if($data[0][0] == '.') {
						if(!preg_match('/'.preg_quote(substr($data[0], 1), '/').'$/i', $host)) {
							unset($cookiesArray[$key]);
						}
					} else {
						if(!preg_match('/^'.preg_quote($data[0], '/').'$/i', $host)) {
							unset($cookiesArray[$key]);
						}
					}
				}
					
				if(!empty($cookiesArray)) {
					$cookiesArray = array_values($cookiesArray);
					$cookiesArrayKV = array();
					$total = count($cookiesArray);
					foreach ($cookiesArray as $key=>$data) {
						$cookiesArrayKV[$data[5]] = $data[6];
					}
					$cookie = $cookiesArrayKV;
				}
			}
		}
		
		return $cookie;
	}
	
	function socket_save_cookie($content, $domain) {
		//парсим кукисы, что получили сейчас в заголовке
		$headersText = substr($content, 0, strpos($content, "\r\n\r\n"));
		preg_match_all('/Set-Cookie:\s*(.*?)\r\n/si', $headersText, $matches);
		
		$cookieList = array();
		foreach ($matches[1] as $str) {
			$data = explode("; ", $str);
			
			$kv = explode("=", $data[0]);
			$cookie = array(
				'key' => $kv[0],
				'value' => $kv[1]
			);
			foreach ($data as $element) {
				$kv = explode("=", $element);
				$cookie[strtolower($kv[0])] = $kv[1];
			}
			
			if(!isset($cookie['path']))  $cookie['path'] = '/';
			if(!isset($cookie['expires']))  $cookie['expires'] = time();
			if(!isset($cookie['domain']))  $cookie['domain'] = $domain;
			
			$cookieList[] = $cookie;
		}
		
		
		//уже сохранённые кукисы:
		$cookiesArray = array();
		if(file_exists($this->cookieFile)) {
			$content = file_get_contents($this->cookieFile);
			$lines = explode("\r\n", $content);			
			foreach ($lines as $line) {
				if(!empty($line) && $line[0] !== '#') {
					$cookiesArray[] = explode("\t", $line);
				}
			}
		}
		
		foreach ($cookieList as $cookieListItem) {
			$exists = false;
			foreach ($cookiesArray as $k=>$cookiesArrayItem) {
				if(strtolower($cookieListItem['domain']) == strtolower($cookiesArrayItem[0]) && $cookieListItem['key'] == $cookiesArrayItem[5]) {
					$exists = true;
					$cookiesArray[$k][4] = strtotime($cookieListItem['expires']);
					$cookiesArray[$k][6] = strtotime($cookieListItem['value']);
				}
			}
			if(!$exists) {
				$cookiesArray[] = array(
					$cookieListItem['domain'],
					'TRUE',
					$cookieListItem['path'],
					'FALSE',
					strtotime($cookieListItem['expires']),
					$cookieListItem['key'],
					$cookieListItem['value']
				);
			}
		}
		
		if(!empty($cookiesArray)) foreach ($cookiesArray as $key=>$array) {
			$cookiesArray[$key] = implode("\t", $array);
		}
		
		file_put_contents($this->cookieFile, implode("\r\n", $cookiesArray));
		chmod($this->cookieFile, 0777);
	}
	
	 function request($url, $post = null, $recursion = 0) {
	 	
		@ini_set('allow_url_fopen', 1);
        @ini_set('default_socket_timeout', $this->timeout);
        @ini_set('user_agent', $this->userAgent);
        
	 	//если есть возможность использования curl:
	 	if(function_exists('curl_init')) {
	 		$content = $this->curl_request($url, $this->cookieFile, $this->userAgent, $post);
	 	} else {
	 		//работаем через сокеты:
	 		$content = $this->socket_request($url, $this->userAgent, $post);
	 	}
		
		//не нужен нам FOLLOWLOCATION. Напишем его САМИ :)
		$pos = strpos($content, "\r\n\r\n");
		
		$headersText = substr($content, 0, $pos);
		$body = substr($content, $pos + 4);
		$headers = explode("\r\n", $headersText);
				
		if(preg_match('/HTTP\/\d\.\d 3../', $headers[0]) && ($recursion < 5)) {
			foreach ($headers as $header) {
				$matches = array();
				
				//кукисы
				if(strtolower(substr($header, 0, 11)) == 'set-cookie:') {
					//установим куку:
					$cookie = trim(substr($header, 11));
					$cookie = explode("; ", $cookie);
					$key = substr($cookie[0], 0, strpos($cookie[0],'='));
					$value = str_replace($key.'=', '', $cookie[0]);
					if(is_string($key) && !empty($value)) {
						$this->cookies[$key] = $value;
					}
				}
				
				//Location:
				if(preg_match('/^Location:\s*(.+)$/i', $header, $matches)) {
					if($matches[1][0]=='/') {
						$matches[1] = preg_replace('/^(?:http:\/\/)?(?:www\.)?([^\/]+)/', '$1', $url).$matches[1];
					}
					return $this->request($matches[1], null, $recursion + 1);
				}
			}
		}	else {
			foreach ($headers as $header) {
				if(strpos($header, "Content-Type") !== false) {
					header($header, true);
				}
				if(strpos($header, "HTTP") === 0 ) {
					header($header, true);
				}
			}
		}
		
		//получение кукисов для Яндекс.Вордстат
		if(preg_match('/src="(http:\/\/(?:\w+\.)?captcha\.yandex\.net\/image\?key=[^"]+)"/mi', str_replace("\n", "", $content), $matches) && $recursion < 2) {
			$this->request($matches[1], null, $recursion + 1);
			$this->request("http://kiks.yandex.ru/su/", null, $recursion + 1);
			return $this->request($url, $post, $recursion + 1);
		}
		return $body;
	}
}

$ip = isset($_GET['ip']) ? $_GET['ip'] : null;

$tunnel = new GTunnel();
if($ip) {
	$tunnel->setIP($ip);
}
$tunnel->run();