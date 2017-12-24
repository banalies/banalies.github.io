<?php
/**
 * MEGAINDEX.ru - ���������������� ������� �����-������� ������
 *
 * PHP-������
 *
 * ����������! �� ����� ������ ������ � ���� �����!
 * ��� ��������� - ����� ��������� ��� ������ ����.
 *
 * class MEGAINDEX_base                 - ������� �����
 * class MEGAINDEX_client             - ����� ��� ������ ������� ������
 *
 * @version 1.1.6 �� 27.01.2012
 */

/**
 * �������� �����, ����������� ��� ������
 */
class MEGAINDEX_base
{
    var $_version = '1.0.1';    // 07.02.2013
    var $_verbose = false;
	var $_charset = 'windows-1251'; // http://www.php.net/manual/en/function.iconv.php
    var $_megaindex_charset = '';
    var $_server_list = array('links.megaindex.ru');
    var $_cache_lifetime = 3600; // ��������� ��� ������ :�)
    // ���� ������� ���� ������ �� �������, �� ��������� ������� ����� ����� ������� ������
    var $_cache_reloadtime = 600;
    var $_error = '';
    var $_host = '';
    var $_request_uri = '';
    var $_multi_site = false;
    var $_fetch_remote_type = ''; // ������ ����������� � ��������� ������� [file_get_contents|curl|socket]
    var $_socket_timeout = 6; // ������� ����� ������
    var $_force_show_code = false;
    var $_is_our_bot = false; // ���� ��� �����
    var $_debug = false;
    var $_ignore_case = false;
    var $_db_file = ''; // ���� � ����� � �������
    var $_use_server_array = false; // ������ ����� ����� uri ��������: $_SERVER['REQUEST_URI'] ��� getenv('REQUEST_URI')
    // TODO false by default
    var $_force_update_db = false;
    var $_is_block_css_showed = false; // ���� ��� ��������� css � ������� �������
    var $_is_block_ins_beforeall_showed = false;
    var $_data_path;
    var $_data_filename = 'links.db';
    var $_link_wrapper = '{link}';

    function MEGAINDEX_base($options = null)
    {

        $host = '';

        if (is_array($options)) {
            if (isset($options['host'])) {
                $host = $options['host'];
            }
        } elseif (strlen($options)) {
            $host = $options;
            $options = array();
        } else {
            $options = array();
        }

        if (isset($options['use_server_array']) && $options['use_server_array'] == true) {
            $this->_use_server_array = true;
        }

        // ����� ����?
        if (strlen($host)) {
            $this->_host = $host;
        } else {
            $this->_host = $_SERVER['HTTP_HOST'];
        }

        $this->_host = preg_replace('/^http:\/\//', '', $this->_host);
        $this->_host = preg_replace('/^www\./', '', $this->_host);

        // ����� ��������?
        if (isset($options['request_uri']) && strlen($options['request_uri'])) {
            $this->_request_uri = $options['request_uri'];
        } elseif ($this->_use_server_array === false) {
            $this->_request_uri = getenv('REQUEST_URI');
        }

        if (strlen($this->_request_uri) == 0) {
            $this->_request_uri = $_SERVER['REQUEST_URI'];
        }

        // �� ������, ���� ������� ����� ������ � ����� �����
        if (isset($options['multi_site']) && $options['multi_site'] == true) {
            $this->_multi_site = true;
        }

        // �������� ���������� � ������
        if (isset($options['debug']) && $options['debug'] == true) {
            $this->_debug = true;
        }
        
        // ���������� ��� ���������� ������
        if (isset($options['data_path'])) {
        	$this->_data_path = $options['data_path'];
        }
        
        // ��� ����� ���������� ���� � ��������
        if (isset($options['data_filename'])) {
        	$this->_data_filename = $options['data_filename'];
        }
        // ������ ��� ������
        if (isset($options['link_wrapper'])) {
        	$this->_link_wrapper = $options['link_wrapper'];
        }

        // ���������� ��� �� �����
        if (isset($_COOKIE['megaindex_cookie']) && ($_COOKIE['megaindex_cookie'] == _MEGAINDEX_USER)) {
            $this->_is_our_bot = true;
            if (isset($_COOKIE['megaindex_debug']) && ($_COOKIE['megaindex_debug'] == 1)) {
                $this->_debug = true;
                //��� �������� ������ ���������
                $this->_options = $options;
                $this->_server_request_uri = $this->_request_uri = $_SERVER['REQUEST_URI'];
                $this->_getenv_request_uri = getenv('REQUEST_URI');
                $this->_MEGAINDEX_USER = _MEGAINDEX_USER;
            }
            if (isset($_COOKIE['megaindex_updatedb']) && ($_COOKIE['megaindex_updatedb'] == 1)) {
                $this->_force_update_db = true;
            }
        } else {
            $this->_is_our_bot = false;
        }

        // �������� �� �������
        if (isset($options['verbose']) && $options['verbose'] == true || $this->_debug) {
            $this->_verbose = true;
        }

        // ���������
        if (isset($options['charset']) && strlen($options['charset'])) {
            $this->_charset = $options['charset'];
        } else {
            $this->_charset = 'windows-1251';
        }

        if (isset($options['fetch_remote_type']) && strlen($options['fetch_remote_type'])) {
            $this->_fetch_remote_type = $options['fetch_remote_type'];
        }

        if (isset($options['socket_timeout']) && is_numeric($options['socket_timeout']) && $options['socket_timeout'] > 0) {
            $this->_socket_timeout = $options['socket_timeout'];
        }

        // ������ �������� ���-���
        if (isset($options['force_show_code']) && $options['force_show_code'] == true) {
            $this->_force_show_code = true;
        }

        if (!defined('_MEGAINDEX_USER')) {
            return $this->raise_error('�� ������ ��������� _MEGAINDEX_USER');
        }

        // �������� %ff � %FF � URL�
        $strUpFunc = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
        $this->_request_uri = preg_replace("/(\%[a-z0-9]{2})/e", $strUpFunc.'("$1")', $this->_request_uri);

        //�� �������� �������� �� ������� ������
        if (isset($options['ignore_case']) && $options['ignore_case'] == true) {
            $this->_ignore_case = true;
            $this->_request_uri = strtolower($this->_request_uri);
        }
    }

