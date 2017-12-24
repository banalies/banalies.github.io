<?php

require_once dirname(__FILE__) . '/code_2.php';

if(!defined('FORCE_START') || FORCE_START == true) {
	$combiner = new Combiner();
	if(function_exists('ob_start') && $combiner->allowStart) {
		ob_start(array($combiner, 'replace_content'), 0, false);
	}
}