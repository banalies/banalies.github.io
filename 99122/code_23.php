<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once('lib/code_12.php');
//---------------�������� ����������. ����� �������� ����� ����� � �������� ����������
if(isset($_FILES['updateLinkatorFile'])) {
	if(!empty($_FILES['updateLinkatorFile']) && $_FILES['updateLinkatorFile']!='.' && $_FILES['updateLinkatorFile']!='..') {		
		
		$fileName = $_FILES['updateLinkatorFile']['name'];
		
		//�������� �� �������� ������������
		$data = array('key'=>$_POST['key'], 'host'=>$_SERVER['HTTP_HOST']);
		$communicator = new Communicator('linkator.oridis.ru', "/communicator", $data);
		$header = $communicator->createPOSTHeader();
		$header = $communicator->putData($header, "checkUpdate");
		$comminicatorResult = $communicator->send($header);
		echo $comminicatorResult;
		if(false !== strpos($comminicatorResult, "VALID_USER")) {		
			if(move_uploaded_file($_FILES['updateLinkatorFile']['tmp_name'], $fileName)) {
				echo "OK";
			} else {
				if(!is_writable($fileName)) {
					echo "���� ���������� ��� ������.";
				} else {
					echo "���������� �������� ����.";
				}
			}
		}
	} else {
		echo "�������� ��� �����";
	}
}