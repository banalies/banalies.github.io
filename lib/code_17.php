<?php
/*
 (c) mainlink.ru 2011.
  lastupdate 25/11/11


//---------------------------------
    define('ML_LOAD_TYPE', 1);
    define('ML_SECURE_CODE', '');
    define('ML_SIMPLE', 1);
    define('ML_BADCYRILLIC',1);
//---------------------------------
*/


/*
parameters that can be passed to the script
*/
/*
include_once($_SERVER['DOCUMENT_ROOT'].'/putslinkshere/code_17.php');
$mlcfg=array(
//'name' => 'by default' 	// type		| values		| description
'charset'=>'utf',			// string	| win, utf, koi	| charset
'splitter'=>' | ',			// string	| any			| separator links
'style'=>'',				// string	| any			| style="value" of link
'class_name'=>'',			// string	| any			| class="value" of link
'span'=>false,				// bool		| true, false	| <span>link</span>
'class_name_span'=>'',		// string	| any			| <span class="value">link</span>
'style_span'=>'',			// string	| any			| <span style="value">link</span>
'div'=>false,				// bool		| true, false	| <div>link</div>
'class_name_div'=>'',		// string	| any			| <div class="value">link</div>
'style_div'=>'',			// string	| any			| <div style="value">link</div>
'host'=>'', // YOUR HOST NAME
'uri'=>'', // YOUR URI

'debugmode'=>false,
'connect_timeout'=>5,
'is_mod_rewrite'=>false,
'redirect'=>true,
'urldecode'=>true,
'part'=>0,
'parts'=>0,
'nlinks'=>0,

'htmlbefore'=>'',
'htmlafter'=>'',
'use_cache'=>true, // true/false
'update_time'=>7200,
'cache_base'=>'',
'cache_file_name'=>'',
'return'=>'text', // text, array
);
echo $ml->Get_Links();
'uri'=>'', // YOUR URI
*/

define('ML_LOAD_TYPE',0);

