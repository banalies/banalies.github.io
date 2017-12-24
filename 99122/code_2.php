<?php

class Combiner {
	
	var $version = '4.42';
	
	/**
	 * @var Linkator
	 */
	var $lkr;
	var $dataPath;
	var $lkrSettingsPath;
	var $lkrArticlesDbFilename;
	var $lkrPlacenumber = 0;
	var $allowedExternalHosts = array();
	var $allowedPages = array();
	var $debug = false;
	var $settings;
	var $isArticle = false;
	var $articles = array();
	var $categories = array();
	var $yandexLogFilename;
	var $phrases = array();
	var $phrasesIgnore = array();
	var $epas = null;
	var $skniltes;
	var $kniltsurt;
	var $deefknil;
	var $serverName;
	var $allowStart;
	var $REQUEST_URI;
	var $charset;
	var $mainlink;
	var $pageHasLinks = false;	//флаг устанавливается в true если на открытой странице есть ссылки
	var $gz = false;
	var $workTime;	//сколько времени работал скрипт
	var $errors = array(); 	//ошибки выполенния скрипта
	var $curDir = null;
	var $blocksCountFound = 0;
	var $mbStringCharset = null;
	var $currentLocale = null;
	/**
	 * 
	 * @var MEGAINDEX_client
	 */
	var $megaindex;
	
	
	function Combiner($dataDir = null) {
		
		$this->curDir = dirname(__FILE__);
		$this->serverName = $this->getServerName();
        /**
         * Некоторые CMS, такие, как Joomla, модифицируют переменную $_SERVER['REQUEST_URI'],
         * поэтому нам надо сохранить её первоначальное значение,
         * Ещё есть плагин vBSeo для Bulletin который тоже меняет URL
         */
        $this->REQUEST_URI = isset($_SERVER['VBSEO_URI']) ? $_SERVER['VBSEO_URI'] : $_SERVER['REQUEST_URI'];
		$this->workTime = microtime(true);
		
		$this->dataPath = $dataDir ? $dataDir : $this->curDir . '/data';
		$this->checkDatadir($this->dataPath);
		$this->lkrSettingsPath = $this->dataPath.'/settings.db';
		$this->lkrArticlesDbFilename = $this->dataPath.'/articles.db';
		$this->yandexLogFilename = $this->dataPath.'/ya.log';
		
		//загружаем настройки
		$this->loadSettings();
		
		/* @TODO задать через сервер лкр */
		$this->setSettings('skniltes_user_id', 12970);
		
		//Если Линкатор отключен на данной странице, то и делать ничего в конструкторе не надо
		if(($this->allowStart = $this->iCanRunOnThisPage()) != false) {
			
			$this->debug = $this->isLinkatorBot();
			
			if($this->debug) {
				ob_start();
				ini_set("display_errors", 1);
				error_reporting(E_ALL & ~E_NOTICE);
			}
			
			//загружаем биржи
			$this->bootstrap();
			
			//Логирование Яндекса
			$this->processYandexRobot();
			
			if($this->debug) {
				$startupContent = ob_get_clean();
				if($startupContent) {
					$this->logError($startupContent);
				}
			}
			
			//показать карту сайта 
			$this->processSitemapXML();
			//обработка статей:
			$this->processArticles();
			
			//Если мы нашли статью, то заканчиваем работу скрипта.
			if($this->isArticle) exit();
		}
		
		$this->workTime = microtime(true) - $this->workTime;
	}
	
	function checkDataDir($dirname) {
		if(!file_exists($dirname)) {
			$this->logError("Data directory $dirname not exists");
		} elseif(!$this->is__writable($dirname)) {
			$this->logError("Data directory $dirname is not writable for PHP scripts");
		}
	}
	
	function getServerName() {
		$sn = $_SERVER['SERVER_NAME'];
		$sn = str_replace("www.", "", $sn);
		if(($pos = strpos($sn, ':')) != false) {
			$sn = substr($sn, 0, $pos);
		}
		$sn = strtolower($sn);
		return $sn;
	}
	
	function loadSettings() {
		
		require_once $this->curDir.'/lib/code_8.php';

		if(file_exists($this->lkrSettingsPath)) {
			$settings = unserialize(file_get_contents($this->lkrSettingsPath));
	
			if(is_array($settings)) {
				$this->settings = $settings;	
				$this->allowedExternalHosts = isset($settings['noindex_open_hosts']) ? $settings['noindex_open_hosts'] : array();
				$this->allowedPages = isset($settings['noindex_open_pages']) ? $settings['noindex_open_pages'] : array();
	
				if(isset($settings['links_phrases']) && !empty($settings['links_phrases'])) {
					foreach ($settings['links_phrases'] as $phrase) {
						if($phrase['ignore'] == true) {
							$this->phrasesIgnore[] = $phrase;
						} else {
							$this->phrases[] = $phrase;
						}
					}
				}
			}
		}
	
		$this->allowedExternalHosts[] = preg_replace('/^(?:https?:\/\/)?(?:www\.)?([^\/]+).*$/', '$1', $this->serverName);
	
		if(!isset($this->settings['mainlink_enable'])) {
			$this->settings['mainlink_enable'] = false;
		}
		if(!isset($this->settings['kniltsurt_enable'])) {
			$this->settings['kniltsurt_enable'] = false;
		}
		if(!isset($this->settings['skniltes_enable'])) {
			$this->settings['skniltes_enable'] = false;
		}
		$this->settings['skniltes_cachedir'] = $this->curDir.'/data/';
		if(!isset($this->settings['epas_enable'])) {
			$this->settings['epas_enable'] = false;
		}
		if(!isset($this->settings['deefknil_enable'])) {
			$this->settings['deefknil_enable'] = false;
		}
		if(!isset($this->settings['url_case_transformation'])) {
			$this->settings['url_case_transformation'] = 'none';
		}
	}
	
	function iCanRunOnThisDomain() {
		$domain = strtolower($this->getSettings('url'));
		return empty($domain) || ($domain == $this->serverName);
	}
	
	/**
	 * Установить текущую локаль в русскую однобайтовую
	 */
	function setLocale() {
		if($this->getSettings("set_win_locale")) {
			$this->currentLocale = setlocale(LC_ALL, 'ru_RU.CP1251', 'rus_RUS.CP1251', 'Russian_Russia.1251');
		}
	}
	
	/**
	 * Проверяет домен, на котором запускается скрипт (на соответствие заданному в настройках).
	 * Проверяет страницу на наличие в списке запрещенных для включения
	 * @return boolean
	 */
	function iCanRunOnThisPage() {
		
		if($this->iCanRunOnThisDomain()) {
				
			if(($pages = $this->getSettings('stop_pages_list')) != null) {
				foreach ($pages as $url) {
					//$url уже обработан функцией preg_quote на стороне сервера, при сохранении настроек на текущий сайт
					if(preg_match('/^'.$url.'$/i', $this->REQUEST_URI)) {
						return false;
					}
				}
			}
			return true;
		} else {
			return false;
		}
	}
	
	function getSettings($key = null) {
		if($key) {
			if(isset($this->settings[$key])) {
				return $this->settings[$key];
			} else {
				return null;
			}
		} else {
			return $this->settings;
		}
	}
	
	function setSettings($key, $value) {
		$this->settings[$key] = $value;
	}
	