    /**
     * ������� ��� ����������� � ��������� �������
     */
    function fetch_remote_file($host, $path, $specifyCharset = false)
    {
        $user_agent = $this->_user_agent . ' ' . $this->_version;

        @ini_set('allow_url_fopen', 1);
        @ini_set('default_socket_timeout', $this->_socket_timeout);
        @ini_set('user_agent', $user_agent);
        if (
            $this->_fetch_remote_type == 'file_get_contents'
            ||
            (
                $this->_fetch_remote_type == ''
                &&
                function_exists('file_get_contents')
                &&
                ini_get('allow_url_fopen') == 1
            )
        ) {
            $this->_fetch_remote_type = 'file_get_contents';

            if ($specifyCharset && function_exists('stream_context_create')) {
                $opts = array(
                    'http' => array(
                        'method' => 'GET',
                        'header' => 'Accept-Charset: ' . $this->_charset . "\r\n"
                    )
                );
                $context = @stream_context_create($opts);
                if ($data = @file_get_contents('http://' . $host . $path, null, $context)) {
                    return $data;
                }
            } else {
                if ($data = @file_get_contents('http://' . $host . $path)) {
                    return $data;
                }
            }

        } elseif (
            $this->_fetch_remote_type == 'curl'
            ||
            (
                $this->_fetch_remote_type == ''
                &&
                function_exists('curl_init')
            )
        ) {
            $this->_fetch_remote_type = 'curl';
            if ($ch = @curl_init()) {

                @curl_setopt($ch, CURLOPT_URL, 'http://' . $host . $path);
                @curl_setopt($ch, CURLOPT_HEADER, false);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_socket_timeout);
                @curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                if ($specifyCharset) {
                    @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Charset: ' . $this->_charset));
                }

                $data = @curl_exec($ch);
                @curl_close($ch);

                if ($data) {
                    return $data;
                }
            }

        } else {
            $this->_fetch_remote_type = 'socket';
            $buff = '';
            $fp = @fsockopen($host, 80, $errno, $errstr, $this->_socket_timeout);
            if ($fp) {
                @fputs($fp, "GET {$path} HTTP/1.0\r\nHost: {$host}\r\n");
                if ($specifyCharset) {
                    @fputs($fp, "Accept-Charset: {$this->_charset}\r\n");
                }
                @fputs($fp, "User-Agent: {$user_agent}\r\n\r\n");
                while (!@feof($fp)) {
                    $buff .= @fgets($fp, 128);
                }
                @fclose($fp);

                $page = explode("\r\n\r\n", $buff);
                unset($page[0]);
                return implode("\r\n\r\n", $page);
            }

        }

