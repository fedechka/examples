<?php

/**
 * $Id$
 */

namespace Da;

use Algo26\IdnaConvert\ToIdn;
use Algo26\IdnaConvert\ToUnicode;

/**
 * Класс состоит только из статических методов,
 * играет роль неймспейса
 */

class Std
{
    public static function grab($name)
    {
        if (isset($_GET[$name])) {
            return $_GET[$name];
        }
        if (isset($_POST[$name])) {
            return $_POST[$name];
        }

        return false;
    }

    public static function grabFloat($name)
    {
        $value = self::grab($name);

        $value = trim($value);
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    public static function grabInt($name)
    {
        $value = self::grab($name);

        $value = trim($value);
        $value = str_replace(' ', '', $value);

        return (int) $value;
    }

    public static function grabBoolean($name)
    {
        if (!isset($_REQUEST[$name])) {
            return null;
        }
        $value = trim(self::grab($name));
        if ($value === 'false') {
            return false;
        }
        return (bool) $value;
    }

    /**
     * Применяется для принятия параметра, который должен быть http/https-ссылкой.
     * Если схема (http/https) не указана, то автоматически подставится http
     *
     * @param string $name
     * @return boolean|string
     */
    public static function grabUrl($name)
    {
        $value = self::grab($name);

        $value = trim($value);
        if (empty($value)) { // Поле пустое или не было передано
            return false;
        }

        // Фикс случаев, когда в результате копипаста получаются URLы вида "http://http://example.com"
        while (preg_match('|^http[s]?://(http.*)$|si', $value, $matches)) {
            $value = $matches[1];
        }

        // Проверка и подстановка схемы
        $tmp = explode("://", $value, 2);

        if (1 == count($tmp)) { // Указан адрес сайта без указания схемы
            $value = 'http://' . $tmp[0]; // Устанавливает схему по-умолчанию - http
        } elseif (!empty($tmp[1])) {
            $scheme = strtolower($tmp[0]);
            switch ($scheme) {
                case 'http':
                case 'https':
                    $value = $scheme . '://' . $tmp[1];
                    break;
                default:
                    // схема не указана, вариант для URL вида "example.com?url=http://test.com"
                    $value = 'http://' . $tmp[0] . '://' . $tmp[1];
            }
        } else {
            return false; // Случай, когда указана только схема и ничего больше
        }

        return $value;
    }

    /**
     * Выводит строку со стандартным ответом 404 и завершает работу.
     */
    public static function get404()
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

        $str = "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n";
        $str .= "<HTML><HEAD>\n";
        $str .= "<TITLE>404 Not Found</TITLE>\n";
        $str .= "</HEAD><BODY>\n";
        $str .= "<H1>Not Found</H1>\n";
        $str .= "The requested URL was not found on this server.<P>\n";
        $str .= "</BODY></HTML>\n";

        echo $str;
        exit();
    }

    /**
     * Выводит строку со стандартным ответом 403 и завершает работу.
     */
    public static function get403()
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');

        $str = "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n";
        $str .= "<html><head>\n";
        $str .= "<title>403 Forbidden</title>\n";
        $str .= "</head><body>\n";
        $str .= "<h1>Forbidden</h1>\n";
        $str .= "<hr>\n";
        $str .= "</body></html>\n";