	function processSitemapXML() {
		if($this->getSettings('sitemap_enable')) {
			$sitemapURL = $this->getSettings('sitemap_url');
			if(!empty($sitemapURL) && $this->REQUEST_URI == $sitemapURL) { 
				$siteMapFilename = $this->dataPath . '/sitemap.xml';
				if(file_exists($siteMapFilename)) {
					$xml = file_get_contents($siteMapFilename);
					header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
					header("Content-type: text/xml; charset=utf-8");
					echo $xml;
					exit();
				}
			}
		}
	}
	
	function bootstrap() {
		
		/* иногда для функция preg_match не хватает лимита, поэтому мы его установим сами */
		ini_set('pcre.backtrack_limit',1000000);
		
		$options = array(
				'host' => $this->serverName,
				'force_show_code' => true,
				'charset' => 'cp1251',
				'data_path' => $this->dataPath,
				'request_uri' => $this->REQUEST_URI
		);
		
		if($this->getSettings('sape_link_wrapper')) {
			$options['_link_wrapper'] = $this->getSettings('sape_link_wrapper');
		}
		if(($fetchType = $this->getSettings('fetch_remote_type')) != null) {
			if($fetchType != 'auto') {
				$options['fetch_remote_type'] = $fetchType;
			}
		}
		
		$this->bootstrapSkniltes($options);
		
		$this->bootstrapKniltsurt($options);
		
		$this->bootstrapMainlink();
		
		$this->bootstrapLinkator($options);
		
		$this->bootstrapEpas($options);
		
		$this->bootstrapDeefknil($options);
		
		$this->bootstrapMegaindex($options);
	}
	
	function bootstrapSkniltes($options) {
		if($this->getSettings('skniltes_enable')) {
				
			require_once($this->curDir.'/lib/code_14.php');
				
			$skniltesConfig = new LSConfig();
			$skniltesConfig->userId = $this->getSettings('skniltes_user_id');
			$skniltesConfig->password = $this->getSettings('skniltes_password');
			$skniltesConfig->cachedir = $this->dataPath.'/';
			$skniltesConfig->show_comment = true;
			$skniltesConfig->_link_wrapper = $this->getSettings('sape_link_wrapper');
			if(isset($options['fetch_remote_type'])) {
				switch ($options['fetch_remote_type']) {
					case 'curl':
						$skniltesConfig->connecttype = 'CURL';
						break;
					case 'socket':
						$skniltesConfig->connecttype = 'SOCKET';
						break;
					default:
						$skniltesConfig->connecttype = null;
				}
			}
			$skniltesConfig->use_safe_method = true;
			$uri = $this->transformUri($this->REQUEST_URI);
			$this->skniltes = new LinkatorLSClient($uri, $skniltesConfig);
		}
	}
	
	function bootstrapKniltsurt($options) {
		if($this->getSettings('kniltsurt_enable')) {
			define('KNILTSURT_USER', $this->getSettings('f3'));
			require_once $this->curDir.'/lib/code_11.php';
			$options['use_cache'] = false;
			$this->kniltsurt = new LinkatorKniltsurtClient($options);
			if($this->debug) {
				$this->kniltsurt->tl_test=true;
				$this->kniltsurt->tl_isrobot=true;
				$this->kniltsurt->tl_verbose = true;

			}
		}
	}
	
	function bootstrapMainlink() {
		if($this->getSettings('mainlink_enable')) {
			require_once($this->curDir.'/lib/code_17.php');
			$this->mainlink = new ML($this->getSettings('mainlink_secure'));
			$this->mainlink->Set_Config(array(
					'cache_base' => $this->dataPath,
					'charset' => 'win',
					'use_cache' => true
			));
		}
	}
	
	function bootstrapLinkator($options) {
		require_once($this->curDir.'/lib/code_12.php');
		$options['articles_indexpage_url'] = $this->getSettings("articles_indexpage");
		$options['template'] = $this->getSettings("lk_template");
		
		$this->lkr = new Linkator($options);
		/**
		 * @TODO
		 * $this->lkr->setArticlesIndexpageTitle($this->getSettings("articles_indexpage_title");
		 */
		
		$url = str_replace('//', '', "/".str_replace($_SERVER['DOCUMENT_ROOT'], "", $this->curDir));
		$this->lkr->setBannersBaseURL($url.'/data/banners');
	}
	
	function bootstrapEpas($options) {
		if($this->getSettings('epas_enable')) {
			$options['request_uri'] = $this->transformUri($this->REQUEST_URI);
			if($this->debug) {
				$options['debug'] = true;
			}
			define('_EPAS_USER', $this->getSettings('f1'));
			require_once($this->curDir.'/lib/code_6.php');
			$this->epas = new LinkatorEpas($options);
		}
	}
	
	function bootstrapDeefknil($options) {
		if($this->getSettings('deefknil_enable')) {
			$options['request_uri'] = $this->transformUri($this->REQUEST_URI);
			define('DEEFKNIL_USER', $this->getSettings('f2'));
			require_once $this->curDir.'/lib/code_4.php';
			$this->deefknil = new LinkatorDeefknil($options);
		}
	}
	
	function bootstrapMegaindex($options) {
		if($this->getSettings('megaindex_enable')) {
			$options['data_filename'] = 'megaindex.links.db';
			$options['charset'] = 'windows-1251';
			$options['link_wrapper'] = $this->getSettings("megaindex_link_wrapper");
			define('_MEGAINDEX_USER', $this->getSettings('megaindex_code'));
			require_once $this->curDir.'/lib/code_16.php';
			$this->megaindex = new MEGAINDEX_client($options);
		}
	}
	
	/**
	 * Функция обрабатывает Линкаторные статьи.
	 * @return boolean : true, если статья найдена, false если нет.
	 */
	function processArticles() {
		//если у нас размещаются статьи
		if($this->getSettings('enable_articles')) {
			
			/* RSS для статей */
			if(($rssURL = $this->getSettings("rss_url")) != null) {
				if($rssURL == $this->REQUEST_URI) {
					require_once $this->curDir . '/lib/code_19.php';
					$builder = new RssBuilder();
					
					$articles = $this->lkr->getRssArticles();
					
					$channelTitle = $this->getSettings("rss_channel_title");
					$channelTitle = $channelTitle ? $channelTitle : "Новости сайта ".$_SERVER['SERVER_NAME'];
					
					$channelLink = 'http://' . $_SERVER['SERVER_NAME'].'/';
					$channelDescription = $this->getSettings("rss_channel_description");
					$channelDescription = $channelDescription  ? $channelDescription : $channelTitle;
					
					$entries = array();
					
					foreach($articles as $article) {
						
						$link = $article['link'];
						if($link[0] != '/') {
							$link = $this->getSettings("articles_indexpage") . $link;
						}
						$link = 'http://'.$_SERVER['SERVER_NAME'] . $link;
						
						$entries[] = array(
							'title' => iconv('cp1251', 'utf-8', $article['name']), 
							'link' => iconv('cp1251', 'utf-8', $link), 
							'pubDate' => $article['date'],
							'description' => iconv('cp1251', 'utf-8', $article['annotation']), 
							'yandex:full-text' => iconv('cp1251', 'utf-8', $article['text'])
						);
					}
					
					$channel = array(
						'title' => iconv("cp1251", "utf-8", $channelTitle), 
						'link' => iconv("cp1251", "utf-8", 'http://'.$_SERVER['SERVER_NAME'].'/'),
						'description' => iconv("cp1251", "utf-8", $channelDescription),
						'image' =>  iconv("cp1251", "utf-8", $this->getSettings("rss_channel_image")),
						'entries' => $entries
					);

					header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
					header("Content-type: text/xml; charset=utf8");
					echo $builder->rss($channel);
					exit();
				}
			}
			
			//сначала проверка на индексную страницу со статьями
			$indexPage = $this->getSettings('articles_indexpage');
			if(!empty($indexPage) && $this->REQUEST_URI == $indexPage) {
				//главаня статей
				$this->isArticle = true;
				header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
				$this->lkr->setArticlesIndexpageTitle($this->getSettings('articles_indexpage_title'));
				echo $this->replace_content($this->lkr->getArticlesHTMLCode());
				return true;
			} else {
			
				//поиск в списке категорий:
				foreach ($this->lkr->getArticleCategoris() as $category ) {
					if($category['link'] == $this->REQUEST_URI) {
						$this->isArticle = true;
						header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
						echo $this->replace_content($this->lkr->getArticlesByCategory($category['id']));
						return true;
					}
				}
				
				//поиск в списке статей
				foreach ($this->lkr->getArticles() as $article) {
					if($article['link'] == $this->REQUEST_URI) {
						$this->isArticle = true;
						header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
						echo $this->replace_content($this->lkr->getArticleHTMLCode($article['id']));
						return true;
					}
				}
			}
		}
		return false;
	}
	