        return $this->raise_error('�� ���� ������������ � �������: ' . $host . $path . ', type: ' . $this->_fetch_remote_type);
    }

    /**
     * ������� ������ �� ���������� �����
     */
    function _read($filename)
    {

        $fp = @fopen($filename, 'rb');
        @flock($fp, LOCK_SH);
        if ($fp) {
            clearstatcache();
            $length = @filesize($filename);
            $mqr = @get_magic_quotes_runtime();
            @set_magic_quotes_runtime(0);
            if ($length) {
                $data = @fread($fp, $length);
            } else {
                $data = '';
            }
            @set_magic_quotes_runtime($mqr);
            @flock($fp, LOCK_UN);
            @fclose($fp);

            return $data;
        }

        return $this->raise_error('�� ���� ������� ������ �� �����: ' . $filename);
    }

    /**
     * ������� ������ � ��������� ����
     */
    function _write($filename, $data)
    {

        $fp = @fopen($filename, 'ab');
        if ($fp) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                ftruncate($fp, 0);
                $mqr = @get_magic_quotes_runtime();
                @set_magic_quotes_runtime(0);
                @fwrite($fp, $data);
                @set_magic_quotes_runtime($mqr);
                @flock($fp, LOCK_UN);
                @fclose($fp);

                if (md5($this->_read($filename)) != md5($data)) {
                    @unlink($filename);
                    return $this->raise_error('�������� ����������� ������ ��� ������ � ����: ' . $filename);
                }
            } else {
                return false;
            }

            return true;
        }

        return $this->raise_error('�� ���� �������� ������ � ����: ' . $filename);
    }

    /**
     * ������� ��������� ������
     */
    function raise_error($e)
    {

        $this->_error = '<p style="color: red; font-weight: bold;">MEGAINDEX ERROR: ' . $e . '</p>';

        if ($this->_verbose == true) {
            print $this->_error;
        }

        return false;
    }

    /**
     * �������� ������
     */
    function load_data()
    {
        $this->_db_file = $this->_get_db_file();

        if (!is_file($this->_db_file)) {
            // �������� ������� ����.
            if (@touch($this->_db_file)) {
                @chmod($this->_db_file, 0666); // ����� �������
            } else {
                return $this->raise_error('��� ����� ' . $this->_db_file . '. ������� �� �������. ��������� ����� 777 �� �����.');
            }
        }

        if (!is_writable($this->_db_file)) {
            return $this->raise_error('��� ������� �� ������ � �����: ' . $this->_db_file . '! ��������� ����� 777 �� �����.');
        }

        @clearstatcache();
        
        /**
         * ��������� 11.07.2014 ������ #61598
         * ��������� ����������� ������� � ����������� ����� �� ����.
         */
        if (filesize($this->_db_file) == 0 && filemtime($this->_db_file) > (time() - $this->_cache_lifetime)) {
        	return false;
        	//return $this->raise_error('����� �������� �� �������!');
        }
        $data = $this->_read($this->_db_file);
        if (
            $this->_force_update_db
            || (
                !$this->_is_our_bot
                &&
                (
                    filemtime($this->_db_file) < (time() - $this->_cache_lifetime)
                    ||
                    filesize($this->_db_file) == 0
                    ||
                    @unserialize($data) == false
                )
            )
        ) {
            // ����� �� �������� �������� ������� � ����� �� ���� ������������� ��������
            @touch($this->_db_file, (time() - $this->_cache_lifetime + $this->_cache_reloadtime));

            $path = $this->_get_dispenser_path();
            if (strlen($this->_charset)) {
                $path .= '&charset=' . $this->_charset;
            }

            foreach ($this->_server_list as $i => $server) {
                if ($data = $this->fetch_remote_file($server, $path)) {
                    if (substr($data, 0, 12) == 'FATAL ERROR:') {
                        $this->raise_error($data);
                    } else {
                        // [������]�������� �����������:
                        $hash = @unserialize($data);
                        if ($hash != false) {
                            // ���������� �������� ��������� � ���
//                            $hash['__megaindex_charset__'] = 'windows-1251';
                            $hash['__megaindex_charset__'] = $this->_charset;
                            $hash['__last_update__'] = time();
                            $hash['__multi_site__'] = $this->_multi_site;
                            $hash['__fetch_remote_type__'] = $this->_fetch_remote_type;
                            $hash['__ignore_case__'] = $this->_ignore_case;
                            $hash['__php_version__'] = phpversion();
                            $hash['__server_software__'] = $_SERVER['SERVER_SOFTWARE'];

                            $data_new = @serialize($hash);
                            if ($data_new) {
                                $data = $data_new;
                            }

                            $this->_write($this->_db_file, $data);
                            break;
                        }
                    }
                }
            }
        }

        // ������� PHPSESSID
        if (strlen(session_id())) {
            $session = session_name() . '=' . session_id();
            $this->_request_uri = str_replace(array('?' . $session, '&' . $session), '', $this->_request_uri);
        }

        $this->set_data(@unserialize($data));
    }
}