        echo $str;
        exit();
    }

    /**
     * Генерирует и возвращает случайный пароль
     *
     * @param int $length
     * @return string
     */
    public static function generatePassword($length = 8)
    {
        // Массив возможных символов
        // Из набора исключены 1 (единица) и l (строчная L)
        $possible = "234567890abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

        // add random characters to $password until $length is reached
        $password = "";
        $i = 0;
        while ($i < $length) {
            // Выбираем случайный символ из разрешенного набора
            $char = substr($possible, mt_rand(0, strlen($possible) - 1), 1);

            // Скипаем символы, которые уже есть в пароле
            if (!strstr($password, $char)) {
                $password .= $char;
                $i++;
            }
        }

        return $password;
    }

    /**
     * Пытается считать значение сначала из GET/POST, затем из сессии
     * По окончании сохраняет значение в сессии
     *
     * @param string $gpc_name      Имя переменной для считывания из GET/POST
     * @param string $session_name  Имя переменной для хранения в сессии
     * @param array $valid_values   Если задан, проверяет значение на валидность по вхождению в массив
     * @param mixed $default_value
     * @return mixed
     */
    public static function grabSession($gpc_name, $session_name, $valid_values = array(), $default_value = false)
    {
        // Сначала пытаемся считать из GET/POST
        $ret = self::grab($gpc_name);
        if ($ret === false) {
            $ret = isset($_SESSION[$session_name]) ? $_SESSION[$session_name] : $default_value;
        }

        // Проверяем валидность значения, если задан массив $valid_values
        if (!empty($valid_values)) {
            if (!in_array($ret, $valid_values)) {
                $ret = $default_value;
            }
        }

        $_SESSION[$session_name] = $ret;
        return $ret;
    }


    /**
     * Конвертирует строку из кириллицы в латиницу, оставляя только символы [^a-zA-Z0-9_]
     *
     * @param string $string
     * @return string
     */
    public static function cyrToLatin($string)
    {
        static $tbl = array(
            'а' => 'a',   'б' => 'b',   'в' => 'v',   'г' => 'g',   'д' => 'd',   'е' => 'e',       'ё' => 'jo',
            'ж' => 'zh',  'з' => 'z',   'и' => 'i',   'й' => 'j',   'к' => 'k',   'л' => 'l',       'м' => 'm',
            'н' => 'n',   'о' => 'o',   'п' => 'p',   'р' => 'r',   'с' => 's',   'т' => 't',       'у' => 'u',
            'ф' => 'f',   'х' => 'h',   'ц' => 'c',   'ч' => 'ch',  'ш' => 'sh',  'щ' => 'shch',    'ъ' => '"',
            'ы' => 'y',   'ь' => "'",   'э' => 'eh',  'ю' => 'yu',  'я' => 'ya',

            'А' => 'A',   'Б' => 'B',   'В' => 'V',   'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
                        'З' => 'Z',   'И' => 'I',   'Й' => 'J',   'К' => 'K',   'Л' => 'L',       'М' => 'M',
            'Н' => 'N',   'О' => 'O',   'П' => 'P',   'Р' => 'R',   'С' => 'S',   'Т' => 'T',       'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',                                           'Ъ' => '"',
            'Ы' => 'Y',   'Ь' => "'",
            ' ' => '_'
        );

        static $uppercase_double_replaces = array(
            'Ё' => 'JO', 'Ж' => 'ZH', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SHCH', 'Э' => 'EH', 'Ю' => 'YU', 'Я' => 'YA',
        );

        static $uppercase_double_replaces_2 = array(
            'Ё' => 'Jo', 'Ж' => 'Zh', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Э' => 'Eh', 'Ю' => 'Yu', 'Я' => 'Ya',
        );

        $string = strtr($string, $tbl);

        // Check letters in an upper case
        $string_array   = array();
        $prev_char      = null;
        $strlen = mb_strlen($string);
        for ($i = 0; $i < $strlen; $i++) {
            $char = mb_substr($string, $i, 1);
            if (empty($uppercase_double_replaces[$char])) {
                // The current char is not in a uppercase
                $string_array[] = $prev_char = $char;
                continue;
            }

            // The current char is in a uppercase

            // Check for the prev char
            if ($prev_char && ctype_alpha($prev_char) && $prev_char == strtoupper($prev_char)) {
                // The prev char is also in uppercase
                $string_array[] = $prev_char = $uppercase_double_replaces[$char];
                continue;
            }

            $is_prev_char_uppercase = true;

            // Check for the next char

            $next_char = mb_substr($string, ($i + 1), 1);
            if ($next_char && ctype_alpha($next_char) && $next_char == strtoupper($next_char)) {
                // The next char is also in uppercase
                $string_array[] = $prev_char = $uppercase_double_replaces[$char];
                continue;
            }

            // The next char is not in uppercase (or the end of string is reached)
            $string_array[] = $prev_char = $uppercase_double_replaces_2[$char];
        }

        $string = join('', $string_array);

        // Cut out all non-latyn characters
        $string = mb_ereg_replace('[^a-zA-Z0-9_.]', '', $string);

        return $string;
    }


    /**
     * Выводит ответ в формате JSON
     *
     * @param array $response
     * @param boolean $escape
     */
    public static function json($response, $escape = false)
    {
        // Экранирование специальных символов для передачи строк в интерфейс
        if ($escape) {
            array_walk_recursive($response, function (&$item) {
                if (is_scalar($item) && !is_numeric($item) && !is_bool($item)) {
                    $item = htmlspecialchars($item, ENT_QUOTES, 'UTF-8', false);
                }
            });
        }

        if (isset($_GET['callback'])) {
            $cb = preg_replace('/[^a-zA-Z0-9_]/m', '', $_GET['callback']);
            header('Content-type: application/javascript');
            print $cb . '(' . json_encode($response) . ')';
            return;
        }
        header('Content-type: application/json');
        print json_encode($response);
    }


    /**
     * Переводит размер файла в байтах в человеческий формат.
     *
     * @param int $size
     * @return string
     */
    public static function convertBytes($size)
    {
        $filesizename = array(" B", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
        return $size ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i] : '0';
    }


    /**
     * Возвращает IP-адрес запроса.
     * Если задан параметр $is_with_proxy и если запрос был через доверенный прокси (наличие заголовка X-Trusted-Proxy),
     * пытается определить реальный IP из заголовка HTTP_X_FORWARDED_FOR.
     * При успешном определении вернет два айпишника в формате "REAL_IP/PROXY_IP"
     *
     * @return string
     */
    public static function visitorIP($is_with_proxy = false)
    {
        if (empty($is_with_proxy)) {
            return $_SERVER['REMOTE_ADDR'];
        }

        if (empty($_SERVER['X-Trusted-Proxy'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        // Запрос пришел от доверенного прокси, определяем адрес

        if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // HTTP_X_FORWARDED_FOR пустой
            return $_SERVER['REMOTE_ADDR'];
        }

        // Пытаемся определить адрес по HTTP_X_FORWARDED_FOR, в котором может быть несколько ip, разделённых запятыми.
        // Нам надо взять первый валидный IP4 из такой цепочки. Не заморачиваемся с проверками на приватность сеток.
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip . '/' . $_SERVER['REMOTE_ADDR'];
            }
        }

        // Не удалось определить IP по HTTP_X_FORWARDED_FOR, возвращаем IP прокси
        return $_SERVER['REMOTE_ADDR'];
    }


    /**
     * Аналогично array_sum(), только на выходе массив сумм соответствующих ключей
     * подмассивов входного массива
     *
     * @param array $array
     * @return array
     */
    public static function arraySumByKeys($array)
    {
        $sum = array();
        foreach ($array as $a) {
            foreach ($a as $key => $value) {
                if (!isset($sum[$key])) {
                    $sum[$key] = is_numeric($value) ? $value : 0;
                } else {
                    $sum[$key] += is_numeric($value) ? $value : 0;
                };
            };
        };
        return $sum;
    }


    /**
     * Возвращает название запрошенного скрипта
     */
    public static function getScriptName()
    {
        return preg_replace("/^([^?]+).*$/", "$1", $_SERVER['REQUEST_URI']);
    }


    /**
     * Обрабатывает заход по реферальной ссылке от другого партнёра
     * Выставляет куку XXX_ref_id (константа COOKIE_REF_ID), которая потом будет проверена при регистрации
     */
    public static function setReferralId()
    {
        global $Config;

        if (!$Config->IS_REFERRAL_PROGRAM_ENABLED) {
            // Заблокировано
            return;
        }

        $referral_id = self::getReferralId();

        // Есть referral_id
        if ($referral_id) {
            setcookie(COOKIE_REF_ID, $referral_id, time() + 60 * 60 * 24 * 365, '/', '.' . BASE_DOMAIN);
        }
    }


    /**
     * Возвращает referral_id, если был заход по реферальной ссылке от другого партнёра
     *
     * @return int
     */
    public static function getReferralId()
    {
        global $Config;

        if (!$Config->IS_REFERRAL_PROGRAM_ENABLED) {
            // Заблокировано
            return null;
        }

        // Проверяем, нет ли параметра в запросе
        $referral_id = self::grabInt('ref');
        if ($referral_id) {
            if (!\Da_User::get($referral_id)) {
                // Юзера не существует
                return null;
            }
            return $referral_id;
        }

        // Проверяем, нет ли в куке
        $referral_id = isset($_COOKIE[COOKIE_REF_ID]) ? $_COOKIE[COOKIE_REF_ID] : null;
        return $referral_id;
    }


    /**
     * Сбрасывает referral_id
     *
     */
    public static function clearReferralId()
    {
        global $Config;

        if (!$Config->IS_REFERRAL_PROGRAM_ENABLED) {
            // Заблокировано
            return;
        }

        setcookie(COOKIE_REF_ID, '', 1, '/', 'partner.' . BASE_DOMAIN);
    }



    /**
     * Обрабатывает заход по реферальной ссылке для регистрации рекламодателя под агентом
     * Выставляет куку XXX_agent_ref_id (константа COOKIE_AGENTREF_ID), которая потом будет проверена при регистрации
     */
    public static function setAgentRefId()
    {
        if (!INSTANCE_DIRECTADVERT) {
            // Пока только в DA
            return;
        }

        $agent_referral_id = self::getAgentRefId();

        // Есть referral_id
        if ($agent_referral_id) {
            setcookie(COOKIE_AGENTREF_ID, $agent_referral_id, time() + 60 * 60 * 24 * 365, '/', '.' . BASE_DOMAIN);
        }
    }


    /**
     * Возвращает agent_referral_id, если был заход по реферальной ссылке для регистрации рекламодателя под агентом
     *
     * @return int
     */
    public static function getAgentRefId()
    {
        if (!INSTANCE_DIRECTADVERT) {
            // Пока только в DA
            return null;
        }

        // Проверяем, нет ли параметра в запросе
        $agent_referral_id = self::grabInt('aref');
        if ($agent_referral_id) {
            if (!\Da\User\Agent::get($agent_referral_id)) {
                // Агента не существует
                return null;
            }
            return $agent_referral_id;
        }

        // Проверяем, нет ли в куке
        $agent_referral_id = isset($_COOKIE[COOKIE_AGENTREF_ID]) ? $_COOKIE[COOKIE_AGENTREF_ID] : null;
        return $agent_referral_id;
    }


    /**
     * Сбрасывает agent_referral_id
     *
     */
    public static function clearAgentRefId()
    {
        if (!INSTANCE_DIRECTADVERT) {
            // Пока только в DA
            return;
        }

        setcookie(COOKIE_AGENTREF_ID, '', 1, '/', '.' . BASE_DOMAIN);
    }


    /**
     * Инициализация Gettext
     *
     * @param string $locale
     * @param string $language
     */
    public static function setGettext($locale, $language)
    {
        putenv('LC_ALL=' . $locale . '.UTF-8');
        setlocale(LC_ALL, $locale . '.utf8', $locale . '.utf-8', $locale . '.UTF8', $locale . '.UTF-8');
        textdomain("directadvert");
        $lang_path = PATH_ROOT . '/lang';
        // Поддержка скинов для языков
        if (defined('TEMPLATE_SKIN') && is_dir(PATH_ROOT . '/lang/skins/' . TEMPLATE_SKIN . '/' . $language)) {
            $lang_path = PATH_ROOT . '/lang/skins/' . TEMPLATE_SKIN;
        }
        bindtextdomain("directadvert", $lang_path);
        bind_textdomain_codeset("directadvert", 'UTF-8');
    }


    /**
     * Скачивает содержимое ссылки
     *
     * @param string $url
     * @param bool $is_head_request Возвратить только http-заголовки, не скачивая содержимое
     * @return string
     */
    public static function download($url, $is_head_request = false)
    {
        if (empty($url)) {
            return false;
        }

        $ch = curl_init($url);

        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $cookie_file = sys_get_temp_dir() . '/' . md5($url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; U; ru) Gecko/20100101 Firefox');
        if ($is_head_request) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        } else {
            curl_setopt($ch, CURLOPT_HEADER, 0);
        }

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }


    /**
     * Функция "чистит" заголовок от излишних знаков препинания и капслока
     *
     * @param string $title
     * @return string
     */
    public static function sanitizeTitle($title)
    {
        // Аббревиатуры
        $sanitize_abbreviations = 'АВИЛОН АЕЭС АЗС АК АКБ АКПП АКСОН АНБ АНПФ АО АПК АПЛ АТО'
                . ' АТС АТЦ АТЭС АЭС БАД БДСМ БДТ БМВ БМД БМП БНД БПЛА БРДМ БРИКС БТР БЦАА'
                . ' ВАДА ВАЗ ВБ ВВП ВВС ВДВ ВДНХ ВИА ВИЧ ВК ВКС ВМС ВМСУ ВМФ ВНЖ ВОВ ВОЗ ВПК ВР'
                . ' ВС ВСК ВСН ВСУ ВТБ ВТО ВФЛА ВЦИОМ ВЧК ВШЭ ГАИ ГБО ГБУ ГД ГДР ГИБДД ГКЧП ГМО'
                . ' ГОК ГПУ ГРУ ГРЭС ГСК ГСО ГУМ ГЭС ДАИШ ДК ДНК ДНР ДПС ДТП ДЦП ЕГАИС ЕГЭ ЕДРИД'
                . ' ЕМА ЕС ЕСПЧ ЕЦБ ЕЭС ЖД ЖК ЖКБ ЖКТ ЖКХ ЗАГС ЗАЗ ЗАО ЗВО ЗВР ЗИЛ ЗИФ ЗОЖ ЗРК'
                . ' ЗСД ИБП ИБС ИГ ИГИЛ ИЖС ИЛ-76 ИП ИТМО КАД КАМАЗ КВН КГБ КНДР КНР КП КПРФ КРС'
                . ' КС КСБ КТ КТРВ КХЛ ЛГБТ ЛДНР ЛДПР ЛДСП ЛНР ЛО МАДИ МАЗ МБР МВД МВФ МГИМО'
                . ' МГТС МГТУ МГУ МДФ МИ-35 МИД МИФИ МКАД МКП МКС МЛМ ММА ММВБ ММКФ МММ ММО'
                . ' ММОРПГ МО МОК МРК МРОТ МРТ МРЭО МТС МФК МФЦ МЦ МЧМ МЧС НАСА НАТО НГ НДС'
                . ' НДФЛ НИИ НКВД НЛО НМШ НПЗ НПО НПФ НТВ НХЛ ОАЕ ОАО ОАЭ ОБСЕ ОБТ ОВД ОГПУ'
                . ' ОДКБ ОИ ОМВД ОМОН ОНФ ООН ООО ОПЕК ОПК ОРВИ ОСАГО ОУН ОУН-УПА ПАК ПАММ'
                . ' ПАСЕ ПБК ПВО ПВП ПВХ ПДД ПЗРК ПК ПКФ ПЛ ПМЖ ПМС ПМЭФ ПТРК ПТС ПТУР ПФР РАМН'
                . ' РАН РАО РБ РБК РВСН РЕН РЖД РИСИ РК РЛС РПГ РПЦ РСЗО РСМД РСФСР РУСАДА РФ РЦ'
                . ' РЭБ САА САР СБ СБУ СВ СВД СИЗО СК СКР СМИ СМС СНГ СП СПА СПГ СПЗ СПИД СПЧ СС'
                . ' СССР СТБ СТС США Т50 ТВ ТВС ТВЦ ТЗ ТНТ ТОСЭР ТРК ТРЦ ТТК ТТП ТЦ ТЭС ТЭФИ ТЭЦ'
                . ' УАЗ УВЗ УЗИ УК УМВД УФНС ФА ФАС ФБК ФБР ФЗ ФИФА ФМС ФНС ФРГ ФРС ФС ФСБ ФСИН'
                . ' ФСК ФССП ХМАО ЦАО ЦАР ЦБ ЦВО ЦДХ ЦИК ЦК ЦНИИ ЦОДД ЦРУ ЦСКА ЦУ ЦУМ ЧАЭС'
                . ' ЧВК ЧМ ЧП ЧС ЧФ ШОС ЭКГ ЮАО ЮАР ЮВО ЮНЕСКО ЯМЗ ЯНАО ЯСИА';
        $sanitize_abbreviations .= ' ТЭК МАЦ ВКП ОПЗ ДФС ОФЗ НБУ СВЧ УПА СКМ ОМПК КБ СММ ОКР ЗПРК'
                . ' ЖЭК БЛА МЭА САО ССО ЗБТ ЕАЭС МОЭК Т-34 Т-64 Т-72 Т-90 Т-80УД Т-90А Т-50 Т-90МС'
                . ' Т-62М Т-44-122 Т-90С Т-14 ФИДЕ ПАО УЕФА МГБ РХБЗ ОЗХО РФПИ ТОФ Т-72БМ ТСН ОФЗ'
                . ' САУ НЭП АФК БРПЛ СНБО Т-80 ХАССП УДК РФПЛ ПЛА ЭРА-ГЛОНАСС Т-62 ОЗУ ЕР СКА ПС';
        $sanitize_abbreviations .= ' ЭЛПУ ТБО МГД СВУ ОДН ХК УССР СЭС ВВО НКО ТГУ ГТО УФСБ ППР ЛСП ЗАЭС НАК ФК';
        $sanitize_abbreviations .= ' ТЮЗ ДРЛО ГЦСИ ЕБРР ГОСТ ИФНС ФСС МФТИ ИКАО ЛЭП ОМС ФИФА ВГТРК';
        $sanitize_abbreviations .= ' УВД НВО СГУ ГСМ ОПГ БФУ ПХГ ВЛКСМ ЭКСПО ТБД АСВ ДРСМД ОП ПАТП ИПК';
        $sanitize_abbreviations .= ' МГЮА ЦСР ЖСК АР ЧТЗ ДВС УДО УСН РСВ ДРГ ФИО СКК ТПУ МЦК ЧОП МЦК КНШ';
        $sanitize_abbreviations .= ' ЦФО КПП ГТА МХТ МКЦ ЧСС ЦУП БМПТ ГТС РТС БКЗ ГК РАМТ ФСО'
                . ' ВФМС ПАЧЭС ВАК БРЭЛЛ БКЗ АКП СФ АКП ЖКУ МСКТ ВИП СВЛК';
        $sanitize_abbreviations .= ' СЦКК МК ГФС КСА УФСИН ОРЗ ЦРБ РКЦ ЮКО ТПП КНБ ВПЗ УФАС РИА СНБ'
                . ' МКД ПСЖ РПА ИНН ОГУ ДДТ ОЭЗ СНТ ДОТ ЛСД АЧС';
        $sanitize_abbreviations .= ' ССА АИ ЕНТ ГУЛАГ БГ ЛФК ТГК СКС ЕНПФ ДВД РФС ККТ БКС ЗИС БЦЖ БАО ГМЗ КГА';
        $sanitize_abbreviations .= ' ТК РПВ ФПБР ОНК ДЧС КБР УПЦ ТС ЗМС РД ГАТИ НГУ РПЛ КЧР СЗАО'
                . ' МНПЗ ПАОК РПЛ ЕГД ЧЕ ФЦП КГНЦ ЦДЖ РПЛ';
        $sanitize_abbreviations .= ' РЭЦ МЮ ГУВД ФНЛ РКК ФРИИ СВХ ПСВ ИИС ИК ФН НТЦ МЖД НЛМК АУЕ'
                . ' ВКО МСБ РУДН СВАО РЭУ ВЭБ ЕАБР';
        $sanitize_abbreviations .= ' НГАУ БТФ БТА БМЗ ОСК НТН КФГД ПСН СГД ГЦС БКЛ ТКО ТАСС МУП ФОМС СГД';
        $sanitize_abbreviations .= ' ВПЧ ССК ЗКО ВПП ТТ НСН КПК НАО ЧК ПФО ГЧП ЧК СКО ЛЧ';
        $sanitize_abbreviations .= ' СНИЛС ФИБА НБА ДОСААФ МПС СПК СЗФО КПЗ РГУ КГД ЕДВ НК КТЖ ЕП'
                . ' УМПО ФПК ЗСО РКН ВСМ ПТУ РККА ЗРС РПК РТР МХАТ ОГЭ ЭЦП РКС ЕГУ ЦДМ БТИ СДС МС ЕП СГБ ОКБ АХЛ СТД';
        $sanitize_abbreviations .= ' КПСС ПЦУ КПВВ КПД СВР ПБС ООС ЛДС ГИА ОБЖ';
        $sanitize_abbreviations .= ' БЖУ РНПК КСУ ИННОПРОМ ИФА ФКР ИВС ТСЖ ВХЛ ЖБИ СНВ ПФЛ ГТРК ПФЛ ОМКФ МФО МСП ТКР';
        $sanitize_abbreviations .= ' БНК ИЗО ППС УИК ВЭФ БСК ЕГРН МГИК ФОК ТД ВККС ИВЛ ПСЦ ГУП МЦД СГК';
        $sanitize_abbreviations .= ' ПФ ДФО ГКБ ПДК СКФО БСМП ГРМ';

        // Слова-исключения, содержащие точки и пробелы, нестандартные замены
        $sanitize_exclude = array(
            'кв. м.'  => 'кв. м.',
            'кв.м.'   => 'кв.м.',
            'леди ю'  => 'Леди Ю',
            'мин. от' => 'мин. от',
            'СПБ'     => 'СПб',
            'СПбГУ'   => 'СПбГУ',
        );

        $pattern = 'А-ЯЁ-'; // Шаблон для слов, написанных капсом

        // Чистим лишние пробелы
        $title = preg_replace('~\s\s+~u', ' ', $title);

        // Восклицательный знак оставляется один (первый от начала заголовка), остальные заменяются на точку.
        $exclamation_mark = mb_stripos($title, '!');
        $title = str_replace('!', '.', $title);
        if ($exclamation_mark !== false) {
            $prev_title = mb_substr($title, 0, $exclamation_mark);
            $post_title = mb_substr($title, $exclamation_mark + 1, mb_strlen($title));
            $title = $prev_title . '!' . $post_title;
        }

        // Заменяем исключения 2
        foreach ($sanitize_exclude as $key => $word) {
            $key_doted = str_replace('.', '&dote;', $key);
            $title = preg_replace('/' . preg_quote($key) . '/ui', $key_doted, $title);
        }

        // Разбираемся со знаками препинания
        // По краям уничтожаем все пробелы, в том числе неразрывные
        // Все многоточия оставляем как есть.
        $title = trim($title, " \t\n\r\0");

        // Последнюю точку уничтожаем, если это не троеточие
        if (mb_substr($title, mb_strlen($title) - 2, 2) != '..') {
            $title = trim($title, '.');
        }

        // Разбираемся с регистром
        // Словам, написанным полностью КАПСЛОКОМ, понижаем регистр,
        // словам, у которых не все буквы большие (Имена, 'ГИБДДшники', 'МКАДе'), регистр не трогаем
        // В некоторых случаях, когда предложение начинается
        // со слова капсом ("ВНЕЗАПНО"), которого нет в словаре сокращений,
        // это может привести к предложению с маленькой буквы. Случаи редки - оставляем на откуп модераторам #24515
        $title = preg_replace_callback('/\b([' . $pattern . ']{2,}+)\b/u', function ($m) {
            return mb_strtolower($m[0]);
        }, $title);

        // Заменяем аббревиатуры
        foreach (explode(' ', $sanitize_abbreviations) as $word) {
            $title = preg_replace('/\b' . preg_quote($word) . '\b/ui', $word, $title);
        }

        // Первый символ в строке переводим в верхний регистр.
        // Учитываем фразы: "это поможет вам похудеть!" - обещает Малышева
        $title = preg_replace_callback('/^["«]?(.)/u', function ($m) {
            return mb_strtoupper($m[0]);
        }, $title);

        // Заменяем исключения 2 обратно
        foreach ($sanitize_exclude as $key => $word) {
            $key_doted = str_replace('.', '&dote;', $key);
            $title = preg_replace('/' . preg_quote($key_doted) . '/ui', $word, $title);
        }

        return $title;
    }

    /**
     * Убирает префикс из строки
     * (используется для удаления префиксов (@nnn@) из email'ов)
     *
     * @param string $str
     * @return string
     */
    public static function clearPrefix($str, $prefix)
    {
        if (!(strpos($str, $prefix) === 0)) {
            return $str;
        }

        return substr($str, strlen($prefix));
    }


    /**
     * Убирает суффикс из строки
     * (используется для удаления суффиксов, например @mediaoption, из email'ов)
     *
     * @param string $str
     * @param string $suffix
     * @return string
     */
    public static function clearSuffix($str, $suffix)
    {
        if ($suffix != substr($str, -strlen($suffix))) {
            return $str;
        }

        return substr($str, 0, -strlen($suffix));
    }


    /**
     * Тег трафика может прийти в формате tag_SITE_ID.
     * Метод возвращает главную часть тега.
     *
     * @param string $tag
     * @return string
     */
    public static function getTrafficTagMainPart($tag)
    {
        if (preg_match('/^([a-zA-Z0-9_]+)_([0-9]+)$/', $tag, $matches)) {
            return $matches[1];
        }

        // В теге нет айдишника сайта, возвращаем тег как есть
        return $tag;
    }


    /**
     * Кодирует строку в urlencode или с пьюникодом, если есть схема и кириллица
     * См. #12302
     *
     * @param string $text
     * @return string
     */
    public static function smartEncode($text)
    {
        $tmpl = "/[^a-zA-Z0-9\-_\.~%\?\:\/#=&@\*\[\]\{\}|\!]/";
        if (preg_match($tmpl, $text)) {
            if (\Da\Helper\Validator::isUrl($text)) {
                $parsed_url = parse_url($text);
                $IDN = new ToIdn();

                if (isset($parsed_url['host'])) {
                    try {
                        $parsed_host = $IDN->convert($parsed_url['host']);
                    } catch (\Throwable $e) {
                        $parsed_host = $parsed_url['host'];
                    }
                } else {
                    $parsed_host = '';
                }

                $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
                $host     = $parsed_host;
                $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
                $path     = (isset($parsed_url['path'])) ? (preg_match($tmpl, $parsed_url['path']) ?
                        urlencode($parsed_url['path']) : $parsed_url['path']) : '';
                $query    = (isset($parsed_url['query'])) ? '?' . (preg_match($tmpl, $parsed_url['query']) ?
                        urlencode($parsed_url['query']) : $parsed_url['query']) : '';
                $fragment = (isset($parsed_url['fragment'])) ? '#' . $parsed_url['fragment'] : '';
                $output = $scheme . $host . $port . str_replace("%2F", "/", $path) . $query . $fragment;
                return $output;
            } else {
                return str_replace("%2F", "/", urlencode($text));
            }
        } else {
            return $text;
        }
    }

    /**
     * Декодирует строку из urlencode или из пьюникода, если есть схема и кириллица
     * См. #12302
     *
     * @param string $text
     * @return string
     */
    public static function smartDecode($text)
    {
        $IDN = new ToUnicode();
        return $IDN->convert(urldecode($text));
    }




    /**
     * "Умный" trim.
     * Заменяет все последовательности пробелов (и неразрывных) и невидимых символов
     * на единичные "обычные" пробелы, а затем - стандартный trim
     *
     * @param string $text
     * @return string
     */
    public static function smartTrim($text)
    {
        return trim(preg_replace('/[\x00-\x20\xA0]+/u', ' ', $text));
    }


    /**
     * Определяет, является ли сегодня выходным
     * Работает только при активном подключении к DB
     *
     * @return boolean
     */
    public static function isWeekend()
    {
        global $db;

        $query = "
            SELECT
                CASE
                    WHEN cw.is_workday THEN 0
                    WHEN NOT cw.is_workday THEN 1
                    WHEN t.dow IN (0, 6) THEN 1
                    ELSE 0
                END AS is_weekend
            FROM (
                SELECT EXTRACT(dow FROM CURRENT_DATE) AS dow
            ) AS t
            LEFT JOIN da.custom_workdays AS cw ON (cw.day = CURRENT_DATE)
        ";
        $sth = $db->prepare($query);
        $sth->execute();
        return (bool) $sth->fetchColumn();
    }
}
