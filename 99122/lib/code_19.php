<?php

class RssBuilder  {
	
	function rss($conf) {
		$wrapper = '<?xml version="1.0" encoding="utf-8"?>
		<rss xmlns:yandex="http://news.yandex.ru" xmlns:media="http://search.yahoo.com/mrss/" version="2.0">
		{channel}
		</rss>';
		
		$channelTemplate = '
	 	<channel>
    		<title><![CDATA[{title}]]></title>
    		<link>{link}</link>
    		<description><![CDATA[{description}]]></description>
    		{image_tag}
    		{items}
    	</channel>
		';
		
		$imageTemplate = '
		<image>
      			<url>{image}</url>
      			<title><![CDATA[{title}]]></title>
      			<link>{link}</link>
    	</image>';
		
		$itemTemplate = '
		<item>
     		<title><![CDATA[{title}]]></title>
      		<link>{link}</link>
      		<description><![CDATA[{description}]]></description>
      		<pubDate>{pubdate}</pubDate>
      		<yandex:full-text><![CDATA[{yandex:full-text}]]></yandex:full-text>
    	</item>';
		
		$itemsHTML = "";
		if($conf['entries']) foreach($conf['entries'] as $item) {
			$itemsHTML .= str_replace(
				array('{title}', '{link}', '{description}', '{pubdate}', '{yandex:full-text}'),
				array($item['title'], $item['link'], $item['description'], $this->rfcDate($item['pubDate']), $item['yandex:full-text']),
			$itemTemplate);
		}
		
		if(isset($conf['image'])) {
			$imageHTML = str_replace(
					array("{image}", "{title}", "{link}"), 
					array($conf['image'], $conf['title'], $conf['link']), 
					$imageTemplate);
		}
		
		$channelHTML = str_replace(
			array('{title}', '{link}', '{description}', '{pubdate}', '{image_tag}', '{items}'), 
			array($conf['title'], $conf['link'], $conf['description'], $this->rfcDate($conf['pubDate']), $imageHTML, $itemsHTML),
			$channelTemplate);
		
		return str_replace("{channel}", $channelHTML, $wrapper);
	}
	
	function rfcDate($date) {
		if(is_numeric($date)) {
			$pubDate = $date;
		} else {
			$pubDate = strtotime($date);
		}
		return  date('r', $pubDate);
	} 
	
}

?>