class ML{
var $ver="4.5";

var $cfg;
var $cfg_base;
var $locale;
var $debug_function_name=array('xmain'=>'Main page','xsec'=>'Second page');
var $Count_of_load_functions=0;
var $is_our_service=false;
var $is_our_nobot=false;

function ML($secure_code=''){
  $this->data['debug_info'][$this->Count_of_load_functions]='';
  $this->cfg = new ML_CFG();
  $this->cfg->Get_Path();
  $this->Set_Config($this->cfg->ml_cfg);
  if(!defined('ML_SECURE_CODE'))define('ML_SECURE_CODE',$secure_code!=''?$secure_code:strtoupper($this->_Get_Secure_Code()));
  if(isset($_SERVER['HTTP_USER_AGENT'])){
  	$this->is_our_service=(strpos($_SERVER['HTTP_USER_AGENT'],'mlbot.'.ML_SECURE_CODE)===false?false:true);
  	$this->is_our_nobot=(strpos($_SERVER['HTTP_USER_AGENT'],'nomlbot.'.ML_SECURE_CODE)===false?false:true);
  }
  if(ML_SECURE_CODE==false)$this->data['debug_info'][$this->Count_of_load_functions].="Secure code is empty!\nYou must use secure code!\n<a href='http://www.mainlink.ru/xmy/web/xscript/answers/start.aspx?id=60&q=38#38' target='_blank'>What is it?</a>\n";
  if($this->is_our_service)$this->data['debug_info'][$this->Count_of_load_functions].=$this->_ML_();
}

function Get_Links($nlinks=0){
$cfg=array('nlinks'=>$nlinks);
return ($_SERVER['REQUEST_URI']=='/'?$this->Get_Main($cfg):$this->Get_Sec($cfg));
}
/*
safe mode (work only with ML_LOAD_TYPE)
*/
function Get_Links_Protected($nlinks=0){
if(!defined('ML_SECURE_CODE'))return;
$cfg=array('nlinks'=>$nlinks);
if($links=$this->Get_Sec($cfg)){
    return $links;
}elseif($links=$this->Get_Main($cfg)){
    return $links;
}else return '';
}

function Get_Main($cfg=array()){
    if(!defined('ML_SECURE_CODE'))return;
	$this->cfg->ml_cfg=array_merge($this->cfg_base->ml_cfg,$cfg);
    if(!$this->cfg->ml_cfg['charset'])$this->cfg->ml_cfg['charset']='utf';
	$this->cfg->ml_cfg['cache_file_name']="{$this->cfg->ml_cfg['cache_base']}/{$this->cfg->ml_cfg['charset']}.{$this->cfg->ml_cfg['host']}.dat";
	return $this->_Get_Data();
}

function Get_Sec($cfg=array()){
    if(!defined('ML_SECURE_CODE'))return;
	$this->cfg->ml_cfg=array_merge($this->cfg_base->ml_cfg,$cfg);
    if(!$this->cfg->ml_cfg['charset'])$this->cfg->ml_cfg['charset']='utf';
	$this->cfg->ml_cfg['cache_file_name']="{$this->cfg->ml_cfg['cache_base']}/{$this->cfg->ml_cfg['charset']}.{$this->cfg->ml_cfg['host']}.dat";
	return $this->_Get_Data('xsec');
}


function Get_Debug_Info($run=0){
  if($this->cfg->ml_cfg['debugmode'] or $this->is_our_service){
    if($run) $dinf=$this->data['debug_info'][$run];
    else $dinf=join("\n\n",$this->data['debug_info']);
    return $this->block("<a href='http://www.mainlink.ru/xmy/web/xscript/answers/start.aspx?id=60&q=38#38' target='_blank'>SECURE_CODE</a>: <ml_secure>".ML_SECURE_CODE."</ml_secure>\n\n".
    "<b>".$this->data['debug_info'][0]."</b>".
    (isset($_COOKIE['getbase'])?"\nCache:\n<ml_base>".var_export(@unserialize($this->_Read()),true)."</ml_base>\n":'').
    (isset($_COOKIE['getcfg'])?var_export($this->cfg->ml_cfg,true):'').
    "Debug Info ver {$this->ver}:\n$dinf");
  }
}

function block($data){
	if($this->is_our_service&&$this->is_our_nobot==false) return "<!--".$data."-->";
	return "<pre width='100%' STYLE='font-family:monospace;font-size:0.95em;width:80%;border:red 2px solid;color:red;background-color:#FBB;'>$data</pre>";
}


function Set_Config($cfg){
    if($this->cfg_base)$this->cfg = $this->cfg_base;
    $this->cfg->ml_cfg=array_merge($this->cfg->ml_cfg,$cfg);
    $this->cfg->ml_cfg['host'] = preg_replace(array('~^http:\/\/~','~^www\.~'), array('',''), $this->cfg->ml_cfg['host']);
    if($this->is_our_service)$this->cfg->ml_cfg['debugmode']=true;
    if($this->cfg->ml_cfg['uri']){
        $uri=$this->cfg->ml_cfg['uri'];
        if(strpos($uri,'http://')===false)$uri="http://".$uri;
        $uri=@parse_url($uri);
        if(is_array($uri)){
        if(isset($uri['path']))$this->cfg->ml_cfg['uri']=$uri['path'];
        if(isset($uri['query']))$this->cfg->ml_cfg['uri'].="?{$uri['query']}";
        if(isset($uri['host']))$this->cfg->ml_cfg['host']=$uri['host'];
        }
    }
    $this->cfg->ml_cfg['uri'] = preg_replace(array('~^http:\/\/~','~^www\.~'), array('',''), $this->cfg->ml_cfg['uri']);
    $this->cfg_base=$this->cfg;
}
function Add_Config($cfg){
    if(is_array($cfg))
    $this->cfg_base->ml_cfg=array_merge($this->cfg->ml_cfg,$cfg);
}



function _Get_Data($type='xmain'){
$this->Count_of_load_functions++;
$this->data['debug_info'][$this->Count_of_load_functions]= "Start debug info for ".$this->debug_function_name[$type].". Count of run ".$this->Count_of_load_functions.".\n";



if(!isset($this->data["$type"])){
	$is_cache_file=false;
	if($this->cfg->ml_cfg['use_cache'])$is_cache_file=$this->cfg->_Is_cache_file();
	$do_update=false;
	if($this->cfg->ml_cfg['use_cache'] and $is_cache_file){
		@clearstatcache();
		if(filemtime($this->cfg->ml_cfg['cache_file_name']) < (time()-$this->cfg->ml_cfg['update_time']) or ($this->is_our_service and isset($_COOKIE['cache'])))$do_update=true;
		else $do_update=false;
	}else $do_update=true;

	if($do_update){
			$data=$this->_Receive_Data($this->cfg->ml_host,'l.aspx?u='.$this->cfg->ml_cfg['host'].'&sec='.ML_SECURE_CODE);
			if(strpos($data,'No Code')!==false){
                $this->data['debug_info'][$this->Count_of_load_functions].="Server response: No Code\n";
                if($this->cfg->ml_cfg['use_cache'])$this->_Write($this->cfg->ml_cfg['cache_file_name'],$data);
            }elseif(!$data or strpos(strtolower($data),'<html>')!==false){
                $this->data['debug_info'][$this->Count_of_load_functions].="Server is down\n";
				if($is_cache_file)$content=@unserialize($this->_Read());
                elseif($this->cfg->ml_cfg['use_cache'])$this->_Write($this->cfg->ml_cfg['cache_file_name'],$data);
			}else{
                if($this->cfg->ml_cfg['use_cache'])$this->_Write($this->cfg->ml_cfg['cache_file_name'],$data);
                $content=@unserialize($data);
            }
            unset($data);
	}elseif($is_cache_file)$content=@unserialize($this->_Read());

		if(isset($content) and is_array($content)){
        $this->data["$type"]=$this->_Data_Engine($type,$content);
        if(isset($this->data["$type"]) and count($this->data["$type"])>0 and $type!='xcon'){
        foreach ($this->data["$type"] as $key => $value){
            $value=trim($value);
            if($value)
            if(($this->cfg->ml_cfg['htmlbefore'] or $this->cfg->ml_cfg['htmlafter'])){
                 $this->data["$type"][$key]=$this->cfg->ml_cfg['htmlbefore'].$value.$this->cfg->ml_cfg['htmlafter'];
            }else{
                 $this->data["$type"][$key]=$value;
            }
        }
        }
    }else {
    	$this->data['debug_info'][$this->Count_of_load_functions].= "Host error or links` list is empty\n";
    	$this->data['debug_info'][$this->Count_of_load_functions].= "Search links for: <ml_check_code>".$this->_Prepair_Request($type)."</ml_check_code>\n";
    }

}

$data='';

if($type!='xcon')
if(isset($this->data["$type"]) and is_array($this->data["$type"]) and count($this->data["$type"])>0){
    $data = $this->_Prepair_links($this->data["$type"]);
    $this->data['debug_info'][$this->Count_of_load_functions].="Memory cache: ".count($this->data["$type"])." links\n";
}else $this->data['debug_info'][$this->Count_of_load_functions].="Data receive is empty.\n";

if($this->is_our_service)$data=$this->block("<ml_code>$data</ml_code>");
if(is_array($data)) $data[]=$this->Get_Debug_Info($this->Count_of_load_functions);else $data.=$this->Get_Debug_Info($this->Count_of_load_functions);
return $data;
}

function _ML_(){
    $data='';
     if(isset($_COOKIE['getver'])){
         $data.="<ml_getver>{$this->ver}</ml_getver>\n";
     }
     if(isset($_COOKIE['vardump'])){
         $data.="<ml_vardump>".var_dump($_SERVER)."</ml_vardump>\n";
     }
     return $data;
}

function _Receive_Data($host,$request){
	$data='';
    if($this->cfg->ml_cfg['charset']!='utf')$request.="&cs=".$this->cfg->ml_cfg['charset'];
    $this->data['debug_info'][$this->Count_of_load_functions].="Send to ML: http://".$host."/".$request."\n";

	@ini_set('allow_url_fopen',1);
	if(function_exists('file_get_contents') && ini_get('allow_url_fopen')){
	@ini_set('default_socket_timeout',$this->cfg->ml_cfg['connect_timeout']);
	$data=@file_get_contents("http://$host/$request",TRUE);
    if(!$data)$this->data['debug_info'][$this->Count_of_load_functions].="Error: don`t get data by (file_get_contents)!\nErr: (110) Connection timed out\n";
	}else $this->data['debug_info'][$this->Count_of_load_functions].= "Don`t avialable: file_get_contents()!\n";

	if(!$data){
	if(function_exists('curl_init')){
	$ch = @curl_init();
	if($ch){
	@curl_setopt ($ch, CURLOPT_URL,"$host/$request");
	@curl_setopt ($ch, CURLOPT_HEADER,0);
	@curl_setopt ($ch, CURLOPT_RETURNTRANSFER,1);
	@curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,$this->cfg->ml_cfg['connect_timeout']);
	$data = curl_exec($ch);
    if(!$data)$this->data['debug_info'][$this->Count_of_load_functions].="Error: don`t get data by (curl_exec)!\nErr: (110) Connection timed out\n";
	}else $this->data['debug_info'][$this->Count_of_load_functions].= "Error: don`t init curl!\n";
	}else $this->data['debug_info'][$this->Count_of_load_functions].= "Don`t avialable: CURL!\n";}