/**
 * ����� ��� ������ � �������� ��������
 */
class MEGAINDEX_client extends MEGAINDEX_base
{

    var $_links_delimiter = ' &nbsp; ';
    var $_links = array();
    var $_links_page = array();
    var $_user_agent = 'MEGAINDEX_Client PHP';

    function MEGAINDEX_client($options = null)
    {
        parent::MEGAINDEX_base($options);
        $this->load_data();
    }

    /**
     * ��������� html ��� ������� ������
     *
     * @param string $html
     * @return string
     */
    function _return_array_links_html($html, $options = null)
    {

        if (empty($options)) {
            $options = array();
        }

        // ���� ��������� ������������ ���������, � �������� ��������� ����, � ��� ������, ������������ � ��������
        if (
            strlen($this->_charset) > 0
            &&
            strlen($this->_megaindex_charset) > 0
            &&
            $this->_megaindex_charset != $this->_charset
            &&
            function_exists('iconv')
        ) {
            $new_html = @iconv($this->_megaindex_charset, $this->_charset, $html);
            if ($new_html) {
                $html = $new_html;
            }
        }

        if ($this->_is_our_bot) {

            $html = '<megaindex_noindex>' . $html . '</megaindex_noindex>';

            if (isset($options['is_block_links']) && true == $options['is_block_links']) {

                if (!isset($options['nof_links_requested'])) {
                    $options['nof_links_requested'] = 0;
                }
                if (!isset($options['nof_links_displayed'])) {
                    $options['nof_links_displayed'] = 0;
                }
                if (!isset($options['nof_obligatory'])) {
                    $options['nof_obligatory'] = 0;
                }
                if (!isset($options['nof_conditional'])) {
                    $options['nof_conditional'] = 0;
                }

                $html = '<megaindex_block nof_req="' . $options['nof_links_requested'] .
                    '" nof_displ="' . $options['nof_links_displayed'] .
                    '" nof_oblig="' . $options['nof_obligatory'] .
                    '" nof_cond="' . $options['nof_conditional'] .
                    '">' . $html .
                    '</megaindex_block>';
            }
        }

        return $html;
    }

    /**
     * ��������� ��������� html ����� ������� ������
     *
     * @param string $html
     * @return string
     */
    function _return_html($html)
    {

        if ($this->_debug) {
            $html .= print_r($this, true);
        }

        return $html;
    }

