<?php
require_once "code_3.php";

class LinkatorDeefknil extends DeefknilClient {
	var $dataPath;
	/**
	 *
	 * @var string обёртка для каждой ссылки
	 */
	var $_link_wrapper = null;
	
	function LinkatorDeefknil($options) {
		if(isset($options['data_path'])) {
			$this->dataPath = $options['data_path'];
		} else {
			$this->dataPath = dirname(__FILE__);
		}
		if (isset($options['_link_wrapper'])) {
			$this->_link_wrapper = $options['_link_wrapper'];
		}
		parent::DeefknilClient($options);
	}
	
	function lc_read($filename) {
		$fp = @fopen($filename, 'rb');
		@flock($fp, LOCK_SH);
		if ($fp) {
			clearstatcache();
			$length = @filesize($filename);
			
			if(phpversion() < 5.3 && function_exists("set_magic_quotes_runtime")) {
				@$mqr = get_magic_quotes_runtime();
				@set_magic_quotes_runtime(0);
			}
			
			if ($length) {
				$data = @fread($fp, $length);
			} else {
				$data = '';
			}
			if(isset($mqr)) {
				@set_magic_quotes_runtime($mqr);
			}
			
			@flock($fp, LOCK_UN);
			@fclose($fp);
	
			return $data;
		}
	
		return $this->raise_error("Cann't get data from the file: " . $filename);
	}
	
	function load_links() {
        if ($this->lc_multi_site) {
            $this->lc_links_db_file = $this->dataPath . '/linkfeed.' . $this->lc_host . '.links.db';
        } else {
            $this->lc_links_db_file = $this->dataPath . '/linkfeed.links.db';
        }

        if (!is_file($this->lc_links_db_file)) {
            if (@touch($this->lc_links_db_file, time() - $this->lc_cache_lifetime)) {
                @chmod($this->lc_links_db_file, 0666);
            } else {
                return $this->raise_error("There is no file " . $this->lc_links_db_file  . ". Fail to create. Set mode to 777 on the folder.");
            }
        }

        if (!is_writable($this->lc_links_db_file)) {
            return $this->raise_error("There is no permissions to write: " . $this->lc_links_db_file . "! Set mode to 777 on the folder.");
        }

        @clearstatcache();

        if (filemtime($this->lc_links_db_file) < (time()-$this->lc_cache_lifetime) ||
           (filemtime($this->lc_links_db_file) < (time()-$this->lc_cache_reloadtime) && filesize($this->lc_links_db_file) == 0)) {

            @touch($this->lc_links_db_file, time());

            $path = '/' . DEEFKNIL_USER . '/' . strtolower( $this->lc_host ) . '/' . strtoupper( $this->lc_charset);

            if ($links = $this->fetch_remote_file($this->lc_server, $path)) {
                if (substr($links, 0, 12) == 'FATAL ERROR:') {
                    $this->raise_error($links);
                } else if (@unserialize($links) !== false) {
                    $this->lc_write($this->lc_links_db_file, $links);
                } else {
                    $this->raise_error("Cann't unserialize received data.");
                }
            }
        }

        $links = $this->lc_read($this->lc_links_db_file);
        $this->lc_file_change_date = gmstrftime ("%d.%m.%Y %H:%M:%S",filectime($this->lc_links_db_file));
        $this->lc_file_size = strlen( $links);
        if (!$links) {
            $this->lc_links = array();
            $this->raise_error("Empty file.");
        } else if (!$this->lc_links = @unserialize($links)) {
            $this->lc_links = array();
            $this->raise_error("Cann't unserialize data from file.");
        }

        if (isset($this->lc_links['__linkfeed_delimiter__'])) {
            $this->lc_links_delimiter = $this->lc_links['__linkfeed_delimiter__'];
        }

        $lc_links_temp=array();
        foreach($this->lc_links as $key=>$value){
          $lc_links_temp[rawurldecode($key)]=$value;
        }
        $this->lc_links=$lc_links_temp;


        if ($this->lc_ignore_tailslash && $this->lc_request_uri[strlen($this->lc_request_uri)-1]=='/') $this->lc_request_uri=substr($this->lc_request_uri,0,-1);
	    $this->lc_links_page=array();
        if (array_key_exists($this->lc_request_uri, $this->lc_links) && is_array($this->lc_links[$this->lc_request_uri])) {
            $this->lc_links_page = array_merge($this->lc_links_page, $this->lc_links[$this->lc_request_uri]);
        }
	    if ($this->lc_ignore_tailslash && array_key_exists($this->lc_request_uri.'/', $this->lc_links) && is_array($this->lc_links[$this->lc_request_uri.'/'])) {
            $this->lc_links_page =array_merge($this->lc_links_page, $this->lc_links[$this->lc_request_uri.'/']);
        }

        $this->lc_links_count = count($this->lc_links_page);
    }
    