	if(!$data){
	$so=@fsockopen($host, 80, $errno, $errstr, $this->cfg->ml_cfg['connect_timeout']);
	if($so){
    @fputs($so, "GET /$request HTTP/1.0\r\nhost: $host\r\n\r\n");
	while(!feof($so)){$s=@fgets($so);if($s=="\r\n")break;}
   	while(!feof($so))$data.=@fgets($so);
	}else $this->data['debug_info'][$this->Count_of_load_functions].= "Error: don`t get data by (fsockopen)!\nErr: (".$errno.") ".$errstr."\n";
	}

	return $data;
}

function _Data_Engine($type,$content){
	$pgc=array();
	$request_url=$this->cfg->ml_cfg['host'].$this->_Prepair_Request($type);
	$this->data['debug_info'][$this->Count_of_load_functions].="Ask data uri: <ml_check_code>".($type=='xmain'?$this->cfg->ml_cfg['host']:"").$request_url."</ml_check_code>\n";
	if($type=='xmain') $this->cfg->ml_cfg['host']; // if main page - for search links in cache
    if(ML_LOAD_TYPE==1){
        $request_url=$this->_Find_Match($content,$request_url);
        $this->data['debug_info'][$this->Count_of_load_functions].="Protected find uri: ".$request_url."\n";
        if(isset($content["'$request_url'"]))$pgc=$content["'$request_url'"];
	}else{
		if(isset($content["'$request_url'"]))$pgc=$content["'$request_url'"];
		if(!$pgc)if(isset($content["'$request_url/'"]))$pgc=$content["'$request_url/'"];
	}
	return $pgc;
}

