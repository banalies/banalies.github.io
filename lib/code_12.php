<?php
require_once "code_8.php";

/**
 * Выполняет полное управление ссылочным контентом на сайте: ссылки, баннеры, статьи, обменные ссылки.
 *
 */
class Linkator {
	/** @var string */
	var $fileWithLinks;
	/** @var string */
	var $articlesPath;
	var $articlesTemplatesPath;
	var $articlesDbFilename;
	var $articlesIndexpageTitle;
	var $articlesIndexpageURL;
	/** @var string */
	var $bannersPath;
	var $bannersDbFilename;
	var $bannersBaseURL;
	var $myHomeplace;
	var $myRelativeHomeplace;
	var $_request_uri;
	var $template;
	var $errors = array();
	var $throughOnly = false;	
	
	/* Насколько я помню, в php 5.3 - Это уже не будет являться конструктором */
	function Linkator($options = null) {
		$this->fileWithLinks = 'data/links.txt';
		$this->articlesPath = 'data/articles';
		$this->bannersPath = 'data/banners';
		$this->articlesIndexpageTitle = 'Articles';
		
		if(is_array($options)) {
			if(isset($options['data_path'])) {
				$this->fileWithLinks = $options['data_path'].'/lkr.db';
				$this->articlesPath = $options['data_path'].'/articles';
				$this->articlesDbFilename = $options['data_path'].'/articles.db';
				$this->articlesTemplatesPath = $options['data_path'].'/templates';
				$this->articlesIndexpageURL = isset($options['articles_indexpage_url']) ? $options['articles_indexpage_url'] : null;
				$this->bannersDbFilename = $options['data_path'].'/banners.db';
				$this->bannersPath = $options['data_path'].'/banners';
			}
			
			if(isset($options['template'])) {
				$this->template = $options['template'];
			}
		}
		
		
		if(is_array($options) && isset($options['request_uri'])) {
			$this->_request_uri = $options['request_uri'];
		} else {
			$this->_request_uri = $_SERVER['REQUEST_URI'];
		}
	}
	
	function setLinksDataBaseFile($fileName)  {
		$this->fileWithLinks = $fileName;
	}
	
	function getLinksDataBaseFile() {
		return $this->fileWithLinks;
	}
	
	function setArticlesPath($path)  {
		$this->articlesPath = $path;
	}
	function setArticlesTemplatesPath($path) {
		$this->articlesTemplatesPath = $path;
	}
	function setArticlesDbFilename($filename) {
		$this->articlesDbFilename = $filename;
	}
	function setArticlesIndexpageTitle($title) {
		$this->articlesIndexpageTitle = $title;
	}
	
	function setBannersPath($path) {
		$this->bannersPath = $path;
	}
	function setBannersDbFilename($filename) {
		$this->bannersDbFilename = $filename;
	}
	function setBannersBaseURL($url) {
		$this->bannersBaseURL = $url;
	}
	function pushError($error) {
		$this->errors[] = $error;
	}
	function getErrors() {
		return $this->errors;
	}

	/**
	 * Сравнивает на соответствие REQUEST_URI и PAGE, если они идентичны, то вовзвращает TRUE иначе FALSE
	 *
	 * @param String $uri - REQUEST_URI
	 * @param String $page - PAGE - имя страницы, на которую выводится сущность
	 * @return boolean - сходны или нет.
	 */
	 function pageCompare($uri, $page) {
    	$page = preg_quote($page);
    	$page = str_replace('/', '\/', $page);
    	$page = str_replace('\*', '.*', $page);
    	$page = str_replace('\?', '.', $page);
    	$regularExpression = '/^'.$page.'$/';
    	if(preg_match($regularExpression, $uri)) {
    		return true;
    	}
    	else {
    		return false;
    	}
	}
	
	/**
	 * Функция setLinks сохраняет ссылки в файл
	 *
	 * @param array linksArray[] - Ассоциативный масив ссылок
	 * @return boolean 1, если сохранение завершилось удачно, 0 - если произошли какие-то ошибки
	 */
	 function saveLinks($linksArray) {
		
		//преобразуем всё в lower-case
		if(is_array($linksArray)) {
			foreach ($linksArray as $key=>$link) {
				foreach ($link as $k=>$v) {
					unset($link[$k]);
					$link[strtolower($k)] = $v;
				}
				$linksArray[$key] = $link;
			}
		}
		
		if(file_put_contents($this->fileWithLinks, serialize($linksArray)) !== false ) {
			@chmod($this->fileWithLinks, 0666);
			return true;
		} else {
			return false;
		}
	}
	
function loadLinks() {
		$this->fetchDataFromServer();
		if(file_exists($this->fileWithLinks)) {
			$links = unserialize(file_get_contents($this->fileWithLinks));
		} else {
			$this->pushError("File $this->fileWithLinks not found! (file_exists = 0)");
			$links = array();
		}
		return $links ? $links : array();
	}
	