    function return_links($n = null) {
    	$result = '';
    	if (isset($this->lc_links['__linkfeed_start__']) && strlen($this->lc_links['__linkfeed_start__']) != 0 &&
    			(in_array($_SERVER['REMOTE_ADDR'], $this->lc_links['__linkfeed_robots__']) || $this->lc_force_show_code)
    	) {
    		$result .= $this->lc_links['__linkfeed_start__'];
    	}
    
    	if (isset($this->lc_links['__linkfeed_robots__']) && in_array($_SERVER['REMOTE_ADDR'], $this->lc_links['__linkfeed_robots__']) || $this->lc_verbose) {
    
    		if ($this->lc_error != '') {
    			$result .= $this->lc_error;
    		}
    
    		$result .= '<!--REQUEST_URI=' . $_SERVER['REQUEST_URI'] . "-->\n";
    		$result .= "\n<!--\n";
    		$result .= 'L ' . $this->lc_version . "\n";
    		$result .= 'REMOTE_ADDR=' . $_SERVER['REMOTE_ADDR'] . "\n";
    		$result .= 'request_uri=' . $this->lc_request_uri . "\n";
    		$result .= 'charset=' . $this->lc_charset . "\n";
    		$result .= 'is_static=' . $this->lc_is_static . "\n";
    		$result .= 'multi_site=' . $this->lc_multi_site . "\n";
    		$result .= 'file change date=' . $this->lc_file_change_date . "\n";
    		$result .= 'lc_file_size=' . $this->lc_file_size . "\n";
    		$result .= 'lc_links_count=' . $this->lc_links_count . "\n";
    		$result .= 'left_links_count=' . count($this->lc_links_page) . "\n";
    		$result .= 'n=' . $n . "\n";
    		$result .= '-->';
    	}
    
    	if (is_array($this->lc_links_page)) {
    		$total_page_links = count($this->lc_links_page);
    
    		if (!is_numeric($n) || $n > $total_page_links) {
    			$n = $total_page_links;
    		}
    
    		$links = array();
    
    		for ($i = 0; $i < $n; $i++) {
    			$links[] = array_shift($this->lc_links_page);
    		}
    
    		if($this->_link_wrapper) {
    			 
    			$this->lc_links_delimiter = "";
    			 
    			//обернём каждую ссылочку в нашу обёртку
    			foreach ($links as $key=>$value) {
    				$links[$key] = str_replace("{link}", $value, $this->_link_wrapper);
    			}
    
    			$result .= implode($this->lc_links_delimiter, $links);
    			 
    		} else {
    			if ( count($links) > 0 && isset($this->lc_links['__linkfeed_before_text__']) ) {
    				$result .= $this->lc_links['__linkfeed_before_text__'];
    			}
    			 
    			$result .= implode($this->lc_links_delimiter, $links);
    
    			if ( count($links) > 0 && isset($this->lc_links['__linkfeed_after_text__']) ) {
    				$result .= $this->lc_links['__linkfeed_after_text__'];
    			}
    		}
    	}
    	if (isset($this->lc_links['__linkfeed_end__']) && strlen($this->lc_links['__linkfeed_end__']) != 0 &&
    			(in_array($_SERVER['REMOTE_ADDR'], $this->lc_links['__linkfeed_robots__']) || $this->lc_force_show_code)
    	) {
    		$result .= $this->lc_links['__linkfeed_end__'];
    	}
    	return $result;
    }
}