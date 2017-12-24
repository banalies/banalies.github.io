<?php
require_once 'code_8.php';

/**
 * Сохранение настроек
 * Должен:
 * 1. Сохранять настройки
 * 2. Сохранять ссылки
 * 3. Сохранять статьи
 * 4. Сохранять шаблоны статей
 *
 */
class Executer {
	var $dataPath;
	var $settingsFilename;
	var $settings;
	var $errors;
	var $clientIP;
	/**
	 *
	 * @var Linkator
	 */
	var $core;
	/**
	 * определение IP клиента
	 * определение команды check для вывода информации о месте установленного кода
	 * stripslashes_array - может, поместить в отдельный файл?
	 * определение magic_quotes
	 * сохранение ссылок
	 * ...
	 */
	
	function setDataPath($path) {
		$this->dataPath = $path;
		return $this;
	}
	
	function setSettingsFilename($filename) {
		$this->settingsFilename = $filename;
		return $this;
	}
	
	function logError($errorString) {
		$this->errors[] = $errorString;
	}
	
	function loadSettings() {
		if(file_exists($this->settingsFilename)) {
			$this->settings = unserialize(file_get_contents($this->settingsFilename));
		} else {
			$this->logError("Settings file $this->settingsFilename not found");
		}
	}
	
	function detectClientIP() {
		$this->clientIP = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
	}
	
	function getSettings($key = null) {
		if($key) {
			return isset($this->settings[$key]) ? $this->settings[$key] : null;
		} else {
			return $this->settings;
		}
	}
	
	function magicQuotesIsOn() {
		$magicQoutes = strtolower(ini_get("magic_quotes_gpc"));
		
		switch ($magicQoutes) {
			case 1:
			case 'on':
				return true;
			case 0:
			case 'off':
				return false;
			default:
				return false;
		}
	}
	
	
	function setSettings($key, $value) {
		$this->settings[$key] = $value;
	}
	
	function clientHasAccess() {
		if(!$this->clientIP) {
			$this->detectClientIP();
		}
		if(($ips = $this->getAllowedIps()) != null ) {
			return in_array($this->clientIP, $ips);
		} else {
			return true;
		}
	}
	
	function getAllowedIps() {
		$ips = $this->getSettings('LINKTRADE_IPS');
		$ips = explode("\n", $ips);
		if(is_array($ips)) {
			foreach ($ips as $key=>$ip) {
				if(empty($ip)) unset($ips[$key]);
			}
		}
		return !empty($ips) ? array_values($ips) : null;
	}
	
