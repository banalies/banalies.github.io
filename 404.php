<?php
	define('FORCE_START', true);
	define('LINKATOR_404', true);
	
	//error_reporting(E_ALL &~E_NOTICE);
	//ini_set('display_errors', 1);
		
	$dirName = str_replace("\\", "/", dirname(__FILE__));
	$DOCUMENT_ROOT = rtrim(str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']), '/');
	$PATH =  str_replace($DOCUMENT_ROOT, "", $dirName);

	if(($pos = strrpos($PATH, "/")) !== false) {
		$currentDirectoryName = substr($PATH, $pos+1);
	} else {
		$currentDirectoryName = $PATH;
	}
	
	if($DOCUMENT_ROOT != str_replace("/".$currentDirectoryName, "", $dirName))  {
		$SERVER_DOCUMENT_ROOT = $DOCUMENT_ROOT."/".str_replace("/".$currentDirectoryName, "", $PATH);
	} else {
		$SERVER_DOCUMENT_ROOT = $DOCUMENT_ROOT;
	}
	
	if($SERVER_DOCUMENT_ROOT != '/') {
		$SERVER_DOCUMENT_ROOT = rtrim($SERVER_DOCUMENT_ROOT, '/');
	}

	require_once ('iprofit.php');

	$urlInfo = parse_url("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);	
	$fileToInclude = $SERVER_DOCUMENT_ROOT .'/'.str_replace("../", "", urldecode($urlInfo['path'])); 
	$fileToInclude = str_replace("//", "/", $fileToInclude);
	
	
	class Htaccess {
		
		var $path;
		var $docRoot;
		var $options = array();
		var $deep = 0;
		
		function Htaccess($path, $docRoot) {
			$this->path = $path;
			$this->docRoot = str_replace('\\', '/', $docRoot);
		}
		
		/**
		 * Get apache options from .htaccess
		 * @param string $htaccessFilename - .htaccess filename
		 * @return string|null
		 */
		function loadOptionsFromFile($htaccessFilename) {
			$options = array();
			$htaccess = file($htaccessFilename);
			foreach($htaccess as $ht_line) {
				if(strpos($ht_line, 'DirectoryIndex') !== false) {
					if(preg_match('/^DirectoryIndex\s+(.*)$/', $ht_line, $matches)) {
						$options['directory_index'] = trim($matches[1]);
					}
				}
				if(strpos($ht_line, 'ErrorDocument') !== false) {
					if(preg_match('/^ErrorDocument\s+(.*)$/', $ht_line, $matches)) {
						$options['error_document'] = trim($matches[1]);
					}
				}
			}
			return $options;
		}
		
		function getOptions() {
			return $this->options;
		}
		
		function scan($path) {
			if(is_dir($path)) {
				if(file_exists($path . DIRECTORY_SEPARATOR . '.htaccess')) {					
					$options = $this->loadOptionsFromFile($path . DIRECTORY_SEPARATOR . '.htaccess');
					$this->options = array_merge($options, $this->options);
				}
				
				if($this->isSubdir($path, $this->docRoot)) {
					return;
				} else {
					return $this->scan(realpath($path. '/..'));
				}
			} else {
				return false;
			}
		}
		
		function isSubdir($subdir, $dir) {
			return substr($dir, 0, strlen($subdir)) == $subdir;
		}
	}

	/* If requested document is directory - we must find DirectoryIndex */
	if(is_dir($fileToInclude)) {
		
		$htaccess = new Htaccess($fileToInclude, $SERVER_DOCUMENT_ROOT);
		$htaccess->scan(rtrim($fileToInclude, '/'));
		$options = $htaccess->getOptions();
		
		if(isset($options['directory_index'])) {
		
			$directoryIndex = explode(" ", $options['directory_index']);
	
			foreach ($directoryIndex as $key => $indexFilename) 
			{
				$filename = str_replace("//", '/', $fileToInclude."/".trim($indexFilename));
				if(file_exists($filename)) {
					$directoryIndex[$key] = $filename;
				} else {
					unset($directoryIndex[$key]);
				}
			}
	
			$directoryIndex = array_values($directoryIndex);
	
			if($directoryIndex) {
				$fileToInclude = $directoryIndex[0];
			}
		} else {
			$errorMessage =  "No DirectoryIndex in your .htaccess";
		}
	}
	
	if(file_exists($fileToInclude) && preg_match('/\.(html|htm|txt|php)$/', $fileToInclude)) {		
		chdir(dirname($fileToInclude));		
		$_SERVER['PHP_SELF'] = str_replace($SERVER_DOCUMENT_ROOT, "", $fileToInclude);

		if($_SERVER['PHP_SELF'][0] != '/') {
			$_SERVER['PHP_SELF'] = '/'.$_SERVER['PHP_SELF'];
		}
		
		$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
		
		if(ini_get("register_globals")) {
			$PHP_SELF = $_SERVER['PHP_SELF'];
			$SCRIPT_NAME = $PHP_SELF;
		}
		include ($fileToInclude);
		die();
	} else {
		
		header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		
		$htaccess = new Htaccess(dirname($fileToInclude), $SERVER_DOCUMENT_ROOT);
		$htaccess->scan(dirname($fileToInclude));
		
		$options = $htaccess->getOptions();
		if(isset($options['error_document']) && preg_match('/^(\d+)\s*(.*)$/', $options['error_document'], $m)) {
			$code = $m[1];
			$filename = $m[2];
			
			if($filename[0] == '/') {
				include $_SERVER['DOCUMENT_ROOT'] . $filename;
			} elseif(substr($filename, 0, 4) == 'http') {
				header("Location: ".$filename);
			} elseif(file_exists(dirname($fileToInclude) . '/' . $filename)) {
				include dirname($fileToInclude) . '/'.$filename;
			} else {
				echo $filename;
				if(isset($errorMessage)) {
					echo $errorMessage;
				}
				die();
			}
		} else {
			if(isset($errorMessage)) {
				echo $errorMessage;
			}
			die();
		}
	}
?>