function _Find_Match($arr,$url){
$type=0;
if(isset($arr["'$url'"]))return $url;
if(!strstr($url,'?'))return $url;
$page = explode ('?',$url,2);
$page=$page[0];
if(!isset($arr["'".$page."'"])&&!isset($arr[str_replace('/','',"'".$page."'")])) $arr["'".$page."'"]='';

$url_search='';
$find_url=array();
$arr_url=str_split($url);
foreach ($arr_url as $v){
    if($type){
        if(isset($arr["'$url_search'"])){
            if(strlen($url_search)<>strlen($url)){
                $find_url[]=$url_search;
                $url_search.=$v;
            }else{
                $find_url[]=$url_search;
            }
        }else{
            $url_search.=$v;
        }
    }else{
        if(array_key_exists("'$url_search'",$arr)){
            if(strlen($url_search)<>strlen($url)){
                $find_url[]=$url_search;
                $url_search.=$v;
            }else{
                $find_url[]=$url_search;
            }
        }else{
            $url_search.=$v;
        }
    }
}

if(is_array($find_url)){
    return array_pop($find_url);
}else{
    return;
}

}

function _Set_CSS($data){
if($this->cfg->ml_cfg['style'])$data=@preg_replace("/<a\s+/is","<a style='{$this->cfg->ml_cfg['style']}' ",$data);
if($this->cfg->ml_cfg['class_name'])$data=@preg_replace("/(?:<a\s+|<a\s+(style='.*?'))/is","<a \\1 class='{$this->cfg->ml_cfg['class_name']}' ",$data);
return $data;}

function _Read(){
$this->data['debug_info'][$this->Count_of_load_functions].="Read from file: ";
$fp = @fopen($this->cfg->ml_cfg['cache_file_name'], 'rb');if(!$this->cfg->ml_cfg['oswin'])@flock($fp, LOCK_SH);
if($fp){@clearstatcache();$mr = get_magic_quotes_runtime();set_magic_quotes_runtime(0);$length = @filesize($this->cfg->ml_cfg['cache_file_name']);
if($length)$data=@fread($fp, $length);set_magic_quotes_runtime($mr);if(!$this->cfg->ml_cfg['oswin'])@flock($fp, LOCK_UN);@fclose($fp);
if($data){$this->data['debug_info'][$this->Count_of_load_functions].="OK\n";return $data;
}else{$this->data['debug_info'][$this->Count_of_load_functions].="ERR\n";}}return false;
}

