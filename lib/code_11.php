<?php
require_once ('code_10.php');

class LinkatorKniltsurtClient extends KniltsurtClient {
	var $dataPath;
	
	function LinkatorKniltsurtClient($options) {
		if(isset($options['data_path'])) {
			$this->dataPath = $options['data_path'];
		} else {
			//очень нежелательное поведение. Но оставим для совместимости.
			$this->dataPath = dirname(__FILE__);
		}
		parent::KniltsurtClient($options);
	}
	
	function getTemplateFilename() {
		return $this->dataPath."/".$this->tl_template.".tpl.html";
	}
	
	function lc_read($filename) {
		$fp = @fopen($filename, 'rb');
		@flock($fp, LOCK_SH);
		if ($fp) {
			clearstatcache();
			$length = @filesize($filename);
			if(phpversion() <  4.3 && @get_magic_quotes_gpc()){
				@$mqr = get_magic_quotes_runtime();
				@set_magic_quotes_runtime(0);
			}
			if ($length) {
				$data = @fread($fp, $length);
			} else {
				$data = '';
			}
			if(isset($mqr)){
				@set_magic_quotes_runtime($mqr);
			}
			@flock($fp, LOCK_UN);
			@fclose($fp);
	
			return $data;
		}
	
		return $this->raise_error("Can't get data from the file: " . $filename);
	}
	
	function load_links() {
		if ($this->tl_multi_site) {
			$this->tl_links_db_file = $this->dataPath . '/trustlink.' . $this->tl_host . '.links.db';
		} else {
			$this->tl_links_db_file = $this->dataPath . '/trustlink.links.db';
		}
		
		if (!$this->setup_datafile($this->tl_links_db_file)){
			return false;
		}
		
		//cache
		if ($this->tl_cache){
			//check dir
			if (!is_dir($this->dataPath .'/'.$this->tl_cache_dir)) {
				if(!@mkdir($this->dataPath .'/'.$this->tl_cache_dir, 0777)){
					return $this->raise_error("There is no dir " . $this->dataPath .'/'.$this->tl_cache_dir  . ". Fail to create. Set mode to 777 on the folder.");
				}
			}
			//check dir rights
			if (!is_writable($this->dataPath .'/'.$this->tl_cache_dir)) {
				return $this->raise_error("There is no permissions to write to dir " . $this->tl_cache_dir . "! Set mode to 777 on the folder.");
			}
		
			for ($i=0; $i<$this->tl_cache_size; $i++){
				$filename=$this->cache_filename($i);
				if (!$this->setup_datafile($filename)){
					return false;
				}
			}
		}
		
		@clearstatcache();
		
		//Load links
		if (filemtime($this->tl_links_db_file) < (time()-$this->tl_cache_lifetime) ||
				(filemtime($this->tl_links_db_file) < (time()-$this->tl_cache_reloadtime) && filesize($this->tl_links_db_file) == 0)) {
		
			@touch($this->tl_links_db_file, time());
		
			$path = '/' . KNILTSURT_USER . '/' . strtolower( $this->tl_host ) . '/' . strtoupper( $this->tl_charset);
			//http://db.trustlink.ru/28e2e4d9e5c0e7e7c4c7e3d35cfd8887ca3c1546/aquamaster.org/cp1251
		
			if ($links = $this->fetch_remote_file($this->tl_server, $path)) {
				if (substr($links, 0, 12) == 'FATAL ERROR:' && $this->tl_debug) {
					$this->raise_error($links);
				} else{
					if (@unserialize($links) !== false) {
						$this->lc_write($this->tl_links_db_file, $links);
						$this->tl_cache_update = true;
					} else if ($this->tl_debug) {
						$this->raise_error("Cans't unserialize received data.");
					}
				}
			}
		}
		
		if ($this->tl_cache && !$this->lc_is_synced_cache()){
			$this->tl_cache_update = true;
		}
		
		if ($this->tl_cache && !$this->tl_cache_update){
			$this->tl_cache_cluster = $this->page_cluster($this->tl_request_uri,$this->tl_cache_size);
			$links = $this->lc_read($this->cache_filename($this->tl_cache_cluster));
		}else{
			$links = $this->lc_read($this->tl_links_db_file);
		}
		
		$this->tl_file_change_date = gmstrftime ("%d.%m.%Y %H:%M:%S",filectime($this->tl_links_db_file));
		$this->tl_file_size = strlen( $links);
		
		if (!$links) {
			$this->tl_links = array();
			if ($this->tl_debug)
				$this->raise_error("Empty file.");
		} else if (!$this->tl_links = @unserialize($links)) {
			$this->tl_links = array();
			if ($this->tl_debug)
				$this->raise_error("Can't unserialize data from file.");
		}
		
		
		if (isset($this->tl_links['__trustlink_delimiter__'])) {
			$this->tl_links_delimiter = $this->tl_links['__trustlink_delimiter__'];
		}
		
		if ($this->tl_test)
		{
			if (isset($this->tl_links['__test_tl_link__']) && is_array($this->tl_links['__test_tl_link__']))
				for ($i=0;$i<$this->tl_test_count;$i++)
				$this->tl_links_page[$i]=$this->tl_links['__test_tl_link__'];
				if ($this->tl_charset!='DEFAULT'){
				$this->tl_links_page[$i]['text']=iconv("UTF-8", $this->tl_charset, $this->tl_links_page[$i]['text']);
				$this->tl_links_page[$i]['anchor']=iconv("UTF-8", $this->tl_charset, $this->tl_links_page[$i]['anchor']);
				}
				} else {
		
				$tl_links_temp=array();
				foreach($this->tl_links as $key=>$value){
					$tl_links_temp[rawurldecode($key)]=$value;
				}
				$this->tl_links=$tl_links_temp;
		
						if ($this->tl_cache && $this->tl_cache_update){
						$this->lc_write_cache($this->tl_links);
				}
		
				$this->tl_links_page=array();
				if (array_key_exists($this->tl_request_uri, $this->tl_links) && is_array($this->tl_links[$this->tl_request_uri])) {
				$this->tl_links_page = array_merge($this->tl_links_page, $this->tl_links[$this->tl_request_uri]);
				}
				}
		
				$this->tl_links_count = count($this->tl_links_page);
	}
	
	function lc_write($filename, $data) {
		$fp = @fopen($filename, 'wb');
		if ($fp) {
			@flock($fp, LOCK_EX);
			/*
			 * Иногда возникают проблемы, когда установлена mb_internal_encoding(UTF-8)
			 * Поэтому явно указываем, что данные у нас в latin1
			 */
			if(function_exists('mb_strlen')) {
				$length = mb_strlen($data, 'latin1');
			} else {
				$length = strlen($data);
			}
			
			@fwrite($fp, $data, $length);
			@flock($fp, LOCK_UN);
			@fclose($fp);
	
			if (md5($this->lc_read($filename)) != md5($data)) {
				return $this->raise_error("Integrity was violated while writing to file: " . $filename);
			}
	
			return true;
		}
	
		return $this->raise_error("Can't write to file: " . $filename);
	}
}

?>