<?php
set_include_path(dirname(__FILE__).'/lib'. PATH_SEPARATOR. get_include_path());
require_once 'code_18.php';

$registrator = new Registrator();
$registrator->setDataPath(dirname(__FILE__).'/data');
$registrator->setServerAddress("linkator.oridis.ru");
$registrator->dispatch();