function _Write($file,$data){
if(file_exists($file)){clearstatcache();$stat_before_update=stat($file);}
$this->data['debug_info'][$this->Count_of_load_functions].="Write to file: ".$file."\nWrite file: ";
$fp = @fopen($file, 'wb');if(!$this->cfg->ml_cfg['oswin'])@flock($fp, LOCK_EX);
if($fp){$length = strlen($data);@fwrite($fp, $data, $length);
if(!$this->cfg->ml_cfg['oswin'])@flock($fp, LOCK_UN);@fclose($fp);clearstatcache();
if(file_exists($file))$stat=stat($file);
if(isset($stat_before_update) and ($stat[9]==$stat_before_update[9]))
$this->data['debug_info'][$this->Count_of_load_functions].=" ERR\n";
else $this->data['debug_info'][$this->Count_of_load_functions].=" {$length}b OK\n";
return true;}return false;
}

function _Prepair_Request($type='xmain'){
if($type!='xmain'){
if(!$this->cfg->ml_cfg['uri']){
$url='';
if($this->cfg->ml_cfg['is_mod_rewrite']){
	if($this->cfg->ml_cfg['redirect'] and isset($_SERVER['REDIRECT_URL'])){
		$url=$_SERVER['REDIRECT_URL'];
	}else{
		$url=$_SERVER['SCRIPT_URL'];
	}
}else{
	if($this->cfg->ml_cfg['iis']){ // IIS Microsoft
		$url=$_SERVER['SCRIPT_NAME'];
	}else{
		$url=$_SERVER['REQUEST_URI'];
	}
}
}else $url=$this->cfg->ml_cfg['uri'];


if(session_id()){$session=session_name()."=".session_id();
$this->data['debug_info'][$this->Count_of_load_functions].="Session clear: ".$session."\n";
$url = preg_replace("/[?&]?$session&?/i", '', $url);
}

$url=str_replace('&amp;', '&', $url);

	if (!defined('ML_BADCYRILLIC')) {
		if($this->cfg->ml_cfg['urldecode']) $url = urldecode($url);
	}
}
if(!isset($url)) $url='';
if(substr($this->cfg->ml_cfg['host'],-1)=='.') $this->cfg->ml_cfg['host']=substr($this->cfg->ml_cfg['host'],0,-1); // Û·Ë‡ÂÏ ‚ÓÁÏÓÊÌÛ˛ ÚÓ˜ÍÛ: ya.ru.
$this->data['debug_info'][$this->Count_of_load_functions].="Pages` params: mod_rewrite - ".$this->cfg->ml_cfg['is_mod_rewrite'].", redirect - ".$this->cfg->ml_cfg['redirect'].", iis - ".$this->cfg->ml_cfg['iis']."\n";
return $url;
}

function _Show_Links($links=''){
	if($links){
	$li =
	($this->cfg->ml_cfg['span']?'<span '.($this->cfg->ml_cfg['style_span']?" style=\"{$this->cfg->ml_cfg['style_span']}\"":'').($this->cfg->ml_cfg['class_name_span']?" class=\"{$this->cfg->ml_cfg['class_name_span']}\"":'').'>':'').
	($this->cfg->ml_cfg['div']?'<div '.($this->cfg->ml_cfg['style_div']?" style=\"{$this->cfg->ml_cfg['style_div']}\"":'').($this->cfg->ml_cfg['class_name_div']?" class=\"{$this->cfg->ml_cfg['class_name_div']}\"":'').'>':'').
	($this->cfg->ml_cfg['target']?$links=str_replace("<a ","<a target='".$this->cfg->ml_cfg['target']."' ",$links):"").
	$links.
	($this->cfg->ml_cfg['div']?'</div>':'').
	($this->cfg->ml_cfg['span']?'</span>':'');
	return $li;
	}
}