	//------------логирует появление яндекса на страницах сайта--------------
	function processYandexRobot() {
		if($this->getSettings('enable_yandex_logging') && $this->isYandexBot()) {
			$fname = $this->yandexLogFilename;
			$f = fopen($fname, "a");
			if($f) {
				fwrite($f, time()."\t".$_SERVER['HTTP_USER_AGENT']."\t".$this->REQUEST_URI."\r\n");
				fclose($f);
			} else {
				$this->logError("Can't create file $fname for yandex log");
			}
		}
	}
	
	function isYandexBot() {
		return preg_match('/yandex|YaDirect/i', $_SERVER['HTTP_USER_AGENT']);
	}
	
	/**
	 * Функция ищет в тексте внешние ссылки и если они не разрешены, то закрывает в <noindex>
	 *
	 * @param string $content
	 * @return string
	 */
	function noindex($content) {
		//сначала проверяем на старницу. Может, не надо тут ничего делать:
		$useNOINDEX = true;
				

		if(!empty($this->allowedPages)) foreach ($this->allowedPages as $page) {
			if(preg_match('/^'.$page.'$/', $this->REQUEST_URI)) {
				$useNOINDEX = false;
				break;
			}
		}
		
		if($useNOINDEX) {
			$clearContent = preg_replace('/<noindex>.*<\/noindex>/iUs', '', $content);
			$clearContent = preg_replace('/<!--noindex-->.*<!--\/noindex-->/iUs', '', $content);
			$clearContent = preg_replace('/<!--openlinks-->.*?<!--\/openlinks-->/iUs', '', $clearContent);
			$clearContent = preg_replace('/<!--.*-->/iUs', '', $clearContent);
			$clearContent = preg_replace('/<script.*<\/script>/iUs', '', $clearContent);			
			
			//новое регулярное выражения для парсинга ссылок. Более короткое и более эффективное. версия лкр 3.3 Если не будет проблем, то оставляем его
			preg_match_all('/<a[^>]*href\s*=\s*["\']?([^\s>"\']*)["\']?[^>]*>(.*?)<\/a>/is', $clearContent, $matches);
			
			$total = count($matches[0]);
			
			$replaces = array(
				'from' => array(),
				'to' => array()
			);
			
			for ($i = 0; $i<$total; $i++) {
				
				$url = $matches[1][$i];
				
				//определить, внешняя ссылка или внутренняя
				if(preg_match('/^https?:\/\/.*$/is', $url)) {
					$url = preg_replace('/^(?:http:\/\/)?(?:www\.)?([^\/]+).*$/', '$1', $url);
					//все внешние урлы, которые не разрешены, надо занести в ноуиндекс
					if(!in_array($url,$this->allowedExternalHosts)) {
						//Костыль для счётчика mail.ru:
						if(strpos($matches[0][$i], "top.mail.ru") !== false) {
							$linkHTML = '<noindex>'.$matches[0][$i].'</noindex>';
						} else {
							$linkHTML = '<!--noindex-->'.$matches[0][$i].'<!--/noindex-->';
						}
						
						
						//если у ссылки нет rel="nofollow", то надо вставить:
						if(!preg_match('/rel\s*=\s*[\'"]?nofollow[\'"]?/i', $linkHTML)) {
							$pos = strpos(strtolower($linkHTML),"<a");
							$linkHTML = substr($linkHTML, 0, $pos).'<a rel="nofollow"'.substr($linkHTML, $pos+2);
						}
						
						if(!in_array($matches[0][$i], $replaces['from'])) {
							$replaces['from'][] = $matches[0][$i];
							$replaces['to'][] = $linkHTML;
						}
					}
				} else {
					//внутренняя:
					/*if(!in_array($_SERVER['SERVER_NAME'],$this->allowedExternalHosts)) {
						$content = str_replace($matches[0][$i], '<noindex>'.$matches[0][$i].'</noindex>', $content);
					}*/
				}
			}
			
			if(!empty($replaces['from'])) {
				$content = str_replace($replaces['from'], $replaces['to'], $content);
			}
		}
		return $content;
	}
	
	function isLinkatorBot() {
		$REMOTE_ADDR = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
		$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
		return  $userAgent && preg_match('/Linkator-robot/', $userAgent) && in_array($REMOTE_ADDR, explode("\n", $this->getSettings('LINKTRADE_IPS')));
	}
	
	function insertAfterBody($content, $statement) {
		$lowerContent = strtolower($content);
		$start = strpos($lowerContent, "<body");
		if($start !== false) {
			$start = strpos($lowerContent, ">", $start);
			if($start !== false) {
				$content = $this->substr_replace($content, $statement, $start+1, 0);
			}
		} else {
			$content = $statement.$content;
		}
		return $content;
	}
	
	function insertBeforeBodyClose($content, $statement) {
		$start = strpos(strtolower($content), "</body>");
		if($start !== false) {
			$content = $this->substr_replace($content, $statement, $start, 0);
		}
		return $content;
	}
	
	/**
	 * Стандартная функция substr_replace некорректно работает с многобайтными строками
	 * @param string $output - искомая строка
	 * @param string $replace - на что будем заменять
	 * @param integer $posOpen - с какого символа начнем
	 * @param integer $posClose - длина замены
	 * @return string
	 */
	function substr_replace($output, $replace, $posOpen, $posClose) {
		
		return substr($output, 0, $posOpen).$replace.substr($output, $posOpen + $posClose);
		//нихера это не работает.
		/* if(function_exists('mb_substr')) {
			return mb_substr($output, 0, $posOpen).$replace.mb_substr($output, $posOpen + $posClose+1);
		} else {
			return substr($output, 0, $posOpen).$replace.substr($output, $posOpen + $posClose+1);
		} */
	}
	
	function logError($message) {
		$this->errors[] = $message;
	}
	
