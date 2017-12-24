<?php

class Registrator {
	var $dataPath;
	/**
	 *
	 * @var Linkator
	 */
	var $core;
	var $serverAddress;
	
	function setDataPath($path) {
		$this->dataPath = $path;
	}
	
	function setServerAddress($url) {
		$this->serverAddress = $url;
	}
	
	/**
	 * ������ �������� ������� is_writable, ������� ����������� �������� �� Windows ����������
	 * @param string $path - ����������� ��������� ������, ���� ��� ����������
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
	
	function dispatch() {
		
		$homePath = dirname($_SERVER['SCRIPT_NAME']);
		
		require_once 'code_12.php';
		$this->core = new Linkator(array(
			'data_path' => $this->dataPath
		));
		
		$files = array(
			$this->dataPath
		);
		$dataFiles = $this->getDirectoryFiles($this->dataPath);
		$files = array_merge($files, $dataFiles);
		
		$files = $this->analyseFiles($files);
		$errors = $this->extractErrorsFromFiles($files);
		/* ��� �� ��� �� ������ */
		/* if($this->is__writable($this->dataPath.'/../.htaccess')) {
			$errors[] = "PHP ����� ����� �� ������ � ����  ".$homePath.'/.htaccess. ���������� ���������� ������ � ����� ����� ��� PHP ��������.';
		}
		if($this->is__writable($this->dataPath.'/.htaccess')) {
			$errors[] = "PHP ����� ����� �� ������ � ����  ".$this->dataPath.'/.htaccess. ���������� ���������� ������ � ����� ����� ��� PHP ��������.';
		} */
		
		$data = array(
					'linkatorPath'=>$homePath,
					'host'=>$_SERVER['HTTP_HOST'],
					'tunnel_exists'=>file_exists('tunnel.php'),
					'links_placenumbercount'=>1
		);
		if(empty($errors)) {
			$errors = $this->register($data);
		}
		
		$this->display($errors, $data);
	}

	function getDirectoryFiles($directoryPath) {
		$files = array();
		if(is_dir($directoryPath)) {
			$fd = opendir($directoryPath);
			if($fd) {
				while(($fileName = readdir($fd)) != false) {
					if($fileName != '.' && $fileName != '..' && $fileName != '.htaccess') {
						if(is_file($directoryPath.'/'.$fileName)) {
							$files[] = $directoryPath.'/'.$fileName;
						}
					}
				}
			}
		}
		return $files;
	}
	
	function analyseFiles($files) {
		foreach ($files as $key=>$filename) {
			$files[$key] = array(
					'name' => $filename,
					'writable' => $this->is__writable($filename),
					'permissions' => $this->getFilePermissions($filename)
			);
		}
		return $files;
	}
	
	function getFilePermissions($filename) {
		if(file_exists($filename)) {
			return substr(sprintf('%o', fileperms($filename)), -4);
		} else {
			return false;
		}
	}
	
	function extractErrorsFromFiles($files) {
		$errors = array();
		foreach ($files as $file) {
			if(!$file['writable']) {
				$errors[] = "���� ".$file['name'].'['.$file['permissions'].'] ���������� ��� ������.';
			}
		}
		return $errors;
	}
	
	function register($data) {
		
		$communicator = new Communicator($this->serverAddress, "/communicator", $data);
		$header = $communicator->createPOSTHeader();
		$header = $communicator->putData($header, "data");
		$comminicatorResult =  $communicator->send($header);
		
		$errors = null;
		if(strpos($comminicatorResult, "SUCCESS")===false) {
			$errors[] = "� ���������� ��������� ������� �� ������� ��������� ������ :".str_replace("ERROR:", "", $comminicatorResult);
		}
		return $errors;
	}
	
	function display($errors, $data = null) {
		
		$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=windows-1251" />
		<title>����������� ����</title>
		<style>
		div.error ul li {list-style-image:url("http://www.link-trade.ru/img/error.png"); font-size:24px;
		}
		div.ok h2 img {margin-bottom:-9px;
		}
		div.ok .serverResponseTitle {font:normal 16px Arial;
		}
		div.ok .serverResponseText {font:normal 16px Arial; color:#777;}
		</style>
		</head>
		<body>
		<h1>����������� ����</h1>';
		if(!empty($errors)) {
			$html .= '
			<div class="error">
				<h2>� �������� ����������� ��������� ������ ('.count($errors).'):</h2>
				<ul>';
			foreach($errors as $error) {
				$html .= '<li>'.$error.'</li>';
			}
			$html .= '</ul>
			</div>
			';
		} else {
			$html .= '
			<div class="ok">
			<h2><img src="http://www.link-trade.ru/img/success.png" alt="�������"/>����������� ����������� �������</h2>
			<table>
				<tr><td width="200">����:</td><td><strong>'.$data['host'].'</strong></td></tr>
				<tr><td width="200">���� � �������������� ����:</td><td><strong>'.$data['linkatorPath'].'</strong></td></tr>
			</table>
			</div>';
		}
		$html .= '
		</body>
		</html>';
		
		header("Content-type:text/html; charset=windows-1251");
		echo $html;
	}
	
}

?>