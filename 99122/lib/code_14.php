<?php
require_once ('code_13.php');

class LinkatorLSClient extends LSClient {
	var $_link_wrapper = '{link}';
	function LinkatorLSClient($uri, $config) {
		parent::LSClient($uri, $config);
		if(isset($config->_link_wrapper)) {
			$this->_link_wrapper = $config->_link_wrapper;
		}
	}
	
	function GetLinks($countlinks=0, $delimiter=false) {
		static $firstlink=true;
	
		if(!$this->IsCached()) {
			if(!$this->DownloadLinks()) {
				if($this->Config->cachetype == "MYSQL") {
					mysql_query("replace into sl_params values ('".$this->host."_errtime', '".time()."')") or $this->Error(mysql_error());
				} else if($this->Config->cachetype == "FILE" && file_exists($this->Config->cachedir.$this->cachefile)) {
					$h = fopen($this->Config->cachedir.$this->cachefile, "r+");
					if($h) {
						$str = fgets($h);
						if(strlen($str) > 25) {
							fseek($h, strlen($str)-11);
							fwrite($h, time());
						}
						fclose($h);
					}
				}
			}
		}
	
	
		$pageid = sprintf("%u", crc32($this->host . $this->uri));
		if ($this->links === false) {
			if($this->Config->cachetype == "MYSQL") {
				$res = mysql_query("select param_value from sl_params where param_name='".$this->host."_delim'") or $this->Error(mysql_error());
				$line = mysql_fetch_assoc($res);
				$this->delimiter = $line['param_value'];
				$res = mysql_query("select param_value from sl_params where param_name='".$this->host."_sid'") or $this->Error(mysql_error());
				$sid = mysql_fetch_assoc($res);
				$res = mysql_query("select links from sl_cache where sid='".intval($sid['param_value'])."' and id='".$this->MysqlEscapeString($pageid)."'") or $this->Error(mysql_error());
				if(mysql_num_rows($res) == 1) {
					$this->links = mysql_fetch_assoc($res);
					$this->links = explode("\t", $this->links['links']);
				} else {
					$this->links = Array();
				}
			} else if($this->Config->cachetype == "FILE") {
				$h = @fopen($this->Config->cachedir.$this->cachefile, "r");
				if($h) {
					$info = explode("\t", @fgets($h));
					$this->servercachetime = $info[0];
					$this->cachetime = $info[1];
					$this->delimiter = $info[2];
					$this->_safe_params = explode(' ',$info[4]);
					$this->_servers = explode(' ',$info[5]);
					$this->links = Array();
	
					while(!feof($h)) {
						$links = explode("\t", @fgets($h));
						$page_ids = explode(' ', $links[0]);
						if ($page_ids[0] == -1 || $page_ids[0] == $pageid || ( isset($page_ids[1]) && $this->Config->use_safe_method && $page_ids[1] == $this->SafeUrlCrc32('http://'.$this->host.$this->uri))) {
							unset($links[0]);
							foreach($links as $link) {
								if (substr($link, 0, 1) == '1')
									$this->context_links[] = substr($link,1);
								else if(substr($link, 0, 1) == '2')
									$this->forever_links[] = substr($link,1);
								else if(substr($link, 0, 1) == '0')
									$this->links[] = substr($link,1);
								else
									$this->links[] = $link;
							}
						}
					}
					@fclose($h);
				}
				$user_ip = (isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR']);
				if($this->Config->show_demo_links || in_array($user_ip, $this->_servers)){
					if($this->links === false || count($this->links)==0)
						$this->links = Array("This is <a href=''>DEMO</a> link!");
					if($this->forever_links === false || count($this->forever_links)==0)
						$this->forever_links = Array("Title: <a href=''>link</a> demo.<br/>This is <a href=''>DEMO FOREVER</a> link.");
				}
			}
		}
	
		//$this->ModerMessage("Page links: ".var_export($this->links,1)."\n".var_export($_SERVER,1));
	
		if ($countlinks == -1) return true;
	
		$returnlinks = Array();
		$cnt = count($this->links);
		if ($countlinks > 0) $cnt = min($cnt, $this->curlink+$countlinks);
		for (; $this->curlink < $cnt; $this->curlink++) {
			$returnlinks[] = str_replace("{link}", $this->links[$this->curlink], $this->_link_wrapper);
		}
	
		$user_ip = (isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR']);
		if ($this->Config->show_comment || (!empty($this->_servers) && in_array($user_ip, $this->_servers)) ) {
			$this->_show_comment = true;
		} else {
			$this->_show_comment = false;
		}
	
		$retstring = (($firstlink && $this->_show_comment) ? '<!--'.substr($this->Config->password, 0, 5).'-->' : '')
		.implode(($delimiter===false ? $this->delimiter : $delimiter), $returnlinks);
		$firstlink = false;
	
		return $retstring . $this->GetModerMessage();
	}
}

?>