    /**
     * ����� ������ � ���� �����
     *
     * @param int $n ������������
     * @param int $offset ��������
     * @param array $options �����
     *
     * <code>
     * $options = array();
     * $options['block_no_css'] = (false|true);
     * // �������������� ������ �� ����� css � ���� ��������: false - �������� css
     * $options['block_orientation'] = (1|0);
     * // �������������� ���������� �����: 1 - ��������������, 0 - ������������
     * $options['block_width'] = ('auto'|'[?]px'|'[?]%'|'[?]');
     * // �������������� ������ �����:
     * // 'auto'  - ������������ ������� �����-������ � ������������� �������,
     * // ���� �������� ���, �� ������ ��� ������
     * // '[?]px' - �������� � ��������
     * // '[?]%'  - �������� � ��������� �� ������ �����-������ � ������������� �������
     * // '[?]'   - ����� ������ ��������, ������� �������������� ������������� CSS
     * </code>
     *
     * @return string
     */
    function return_block_links($n = null, $offset = 0, $options = null)
    {

        // ���������� ���������
        if (empty($options)) {
            $options = array();
        }

        $defaults = array();
        $defaults['block_no_css'] = false;
        $defaults['block_orientation'] = 1;
        $defaults['block_width'] = '';

        $ext_options = array();
        if (isset($this->_block_tpl_options) && is_array($this->_block_tpl_options)) {
            $ext_options = $this->_block_tpl_options;
        }

        $options = array_merge($defaults, $ext_options, $options);

        // ������ �������� �� �������� (���-���) => ������� ��� ���� + ���� � �����
        if (!is_array($this->_links_page)) {
            $html = $this->_return_array_links_html('', array('is_block_links' => true));
            return $this->_return_html($this->_links_page . $html);
        } // �� �������� ������� => ������ ������� ������ - ������ �� ������
        elseif (!isset($this->_block_tpl)) {
            return $this->_return_html('');
        }

        // ��������� ������ ����� ��������� � �����

        $total_page_links = count($this->_links_page);

        $need_show_obligatory_block = false;
        $need_show_conditional_block = false;
        $n_requested = 0;

        if (isset($this->_block_ins_itemobligatory)) {
            $need_show_obligatory_block = true;
        }

        if (is_numeric($n) && $n >= $total_page_links) {

            $n_requested = $n;

            if (isset($this->_block_ins_itemconditional)) {
                $need_show_conditional_block = true;
            }
        }

        if (!is_numeric($n) || $n > $total_page_links) {
            $n = $total_page_links;
        }

        // ������� ������
        $links = array();
        for ($i = 1; $i <= $n; $i++) {
            if ($offset > 0 && $i <= $offset) {
                array_shift($this->_links_page);
            } else {
                $links[] = array_shift($this->_links_page);
            }
        }

        $html = '';

        // ������� ����� ������������ ������
        $nof_conditional = 0;
        if (count($links) < $n_requested && true == $need_show_conditional_block) {
            $nof_conditional = $n_requested - count($links);
        }

        //���� ��� ������ � ��� �������� ������, �� ������ �� �������
        if (empty($links) && $need_show_obligatory_block == false && $nof_conditional == 0) {

            $return_links_options = array(
                'is_block_links' => true,
                'nof_links_requested' => $n_requested,
                'nof_links_displayed' => 0,
                'nof_obligatory' => 0,
                'nof_conditional' => 0
            );

            $html = $this->_return_array_links_html($html, $return_links_options);

            return $this->_return_html($html);
        }

        // ������ ����� ������, ������ ���� ���. ��� �� ������� �� ������, ���� ��� ������ � ����������
        if (!$this->_is_block_css_showed && false == $options['block_no_css']) {
            $html .= $this->_block_tpl['css'];
            $this->_is_block_css_showed = true;
        }

        // �������� ���� � ������ ���� ������
        if (isset($this->_block_ins_beforeall) && !$this->_is_block_ins_beforeall_showed) {
            $html .= $this->_block_ins_beforeall;
            $this->_is_block_ins_beforeall_showed = true;
        }

        // �������� ���� � ������ �����
        if (isset($this->_block_ins_beforeblock)) {
            $html .= $this->_block_ins_beforeblock;
        }

        // �������� ������� � ����������� �� ���������� �����
        $block_tpl_parts = $this->_block_tpl[$options['block_orientation']];

        $block_tpl = $block_tpl_parts['block'];
        $item_tpl = $block_tpl_parts['item'];
        $item_container_tpl = $block_tpl_parts['item_container'];
        $item_tpl_full = str_replace('{item}', $item_tpl, $item_container_tpl);
        $items = '';

        $nof_items_total = count($links);
        foreach ($links as $link) {

            preg_match('#<a href="(https?://([^"/]+)[^"]*)"[^>]*>[\s]*([^<]+)</a>#i', $link, $link_item);

            if (function_exists('mb_strtoupper') && strlen($this->_megaindex_charset) > 0) {
                $header_rest = mb_substr($link_item[3], 1, mb_strlen($link_item[3], $this->_megaindex_charset) - 1, $this->_megaindex_charset);
                $header_first_letter = mb_strtoupper(mb_substr($link_item[3], 0, 1, $this->_megaindex_charset), $this->_megaindex_charset);
                $link_item[3] = $header_first_letter . $header_rest;
            } elseif (function_exists('ucfirst') && (strlen($this->_megaindex_charset) == 0 || strpos($this->_megaindex_charset, '1251') !== false)) {
                $link_item[3][0] = ucfirst($link_item[3][0]);
            }

            // ���� ���� ��������������� URL, �� �������� ��� ��� ������

            if (isset($this->_block_uri_idna) && isset($this->_block_uri_idna[$link_item[2]])) {
                $link_item[2] = $this->_block_uri_idna[$link_item[2]];
            }

            $item = $item_tpl_full;
            $item = str_replace('{header}', $link_item[3], $item);
            $item = str_replace('{text}', trim($link), $item);
            $item = str_replace('{url}', $link_item[2], $item);
            $item = str_replace('{link}', $link_item[1], $item);
            $items .= $item;
        }

        // �������� ����������� ������� � �����
        if (true == $need_show_obligatory_block) {
            $items .= str_replace('{item}', $this->_block_ins_itemobligatory, $item_container_tpl);
            $nof_items_total += 1;
        }

        // �������� ������������ �������� � �����
        if ($need_show_conditional_block == true && $nof_conditional > 0) {
            for ($i = 0; $i < $nof_conditional; $i++) {
                $items .= str_replace('{item}', $this->_block_ins_itemconditional, $item_container_tpl);
            }
            $nof_items_total += $nof_conditional;
        }

        if ($items != '') {
            $html .= str_replace('{items}', $items, $block_tpl);

            // ����������� ������, ����� ����� ��������� ����
            if ($nof_items_total > 0) {
                $html = str_replace('{td_width}', round(100 / $nof_items_total), $html);
            } else {
                $html = str_replace('{td_width}', 0, $html);
            }

            // ���� ������, �� �������������� ������ �����
            if (isset($options['block_width']) && !empty($options['block_width'])) {
                $html = str_replace('{block_style_custom}', 'style="width: ' . $options['block_width'] . '!important;"', $html);
            }
        }

        unset($block_tpl_parts, $block_tpl, $items, $item, $item_tpl, $item_container_tpl);

        // �������� ���� � ����� �����
        if (isset($this->_block_ins_afterblock)) {
            $html .= $this->_block_ins_afterblock;
        }

        //��������� ���������� ������������ ����������
        unset($options['block_no_css'], $options['block_orientation'], $options['block_width']);

        $tpl_modifiers = array_keys($options);
        foreach ($tpl_modifiers as $k => $m) {
            $tpl_modifiers[$k] = '{' . $m . '}';
        }
        unset($m, $k);

        $tpl_modifiers_values = array_values($options);

        $html = str_replace($tpl_modifiers, $tpl_modifiers_values, $html);
        unset($tpl_modifiers, $tpl_modifiers_values);

        //������� ������������� ������������
        $clear_modifiers_regexp = '#\{[a-z\d_\-]+\}#';
        $html = preg_replace($clear_modifiers_regexp, ' ', $html);

        $return_links_options = array(
            'is_block_links' => true,
            'nof_links_requested' => $n_requested,
            'nof_links_displayed' => $n,
            'nof_obligatory' => ($need_show_obligatory_block == true ? 1 : 0),
            'nof_conditional' => $nof_conditional
        );

        $html = $this->_return_array_links_html($html, $return_links_options);

        return $this->_return_html($html);
    }

