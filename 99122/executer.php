<?php
set_include_path(dirname(__FILE__).'/lib'. PATH_SEPARATOR. get_include_path());
@clearstatcache();
require_once 'code_7.php';
ini_set("display_errors", 0);
$executer = new Executer();
$executer->setDataPath(dirname(__FILE__).'/data');
$executer->setSettingsFilename(dirname(__FILE__).'/data/settings.db');
$executer->dispatch();