	/**
	 * Загрузить ссылки с сервера
	 */
	function fetchDataFromServer() {
		
		$this->_db_file = $this->fileWithLinks;
		$this->_cache_lifetime = 3600;
		$this->_cache_reloadtime = 600;
		
		if (!is_file($this->_db_file)) {
			// Пытаемся создать файл.
			if (@touch($this->_db_file)) {
				@chmod($this->_db_file, 0666); // Права доступа
			} else {
				$this->pushError('Нет файла ' . $this->_db_file . '. Создать не удалось. Выставите права 777 на папку. Текущий каталог: '.dirname(__FILE__));
				return;
			}
		}
	
		@clearstatcache();
	
		$data = file_get_contents($this->_db_file);
		
		if (
			filemtime($this->_db_file) < (time() - $this->_cache_lifetime)
			||
			filesize($this->_db_file) == 0
			||
			@unserialize($data) === false	// ! Обязательно === так как сервер может вернуть пустой массив и это должно рассматриватсья как нормальный ответ.
		) {

			// Чтобы не повесить площадку клиента и чтобы не было одновременных запросов
			@touch($this->_db_file, (time() - $this->_cache_lifetime + $this->_cache_reloadtime));
	
			$url = $this->getDispenserURL();

			$hostname = str_replace("www.", "", $_SERVER['HTTP_HOST']);
			$data = $this->fetchRemote($url . $hostname . '/');
			$links = unserialize($data);
			if(is_array($links)) {
				$this->saveLinks($links);
			} else {
				$this->pushError("Corrupted data recieved. Can't unserialize()");
				@touch($this->_db_file);	// Чтоб не ломиться каждый раз на сервер пока там ведутся работы.
			}
		}
	}

	function getDispenserURL() {
		return base64_decode('aHR0cDovL2xpbmthdG9yLm9yaWRpcy5ydS9saW5rcy8=');
	}
	
	/**
	 * Скачать удалённый файл по протоколу HTTP
	 * @param string $url
	 * @return string
	 */
	function fetchRemote($url) {
		
	$urlData = parse_url($url);
		$host = $urlData['host'];
		$path = $urlData['path'];
	
		$userAgent = 'Linkator-client ';
		$socketTimeout = 5;
	
		@ini_set('allow_url_fopen', 1);
		@ini_set('default_socket_timeout', $socketTimeout);
		@ini_set('user_agent', $userAgent);
		
		if(function_exists("file_get_contents")) {
			// file_get_contents не подведёт :)
			return file_get_contents($url);
		} else {
		
			$buff = '';
			$fp = @fsockopen($host, 80, $errno, $errstr, $socketTimeout);
			if ($fp) {
				fputs($fp, "GET {$path} HTTP/1.0\r\nHost: {$host}\r\n");
				fputs($fp, "User-Agent: {$userAgent}\r\n\r\n");
				while (!feof($fp)) {	//На некоторых сайтах эта конструкция приводит к поспешному выходу из цикла. Хз, почему так. 
					$buff .= fread($fp, 1024);
				}
				fclose($fp);
			
				$page = explode("\r\n\r\n", $buff);
			
				return $page[1];
			} else {
				$this->pushError("Can't connect to $host");
			}
		}
	}
	
	function getLinksHTMLCode($placenumber) {
		$links = array();
		$through = 0;
    	foreach($this->loadLinks() as $link) {
    		if($this->pageCompare($this->_request_uri, $link['site_page']) && $link['placenumber']==$placenumber && (!isset($link['phrase']) || !$link['phrase'])) {
    			
    			if(strpos($link['site_page'], "/*") !== false) {
    				$through ++;
    			}
    			    			
    			$links[] = $link;
			}
		}
		
		// надо где-то отметить, что были запрошены только сквозные ссылки
		$this->throughOnly = ($through == count($links));
		
		return $this->compileLinks($links);
	}
	
