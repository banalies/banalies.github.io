<?php
if(!function_exists('file_get_contents')) {
	function file_get_contents($filename) {
		$content = null;
		if(file_exists($filename)) {
			$fd = fopen($filename, "rb");
			if($fd) {
				$size = filesize($filename);
				$content = fread($fd, $size);
				fclose($fd);
			}
		}
		return $content;
	}
}

if(!function_exists('file_put_contents')) {
	function file_put_contents($filename, $data) {
		$fd = fopen($filename, "wb");
		if($fd) {
			fwrite($fd, $data);
			fclose($fd);
			return strlen($data);
		} else {
			return false;
		}
	}
}