	/**
	 * Замена нативной функции is_writable, которая некорректно работает на Windows платформах
	 * @param string $path - обязательно закончить слешем, если это дириктория
	 * @return boolean
	 */
	function is__writable($path) {
	
		if ($path{strlen($path)-1}=='/') // recursively return a temporary file path
			return $this->is__writable($path.uniqid(mt_rand()).'.tmp');
		else if (is_dir($path))
			return $this->is__writable($path.'/'.uniqid(mt_rand()).'.tmp');
		// check tmp file for read/write capabilities
		$rm = file_exists($path);
		$f = @fopen($path, 'a');
		if ($f===false)
			return false;
		fclose($f);
		if (!$rm)
			unlink($path);
		return true;
	}
	
	function getDebugInfo($content) {
		$debug = array();
		$debug['version'] = $this->version;
		$debug['php_version'] = phpversion();
		$debug['os'] = $_SERVER['SERVER_SOFTWARE'];
		$debug['transform_uri'] = $this->getSettings("url_case_transformation");
		$debug['request_uri'] = $this->REQUEST_URI;
		$debug['request_uri_norm'] = $this->transformUri($this->REQUEST_URI);
		$debug['linkator_path'] = str_replace($_SERVER['DOCUMENT_ROOT'], '',  dirname(__FILE__));
		$debug['data_path'] = $this->dataPath;
		$debug['data_path_is_writable'] = $this->is__writable($this->dataPath)? 'Yes' : 'No';
		$debug['charset_settings'] = $this->settings['charset'];
		$debug['charset_detected'] = $this->charset;
		$debug['php_gzip'] = $this->gz ? "Yes" : "No";
		$debug['use_sape'] = $this->getSettings('epas_enable') ? "Yes" : "No";
		$debug['use_sape_context'] = $this->getSettings('sape_context') ? "Yes" : "No";
		$debug['use_linkfeed'] = $this->getSettings('deefknil_enable') ? "Yes" : "No";
		$debug['use_trustlink'] = $this->getSettings('kniltsurt_enable') ? "Yes" : "No";
		$debug['use_setlinks'] = $this->getSettings('skniltes_enable') ? "Yes" : "No";
		$debug['use_mainlink'] = $this->getSettings('mainlink_enable') ? "Yes" : "No";
		$debug['use_megaindex'] = $this->getSettings('megaindex_enable') ? "Yes" : "No";
		$debug['use_articles'] = $this->getSettings('enable_articles') ? "Yes" : "No";
		$debug['partners_enable'] = $this->getSettings('partners_enable') ? "Yes" : "No";
		$debug['sitemap_enable'] = $this->getSettings('sitemap_enable') ? "Yes" : "No";
		if($this->getSettings('sitemap_enable')) {
			$debug['sitemap_url'] = $this->getSettings('sitemap_url');
		}
		if($this->getSettings('enable_articles')) {
			$debug['articles_index'] = $this->getSettings('articles_indexpage');
			$debug['articles_charset'] = $this->getSettings('articles_charset');
		}
		$debug['page_has_links'] = $this->pageHasLinks ? "Yes" : "No";
		$debug['phrases_count'] = count($this->phrases);
		$debug['phrases_found'] = $this->blocksCountFound;
		$debug['opened_hosts'] = $this->getSettings("hosts") ? $this->getSettings("hosts") : 'none';
		$debug['opened_pages'] = $this->getSettings("pages") ? nl2br($this->getSettings("pages")) : 'none';
		
		$debug['locale_status'] = strtolower("ПРОВЕРКА") == 'проверка' ? 'OK' : 'ERROR';
		$debug['locale_test_string'] = strtolower("ПРОВЕРКА");
		$debug['locale_set_win_force'] = $this->getSettings("set_win_locale") ? 'YES' : 'NO';
		
		if($this->getSettings("set_win_locale")) {
			$debug['locale_set'] = $this->currentLocale ? $this->currentLocale : 'not_set';
		}
		
		/* Вова просил добавить информацию о том, где линкатор подключен */
		$debug['where_i_am'] = file_exists($this->curDir . DIRECTORY_SEPARATOR . 'where_am_i.txt') ? file_get_contents($this->curDir . DIRECTORY_SEPARATOR . 'where_am_i.txt') : 'NO_FILE';
		$debug['work_time'] = (round(1000 * $this->workTime) / 1000). " sec";
		//$debug['headers_list'] = function_exists('headers_list') ? implode("\r\n<br />", headers_list()) : "No";
		
		$rowTemplate = '<tr valign="top" style="font:normal 12px Arial;"><td style="padding:2px;">{key}</td><td rel="{key}">{value}</td></tr>';
		$rowsHTML = "";
		foreach ($debug as $key => $value) {
			if(!is_array($value)) {
				$rowsHTML .= str_replace(array('{key}', '{value}'), array($key, $value), $rowTemplate);
			}
		}
		
		/* запросим ошибки у линкатора */
		if($this->lkr->getErrors()) {
			$this->errors = array_merge($this->errors, $this->lkr->getErrors());
		}
		
		/* ошибки выполнения */
		if($this->errors) {
			$label = $this->processCharset("Ошибки");
			$rowsHTML .= '<tr valign="top"><td colspan="2" style="padding:2px">'.$label.'</td></tr>';
			foreach ($this->errors as $error) {
				$error = $this->processCharset($error);
				$rowsHTML .= '<tr valign="top"><td colspan="2" style="padding:2px; color:red;">'.$error.'</td></tr>';
			}
		}
		
		$debugContent = '<script>function tldi() {
			var div = document.getElementById("LinkatorDebugInfo");
			var table = div.childNodes[1];
			var d = table.style.display || "table";
			if(d == "table") {
	    		table.style.display = "none";
			} else {
	    		table.style.display = "table";
			}
		}</script><div id="LinkatorDebugInfo" style="position:absolute;z-index:9999; background-color:#FFF; border:1px solid #ccc; padding:4px;left:10px; font:normal 12px Arial;"><h3 onclick="tldi();" style="cursor:pointer;">Linkator Debug Info</h3><table>'.$rowsHTML.'</table></div>';
		