    /**
     * ����� ������ � ������� ���� - ����� � ������������
     *
     * @param int $n ������������
     * @param int $offset ��������
     * @param array $options �����
     *
     * <code>
     * $options = array();
     * $options['as_block'] = (false|true);
     * // ���������� �� ������ � ���� �����
     * </code>
     *
     * @see return_block_links()
     * @return string
     */
    function return_links($n = null, $offset = 0, $options = null)
    {

        //����������, ��� �������� ������
        $as_block = $this->_show_only_block;

        if (is_array($options) && isset($options['as_block']) && false == $as_block) {
            $as_block = $options['as_block'];
        }

        if (true == $as_block && isset($this->_block_tpl)) {
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
            
            /* �������� � ������� ������ ����������� */
            foreach($links as $key => $linkHTML) {
            	$links[$key] = str_replace("{link}", $linkHTML, $this->_link_wrapper);
            }

            $html = join($this->_links_delimiter, $links);

            // ���� ��������� ������������ ���������, � �������� ��������� ����, � ��� ������, ������������ � ��������
            if (
                strlen($this->_charset) > 0
                &&
                strlen($this->_megaindex_charset) > 0
                &&
                $this->_megaindex_charset != $this->_charset
                &&
                function_exists('iconv')
            ) {
                $new_html = @iconv($this->_megaindex_charset, $this->_charset, $html);
                if ($new_html) {
                    $html = $new_html;
                }
            }

            if ($this->_is_our_bot) {
                $html = '<megaindex_noindex>' . $html . '</megaindex_noindex>';
            }
        } else {
            $html = $this->_links_page;
            if ($this->_is_our_bot) {
                $html .= '<megaindex_noindex></megaindex_noindex>';
            }
        }

        if ($this->_debug) {
            $html .= print_r($this, true);
        }

        return $html;
    }