	function dispatch() {
		if(!$this->clientHasAccess()) {
			header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
			exit();
		}

		require_once 'code_12.php';
		$this->core = new Linkator(array(
			'data_path' => $this->dataPath
		));
		
		if($_SERVER['REQUEST_METHOD'] == 'GET') {
			if(isset($_GET['check'])) $this->echoCheckInfo();
			if(isset($_GET['getDbFile'])) echo $this->getDbFile($_GET['dbname']);
			if(isset($_GET['get_server_info'])) {
				$info =  $this->getServerInfo();
				$xml = '<?xml version="1.0" encoding="windows-1251"?><info>';
				foreach($info as $name=>$row) {
					$name = str_replace(array(" ", "."), "_", $name);
					$xml .= '<'.$name.'>'.htmlspecialchars($row['local']).'</'.$name.'>';
				}
				$xml .= '</info>';
				header('Content-type: text/xml; charset=windows-1251');
				echo $xml;
				exit();
			}
			if(isset($_GET['permissions'])) {
				$this->showDataPermissions();
			}
			if(isset($_GET['chmod'])) {
				$this->chmod($this->dataPath);
				exit();
			}
		}
		
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			
			if($this->magicQuotesIsOn()) {
				$_POST = $this->stripSlashes($_POST);
			}
			
			if(isset($_POST['links'])) $this->saveLinks($_POST['links']);
			if(isset($_POST['hosts'])) $this->saveSettings($_POST['hosts']);
			if(isset($_POST['issues'])) $this->saveArticles($_POST['issues']);
			if(isset($_POST['categories'])) $this->saveArticlesCategories($_POST['categories']);
			if(isset($_POST['atemplates'])) $this->saveArticlesTemplates($_POST['atemplates']);
			if(isset($_POST['commands'])) $this->processCommand($_POST['commands']['action']);
			if(isset($_POST['banner'])) $this->saveBanner($_POST['banner']);
			if(isset($_GET['remove_file'])) $this->removeFile($_POST['filename']);			
			if(isset($_GET['sitemap'])) {
				$multipart = $_GET['sitemap'] == 'multipart';
				if(isset($HTTP_RAW_POST_DATA) && !empty($HTTP_RAW_POST_DATA)) {
					$postData = $HTTP_RAW_POST_DATA;
				} else {
					$postData = file_get_contents('php://input');
				}
				$this->saveSitemap($postData, $multipart);
			}
			echo $this->makeReport();
		}
	}
	
	function echoCheckInfo()	{
		$dirName = str_replace("\\", "/", dirname(__FILE__));
		$DOCUMENT_ROOT = str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']);
		$DOCUMENT_ROOT = preg_replace('/(\/)$/', '', $DOCUMENT_ROOT);
		$PATH =  str_replace($DOCUMENT_ROOT."/", "", $dirName);
		$PATH =  str_replace("/lib", "", $PATH);
			
		header('Content-type:text/html; charset=windows-1251');
		echo serialize(array(
				'OK' => 1,
				'linkatorPath' => $PATH, 
				'dirname' => realpath($dirName . DIRECTORY_SEPARATOR . "..") 
		));
		exit();
	}
	
	function getServerInfo() {
		ob_start();
		phpinfo(INFO_CONFIGURATION);
		$info_arr = array();
		$info_lines = explode("\n", strip_tags(ob_get_clean(), "<tr><td><h2>"));
		$cat = "General";
		foreach($info_lines as $line)
		{
			// new cat?
			preg_match("~<h2>(.*)</h2>~", $line, $title) ? $cat = $title[1] : null;
			if(preg_match("~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~", $line, $val))
			{
				$info_arr[$cat][$val[1]] = $val[2];
			}
			elseif(preg_match("~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~", $line, $val))
			{
				$info_arr[strtolower($cat)][strtolower($val[1])] = array("local" => $val[2], "master" => $val[3]);
			}
		}
		return $info_arr['php core'];
	}
	
	function stripSlashes($array) {
		return is_array($array) ? array_map(array($this, "stripSlashes"), $array) : stripslashes($array);
	}
	
	function saveLinks($links) {
		$links = !empty($links) ? $links : array();
		$ok = $this->core->saveLinks($links);
		if(!$ok) {
			$filename = $this->core->getLinksDataBaseFile();
			if(file_exists($filename) && !is_writable($filename)) {
				$this->logError("Can't save links to $filename\nFile exists but not writebale");
			}
			if(!is_writable(dirname($filename))) {
				$this->logError("Can't save links to $filename\nFile not exists and directory is not writable");
			}
		}
	}
	
	function saveSettings($settings) {
		if($settings['kniltsurt_enable']) {
			$trustlinkTemplateSaved = $this->saveTrustlinkTemplate($settings['kniltsurt_template']);
		}
		if(isset($settings['kniltsurt_template'])) {
			unset($settings['kniltsurt_template']);
		}
		
		$serialized = serialize($settings);
		
		$ok = file_put_contents($this->settingsFilename, $serialized);
		if(!$ok) {
			$filename = $this->settingsFilename;
			if(file_exists($filename) && !is_writable($filename)) {
				$this->logError("Can't save links to $filename\nFile exists but not writebale");
			}
			if(!is_writable(dirname($filename))) {
				$this->logError("Can't save links to $filename\nFile not exists and directory is not writable");
			}
		}
	}
	
	function saveTrustlinkTemplate($templateHTML) {
		$filename = $this->dataPath.'/template.tpl.html';
		if(!file_put_contents($filename, $templateHTML)) {
			$this->logError("Can't save template to $filename - check permissions");
			return false;
		} else {
			return true;
		}
	}
	
	function saveArticles($articles) {
		if(!$this->core->saveArticles($articles)) {
			$this->logError("Can't save articles. Check the permissions");
		}
	}
	
	function saveArticlesCategories($categories) {
		if(!$this->core->saveArticlesCategories($categories)) {
			$this->logError("Can't save categories. Check the permissions");
		}
	}
	
	function saveArticlesTemplates($templates) {
		if(!$this->core->saveArticlesTemplates($templates)) {
			$this->logError("Can't save templates. Check the permissions");
		}
	}
	
	function saveSitemap($xmlContent, $multipart = false) {		
		if($multipart && is_numeric($_GET['number'])) {
			$number = $_GET['number'];
			$xmlFilename = 'sitemap_'.$number.'.xml';
			$filename = $this->dataPath . '/' . $xmlFilename;
			$ok = @file_put_contents($filename, $xmlContent);
			if($ok === false) {
				$this->logError("Can't save sitemap.xml. Check permissions");
			}
			
			$sitemapXML = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';			
			for($i = 0; $i <= $number; $i ++) {
				
				//текущий URL-path
				$path = dirname(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
				
				//путь к папке с data
				$dataPath = str_replace('\\', '/', $this->dataPath);
				
				//URL до папки с данными
				$dataUrl = substr($dataPath, strpos($dataPath, $path));
				
				$xmlFilename = 'sitemap_'.($i).'.xml';
				
				$url = 'http://'.$_SERVER['SERVER_NAME'].$dataUrl.'/'.$xmlFilename;
				
				$sitemapXML .= '<sitemap>
      				<loc>'.$url.'</loc>
      				<lastmod>'.date('Y-m-d').'</lastmod>
   				</sitemap>';
			}
			$sitemapXML .= '</sitemapindex>';
			
			$xmlContent = $sitemapXML;
		}
		
		$ok = @file_put_contents($this->dataPath . '/sitemap.xml', $xmlContent);
		if($ok === false) {
			$this->logError("Can't save sitemap.xml. Check permissions");
		}
	}
	
	function processCommand($command) {
		switch ($command) {
			case 'clearAll': {
				if(($errors = $this->core->clearBanners()) != null) {
					$this->logError("Errors has been occured while removing banners: \n".implode("\n", $errors));
				}
				break;
			}
			case 'clearYandexLog': {
				$fileName = $this->dataPath.'/ya.log';
				if(file_exists($fileName)) {
					$size = filesize($fileName);
					$fd = fopen($fileName, "w");
					if($fd) {
						fclose($fd);
					} else {
						$this->logError("Can't write to $fileName. Check the permissions. Size of file = ".round($size / 2014)." Kb.");
					}
				}
				break;
			}
		}
	}
	
	function saveBanner($banner) {
		
		if(isset($banner['data']))
			$banner['data'] = base64_decode($banner['data']);
		 
		if(isset($banner['flash_data']))
			$banner['flash_data'] = base64_decode($banner['flash_data']);
		 
		if(($errors = $this->core->saveBanner($banner)) != null) {
			$this->logError("Can't save banner. Got errors: \n".implode("\n", $errors));
		}
	}
	
	function getDbFile($file) {
		$data = array();
		$httpHost = strtolower(str_replace("www.", "", $_SERVER['HTTP_HOST']));
		switch($file) {
			case 'EPAS': {
				$fileName = $this->dataPath.'/links.db';
				if(file_exists($fileName)) {
					$data = unserialize(file_get_contents($fileName));
				}
				break;
			}
			case 'LFEED': {
				$fileName = $this->dataPath.'/linkfeed.links.db';
				if(file_exists($fileName)) {
					$data = unserialize(file_get_contents($fileName));
				}
				break;
			}
			case 'MEGAINDEX': {
				$fileName = $this->dataPath.'/megaindex.links.db';
				if(file_exists($fileName)) {
					$data = unserialize(file_get_contents($fileName));
				}
				break;
			} 
			case 'SETLINKS': {
				$fileName = $this->dataPath.'/'.$httpHost.'.links';
				if(file_exists($fileName)) {
					$rows = file($fileName);
					$rowsCount = count($rows);
					for($i = 1; $i < $rowsCount; $i++) {
						$rowInfo = explode("\t", $rows[$i]);
	                    $page_ids = explode(' ', $rowInfo[0]);
	                    unset($rowInfo[0]);
						foreach($rowInfo as $link) {
							if (substr($link, 0, 1) == '1') {
								//$setLinksLinksArray[$page_ids[0]][] = substr($link,1);
							}
							elseif(substr($link, 0, 1) == '0')
								$data[$page_ids[0]][] = substr($link,1);
							else
								$data[$page_ids[0]][] = $link;
						}
					}
				}
				break;
			}
			case 'MAINLINK': {
				$fileName = $this->dataPath.'/win.'.$httpHost.'.dat';
				if(file_exists($fileName)) {
					$data = unserialize(file_get_contents($fileName));
				}
				$fileName = $this->dataPath.'/win.'.$httpHost.'.xsec.dat';
				if(file_exists($fileName)) {
					$data_2 = unserialize(file_get_contents($fileName));
					if(!empty($data_2)) foreach($data_2 as $url=>$links) {
						foreach ($links as $link) {
							if(!in_array($link, $data[$url])) {
								$data[$url][] = $link;
							}
						}
					}
				}
				
				if(!empty($data)) foreach ($data as $url=>$items) {
					$key = str_replace(array("'", $httpHost), "", $url);
					$data[$key] = $items;
					unset($data[$url]);
				}
				break;
			}
			case 'KNILTSURT': {
				$fileName = $this->dataPath.'/trustlink.links.db';
				if(file_exists($fileName)) {
					$data = unserialize(file_get_contents($fileName));
				}
				break;
			}
			case 'LINKATOR': {
				$fileName = $this->core->getLinksDataBaseFile();
				if(file_exists($fileName)) {
					$data = unserialize(file_get_contents($fileName));
					if(!empty($data)) {
						$newData = array();
						foreach ($data as $link) {
							if(preg_match('/\[\[(.*?)\]\]/im', $link['text'])) {
								$newData[$link['site_page']][] = preg_replace('/\[\[(.*?)\]\]/im', '<a href="http://'.$link['link_url'].'" >\\1</a>', $link['text']);
							} else {
								$newData[$link['site_page']][] = $link['text'];
							}
						}
						$data = $newData;
						unset($newData);
					}
				}
				break;
			}
		}
		return  serialize($data);
	}
	
	function makeReport() {
		$errors = $this->errors ? $this->errors : array();
		$xml = '<?xml version="1.0" encoding="windows-1251"?>';
		$xml .= '<response>';
		$xml .= '<errors count="'.count($errors).'">';
		foreach ($errors as $error) {
			$xml .= '<error>'.$error.'</error>';
		}
		$xml .= '</errors>';
		$xml .= '</response>';
		return $xml;
	}
	
	function showDataPermissions() {
		$permissions = array(
			'data' => array(
				'writable' => is_writable($this->dataPath), 
				'permissions' => substr(sprintf('%o', fileperms($this->dataPath)), -4)
			)
		);
		
		$dh = opendir($this->dataPath);
		if($dh) {
			while(($filename = readdir($dh)) != false) {
				if($filename != '.' && $filename != '..') {
					$permissions['data']['children'][$filename] = array(
						'writable' => is_writable($this->dataPath . '/' . $filename),
						'permissions' => substr(sprintf('%o', fileperms($this->dataPath . '/' . $filename)), -4)
					);
				}
			}
		}
		
		echo serialize($permissions);
		exit();
	}
	
	function removeFile($filename) {
		$fqFilename = $this->dataPath . '/' . $filename;
		if(is_dir($fqFilename)) {
			$dh = opendir($fqFilename);
			while($f = readdir($dh)) {
				if($f != '.' && $f != '..') {
					unlink($fqFilename . DIRECTORY_SEPARATOR . $f);
				}
			}
			closedir($dh);
			rmdir($fqFilename);
		}
		$success = unlink($this->dataPath . '/' . $filename);
		echo $success ? 'Y' : 'N';
		exit();
	}
	
	function chmod($path) {
		$perm = 0777;
		
		$handle = opendir($path);
		while ( false !== ($file = readdir($handle)) ) {
			if ( $file != '.' && $file != "..") {
				if(@chmod($path . "/" . $file, $perm)) {
					echo '[<span style="color:green">OK</span>]:'.$path . "/" . $file."<br />";
				} else {
					echo '[<span style="color:red">ERR</span>]:'.$path . "/" . $file."<br />";
				}
				if(is_dir($path . DIRECTORY_SEPARATOR . $file)) {
					$this->chmod($path . DIRECTORY_SEPARATOR . $file);
				}
			}
		}
		closedir($handle);
	}
}