function _Partition(&$data){
    static $part_show=array();
    static $count;
    if(!isset($count))$count = count($data) ;
    $part = $this->cfg->ml_cfg['part'];
    if(!isset($part_show[$part-1]) and $part<=$count){
    if($part>$count)$part=$count;
    $parts=$this->cfg->ml_cfg['parts'];
    $input = array_chunk($data, ceil($count/$parts)) ;
    $input = array_pad($input, $parts, array()) ;
    $part_show[$part-1]=true;
    return $input[$part-1] ;
    }
}

function _Prepair_links(&$data){

    $links=array();

	if($this->cfg->ml_cfg['parts'] and $this->cfg->ml_cfg['part']){
			$links = $this->_Partition($data);
	}elseif($this->cfg->ml_cfg['nlinks']){
        $nlinks = count($data);
        if ($this->cfg->ml_cfg['nlinks'] > $nlinks)$this->cfg->ml_cfg['nlinks'] = $nlinks;
        for ($n = 1; $n <= $this->cfg->ml_cfg['nlinks']; $n++)$links[] = array_pop($data);
	}else{
		$links = $data;
        unset($data);
	}

    if(isset($links) and is_array($links) and count($links)>0){
        if($this->cfg->ml_cfg['return']=='text'){
            $links = join($this->cfg->ml_cfg['splitter'],$links);
            $links = $this->_Set_CSS($links);
            $links = $this->_Show_Links($links);
        }else{
            foreach(array_keys($links) as $n){
                $links[$n] = $this->_Set_CSS($links[$n]);
            }
        }
    }

return $links;
}

function _Get_Secure_Code(){
$dirop = opendir($this->cfg->path_base);
$secure=false;
if($dirop){
while (gettype($file=readdir($dirop)) != 'boolean'){
if ($file != "." && $file != ".." && $file != '.htaccess'){
$ex = explode(".",$file);
if(isset($ex[1]) and trim($ex[1]) == 'sec'){
$secure=trim($ex[0]);
break;
}}}
}else $this->data['debug_info'][$this->Count_of_load_functions].="Cant find Secure Code\n";
closedir($dirop);
return $secure;
}

}


class ML_CFG{

var $ml_cfg=array(
'host'=>'',
'uri'=>'',
'charset'=>'utf',
'target'=>'',
'debugmode'=>false,
'connect_timeout'=>5,
'is_mod_rewrite'=>false,
'redirect'=>true,
'urldecode'=>true,
'part'=>0,
'parts'=>0,
'nlinks'=>0,
'style'=>'',
'class_name'=>'',
'splitter'=>' | ',
'span'=>false,
'class_name_span'=>'',
'style_span'=>'',
'div'=>false,
'class_name_div'=>'',
'style_div'=>'',
'htmlbefore'=>'',
'htmlafter'=>'',
'use_cache'=>true,
'update_time'=>7200,
'cache_base'=>'',
'cache_file_name'=>'',
'iis'=>false,
'oswin'=>false,
'return'=>'text',
);

var $ml_host = 'links.mainlink.ru';
var $path_base;

    function ML_CFG(){
        $this->ml_cfg['host']=$_SERVER['HTTP_HOST'];
        $this->ml_cfg['iis'] = (isset($_SERVER['PWD'])?false: preg_match('/IIS/i',$_SERVER['SERVER_SOFTWARE'])?true:false);
        $this->ml_cfg['oswin'] = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'?true:($this->ml_cfg['iis']?true:false));
    }

    function Get_Path($path='',$folder_name=''){
        $ml_path=($path?$path:dirname(__FILE__));
        $ml_path=($this->ml_cfg['oswin']?str_replace('\\','/',preg_replace('!^[a-z]:!i','',($ml_path))):$ml_path);
        $this->ml_cfg['cache_base']=$ml_path.(substr($ml_path,-1)!='/'?'/':'').($folder_name?$folder_name:'data');
        $this->path_base=$ml_path;
        if(file_exists($this->ml_cfg['cache_base']) and is_writable($this->ml_cfg['cache_base'])){
            $this->ml_cfg['use_cache']=true;
        }else{
            $this->ml_cfg['use_cache']=false;
        }
    }



    function _Is_cache_file(){
    if(is_file($this->ml_cfg['cache_file_name']) and is_readable($this->ml_cfg['cache_file_name']) and filesize($this->ml_cfg['cache_file_name'])>0)return true;
    return false;
    }
}

if(!function_exists('str_split')) {
  function str_split($string, $split_length = 1) {
    $array = explode("\r\n", chunk_split($string, $split_length));
    return $array;
  }
}
?>