    function _get_db_file()
    {
    	$path = $this->_data_path ? $this->_data_path : dirname(__FILE__);
    	$filename = $this->_data_filename;
    	
        if ($this->_multi_site) {
            return $path . DIRECTORY_SEPARATOR . $this->_host . '.' . $filename;
        } else {
            return $path . DIRECTORY_SEPARATOR . $filename;
        }
    }

    function _get_dispenser_path()
    {
        return '/dispenser.php?user=' . _MEGAINDEX_USER . '&host=' . $this->_host;
    }

    function set_data($data)
    {
        if ($this->_ignore_case) {
            $this->_links = array_change_key_case($data);
        } else {
            $this->_links = $data;
        }
        if (isset($this->_links['__megaindex_delimiter__'])) {
            $this->_links_delimiter = $this->_links['__megaindex_delimiter__'];
        }
        // ���������� ��������� ����
        if (isset($this->_links['__megaindex_charset__'])) {
            $this->_megaindex_charset = $this->_links['__megaindex_charset__'];
        } else {
            $this->_megaindex_charset = '';
        }
        if (@array_key_exists($this->_request_uri, $this->_links) && is_array($this->_links[$this->_request_uri])) {
            $this->_links_page = $this->_links[$this->_request_uri];
        } else {
            if (isset($this->_links['__megaindex_new_url__']) && strlen($this->_links['__megaindex_new_url__'])) {
                if ($this->_is_our_bot || $this->_force_show_code) {
                    $this->_links_page = $this->_links['__megaindex_new_url__'];
                }
            }
        }

        // ���� �� ���� ������� ������
        if (isset($this->_links['__megaindex_show_only_block__'])) {
            $this->_show_only_block = $this->_links['__megaindex_show_only_block__'];
        } else {
            $this->_show_only_block = false;
        }

        // ���� �� ������ ��� �������� ������
        if (isset($this->_links['__megaindex_block_tpl__']) && !empty($this->_links['__megaindex_block_tpl__'])
            && is_array($this->_links['__megaindex_block_tpl__'])
        ) {
            $this->_block_tpl = $this->_links['__megaindex_block_tpl__'];
        }

        // ���� �� ��������� ��� �������� ������
        if (isset($this->_links['__megaindex_block_tpl_options__']) && !empty($this->_links['__megaindex_block_tpl_options__'])
            && is_array($this->_links['__megaindex_block_tpl_options__'])
        ) {
            $this->_block_tpl_options = $this->_links['__megaindex_block_tpl_options__'];
        }

        // IDNA-������
        if (isset($this->_links['__megaindex_block_uri_idna__']) && !empty($this->_links['__megaindex_block_uri_idna__'])
            && is_array($this->_links['__megaindex_block_uri_idna__'])
        ) {
            $this->_block_uri_idna = $this->_links['__megaindex_block_uri_idna__'];
        }

        // �����
        $check_blocks = array(
            'beforeall',
            'beforeblock',
            'afterblock',
            'itemobligatory',
            'itemconditional',
            'afterall'
        );

        foreach ($check_blocks as $block_name) {

            $var_name = '__megaindex_block_ins_' . $block_name . '__';
            $prop_name = '_block_ins_' . $block_name;

            if (isset($this->_links[$var_name]) && strlen($this->_links[$var_name]) > 0) {
                $this->$prop_name = $this->_links[$var_name];
            }

        }
    }
}

?>