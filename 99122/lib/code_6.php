<?php
require_once 'code_5.php';

class LinkatorEpas extends EPAS_client {
	var $_link_wrapper = null;
	var $dataPath = null;
	
	function LinkatorEpas($options) {
		/**
		 * Добавляем свою обёртку для КАЖДОЙ ссылки
		 * @gourry 13.01.2012
		 */
		if (isset($options['_link_wrapper'])) {
			$this->_link_wrapper = $options['_link_wrapper'];
		}
		if(isset($options['data_path'])) {
			$this->dataPath = $options['data_path'];
		} else {
			$this->dataPath = dirname(__FILE__);
		}
		parent::EPAS_client($options);
	}
	
	/**
	 * Вывод ссылок в обычном виде - текст с разделителем
	 *
	 * @param int $n Количествово
	 * @param int $offset Смещение
	 * @param array $options Опции
	 *
	 * <code>
	 * $options = array();
	 * $options['as_block'] = (false|true);
	 * // Показывать ли ссылки в виде блока
	 * </code>
	 *
	 * @see return_block_links()
	 * @return string
	 */
	function return_links($n = null, $offset = 0, $options = null) {
	
		//Опрелелить, как выводить ссылки
		$as_block = $this->_show_only_block;
	
		if(is_array($options) && isset($options['as_block']) && false == $as_block) {
			$as_block = $options['as_block'];
		}
	
		if(true == $as_block && isset($this->_block_tpl)) {
			return $this->return_block_links($n, $offset, $options);
		}
	
		//-------
	
		if (is_array($this->_links_page)) {
	
			$total_page_links = count($this->_links_page);
	
			if (!is_numeric($n) || $n > $total_page_links) {
				$n = $total_page_links;
			}
	
			$links = array();
	
			for ($i = 1; $i <= $n; $i++) {
				if ($offset > 0 && $i <= $offset) {
					array_shift($this->_links_page);
				} else {
					$links[] = array_shift($this->_links_page);
				}
			}
	
			if($this->_link_wrapper) {
				 
				$this->_links_delimiter = "";
				 
				//обернём каждую ссылочку в нашу обёртку
				foreach ($links as $key=>$value) {
					$links[$key] = str_replace("{link}", $value, $this->_link_wrapper);
				}
			}
	
			$html = join($this->_links_delimiter, $links);
	
			// если запрошена определенная кодировка, и известна кодировка кеша, и они разные, конвертируем в заданную
			if (
					strlen($this->_charset) > 0
					&&
					strlen($this->_sape_charset) > 0
					&&
					$this->_sape_charset != $this->_charset
					&&
					function_exists('iconv')
			) {
				$new_html = @iconv($this->_sape_charset, $this->_charset, $html);
				if ($new_html) {
					$html = $new_html;
				}
			}
	
			if ($this->_is_our_bot) {
				$html = '<sape_noindex>' . $html . '</sape_noindex>';
			}
		} else {
			$html = $this->_links_page;
			if ($this->_is_our_bot) {
				$html .= '<sape_noindex></sape_noindex>';
			}
		}
		
		$html = $this->_return_html($html);
	
		return $html;
	}
	
	function _get_db_file() {
		if ($this->_multi_site) {
			return $this->dataPath . '/' . $this->_host . '.links.db';
		} else {
			return $this->dataPath . '/links.db';
		}
	}
}

class LinkatorEpasContext extends EPAS_context {
	var $dataPath;
	function LinkatorEpasContext($options) {
		if(isset($options['data_path'])) {
			$this->dataPath = $options['data_path'];
		} else {
			$this->dataPath = dirname(__FILE__);
		}
		parent::EPAS_context($options);
	}
	
	function _get_db_file() {
		if ($this->_multi_site) {
			return $this->dataPath . '/' . $this->_host . '.words.db';
		} else {
			return $this->dataPath . '/words.db';
		}
	}
	
	function detectSapeCharset($data) {
		// определяем кодировку кеша
		if (isset($data['__sape_charset__'])) {
			$this->_sape_charset = $data['__sape_charset__'];
		} else {
			$this->_sape_charset = '';
		}
	}
	
	/**
	 * Преобразовать строку в нужную кодировку
	 * @param string $string
	 * @return string
	 */
	function processCharset($string) {
		if (
				strlen($this->_charset) > 0
				&&
				strlen($this->_sape_charset) > 0
				&&
				$this->_sape_charset != $this->_charset
				&&
				function_exists('iconv')
		) {
			$string = @iconv($this->_sape_charset, $this->_charset.'//IGNORE', $string);			
		}
		
		return $string;
	}
	
	/**
	 * Преобразовать строку в нужную кодировку
	 * @param string $string
	 * @return string
	 */
	function processReverseCharset($string) {
		if (
				strlen($this->_charset) > 0
				&&
				strlen($this->_sape_charset) > 0
				&&
				$this->_sape_charset != $this->_charset
				&&
				function_exists('iconv')
		) {
			$string = @iconv($this->_charset, $this->_sape_charset.'//IGNORE', $string);
		}
	
		return $string;
	}
}