	function compileLinks($links) {
		
		$linksHTML = '';
		
		if($this->template) {
			if(preg_match('#{{\s*links\s*}}(.*?){{\s*/links\s*}}#ims', $this->template, $tm)) {
				$linksTemplate = $tm[1];
				
				require_once 'code_9.php';
				$idna = new idna_convert();				
				
				foreach ($links as $link) {
					if(preg_match('#<a.*href\s*=\s*[\'"]?([^\s\'">]+)[\'"]?.*>(.*)</a>#ims', $link['text'], $m)) {
						
						$domain = preg_replace('#^(?:http://)?(?:www\.)?([^/]+).*$#', '\\1', $m[1]);
						$l = array(
								"{html}" => $link['text'],
								"{text}" => strip_tags($link['text']),
								"{url}" => $m[1],
								"{anchor}" => $m[2],
								"{domain}" => iconv('utf-8', 'cp1251', $idna->decode($domain))
						);
							
						$linksHTML .= str_replace(array_keys($l), array_values($l), $linksTemplate);
					}
				}
				
				$linksHTML =  str_replace($tm[0], $linksHTML, $this->template);
			}
			
		} else {
			foreach ($links as $link) {
				$before = isset($link['text_before']) ? $link['text_before'] : "";
				$after = isset($link['text_after']) ? $link['text_after'] : "";
			
				$contents = (isset($link['link_contents']) && !empty($link['link_contents'])) ? ' '.$link['link_contents'] : "";
				if(isset($link['nofollow']) &&  $link['nofollow']) {
					$contents .= ' rel="nofollow" ';
				}
			
				@$linksHTML .= $before.preg_replace('/\[\[(.+)\]\]/', '<a href="http://'.$link['link_url'].'"'.$contents.'>$1</a>', $link['text']).$after;
			}
		}
		
		return $linksHTML;
	}
	
	function getLinksHTMLCodeByPhrase($phrase) {
		$linksHTMLCode = '';
		$links = $this->loadLinks();
    	for($i = 0; $i< count($links); $i++) {
    		$link = $links[$i];
    		if($this->pageCompare($this->_request_uri, $link['site_page']) && $link['phrase'] == $phrase) {
    			$linksHTMLCode .= $link['text_before'].preg_replace('/\[\[(.+)\]\]/', '<a href="http://'.$link['link_url'].'" '.$link['link_contents'].' >$1</a>', $link['text']).$link['text_after'];
    		}
    	}
		return $linksHTMLCode;
	}
	