		return  $this->insertAfterBody($content, $debugContent);
	}
	
	function epasReturnLinks($n = null) {
		if($this->getSettings('sape_blocklinks')) {
			return $this->epas->return_block_links($n);
		} else {
			return $this->epas->return_links($n);
		}
	}
	
	function return_links($n = null) {
		
		$block = "";
		
		if($this->epas) {
			$block .= $this->wrap($this->epasReturnLinks($n));
		}
		
		if($this->deefknil) {
			$block .= $this->wrap($this->deefknil->return_links($n));
		}
		
		$block .= $this->wrap($this->lkr->getLinksHTMLCode($this->lkrPlacenumber++));
		
		if($this->settings['skniltes_enable']) {
			$block .= $this->wrap($this->skniltes->GetLinks());
		}
		if($this->settings['kniltsurt_enable']) {
			$block .= $this->wrap($this->kniltsurt->build_links());
		}
		if($this->settings['mainlink_enable']) {
			$block .= $this->wrap($this->mainlink->Get_Links());
		}
		
		return $block;
	}
	
	
	/**
	 * Анализирует контент, настройки, возвращает имя кодировки
	 * @param string $content
	 * @return string $charset
	 */
	function getCharset($content) {
		$charset = $this->settings['charset'];
		if($charset != 'cp1251' && $charset != 'auto') {
			return $charset;
		} elseif($charset=='auto') {
			
			if(function_exists('headers_list')) {
				$headers = implode("\r\n", headers_list());
				$matches = array();
				if(preg_match('/charset\s*=\s*[\'"]?([^"\'\s]+)/im', $headers, $matches)) {
					$charset = strtolower($matches[1]);
				}
			}
			
			/* если $charset все ещё == auto */
			if($charset == 'auto') {
			
				//вычленяем тег <head> из всего документа, чтоб остальной мусор нам не мешал кодировку определять
				$content = strtolower($content);
				$firstSignature = '<head>';
				$secondSignature = '</head>';
				$headStartPos = strpos($content, $firstSignature);
				$headStopPos = strpos($content, $secondSignature, $headStartPos);
				if($headStartPos !== false && $headStopPos !== false) {
					$content = substr($content, $headStartPos, $headStopPos + strlen($secondSignature) - $headStartPos);
				}
				
				if(preg_match('/<meta [^>]*content\s*=\s*["\']\s*text\/html\s*;\s*charset\s*=\s*([^"\'\s]+)\s*["\']/isU', $content, $matches)) {
					$charset = strtolower($matches[1]);
				} elseif(preg_match('#<meta\s+charset\s*=\s*["\']([^"\'\s]+)#is', $content, $matches)) {
					$charset = strtolower($matches[1]);
				}
			}
			
			if($charset != 'auto' && !preg_match("/cp1251|windows-1251|ISO-8859-1|windows-1252/i", $charset)) {
				return $charset;
			}
		}
		return 'cp1251';
	}
	
	function processCharset($block) {
		
		if($this->charset != 'cp1251') {
			$block =  iconv('cp1251', $this->charset."//IGNORE", $block);
		}
		return $block;
	}
	
	function set200OK() {
		if($this->pageHasLinks && $_SERVER['REQUEST_METHOD'] == 'GET') {
			header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
		}
	}
	
	function isEmptyBlock($block) {
		$isLinkatorBlock = strpos($block, '<!--lkr-->') !== false;
		$block = preg_replace('/<!--.*-->/Usm', '', $block);
		$block = preg_replace('#<script.*?</script>#ims', '', $block);
		$block = str_replace(array("\n", "\t", "\r", " "), "", $block);
		return empty($block) || ($isLinkatorBlock && $this->lkr->throughOnly);
	}
	
	/**
	 * Функция Вставляет баннеры на страницу
	 * @param string $content исходный код страницы
	 */
	function processBanners($content) {
		$placenumber = 0;
		foreach ($this->getSettings('banner_phrases') as $phrase) {
			
			$phrase['phrase'] = $this->processCharset($phrase['phrase']);
			
			if (false !== ($pos = strpos($content, $phrase['phrase']))) {
				$bannersHTML = $this->lkr->getBannerHTMLCode($placenumber++);
				switch($phrase['type']) {
					case 'before': {
						$content = $this->substr_replace($content, $bannersHTML, $pos, 0);
						break;
					}
					case 'replace': {
						$content = $this->substr_replace($content, $bannersHTML, $pos, strlen($phrase['phrase']));
						break;
					}
					case 'after': {
						$content = $this->substr_replace($content, $bannersHTML, $pos + strlen($phrase['phrase']), 0);
						break;
					}
				}
				break;
			}
		}
		return $content;
	}
	
	/**
	 * @param string $content - Текст, который обрабатываем.
	 * @param strign $linksHTML - Текст, который вставляем
	 * @param array $phrase - Фраза в виде ассоц.массива [type, pos, phrase]
	 */
	function insert($content, $linksHTML, $phrase) {
		
		if($this->debug) {
			$linksHTML = '<!--I_AM_HERE_BRO--><div style="border:1px solid red;padding:4px;">'.$linksHTML.'</div>';
		}
		
		if(isset($phrase['after_body']) && $phrase['after_body']) {
			return $this->insertAfterBody($content, $linksHTML);
		}
		if(isset($phrase['before_body_close']) && $phrase['before_body_close']) {
			return $this->insertBeforeBodyClose($content, $linksHTML);
		}
		
		$pos = $phrase['pos'];
		
		if($pos !== false) {
			$insertType = $phrase['insert_type'];
			$text = $phrase['text'];
			switch($insertType) {
				case 'before': {
					$content = $this->substr_replace($content, $linksHTML, $pos, 0);
					break;
				}
				case 'replace': {
					$content = substr_replace($content, $linksHTML, $pos, strlen($text));
					break;
				}
				case 'after': {
					$content = $this->substr_replace($content, $linksHTML, $pos + strlen($text), 0);
					break;
				}
			}
		}
		return $content;
	}
	
	/**
	 * Оборачиваем блок ссылок в заданный шаблон
	 * @param string $block
	 * @return string $block - обернутый блок ссылок
	 */
	function wrap($block) {
		$clearBlock = preg_replace('/<!--.*-->/iUs', '', $block);
		$wrapper = $this->processCharset($this->getSettings('links_wrapper'));
		if($wrapper && strpos($wrapper, '{links}') !== false && !empty($clearBlock)) {
			$block = str_replace("{links}", $block, $wrapper);
		}
		return $block;
	}
	
	function wrapBlock($block) {
		$clearBlock = preg_replace('/<!--.*-->/iUs', '', $block);
		$wrapper = $this->processCharset($this->getSettings('block_wrapper'));
		if($wrapper && strpos($wrapper, '{links}') !== false && !empty($clearBlock)) {
			$block = str_replace("{links}", $block, $wrapper);
		}
		return $block;
	}
	
	
	function findSignature($signature, $content) {
		$text = $this->processCharset($signature['text']);
		if($text == '<body>' && $signature['insert_type'] == 'after') {
			$text = '<body';
		}
		
		$ignoreCase = isset($signature['ignore_case']) && $signature['ignore_case'];
		
		if($ignoreCase) {
			return strpos(strtolower($content), strtolower($text));
		} else {
			return strpos($content, $text);
		}
	}
	
	function getLinkBlocks() {
		
		$blocks = array();
			
		if($this->epas || $this->deefknil) {
			$links = ""; 
			if($this->epas) {
				$links .= $this->epasReturnLinks();
			}
			if($this->deefknil) {
				$links .= $this->deefknil->return_links();
			}
			$blocks[] = $links;
		}
		if($this->settings['kniltsurt_enable']) {
			$blocks[] = $this->kniltsurt->build_links();
		}
		if($this->settings['skniltes_enable']) {
			$blocks[] = $this->skniltes->GetLinks();
		}

		// пометим этот блок как блок линкаторых ссылок (для функции isEmptyBlock)
		$blocks[] = '<!--lkr-->'.$this->lkr->getLinksHTMLCode($this->lkrPlacenumber++);
		
		if($this->settings['mainlink_enable']) {
			$blocks[] = $this->mainlink->Get_Links();
		}
		
		if($this->getSettings("megaindex_enable")) {
			if($this->getSettings("megaindex_use_block_links")) {
				$blocks[] = $this->megaindex->return_block_links();
			} else {
				$blocks[] = $this->megaindex->return_links();
			}
		}
		
		return $blocks;
	}
	
	function processLinks($content) {
		
		/*
		 * Алгоритм такой:
		 * Ищем сразу все заданные фразы. Найденная фраза = новый ссылкоблок
		 * В зависимости от количества найденный фраз, следуем по одной из 5-и веток алгоритма
		 * Обрабатываем все ссылкоблоки, вставляя ссылки.
		 * Возвращаем контент
		 */
		
		$source = $this->prepareContentForSearching($content);
		
		if(is_array($this->phrases) && !empty($this->phrases)) {
			
			
			$signatures = $this->getSignatures($this->phrases);
			
			$blocks = $this->findBlocks($signatures, $source);
			
			/* количество ссылкоблоков, найденных на странице */
			$blocksCount = count($blocks);
			
			if($blocksCount) {
				
				foreach ($blocks as $block) {
					if($block['regular']) {
						$this->blocksCountFound ++;
					}
				}
				
				/* получим блоки ссылок, которые будем вставлять в ссылкоблоки */
				$linkBlocks = $this->getLinkBlocks();
				
				/* отметим, есть ли ссылки на текущей странице */
				if($linkBlocks) foreach ($linkBlocks as $key => $linkBlock) {
					if(!$this->isEmptyBlock($linkBlock)) {
						$this->pageHasLinks = true;
					}
					$linkBlocks[$key] = str_replace('<!--lkr-->', '', $linkBlock);
				}
				
				/* if($linkBlocksCount > $blocksCount) {
					$inBlock = floor($linkBlocksCount / $blocksCount);
				} else {
					$inBlock = 1;
				} */
				/* По одному блоку в ссылкоблок. Если ссылкоблок последний - все оставшиеся блоки в него */
				
				
				$content = $this->insertBlocks($blocks, $linkBlocks, 1, $content);
				
			} else {
				if($this->getSettings('append_links_to_content')) {
					$links = $this->processCharset($this->return_links());
					$links = $this->debug ? '<!--I_AM_HERE_BRO-->'.$links : $links;
					$this->pageHasLinks = !$this->isEmptyBlock($links);
					$content = $content.$links;
				} else {
					/* Это, конечно, враньё. Но оно нужно для того, чтоб set200OK
					 * не отдавал 200OK если линкатор не собирается вставлять ссылки
					*/
					$this->pageHasLinks = false;
				}
			}
		}
				
		return $content;
	}
	
	/**
	 * Получить подготовленные объекты-сигнатуры, на место которых будем вставлять ссылки. 
	 * @return array
	 */
	function getSignatures($phrases) {
		$signatures = array();
		foreach ($phrases as $phrase) {
			$signatures[] = array(
					'text' => $phrase['phrase'],
					'insert_type' =>  $phrase['type'],
					'regular'	 => true,
					'after_body' => ($phrase['phrase'] == '<body>') && ($phrase['type'] == 'after'), 
					'ignore_case' => $phrase['ignore_case'],
					'adv_id' => isset($phrase['adv_id']) ? $phrase['adv_id'] : null
			);
		}
		/* всегда добавляем 2 сигнатуры, чтоб вставить ссылки в том случае, когда ни один из блоков не обнаружен */
		$signatures[] = array(
				array(
						'text' => '</body>',
						'insert_type' => 'before',
						'regular' => false, 	//отметим их как "специальный блок"
						'after_body' => false, 
						'ignore_case' => true,
						'adv_id' => null
				),
				array(
						'text' => '</html>',
						'insert_type' => 'before',
						'regular' => false, //отметим их как "специальный блок"
						'after_body' => false,
						'ignore_case' => true,
						'adv_id' => null
				)
		);
		
		return $signatures;
	}
	
	/**
	 * Поиск ключевых фраз в заданном тексте
	 * @param array $signatures
	 * @param string $source
	 * @return array
	 */
	function findBlocks($signatures, $source) {
		//Найдём все ссылкоблоки
		
		$positions = array();
		$blocks = array();
		
		foreach ($signatures as $signature) {
			$pos = false;
			if(isset($signature['text'])) {
				$pos = $this->findSignature($signature, $source);
			} elseif (is_array($signature)) {
				
				/* добавляем системные блоки только в том случае, когда не найдены обычные */
				if(empty($blocks)) {
					/* вариант с </body> </html> когда надо найти первую из заданного списка сигнатур */
					foreach ($signature as $sig) {
						if(($pos = $this->findSignature($sig, $source)) !== false) {
							$signature = $sig;
							break;
						}
					}
				}
			}
		
			if($pos !== false) {
				$signature['pos'] = $pos;
				$blocks[] = $signature;
				$positions[] = $pos;
			}
		}
		
		return $blocks;
	}
	
	/**
	 * Расставить ссылки по своим местам в контенте страницы
	 * @param array $blocks - ссылкоблоки (места, куда вставляются ссылки)
	 * @param array $linkBlocks - массив ссылок. Каждый элемент представляет собой ссылки из одной биржи
	 * @param integer $inBlock - по сколько ссылок в блок можно вставлять
	 * @param string $content - контент страницы
	 * @return string - контент страницы с внедрёнными ссылками
	 */
	function insertBlocks($blocks, $linkBlocks, $inBlock, $content) {
		
		/* Существуют как "обычные" блоки, указанные администратором, так и "системные": "после </html>" или "после </body>"
		 * Если на странице найдены "обычные" блоки, то вставлять ссылки мы должны только в них!
		 * Поэтому надо скорректировать $blocksCount
		 */
		$blocksCount = count($blocks);
		
		$linkBlocksCount = count($linkBlocks);
		
		$blockNumber = 0;
		for ($i = 0; $i < $linkBlocksCount && $blockNumber < $blocksCount; $i += $inBlock) {
			
			/* количество бирж в текущем ссылкоблоке */
			$cutLength = $inBlock;
			if($blockNumber + 1  == $blocksCount) {
				/* в последний блок надо добавить все оставшиеся ссылки */
				$cutLength = $linkBlocksCount - $i;
			}
				
			$links = array_slice($linkBlocks, $i, $cutLength);
			$linkBlockHTML = "";
			foreach ($links as $linkBlock) {
				$linkBlockHTML .= $this->wrap($this->processCharset($linkBlock));
			}
			
			// если в блоке отсутствуют ссылки, то попробуем вставить контекстную рекламу
			$linkBlockHTMLClear = preg_replace('/<script.*?<\/script>/is', '', $linkBlockHTML);				
			if(!preg_match('/<a[^>]*href\s*=\s*["\']?([^\s>"\']*)["\']?[^>]*>(.*?)<\/a>/is', $linkBlockHTMLClear)) {
				if($blocks[$blockNumber]['adv_id']) {
					$linkBlockHTML .= str_replace("{id}", $blocks[$blockNumber]['adv_id'], $this->getSettings("adv_block_template"));
				}
			}
			
			$linkBlockHTML = $this->wrapBlock($linkBlockHTML);
			
			$length = strlen($content);
			$content = $this->insert($content, $linkBlockHTML, $blocks[$blockNumber]);
			$diff = strlen($content) - $length;

			// коррекция позиции следующих блоков
			for($key = 0; $key < $blocksCount; $key ++) {
				if($blocks[$key]['pos'] > $blocks[$blockNumber]['pos']) {
					$blocks[$key]['pos'] += $diff;
				}
			}
				
			$blockNumber ++;
		}
		
		// В оставшиеся пустые блоки идёт вставка блока контекстной рекламы, если указан его id
		for(; $blockNumber < $blocksCount; $blockNumber ++) {
			if($blocks[$blockNumber]['adv_id']) {
				$str = str_replace("{id}", $blocks[$blockNumber]['adv_id'], $this->getSettings("adv_block_template"));
				
				$length = strlen($content);
				$content = $this->insert($content, $str, $blocks[$blockNumber]);
				$diff = strlen($content) - $length;
				
				// коррекция позиции следующих блоков
				for($key = $blockNumber + 1; $key < $blocksCount; $key ++) {
					$blocks[$key]['pos'] += $diff;
				}
			}
		}
		
		return $content;
	}
	
	function prepareContentForSearching($content) {		
		$content = preg_replace_callback('#<script.*?</script>#ims', array($this, 'replaceTagWithSpaces'), $content);
		if(function_exists("preg_last_error")) {
			switch(preg_last_error()) {
				case PREG_NO_ERROR:
					break;
				case PREG_INTERNAL_ERROR:
					$this->logError("PREG_INTERNAL_ERROR");
					break;
				case PREG_BACKTRACK_LIMIT_ERROR:
					$this->logError("PREG_BACKTRACK_LIMIT_ERROR");
					break;
				case PREG_RECURSION_LIMIT_ERROR:
					$this->logError("PREG_RECURSION_LIMIT_ERROR");
					break;
			}
		}
		
		return $content;
	}
	
	function replaceTagWithSpaces($matches) {
		if($matches) {
			$length = strlen($matches[0]);
			return str_repeat(" ", $length);
		}
	}
	
	function processLinksByPhrase($content) {
		if(is_array($this->phrasesIgnore)) foreach($this->phrasesIgnore as $phrase) {
			$phrase['phrase'] = $this->processCharset($phrase['phrase']);
			
			
			$signature = array(
				'text' => $phrase['phrase'],
				'insert_type' =>  $phrase['type'],
				'regular'	 => true,
				'after_body' => ($phrase['phrase'] == '<body>') && ($phrase['type'] == 'after'), 
				'ignore_case' => $phrase['ignore_case']
			);
			
			$signature['pos'] = $this->findSignature($signature, $content);
			
			if($signature['pos'] !== false) {
				$linksHTML = $this->processCharset($this->lkr->getLinksHTMLCodeByPhrase($phrase['id']));
				$content = $this->insert($content, $linksHTML, $signature);
			}
		}
		return $content;
	}
	
	function processStopwords($content) {
		
		if(($stopWords = $this->getSettings("stopwords_titles")) != null) {
			$headHTML = $this->getHTMLHead($content);
			if($headHTML) {
				$content = str_replace($headHTML, $this->replaceWords($stopWords, $headHTML), $content);
			}			
		}
		if(($stopWords = $this->getSettings("stopwords_text")) != null) {
			$bodyHTML = $this->getHTMLBody($content);
			if($bodyHTML) {
				$content = str_replace($bodyHTML, $this->replaceWords($stopWords, $bodyHTML), $content);
			}
		}
		
		return $content;
	}
	
	function getHTMLHead($html) {
		if(preg_match('/<head.*?<\/head>/is', $html, $matches)) {
			return $matches[0];			
		}
	}
	function getHTMLBody($html) {
		if(preg_match('/<body.*?<\/body>/is', $html, $matches)) {
			return $matches[0];
		}
	}
	
	function replaceWords($stopWords, $blockHTML) {
		foreach ($stopWords as $needle=>$replacement) {
			$needle = $this->processCharset($needle);
			$replacement = $this->processCharset($replacement);
			$blockHTML = preg_replace('/'.preg_quote($needle, '/').'/i', $replacement, $blockHTML);
		}
		return $blockHTML;
	}
	
	
	function isGzip() {
		if(function_exists('headers_list')) {
			$headers = headers_list();

			foreach ($headers as $header) {
				if(false !== strpos($header, 'Content-Encoding:') && false !== strpos($header, 'gzip')) {
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Функция определяет, необходимо ли остановить обработку контента (в случаях, когда контент - это не текстовая страница)
	 * @param string $content
	 */
	function stopIfNoHTML($content) {
		//1 вариант. Если включена галка die_if_no_html
		if($this->getSettings('die_if_no_html') && ( strpos(strtolower($content), '<body') === false)) {
			return true;
		}
		/* 2 вариант. Просматриваем заголовки ответа.
		 * Если это капча, то она отправила MIME заголовок image
		 */
		if(function_exists('headers_list')) {
			$headers = headers_list();
			foreach ($headers as $header) {
				if(false !== strpos($header, 'Content-Type:') &&
					(
					 false !== strpos($header, 'image') ||
					 false !== strpos($header, 'video') ||
					 false !== strpos($header, 'application')
					)) {
					return true;
				}
			}
		}
		return false;
	}
	
	function gzdecode($data){
		$zipFileName = tempnam($this->dataPath,'gzt');
		chmod($zipFileName, 0777);
		$zfd = fopen($zipFileName, "w");
		fwrite($zfd, $data, strlen($data));
		fclose($zfd);
		
		$zfd = gzopen($zipFileName, "r");
		//хз. как узнать, сколько надо считать из файла. считываем 10Мб
		$data = gzread($zfd, 1024 * 1024 * 10);
		gzclose($zfd);
		unlink($zipFileName);
	  	return $data;
	}
	
 	function transformUri($url) {
 		
 		if($this->getSettings('url_case_transformation') == 'none') {
 			return $url;
 		}
 		//маленький фикс для урлов.
 		preg_match_all('/%([\w\d][\w\d])/', $url, $matches);
 		if(!empty($matches[1])) {
 			foreach($matches[1] as $code) {
 				if($this->settings['url_case_transformation'] == 'uppercase') {
 					$transformCode = strtoupper($code);
 				} elseif($this->settings['url_case_transformation'] == 'lowercase') {
 					$transformCode = strtolower($code);
 				} else {
 					$transformCode = $code;
 				}
 				$url = str_replace($code, $transformCode, $url);
 			}
 		}
 		return $url;
 	}
 	/**
 	 * Функция решает проблему, когда включена перегрузка строковых функций 
 	 * и установлена внутренняя кодировка UTF-8. В таком случае все строковые функции
 	 * начинают работать неверное из-за того, что все наши данные хранятся в однобайтовых кодировках
 	 */
 	function turnOffMBString() {
 		if(ini_get("mbstring.func_overload") == 2) {
 			$this->mbStringCharset = mb_internal_encoding();
 			mb_internal_encoding('ISO-8859-1');	// однобайтовая latin-1
 		}
 	}
 	
 	function turnOnMBString() {
 		if($this->mbStringCharset) {
 			mb_internal_encoding($this->mbStringCharset);
 		}
 	}
 	
 	function processPartners($content) {
 		if($this->getSettings("partners_enable")) {
 			$keycodes = $this->getSettings("partners_keycodes");
 			$partners = $this->getSettings("partners");
 			
 			if($keycodes) {
 				$searchContent = $this->prepareContentForSearching($content);
 				foreach($keycodes as $keycode) {
 					$needle = $this->processCharset($keycode['keycode']);
 					$insertData = null;
 					
 					if($needle == '<body>' && $keycode['keycode_insert_type'] == 'after') {
 						$insertData = array(
 							'after_body' => true
 						);
 					} elseif ($needle == '</body>' && $keycode['keycode_insert_type'] == 'before') {
 						$insertData = array(
 								'before_body_close' => true
 						);
 					} else {
 						$pos = strpos($searchContent, $needle);
 						if($pos !== false) {
 							$insertData = array(
 								'pos' => $pos, 
 								'text' => $needle, 
 								'insert_type' => $keycode['keycode_insert_type']
 							);
 						}
 					}
 					
 					/* если мы нашли куда вставлять этот блок */
 					if($insertData) {
 						$insertText = $this->processCharset($this->parsePartnerText($keycode['text'], $keycode['partners']));
 						$content = $this->insert($content, $insertText , $insertData);
 						$searchContent = $this->insert($searchContent, $insertText , $insertData);
 					}
 				}
 			}
 		}
 		
 		return $content;
 	}
 	
 	function parsePartnerText($text, $partners) {
 		preg_match_all('#%(\w+)(?:\((\d+)\))?%#im', $text, $matches, PREG_OFFSET_CAPTURE);
 		$partners = $this->groupBy($partners, 'partner_type_name');
 		$diff = 0;
 		$textLength = strlen($text);
 		foreach($matches[0] as $key => $string) {
 			
 			$typeName = $matches[1][$key][0];
 			$insertedItems = 0;
 			$maxItems = isset($matches[2][$key][0]) ? $matches[2][$key][0] : 0;
 			$thisTypePartners = isset($partners[$typeName]) ? $partners[$typeName] : array(); 			
 			
 			$html = '';
 			
 			if($thisTypePartners) {
 				$isEnough = false;
 				while($thisTypePartners && !$isEnough) {
 					/* выбираем рандомно следующую партнёрку */
 					$i = rand(0, count($thisTypePartners) - 1);
 					$partner = $thisTypePartners[$i];
 					
 					/* партнёрка подходит, 
 					 * если нет ограничений по количеству вставляемых объявлений
 					 * если количество вставляемых объявлений не превышает заданный максимум
 					 */
 					if($maxItems == 0 || ($maxItems && $insertedItems + $partner['items_count'] <= $maxItems)) {
 						$partnerCode = $partner['partner_code_html'];
 						
 						/* в коде партнёрки заменяем ключи, свойственные конкретному сайту, на их значения 
 						 * {key} => 333 например */
 						preg_match_all('/{key(\d+)?}/', $partnerCode, $keyMatches);
 						foreach($keyMatches[0] as $k => $keyStr) {
 							$keyNumber = !empty($keyMatches[1][$k]) ? $keyMatches[1][$k] : 1;
 							$partnerCode = str_replace($keyStr, $partner['partner_key_'.$keyNumber], $partnerCode);
 						}
 						
 						$html .= $partnerCode;
 						
 						$insertedItems +=  $partner['items_count'];
 						
 						/* больше в этот блок вставить нечего */
 						if($maxItems && $insertedItems == $maxItems) {
 							$isEnough = true;
 						}
 					}
 					
 					/* одна партнёрка проверяется только один раз */
 					unset($thisTypePartners[$i]); 					
 					$thisTypePartners = array_values($thisTypePartners);
 				}
 			} elseif ($typeName == 'LINKS') {
 				$html = $this->return_links();
 			}
 			
 			$insertPos = $matches[0][$key][1] + $diff;
 			$text = substr_replace($text, $html, $insertPos, strlen($matches[0][$key][0]));
 			
 			/* рассчитаем, на сколько мы сдвинулись относительно начальных позиций, рассчитанных preg_match */
 			$newTextLength = strlen($text); 
 			$diff += $newTextLength - $textLength;
 			$textLength = $newTextLength;
 		}
 		
 		return $text;
 	}

 	/**
 	 * Получить список партнёрок, сгруппированных по типу
 	 * @return array
 	 */
 	function getPartnersGrouppedByType() {
 		if(!isset($this->partners)) {
 			$this->partners = $this->getSettings("partners");
 		}
 		return $this->groupBy($this->partners, 'partner_type_name');
 	}
 	
 	function groupBy($items, $groupField) {
 		$result = array();
 		if($items) foreach($items as $item) {
 			$result[$item[$groupField]][] = $item;
 		}
 		return $result;
 	}
 	
 	function removePartner($id) {
 		if(isset($this->partners)) {
 			foreach($this->partners as $key => $value) {
 				if($value['partner_code_id'] == $id) {
 					unset($this->partners[$key]);
 				}
 			}
 		}
 	}
 	
 	function processLastArticlesBlock($content) {
 		if(($pos = strpos($content, "<!--iprofit_last_articles")) !== false) {
 			$word = substr($content, $pos, strpos($content, "-->", $pos) + 3 - $pos);
 			$replacement = null;
 			if(preg_match('/iprofit_last_articles\[(\d+)\]/im', $word, $m)) {
 				$limit = $m[1];
 				$replacement = $this->lkr->renderLastArticlesBlock($limit);
 			} else {
 				$replacement = "Set articles limit via [], please.";
 			}
 			
 			$replacement = $this->processCharset($replacement);
 			
 			$content = str_replace("<!--iprofit_last_articles[$limit]-->", $replacement, $content);
 		}
 		
 		return $content;
 	}
 	
		
	/**
	 *
	 * @param string $content
	 * @return string $content
	 */
	function replace_content($content) {
		
		$workTime = microtime(true);
				
		/**
		 * Возвращаем назад первоначальное значение переменной $_SERVER['REQUEST_URI']
		 * @var string
		 */
		$_SERVER['REQUEST_URI'] = $this->REQUEST_URI;
		
		//установим однобайтовую кодирвоку, если это надо
		$this->turnOffMBString();
		
		//проверка на gzip:
		if($this->isGzip()) {
			$this->gz = true;
			$content = $this->gzdecode($content);
		}
		
		if($this->stopIfNoHTML($content)) {
			if($this->gz) $content =  gzencode($content);
			return $content;
		}
		
		//определимся с кодировкой:
		$this->charset = $this->getCharset($content);
		//установим русскую локаль, если требуется
		$this->setLocale();
				
		//Закрываем внешние ссылки на странице
		if(!$this->isArticle) $content = $this->noindex($content);

		//Вставляем контекстные ссылки SAPE
		if($this->getSettings("epas_enable") && $this->getSettings('sape_context')) {
			$sc = new LinkatorEpasContext(array(
				'charset' => $this->charset,
				'host' => $this->serverName,
				'data_path' => $this->dataPath
			));
			$content = $sc->replace_in_page($content);
		}
		
		//Вставляем баннеры
		if(isset($this->settings['enable_banners']) && $this->settings['enable_banners'] && !empty($this->settings['banner_phrases'])) {
			$content = $this->processBanners($content);
		}
		
		//$content = str_replace("\r", "", $content);
		
		$content = $this->processLinks($content);
		$content = $this->processLinksByPhrase($content);
		$content = $this->processStopwords($content);
		$content = $this->processPartners($content);
		$content = $this->processLastArticlesBlock($content);
		
		$this->set200OK();
		
		if($this->debug) {
			$this->workTime = (microtime(true) - $workTime) + $this->workTime;
			$content = str_replace("<!-- Linkator has been successfully installed -->", "", $content);
			$content = $this->getDebugInfo($content);
		}
		
		//вернем многобайтовую кодировку, если она была сброшена в однобайтную
		$this->turnOnMBString();
		
		if($this->gz) {
			$content = gzencode($content);
		}
		
		return $content;
	}
}