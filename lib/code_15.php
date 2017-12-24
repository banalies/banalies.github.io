<?php
/*
 Код вызова ссылок SetLinks.ru.
 Версия 4.0.0
*/
define('SETLINKS_CODE_VERSION', '4.0.0');

class LSConfig {
    var $aliases = Array(); // алиасы сайтов. без www, в нижнем регистре. пример: Array("sitealias.ru" => "mainsite.ru", "sitealias2.ru" => "mainsite.ru")
    var $userId = null;
    var $password = '';  // Пароль
    var $encoding = 'WINDOWS-1251'; // Необходимая вам кодировка. (WINDOWS-1251, UTF-8, KOI8-R)
    var $server = 'show.setlinks.ru'; // сервер с которого берутся коды ссылок
    var $cachetimeout = 3600;  // Время обновления кэша в секундах
    var $errortimeout = 600;  // Период обновления кэша после ошибки в секундах
    var $cachedir = ''; // Директория куда будет сохраняться кэш(если пусто, то будет сохранен в папке со скриптом), в конце обязателен слэш "/"
    var $cachetype = 'FILE'; // тип кэша. (FILE, MYSQL)
    var $connecttype = '';  // тип соединения с сервером setlinks. (CURL - использовать библиотеку CURL, SOCKET - использовать сокеты, NONE - не соединяться с сервером, использовать данные кэша)
                            // если $connecttype пусто, то тип соединения определяется автоматом
    var $sockettimeout = 5; // Ожидание кода, секунд
    var $indexfile = '^/index\\.(html|htm|php|phtml|asp)$'; // фильтр индексной страницы

    var $use_safe_method = false; // защита от проверки на продажность ссылок, читать тут http://forum.setlinks.ru/showthread.php?p=1506#post1495
    var $allow_url_params = ""; // параметры которые могут появлятся в урле через пробел "mod id username"

    var $show_comment = false; // если true, то выводить коментарии всем, а не только индексаторам
    var $show_errors = false; // выводить или нет ошибки

    // --- настройки для контекста ---
    // Список тегов в которых не будут проставляться контекстные ссылки
    var $context_bad_tags = array( "a", "title", "head", "meta", "link", "h1", "h2", "thead", "xmp", "textarea", "select", "button", "script", "style", "label", "noscript", "noindex" );
    var $context_show_comments = false; // если true, то выводить коментарии всем, а не только индексаторам

    var $path = '/articles/'; // путь с которого берутся статьи

    var $show_demo_links = false;
    var $_link_wrapper = '{link}';
}

?>