	function saveArticles($articles) {
		
		if(!file_exists($this->articlesPath)) {
			mkdir($this->articlesPath);
		}
		if(!file_exists($this->articlesDbFilename)) {
			file_put_contents($this->articlesDbFilename, null);
			@chmod($this->articlesDbFilename, 0666);
		}
			
		if(file_exists($this->articlesDbFilename)) {
			if(is_array($articles)) {
				foreach ($articles as $key=>$article) {
					$textFileName = $this->articlesPath.'/article_'.$article['id'].'_text.txt';
					if(file_put_contents($textFileName,$article['text']) !== false) {
						@chmod($textFileName, 0666);
						$annotationFileName = $this->articlesPath.'/article_'.$article['id'].'_annotation.txt';
						if(file_put_contents($annotationFileName,$article['annotation']) !== false) {
							@chmod($annotationFileName, 0666);
							unset($articles[$key]['text']);
							unset($articles[$key]['annotation']);
						} else {
							return false;
						}
					} else {
						return false;
					}
				}
				
				$dbarticles = unserialize(file_get_contents($this->articlesDbFilename));
				
				$dbarticles['articles'] = $articles;
				return file_put_contents($this->articlesDbFilename, serialize($dbarticles));
			} else {
				// @TODO удалить все статьи
				return true;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Функция saveArticlesCategories сохраняет список категорий для статей в файл
	 * @param array [] $categories
	 * @return boolean - сохранено или нет
	 */
	function saveArticlesCategories($categories) {
		if(file_exists($this->articlesDbFilename)) {
			$db = unserialize(file_get_contents($this->articlesDbFilename));
		} else {
			$db = array();
		}
	
		$db['categories'] = $categories;
	
		return file_put_contents($this->articlesDbFilename, serialize($db));
	}
	
	/**
	 * Функция saveArticlesTemplates - сохраняет шаблоны для статей
	 * @param array [] $templates - ассоциативный массив
	 */
	 function saveArticlesTemplates($templates) {
	 	if(is_array($templates)) {
		 	if(!file_exists($this->articlesTemplatesPath)) {
		 		mkdir($this->articlesTemplatesPath);
		 	}
			if(file_exists($this->articlesTemplatesPath)) {			
				foreach ($templates as $template) {
					$filename = $this->articlesTemplatesPath.'/template_'.$template['title'].'.html';
					if(file_put_contents($filename, $template['content']) === false) {
						return false;
					} else {
						@chmod($filename, 0777);
					}
				}
			} else {
				return false;
			}
	 	} else {
	 		// @TODO удалить всё из папки шаблонов
	 	}
		return true;
	}
	
	/**
	 * Возвращает содержимое файла с шаблоном
	 *
	 * @param string $templateName - название шаблона (без расширения, например index, article)
	 * @return string
	 */
	 function getArticleTemplate($templateName) {
	 	$fileName = $this->articlesTemplatesPath.'/template_'.$templateName.'.html';
		if(file_exists($fileName)) {
			return file_get_contents($fileName);
		} else {
			return null;
		}
	}
	
	/**
	 * Функция replaceArticleKeywords замещает все ключевые фразы уровня статьи на соответствующее содержимое
	 * @param string $content - Текст, в котором будут производиться замены
	 * @param array [] $article - Ассоциативный массив, содержащий в себе статью
	 */
	 function replaceArticleKeywords($content, $article) {
		preg_match_all('/%ARTICLE\[([^\]]+)\]%/s', $content, $matches);
		if(is_array($matches[1])) {
			foreach ($matches[1] as $key=>$field) {
				if($field=='URL' && empty($article[strtolower($field)])) {
					//костыль для commercialrealty.ru
					$content = str_replace($matches[0][$key], $article['id'].'.html', $content);
				} else {
					$content = str_replace($matches[0][$key], $article[strtolower($field)], $content);
				}
			}
		}
		return $content;
	}
	
	/**
	 * Функция replacePageKeywords замещает все ключевые фразы уровня страницы на соответствующее содержимое
	 * @param string $content - Текст, в котором будут производиться замены
	 * @param array [] $article - Ассоциативный массив, содержащий в себе статью
	 */
	function replacePageKeywords($content, $article) {
		preg_match_all('/%PAGE\[([^\]]+)\]%/s', $content, $matches);
		if(is_array($matches[1])) {
			foreach ($matches[1] as $key=>$field) {
				$content = str_replace($matches[0][$key], $article[strtolower($field)], $content);
			}
		}
		return $content;
	}
	
	/**
	 * Функция replacePageKeywords замещает все ключевые фразы уровня категории на соответствующее содержимое
	 * @param string $content - Текст, в котором будут производиться замены
	 * @param array [] $article - Ассоциативный массив, содержащий в себе статью
	 */
	function replaceCategoryKeywords($content, $article) {
		preg_match_all('/%CATEGORY\[([^\]]+)\]%/s', $content, $matches);
		if(is_array($matches[1])) {
			foreach ($matches[1] as $key=>$field) {
				if($field=='URL' && empty($article[strtolower($field)])) {
					$content = str_replace($matches[0][$key], $article['id'].'/', $content);
				} else {
					$content = str_replace($matches[0][$key], $article[strtolower($field)], $content);
				}
				
			}
		}
		return $content;
	}
	
	/**
	 * Функция Подчищает контент, удаляя незаменённые ключевые слова.
	 * @param $content
	 */
	function clearAllKeywords($content) {
		preg_match_all('/(%[^\]]+\[[^\]]+\]%)/sU', $content, $matches);
		return preg_replace('/(%\w+\[\w+\]%)/isU', "", $content);
	}
	
	function getArticles($options = array()) {
		$articles = array();
		if(file_exists($this->articlesDbFilename)) {
			$db = unserialize(file_get_contents($this->articlesDbFilename));
			if(isset($db['articles']) && $db['articles']) {
				foreach ($db['articles'] as $key => $article) {
					
					
					$include = true;
					if(isset($options['where']) && is_array($options['where'])) {
						/*
						 * where => array(
						 * 	"category_id" => 5, 
						 * 	"in_rss" => true
						 * )
						 */
						foreach($options['where'] as $column => $value) {
							/* статья не подходит по заданному критерию */
							if($article[$column] != $value) {
								$include = false;
								break;
							}
						}
					}
					
					if($include) {
					
						if(isset($options['annotation']) && $options['annotation']) {
							$annotationFilename = $this->articlesPath.'/article_'.$article['id'].'_annotation.txt';
							if(file_exists($annotationFilename)) {
								$article['annotation'] = file_get_contents($annotationFilename);
							} else {
								$article['annotation'] = '';
							}
						}
						
						if(isset($options['text']) && $options['text']) {
							$textFilename = $this->articlesPath.'/article_'.$article['id'].'_text.txt';
							if(file_exists($textFilename)) {
								$article['text'] = file_get_contents($textFilename);
							} else {
								$article['text'] = '';
							}
						}
						
						$article['link'] = $this->getArticleURL($article);
						
						$articles[] = $article;
					}
				}
			}
		}
		return $articles;
	}
	
	/**
	 * Получить URL для статьи
	 * @param array $article
	 * @return string
	 */
	function getArticleURL($article) {
		/* Генерируем URL для статьи */
		if(isset($article['url']) && $article['url']) {
			$url = $article['url'];
		} else {
			$url = null;
			$urlParts = array();
			$category = $this->findCategory($article['category_id']);
			// Если категория есть и их больше одной. Если одна - то мы её опускаем
			if($category && count($this->getArticleCategoris()) > 1) {
				if(isset($category['url']) && $category['url']) {
					$urlParts[] = $category['url'];
				} else {
					$urlParts[] = $category["id"];
				}
			}
		
			$urlParts[] = $article['id'].'.html';
			$url = implode("/", $urlParts);
			if($this->articlesIndexpageURL) {
				$url = str_replace("//", "/", $this->articlesIndexpageURL . '/' . $url);
			}
		}
		
		return $url;
	}
	
	/**
	 * Получить список статейных категорий
	 * @return array
	 */
	function getArticleCategoris() {
		if(!isset($this->categories)) {
			$this->categories = array();
			if(file_exists($this->articlesDbFilename)) {
				$db = unserialize(file_get_contents($this->articlesDbFilename));
				if(isset($db['categories']) && $db['categories']) {
					foreach ($db['categories'] as $category) {
						// url может быть не задан вовсе, быть пустым, задан как относительный или абсолютный
						if(isset($category['url']) && $category['url'] && $category['url'][0] == '/') {
							$category['link'] = $category['url'];
						} else {
							$category['link'] = str_replace("//", "/", $this->articlesIndexpageURL . '/' . (isset($category['url']) && $category['url'] ?  $category['url'] : $category['id']) . '/');
						}
						$this->categories[] = $category;
					}
				}
			}
		}
		return $this->categories;
	}
	
	function getRssArticles() {
		$options = array(
			"where" => array(
				"in_rss" => 1
			), 
			"annotation" => true, 
			"text" => true
		);
		return $this->getArticles($options);
	}
	
	/**
	 * Найти категорию статьи по её id
	 * @param integer $id
	 * @return array
	 */
	function findCategory($id) {		
		foreach($this->getArticleCategoris() as $category) {
			if($category['id'] == $id) {
				return $category;
			}
		}
	}
	
	
	/**
	 * Выводит список категорий, если их более 1й, или сразу статьи, если категория только одна
	 *
	 * @return string
	 */
	 function getArticlesHTMLCode() {
		$fairyTale = null;
	 	if(file_exists($this->articlesDbFilename)) {
			$db = unserialize(file_get_contents($this->articlesDbFilename));
			$indexTemplate = $this->getArticleTemplate('index');
			
			//если есть категории и их больше 1й, то надо выводить список категорий:
	
			if(count($db['categories'])>1) {
				$item = $this->getArticleTemplate('category_list');
				$wrapper = "<!--[ISSUES_RUBRICS]-->".$this->getArticleTemplate('category_list_wrapper')."<!--[/ISSUES_RUBRICS]-->";
				
				
				$fairyTale = '';
				foreach ($db['categories'] as $row) {
					$html = $this->replaceCategoryKeywords($item, $row);
					$fairyTale .= $html;
				}
				
				$page = array(
					'title'=> $this->articlesIndexpageTitle
				);
				
				$fairyTale = str_replace("%CONTENT%", $fairyTale, $wrapper);
				$fairyTale = str_replace("%CONTENT%", $fairyTale, $indexTemplate);
				$fairyTale = $this->replacePageKeywords($fairyTale, $page);
			} else {
				$item = $this->getArticleTemplate('article_list');
				$wrapper = $this->getArticleTemplate('article_list_wrapper');
				
				
				$fairyTale = '';
				$articles = $this->getArticles();
				if($articles) {
					foreach ($articles as $article) {
						$article['annotation'] = file_get_contents($this->articlesPath.'/article_'.$article['id'].'_annotation.txt');
						$html = $this->replaceArticleKeywords($item, $article);
						$fairyTale .= $html;
					}
				}
				
				$page = array(
					'title'=> $this->articlesIndexpageTitle
				);
				
				$fairyTale = str_replace("%CONTENT%", $fairyTale, $wrapper);
				$fairyTale = str_replace("%CONTENT%", "<!--[ISSUES_ARTICLES]-->".$fairyTale."<!--[/ISSUES_ARTICLES]-->", $indexTemplate);
				$fairyTale = $this->replacePageKeywords($fairyTale, $page);
			}
			$fairyTale = $this->clearAllKeywords($fairyTale);
	 	}
		return $fairyTale;
	}
	
	/**
	 * Сгенерируем HTML блок для последних статей
	 * @param integer $limit - количество последних статей
	 * @return string
	 */
	function renderLastArticlesBlock($limit) {
		$articles = $this->getArticles(array(
				"annotation" => true
		));
		$articles = array_slice($articles, 0, $limit);
		$template = $this->getArticleTemplate("last_articles_list");
		/* 
		 * Шаблон такого вида:
		 * <div class="last_articles">
		 * {articles}
		 * <article><h1>{title}</h1><p>{annotation}</p></article>
		 * {/articles}
		 * </div>
		 */
		if(preg_match('/{articles}(.*){\/articles}/ims', $template, $m)) {
			$itemTemplate = $m[1];
			$html = "";
			foreach ($articles as $article) {
				$replaces = array();
				foreach($article as $column => $value) {
					$replaces["{".$column."}"] = $value;
				}
				$html .= str_replace(array_keys($replaces), array_values($replaces), $itemTemplate);
			}
			$template = str_replace($m[0], $html, $template);
		}
		
		return $template;
	}
	
	/**
	 * Функция getArticlesByCategory возвращает HTML содержимое статей, находящихся в одной категории
	 * @param integer $categoryId
	 * @return string
	 */
	function getArticlesByCategory($categoryId) {
		if(file_exists($this->articlesDbFilename)) {
			$db = unserialize(file_get_contents($this->articlesDbFilename));
			
			$indexTemplate = $this->getArticleTemplate('index');
			$item = $this->getArticleTemplate('article_list');
			$wrapper = $this->getArticleTemplate('article_list_wrapper');
			
			$fairyTale = '';
			$categoriesCount = 0;
			
			//найдём категорию
			foreach ($db['categories'] as $row) {
				if($row['id']==$categoryId) {
					$category = $row;
					break;
				}
			}
			
			//найдём статьи в этой категории:
			if(!empty($db['articles'])) {
				foreach ($db['articles'] as $article) {
					if($article['category_id']==$category['id']) {
						$article['annotation'] = file_get_contents($this->articlesPath.'/article_'.$article['id'].'_annotation.txt');
						$html = $this->replaceArticleKeywords($item, $article);
						$fairyTale .= $html;
						$categoriesCount++;
					}
				}
			}
			
			$page = array(
					'title'=>'Статьи - '.$category['name']
			);
			
			$fairyTale = str_replace("%CONTENT%", $fairyTale, $wrapper);
			$fairyTale = $this->replaceCategoryKeywords($fairyTale, $category);
			$fairyTale = str_replace("%CONTENT%", "<!--[ISSUES_ARTICLES]-->".$fairyTale."<!--[/ISSUES_ARTICLES]-->", $indexTemplate);
			$fairyTale = $this->replacePageKeywords($fairyTale, $page);
			
			return $fairyTale;
		}
	}
	
	/**
	 * Функция getArticleHTMLCode возвращает HTML содержимое страницы заданной статьи
	 * @param integer $id - Номер статьи
	 * @return string
	 */
	function getArticleHTMLCode($id) {
		if(file_exists($this->articlesDbFilename)) {
			$db = unserialize(file_get_contents($this->articlesDbFilename));
			$ar = null;
			$html = null;
			foreach ($db['articles'] as $article) {
				if($article['id']==$id) {
					$ar = $article;
					break;
				}
			}
	
			if($ar) {
				//найдём категорию
				foreach ($db['categories'] as $row) {
					if($row['id']==$ar['category_id']) {
						$category = $row;
						break;
					}
				}
			
				$ar['text'] = file_get_contents($this->articlesPath.'/article_'.$ar['id'].'_text.txt');
				$indexTemplate = $this->getArticleTemplate('index');
				$articleTemplate = $this->getArticleTemplate('article');
				$html = str_replace("%CONTENT%", "<!--[ISSUES_ARTICLES]-->".$articleTemplate."<!--[/ISSUES_ARTICLES]-->", $indexTemplate);
				$html = $this->replaceArticleKeywords($html, $ar);
				$html = $this->replaceCategoryKeywords($html, $category);
			}
			$page = array('title'=>$ar['name']);
			$html = $this->replacePageKeywords($html, $page);
			$html = $this->clearAllKeywords($html, $page);
			return $html;
		}
	}
		
	
	function clearBanners() {
		$errors = null;
		if(file_exists($this->bannersPath)) {
			$handle = opendir($this->bannersPath);
			while(false !== ($file = readdir($handle))) {
				if($file!="."&&$file!="..") {
				  if(!unlink($this->bannersPath."/".$file)) {
				  	$errors[] = "Can't remove file ".$this->bannersPath."/".$file;
				  }
				}
			}
			if(file_exists($this->bannersDbFilename)) {
				if(!file_put_contents($this->bannersDbFilename, serialize(array()))) {
					$errors[] = "Can't write settings to ".$this->bannersDbFilename;
				}
			}
		}
		return $errors;
	}
	
	/**
	 * Method saves the banner. If exists - rewrites it, else - create new record in banners.db and new banner_id.ext file
	 *
	 * @param banner[] Array
	 */
	 function saveBanner($banner) {
	 	
	 	$errors = null;
	 	
	 	//если категории нет, то надо создать:
	 	if(!file_exists($this->bannersPath)) {
	 		if(!mkdir($this->bannersPath, 0777)) {
	 			$errors[] = "Невозможно создать дирикторию: ".$this->bannersPath;
	 			return false;
	 		}
	 	}
	 	
	 	//если нет файлика banners.db, то надо его создать
	 	if(!file_exists($this->bannersDbFilename)) {
	 		if(!file_put_contents($this->bannersDbFilename, serialize(array()))) {
	 			$errors[] =  "Невозможно создать файл banners.db";
	 			return false;
	 		}
	 	}
	 	
	 	$saved = false;
	 	
		//Сохраним Картинку и Флешку:
		if(isset($banner['data'])) {
			$filename =$this->bannersPath."/banner_".$banner['id'].".".$banner['ext'];
			$saved = file_put_contents($filename,  $banner['data']);
			unset($banner['data']);
			if(!$saved) {
				$errors[] = "Невозможно сохранить файл $filename";
			}
		}
		if(isset($banner['flash_data'])) {
			$filename = $this->bannersPath."/banner_".$banner['id']."_flash.swf";
			$saved = file_put_contents($filename,  $banner['flash_data']);
			unset($banner['flash_data']);
			if(!$saved) {
				$errors[] = "Невозможно сохранить файл $filename";
			}
		}
		
		//если существует файл banners.db
		if(file_exists($this->bannersDbFilename)) {
			//взять информацию оттуда, найти баннер по айди. или добавить новый, если не найдём
			$contents = file_get_contents($this->bannersDbFilename);
			$bannersDB = unserialize($contents);
			
			$found = false;
			if(!empty($bannersDB)) {
				foreach($bannersDB as $key=>$bnr) {
					if($bnr['id'] == $banner['id']) {
						$bannersDB[$key] = $banner;
						$found = true;
						break;
					}
				}
			}
			
			//если мы не нашли баннер, то надо его добавить
			if(!$found) $bannersDB[] = $banner;
			
			$saved = file_put_contents($this->bannersDbFilename, serialize($bannersDB));
			if(!$saved) {
				$errors[] = "Can't save data to ".$this->bannersDbFilename;
			}
		}
		
		return $errors;
	}
	
	 function getBannerHTMLCode($placenumber) {
	 	
	 	$bannersPath = str_replace('\\', '/', $this->bannersPath);
	 	$baseURL = str_replace($_SERVER['DOCUMENT_ROOT'], "", $bannersPath);
	 	
	 	
	 	
		$bannersHTMLCode = '';
		if(file_exists($this->bannersDbFilename)) {
			$contents = file_get_contents($this->bannersDbFilename);
			$banners = unserialize($contents);
    		
			foreach ($banners as $banner) {
				if($this->pageCompare($this->_request_uri, $banner['site_page']) && $banner['placenumber'] == $placenumber) {
					if(file_exists($this->bannersPath."/banner_".$banner['id']."_flash.swf")) {
		    		  	//Баннер подходит, формируем HTML-Code
		    		  	$bannerFileName = "banner_".$banner['id'].".".$banner['ext'];
		    		    $url = $baseURL . '/'.$bannerFileName;
		    		    $bannersHTMLCode .= '
		    		  	  <span id="flash_'.$banner['id'].'">
		      			   <a href="http://'.$banner['link_url'].'"><img id="banner_n_'.$banner['id'].'" style="border:0" src="'.$url.'" width="'.$banner['width'].'" height="'.$banner['height'].'" /></a>
		    			  </span>';
		    		  	
		    		  	$file = $baseURL."/banner_".$banner['id']."_flash.swf";
		    		  	$bannersHTMLCode .= '
		    		  	<script type="text/javascript">
		    			  swfobject.embedSWF("'.$file.'", "flash_'.$banner['id'].'", "'.$banner['width'].'", "'.$banner['height'].'", "9.0.0");
		    			</script>';
					}
					else {
						//Баннер подходит, формируем HTML-Code
						$bannerFileName = "banner_".$banner['id'].".".$banner['ext'];
						$url = $baseURL . '/'.$bannerFileName;
						$bannersHTMLCode .= '<a href="http://'.$banner['link_url'].'"><img id="banner_n_'.$banner['id'].'" style="border:0" src="'.$url.'" class="bannerImage" width="'.$banner['width'].'" height="'.$banner['height'].'"/></a>';
	    			}
				}
			}
			if(strlen($bannersHTMLCode)) {
		   		//надо бы это делать в хедере сайта
	    		$bannersHTMLCode = '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js"></script>'.$bannersHTMLCode;
			}
		}
		return $bannersHTMLCode;
	}
}

/**
 * Класс служит для отправки и приёма пост-данных
 * @author Gourry Gabriev
 *
 */
class Communicator {
	var $DataArray;
	var $ParamsArray;
	var $host;
	var $linkatorPath;
	var $userAgent;
	
	function Communicator($host, $linkatorPath, $data=null, $params=null) {
		$this->host = $host;
		$this->linkatorPath = $linkatorPath;
		$this->DataArray = $data;
		$this->ParamsArray = $params;
		$this->userAgent = 'LinkatorAdministrator';
	}
	
	function fileGetContent($filename) {
		$content = '';
		if(file_exists($filename)) {
			$size = filesize($filename);
			$fd = fopen($filename, "rb");
			$content = fread($fd, $size);
			fclose($fd);
		}
		else {
			return null;
		}
		return $content;
	}
	
	function filePutContent($filename, $content) {
		$fd = fopen($filename, "wb");
		fwrite($fd, $content);
		fclose($fd);
		return "File Saved OK";
	}
	
	function setData($array) {
		$this->DataArray = $array;
	}
	 function setParams($array) {
		$this->ParamsArray = $array;
	}
	 function createPOSTHeader() {
		$header = "POST ".$this->linkatorPath."/executer.php HTTP/1.1\r\n";
		$header .= "Host: ".$this->host."\r\n";
		$header .= "Referer: ".$this->host."/\r\n";
		$header .= "User-Agent: ".$this->userAgent."\r\n";
		return $header;
	}
	 function arrayToString($array, $key) {
		if(is_array($array)) {
			$data = '';
			foreach ($array as $nkey=>$value) {
				$data.=$this->arrayToString($value, $key."[".$nkey."]");
			}
			return $data;
		}
		else {
			return $key."=".urlencode($array)."&";
		}
	}
	
	/**
	 * Добавляет в созданный post header информацию о длине данных и сами данные
	 *
	 * @param String $header - Уже сформированный POST заголовок
	 * @param String $dataName - Имя отправляемого массива, например, "links" = links[0][url]
	 * @return $header - полностью сформированный заголовок с данными
	 */
	 function putData($header, $dataName, $paramsName = 'parameters') {
		$data = $this->arrayToString($this->DataArray, $dataName).$this->arrayToString($this->ParamsArray, $paramsName);
		$length = strlen($data)-1;
		$data = substr($data, 0, $length);
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: ".$length."\r\n\r\n";
		$header .= $data."\r\n\r\n";
		return $header;
	}
	 function putFile($header, $filename, $FileName, $FieldName) {
		if(file_exists($filename)) {
			$content = $this->fileGetContent($filename);
			$header .= "Content-type: multipart/form-data, boundary=AaB03x\r\n";
			
			$hBody = "--AaB03x\r\n";
        	$hBody .="Content-Disposition: form-data; name=\"".$FieldName."\"; filename=\"".$FileName."\"\r\n";
        	$hBody .="Content-Type: application/octet-stream\r\n";
        	$hBody .="Content-Transfer-Encoding: binary\r\n\r\n";
			$hBody .= $content."\r\n";
			$hBody .="--AaB03x--\r\n";
			
			$header .= "Content-Length: ".strlen($hBody)."\r\n\r\n";
			$header .=$hBody;
					
		}
		return $header;
	}
	/**
	 * Отправляет на заданный сервер POST заголовок
	 *
	 * @param String $header - предварительно сформированный POST заголовок (с данными)
	 * @return $response - Ответ от скрипта, принимающего посланный запрос.
	 */
	 function send($header) {
		$response = '';
		$socket = fsockopen($this->host, 80, $errno, $errstr, 10);
		if($socket) {
			fputs($socket, $header);
			$response = fread($socket, 2048);
			$response = $this->ParseAnswer($response);
			$response = $response['body'];
		}
		else {
			$response = "Невозможно установить соединение с ".$this->host.": ".$errstr.".";
		}
		return $response;
	}
	function ParseAnswer( $content )
	{
		$end_headers = strpos( $content, "\r\n\r\n" );
		$header = substr( $content, 0, $end_headers + 2 );
		$body = substr( $content, $end_headers + 4 );
		
		if(strpos($header, "Transfer-Encoding: chunked")) {
			$pos_1 = strpos($body, "\n");
			$pos_2 = strrpos($body, "0");
			$body = substr($body, $pos_1+1, $pos_2 - $pos_1-1);
		}
		
		$answer['headers'] = $header;
		$answer['body'] = $body;
		return $answer;
	}
}
?>