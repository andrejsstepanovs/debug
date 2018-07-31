<?php

/**
 * Debugging class.
 *
 * To be able to allways use these methods - add this line to php.ini
 * auto_prepend_file = full_path_to/auto_prepend_file.php
 *
 * @author Andrejs Stepanovs <andrejsstepanovs@gmail.com>
 */
class DEBUG // @codingStandardsIgnoreLine
{
    /** @var bool */
    protected static $enabled = true;

    /**
     * @param mixed $enabled
     */
    public static function setEnabled($enabled)
    {
        self::$enabled = $enabled;
    }

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        return self::$enabled;
    }

    /**
     * dump data and continue
     *
     * @param mixed $data can process multiple parameters
     */
    public static function dumplive($data)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        $args = func_get_args();
        foreach ($args as $arg) {
            $data  = DEBUG::addCalledIn();
            $out = [];
            $out[] = $data[0];
            $out[] = DEBUG::intDump($arg);

            DEBUG::showFormated(implode('', $out), $data[1], $data[2]);
        }
    }

    /**
     * @param object $data
     * @return void|string|null
     */
    public static function getclass($data)
    {
        return self::dump(get_class($data));
    }

    /**
     * dump data and die
     *
     * @param mixed $data can process multiple parameters
     */
    public static function dump($data)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        $args = func_get_args();
        foreach ($args as $arg) {
            DEBUG::dumplive($arg);
        }
        exit;
    }

    /**
     * Shows back trace and live.
     *
     * @param integer      $showLast  tace line count to show. You can pass here $highlight value too.
     * @param string|array $highlight lines that will have this sting will be highlighted
     */
    public static function tracelive($showLast = null, $highlight = 'mysportbrands_')
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        if (strval($highlight)) {
            $highlight = array($highlight);
        }
        if ($showLast && !is_numeric($showLast)) {
            if (is_array($showLast)) {
                $highlight = $showLast;
            } elseif (is_string($showLast)) {
                $highlight = array ($showLast);
            }
        }

        $xdebug = true;
        ob_start();
        if (function_exists('xdebug_print_function_stack')) {
            xdebug_print_function_stack();
        } else {
            $xdebug = false;
            debug_print_backtrace();
        }
        $traceOutput = ob_get_contents();
        ob_end_clean();

        if ($xdebug) {
            $traceOutput = strip_tags($traceOutput);
        }

        // converting data to array
        $trace = explode("\n", $traceOutput);


        //removing first and last unnecessary lines
        if ($xdebug) {
            foreach ($trace as $i => $line) {
                if (stripos($line, __METHOD__) !== false) {
                    break;
                }
            }
            $trace = array_slice($trace, 4, $i - 4);

            // show only last X trace lines
            if (is_numeric($showLast) && $showLast > 0) {
                $trace = array_slice($trace, count($trace) - $showLast);
            }
        }

        $traceArray = debug_backtrace();

        $files = array();
        foreach ($trace as $i => $line) {
            // remove time
            if ($xdebug) {
                $len = strspn($line, "1234567890.");
                if ($len > 0) {
                    $line = substr($line, $len);
                }
            }

            // highlight occurance lines
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                if ($highlight && is_array($highlight)) {
                    foreach ($highlight as $highlightVal) {
                        if (stripos($line, $highlightVal) !== false) {
                            $line = '<span style="color:red;display:inline-block;width:100%;">'.$line.'</span>';
                        }
                    }
                }
            }

            // find files
            if (strrpos($line, ':') !== false) {
                $lineArr = explode(':', $line);
                if (count($lineArr) > 1) {
                    $file = $lineArr[count($lineArr) - 2];
                    if (strpos($file, '/') !== false) {
                        $file = trim(substr($file, strpos($file, '/')));

                        if (strpos($file, ' ') !== false) {
                            $fileArr = explode(' ', $file);
                            foreach ($fileArr as $file) {
                                $file = str_replace("'", '', $file);
                                $file = trim(substr($file, strpos($file, '/')));
                                if (preg_match("/[.]+[a-z]{3}+$/", $file)) {
                                    $files[] = $file;
                                }
                            }
                        } elseif (preg_match("/[.]+[a-z]{3}+$/", $file)) {
                            $files[] = $file;
                        }
                    }
                }
            }
            $trace[$i] = $line;
        }

        // make lines clickable
        $id          = uniqid('trace');
        $markedColor = 'yellow';
        foreach ($trace as $i => $line) {
            $span   = array();
            $span[] = '<span onclick="this.style.backgroundColor=this.style.backgroundColor == \''.$markedColor.'\' ? \'\' : \''.$markedColor.'\'" >';
            $span[] = $trace[$i];
            $span[] = '</span>';

            $trace[$i] = str_replace(array("\n", '<p>', '</p>'), '', implode('', $span));
        }


        $html = implode("\n", $trace);

        // files to a href
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            if (count($files) > 0) {
                $files = array_unique($files);
                foreach ($files as $file) {
                    $html = str_replace(
                        $file,
                        '<a href="file:///'.ltrim($file, '/').'" onclick="return false;" >'.$file.'</a>',
                        $html
                    );
                }
            }
        }

        $data = DEBUG::addCalledIn();
        $html = $data[0].$html;

        DEBUG::showFormated($html, $data[1], $data[2]);
    }

    /**
     * Shows back trace and die.
     *
     * @param int          $showLast  tace line count to show
     * @param string|array $highlight lines that will have this sting will be highlighted
     */
    public static function trace($showLast = null, $highlight = 'mysportbrands_')
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        DEBUG::tracelive($showLast, $highlight);
        exit;
    }

    /**
     * Show class methods
     *
     * @param object $object
     * @param bool   $html
     */
    public static function methodslive($object, $html = true)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        if (is_object($object)) {
            $className = get_class($object);

            $class = new ReflectionClass($className);

            $methods = array();
            foreach ($class->getMethods() as $method) {
                $methods[$method->class][] = $method->name;
            }

            if (empty($_SERVER['DOCUMENT_ROOT']) || !$html) {
                $out   = array();
                $out[] = $className;

                $reflection = new ReflectionClass($object);
                $out[]      = $reflection->getFileName();
            } else {
                $out   = array('<div>');
                $out[] = '<span style="font-weight:normal;">Class</span> <span style="font-weight:bold;">'.$className.'</span>';

                $reflection = new ReflectionClass($object);
                $out[] = ' <a href="file:///'.ltrim($reflection->getFileName(), '/').'" onclick="return false;" >'.$reflection->getFileName().'</a>';
                $out[] = '</div>';
            }
            $lastDeclaringClass = null;

            $methodsData = array();
            foreach ($methods as $class => $methodsList) {
                foreach ($methodsList as $i => $method) {
                    $refMethod = new ReflectionMethod($class, $method);
                    $params    = $refMethod->getParameters();

                    // parameters string
                    $paramsString = array();
                    foreach ($params as $param) {
                        $par = array();
                        if ($param->isArray()) {
                            $par[] = '(array)';
                        }
                        $par[] = '$'.$param->getName();
                        if ($param->isDefaultValueAvailable()) {
                            $default = $param->getDefaultValue();
                            if (is_string($default)) {
                                if ($default == '') {
                                    $par[] = "= ''";
                                } else {
                                    $par[] = "= '".$default."'";
                                }
                            } elseif (is_array($default) && !count($default)) {
                                $par[] = '= array()';
                            } elseif (is_null($default)) {
                                $par[] = '= NULL';
                            } else {
                                $par[] = '= '.var_export($default, true);
                            }
                        }
                        $paramsString[] = implode(' ', $par);
                    }

                    $declaringClass = new ReflectionClass($class);

                    $scope = array();
                    if ($refMethod->isPublic()) {
                        $scope[] = 'public';
                    }
                    if ($refMethod->isStatic()) {
                        $scope[] = 'static';
                    }
                    if ($refMethod->isProtected()) {
                        $scope[] = 'protected';
                    }
                    if ($refMethod->isPrivate()) {
                        $scope[] = 'private';
                    }
                    if ($refMethod->isFinal()) {
                        $scope[] = 'final';
                    }

                    if (empty($_SERVER['DOCUMENT_ROOT']) || !$html) {
                        $methods[$i] = implode(' ', $scope).' '.$method.'('.implode(', ', $paramsString).') ';
                        $methods[$i] .= $class.' '.$declaringClass->getFileName().' : '.$refMethod->getStartLine();
                    } else {
                        $id          = uniqid();
                        $methods[$i] = '<span id="'.$id.'_name" onclick="
                                            var decl = document.getElementById(\''.$id.'\');
                                            var name = document.getElementById(\''.$id.'_name\');
                                            if(decl.style.display == \'inline-block\'){
                                                decl.style.display=\'none\';
                                            }else{
                                                decl.style.display=\'inline-block\';
                                            }
                                        " >'.implode(' ', $scope).' '.$method.'('.implode(', ', $paramsString).')'.'</span>';

                        $methods[$i] .= ' <span id="'.$id.'" style="border:1px solid black;display:none;padding-right:10px;text-align:left;" >';
                        $methods[$i] .= $class.' <a href="file:///'.ltrim($declaringClass->getFileName(), '/').'" onclick="return false;" >'.$declaringClass->getFileName().'</a> : '.$refMethod->getStartLine();
                        $methods[$i] .= '</span>';
                    }

                    $methodsData[$class][] = $methods[$i];
                }
            }

            $out[] = DEBUG::intDump($methodsData);

            $data = DEBUG::addCalledIn();
            DEBUG::showFormated($data[0].implode('', $out), $data[1], $data[2], $html);
        } else {
            DEBUG::dumplive($object);
        }
    }

    /**
     * @param object $object
     */
    public static function methods($object)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        DEBUG::methodslive($object);
        exit;
    }

    /**
     * Returns class full path to filename
     *
     * @param Object $object
     *
     * @return string|null path to file
     */
    public static function classFile($object)
    {
        if (!\DEBUG::isEnabled() || !is_object($object)) {
            return null;
        }
        $reflection = new ReflectionClass($object);

        return $reflection->getFileName();
    }

    /**
     * Checks if all values in array are normal arrays.
     *
     * @param mixed $array
     *
     * @return boolean
     */
    public static function isPureArray($array)
    {
        if (!is_array($array)) {
            return false;
        }
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                if (!DEBUG::isPureArray($val)) {
                    return false;
                }
            } else {
                if (is_object($val)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param mixed $value
     * @param bool  $html
     * @param bool  $isLog
     * @return string|void
     */
    public static function intDump($value, $html = true, $isLog = false)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        $dump = false;
        ob_start();
        if (is_string($value)) {
            echo 'string('.strlen($value).') "'.$value.'"';
        } elseif (is_array($value) && DEBUG::isPureArray($value)) {
            print_r($value);
        } else {
            var_dump($value);
            $dump = true;
        }
        $output = ob_get_contents();
        ob_end_clean();


        if (is_object($value) && is_a($value, 'Zend_Db_Select')) {
            $sqloutput = DEBUG::getFormattedSQL((string) $value);
            if (empty($_SERVER['DOCUMENT_ROOT']) || !$html) {
            } else {
                $id = uniqid();
                $sqloutput
                    = '<div style="border:1px solid gray;margin-top:15px;padding:5px;" onclick="
                    var full = document.getElementById(\''.$id.'_full\');
                    if(full.style.display == \'block\'){
                        full.style.display=\'none\';
                    }else{
                        full.style.display=\'block\';
                    }
                ">'.trim($sqloutput, '<br/>').'</div>';
                $sqloutput .= '<div style="border:1px solid brown;white-space:normal;margin-top:15px;padding:5px;display:none;" id="'.$id.'_full" >'.(string) $value.'</div>';
            }
            if ($isLog) {
                $output = (string) $value;
            } else {
                $output = $sqloutput."\n\n".$output;
            }
        } elseif (is_string($value) && strpos($value, 'SELECT') !== false && strpos($value, 'FROM') !== false) {
            $sqloutput = DEBUG::getFormattedSQL($value);

            $id = uniqid();
            $sqloutput
                = '<div style="border:1px solid gray;margin-top:15px;padding:5px;" onclick="
                var full = document.getElementById(\''.$id.'_full\');
                if(full.style.display == \'block\'){
                    full.style.display=\'none\';
                }else{
                    full.style.display=\'block\';
                }
            ">'.trim($sqloutput, '<br/>').'</div>';
            $sqloutput .= '<div style="border:1px solid brown;white-space:normal;margin-top:15px;padding:5px;display:none;" id="'.$id.'_full" >'.$value.'</div>';

            if ($isLog) {
                $output = $value;
            } else {
                $output = $sqloutput."\n\n".$output;
            }
        }

        // formating var_dump output. removing unnecessary spaces and newlines
        if ($dump) {
            $output = str_replace("=>\n", '=>', $output);
            $output = str_replace("  ", '    ', $output);
            for ($i = 50; $i >= 4; $i--) {
                $space = str_repeat(' ', $i);
                if (strpos($output, $space) === false) {
                    continue;
                }
                $output = str_replace("{\n".$space."}", '{}', $output);
                $output = str_replace('=>'.$space, '=> ', $output);
            }
        }

        $classFile = $methods = '';
        if (is_object($value)) {
            $reflection = new ReflectionClass(get_class($value));
            if (empty($_SERVER['DOCUMENT_ROOT']) || !$html) {
                $classFile = "\n".$reflection->getFileName()."\n";
                $output    = "\n".$output."\n";
            } else {
                $classFile = '<a href="file:///'.ltrim($reflection->getFileName(), '/').'" onclick="return false;" >'.$reflection->getFileName().'</a>'."\n";
                ob_start();
                DEBUG::methodslive($value);
                $methods = ob_get_contents();
                ob_end_clean();
            }
        }

        return $classFile.$output.$methods;
    }

    /**
     * @param string $sqlRaw
     * @return bool|string
     */
    public static function getFormattedSQL($sqlRaw)
    {
        if (empty($sqlRaw) || !is_string($sqlRaw)) {
            return false;
        }

        $sqlReservedAll = array(
            'ACCESSIBLE', 'ACTION', 'ADD', 'AFTER', 'AGAINST', 'AGGREGATE', 'ALGORITHM', 'ALL', 'ALTER', 'ANALYSE',
            'ANALYZE', 'AND', 'AS', 'ASC',
            'AUTOCOMMIT', 'AUTO_INCREMENT', 'AVG_ROW_LENGTH', 'BACKUP', 'BEGIN', 'BETWEEN', 'BINLOG', 'BOTH', 'BY',
            'CASCADE', 'CASE', 'CHANGE', 'CHANGED',
            'CHARSET', 'CHECK', 'CHECKSUM', 'COLLATE', 'COLLATION', 'COLUMN', 'COLUMNS', 'COMMENT', 'COMMIT',
            'COMMITTED', 'COMPRESSED', 'CONCURRENT',
            'CONSTRAINT', 'CONTAINS', 'CONVERT', 'CREATE', 'CROSS', 'CURRENT_TIMESTAMP', 'DATABASE', 'DATABASES', 'DAY',
            'DAY_HOUR', 'DAY_MINUTE',
            'DAY_SECOND', 'DEFINER', 'DELAYED', 'DELAY_KEY_WRITE', 'DELETE', 'DESC', 'DESCRIBE', 'DETERMINISTIC',
            'DISTINCT', 'DISTINCTROW', 'DIV',
            'DO', 'DROP', 'DUMPFILE', 'DUPLICATE', 'DYNAMIC', 'ELSE', 'ENCLOSED', 'END', 'ENGINE', 'ENGINES', 'ESCAPE',
            'ESCAPED', 'EVENTS', 'EXECUTE',
            'EXISTS', 'EXPLAIN', 'EXTENDED', 'FAST', 'FIELDS', 'FILE', 'FIRST', 'FIXED', 'FLUSH', 'FOR', 'FORCE',
            'FOREIGN', 'FROM', 'FULL', 'FULLTEXT',
            'FUNCTION', 'GEMINI', 'GEMINI_SPIN_RETRIES', 'GLOBAL', 'GRANT', 'GRANTS', 'GROUP', 'HAVING', 'HEAP',
            'HIGH_PRIORITY', 'HOSTS', 'HOUR', 'HOUR_MINUTE',
            'HOUR_SECOND', 'IDENTIFIED', 'IF', 'IGNORE', 'IN', 'INDEX', 'INDEXES', 'INFILE', 'INNER', 'INSERT',
            'INSERT_ID', 'INSERT_METHOD', 'INTERVAL',
            'INTO', 'INVOKER', 'IS', 'ISOLATION', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LAST_INSERT_ID', 'LEADING', 'LEFT',
            'LEVEL', 'LIKE', 'LIMIT', 'LINEAR',
            'LINES', 'LOAD', 'LOCAL', 'LOCK', 'LOCKS', 'LOGS', 'LOW_PRIORITY', 'MARIA', 'MASTER',
            'MASTER_CONNECT_RETRY', 'MASTER_HOST', 'MASTER_LOG_FILE',
            'MASTER_LOG_POS', 'MASTER_PASSWORD', 'MASTER_PORT', 'MASTER_USER', 'MATCH', 'MAX_CONNECTIONS_PER_HOUR',
            'MAX_QUERIES_PER_HOUR',
            'MAX_ROWS', 'MAX_UPDATES_PER_HOUR', 'MAX_USER_CONNECTIONS', 'MEDIUM', 'MERGE', 'MINUTE', 'MINUTE_SECOND',
            'MIN_ROWS', 'MODE', 'MODIFY',
            'MONTH', 'MRG_MYISAM', 'MYISAM', 'NAMES', 'NATURAL', 'NOT', 'NULL', 'OFFSET', 'ON', 'OPEN', 'OPTIMIZE',
            'OPTION', 'OPTIONALLY', 'OR',
            'ORDER', 'OUTER', 'OUTFILE', 'PACK_KEYS', 'PAGE', 'PARTIAL', 'PARTITION', 'PARTITIONS', 'PASSWORD',
            'PRIMARY', 'PRIVILEGES', 'PROCEDURE',
            'PROCESS', 'PROCESSLIST', 'PURGE', 'QUICK', 'RAID0', 'RAID_CHUNKS', 'RAID_CHUNKSIZE', 'RAID_TYPE', 'RANGE',
            'READ', 'READ_ONLY',
            'READ_WRITE', 'REFERENCES', 'REGEXP', 'RELOAD', 'RENAME', 'REPAIR', 'REPEATABLE', 'REPLACE', 'REPLICATION',
            'RESET', 'RESTORE', 'RESTRICT',
            'RETURN', 'RETURNS', 'REVOKE', 'RIGHT', 'RLIKE', 'ROLLBACK', 'ROW', 'ROWS', 'ROW_FORMAT', 'SECOND',
            'SECURITY', 'SELECT', 'SEPARATOR',
            'SERIALIZABLE', 'SESSION', 'SET', 'SHARE', 'SHOW', 'SHUTDOWN', 'SLAVE', 'SONAME', 'SOUNDS', 'SQL',
            'SQL_AUTO_IS_NULL', 'SQL_BIG_RESULT',
            'SQL_BIG_SELECTS', 'SQL_BIG_TABLES', 'SQL_BUFFER_RESULT', 'SQL_CACHE', 'SQL_CALC_FOUND_ROWS', 'SQL_LOG_BIN',
            'SQL_LOG_OFF',
            'SQL_LOG_UPDATE', 'SQL_LOW_PRIORITY_UPDATES', 'SQL_MAX_JOIN_SIZE', 'SQL_NO_CACHE', 'SQL_QUOTE_SHOW_CREATE',
            'SQL_SAFE_UPDATES',
            'SQL_SELECT_LIMIT', 'SQL_SLAVE_SKIP_COUNTER', 'SQL_SMALL_RESULT', 'SQL_WARNINGS', 'START', 'STARTING',
            'STATUS', 'STOP', 'STORAGE',
            'STRAIGHT_JOIN', 'STRING', 'STRIPED', 'SUPER', 'TABLE', 'TABLES', 'TEMPORARY', 'TERMINATED', 'THEN', 'TO',
            'TRAILING', 'TRANSACTIONAL',
            'TRUNCATE', 'TYPE', 'TYPES', 'UNCOMMITTED', 'UNION', 'UNIQUE', 'UNLOCK', 'UPDATE', 'USAGE', 'USE', 'USING',
            'VALUES', 'VARIABLES',
            'VIEW', 'WHEN', 'WHERE', 'WITH', 'WORK', 'WRITE', 'XOR', 'YEAR_MONTH',
        );

        $sqlSkipReservedWords    = array('AS', 'ON', 'USING');
        $sqlSpecialReservedWords = array('(', ')');

        $sqlRaw = str_replace("\n", " ", $sqlRaw);

        $sqlFormatted = "";

        $prevWord = "";
        $word      = "";

        for ($i = 0, $j = strlen($sqlRaw); $i < $j; $i++) {
            $word .= $sqlRaw[$i];

            $wordTrimmed = trim($word);

            if ($sqlRaw[$i] == " " || in_array($sqlRaw[$i], $sqlSpecialReservedWords)) {
                $wordTrimmed = trim($word);

                $trimmedSpecial = false;

                if (in_array($sqlRaw[$i], $sqlSpecialReservedWords)) {
                    $wordTrimmed    = substr($wordTrimmed, 0, -1);
                    $trimmedSpecial = true;
                }

                $wordTrimmed = strtoupper($wordTrimmed);

                if (in_array($wordTrimmed, $sqlReservedAll) && !in_array($wordTrimmed, $sqlSkipReservedWords)) {
                    if (in_array($prevWord, $sqlReservedAll)) {
                        $sqlFormatted .= '<strong>'.strtoupper(trim($word)).'</strong>'.'&nbsp;';
                    } else {
                        $sqlFormatted .= '<br/>&nbsp;';
                        $sqlFormatted .= '<strong>'.strtoupper(trim($word)).'</strong>'.'&nbsp;';
                    }

                    $prevWord = $wordTrimmed;
                    $word      = "";
                } else {
                    $sqlFormatted .= trim($word).'&nbsp;';

                    $prevWord = $wordTrimmed;
                    $word      = "";
                }
            }
        }

        $sqlFormatted .= trim($word);

        return $sqlFormatted;
    }

    /**
     * @param mixed $data
     * @param null  $name
     * @param bool  $minimized
     * @param bool  $showhtml
     */
    public static function showFormated($data, $name = null, $minimized = false, $showhtml = true)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        $html = array();

        if (empty($_SERVER['DOCUMENT_ROOT']) || !$showhtml) {
            $html[] = '####### '.$name." #######\n".$data;
        } else {
            $id = uniqid();
            if ($name) {
                $html[] = '<div id="'.$id.'_name" style="font-weight:bold;text-decoration:underline;cursor: pointer;" onclick="
                    var pre = document.getElementById(\''.$id.'\');
                    var name = document.getElementById(\''.$id.'_name\');
                    if(pre.style.display == \'block\'){
                        pre.style.display=\'none\';
                        name.innerHTML = \''.addslashes($name).' [+]\'
                    }else{
                        pre.style.display=\'block\';
                        name.innerHTML = \''.addslashes($name).' [-]\'
                    }
                " >'.$name.' ['.($minimized ? '+' : '-').']</div>';
            }

            $display = $minimized ? 'none' : 'block';
            $html[] = '<pre style="padding:5px;text-align:left;border:1px solid black;float:none;background:white;min-width:850px;max-width:1260px;margin:10px auto;display:'.$display.';" id="'.$id.'" >';
            $html[] = $data;
            $html[] = '</pre>';
        }

        echo implode('', $html);
    }

    /**
     * @return array
     */
    public static function addCalledIn()
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        $trace = debug_backtrace();

        // find where function is called
        $i = 0;
        while (count($trace) > $i && array_key_exists($i, $trace) && array_key_exists('class', $trace[$i])
            && $trace[$i]['class'] == get_class()) {
            $i++;
        }

//        if($trace[$i]['function'])

        $methods = array('_add_called_in', 'methodslive', '_dump', 'dumplive', 'log', 'logtrace', 'logmethods');
        $found   = true;
        foreach ($methods as $method) {
            $tmpMethodFound = false;
            foreach ($trace as $data) {
                if ($data['function'] == $method) {
                    $tmpMethodFound = true;
                    break;
                }
            }
            if (!$tmpMethodFound) {
                $found = false;
                break;
            }
        }

        // called from DEBUG class.
        if ($found) {
            $i = $i - 2;
            if ($trace[$i]['function'] == 'dumplive') {
                $i = $i - 1;
            }
        }

        // find called line $variable name
        $calledAt    = $trace[$i - 1];
        $callingFile = file($calledAt['file'], FILE_IGNORE_NEW_LINES);

        // finding correct line if method is called in multiple lines. concat all lines together so all parameters are included.
        $j           = 1;
        $callingLine = $calledIn = '';
        while (strpos($callingLine, get_class()) === false) {
            if ($calledAt['line'] - $j <= 0) {
                break;
            }
            if (array_key_exists($calledAt['line'] - $j, $callingFile)) {
                $callingLine = $callingFile[$calledAt['line'] - $j].trim($callingLine);
            }
            $j++;
        }

        // removing all text before called method and last ';'
        $callingLine = trim(substr($callingLine, strpos($callingLine, get_class().'::')), ' ;');

        // if method is not ending with ) then add '...'
        if (substr($callingLine, -1) != ')' || substr_count($callingLine, '(') != substr_count($callingLine, ')')) {
            $callingLine .= '...';
        }

        // create 'Called in' button
        $id  = uniqid();
        $out = array();

        $file = explode('/', $trace[$i - 1]['file']);

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $out[] = "\n".array_pop($file).' : '.$trace[$i - 1]['line'];
            $k     = !array_key_exists($i, $trace) ? $i - 1 : $i;
            if (array_key_exists($k, $trace) && array_key_exists('class', $trace[$k]) && array_key_exists('file', $trace[$k])) {
                $out[] = "\n".$trace[$k]['class'].'::'.$trace[$k]['function'].' '.$trace[$i - 1]['file'].' : '.$trace[$i - 1]['line']."\n";
                $calledIn = array($trace[$k]['class'], $trace[$k]['function'], $trace[$i - 1]['file'], $trace[$i - 1]['line']);
            }
        } else {
            $out[] = '<div id="'.$id.'_trace" style="display:inline-block;float:right;" >';
            $out[] = '<span id="'.$id.'_name" style="color:gray;font-size:8px;cursor:pointer;" onclick="
                                var call = document.getElementById(\''.$id.'\');
                                var name = document.getElementById(\''.$id.'_name\');
                                var trace = document.getElementById(\''.$id.'_trace\');
                                if(call.style.display == \'inline-block\'){
                                    call.style.display=\'none\';
                                    trace.style.display=\'inline-block\';
                                    trace.style.float=\'right\';
                                }else{
                                    call.style.display=\'inline-block\';
                                    trace.style.display=\'block\';
                                    trace.style.float=\'none\';
                                }
                          " >'.array_pop($file).' : '.$trace[$i - 1]['line'].'</span>';
            $out[] = '<div id="'.$id.'" style="display:none;" >';
            $k     = !array_key_exists($i, $trace) ? $i - 1 : $i;
            if (array_key_exists($k, $trace) && array_key_exists('class', $trace[$k]) && array_key_exists('file', $trace[$k])) {
                $out[] = '&nbsp;<span style="color:gray;" >'.$trace[$k]['class'].'::'.$trace[$k]['function'].'</span> <a href="file:///'.ltrim($trace[$k]['file'], '/').'" onclick="return false;" >'.$trace[$i - 1]['file'].'</a> : '.$trace[$i - 1]['line'].'&nbsp;';
                $calledIn = array($trace[$k]['class'], $trace[$k]['function'], $trace[$i - 1]['file'], $trace[$i - 1]['line']);
            }
            $out[] = '</div>';
            $out[] = '</div>';
        }

        return array(implode('', $out), $callingLine, $found, $calledIn);
    }

    /**
     * Log message to $filename.
     *
     * @param mixed   $message   if not set will log called in __METHOD__
     * @param boolean $stripTags strip html tags before log to file
     * @param string  $filename  optional. full path to log file.
     */
    public static function log($message = '__METHOD__', $stripTags = true, $filename = null)
    {
        if (!$filename) {
            $filename = dirname(__FILE__).'/log.txt';
        }
        if ($message !== '__METHOD__') {
            $message = DEBUG::intDump($message, false, true);
        }

        $terminalLength = 131;

        // logging where and how it is called
        $calledLine = 'unknown';
        $calledIn   = DEBUG::addCalledIn();
        if (is_array($calledIn) && count($calledIn) >= 3) {
            $calledLine = $calledIn[1];
            if (is_array($calledIn[3]) && count($calledIn[3]) >= 3) {
                $pathinfo = pathinfo($calledIn[3][2]);

                $file = $pathinfo['filename'].'.'.$pathinfo['extension'];
                $file = str_pad($file, 20);

                $calledLine = $file.':'.str_pad($calledIn[3][3], 4).' >> '.substr($calledLine, 0, 22);

                if ($message == '__METHOD__') {
                    // default called __METHOD__
                    $message = $calledIn[3][0].'::'.$calledIn[3][1];
                }
            }
        }

        if (strpos($message, 'bool(') !== false) {
            $message = str_replace("\n", '', $message);
        }

        if ($stripTags) {
            $message = strip_tags($message);
            if (extension_loaded('xdebug')) {
                $message = html_entity_decode($message);
            }
        }

        $time    = time();
        $message = date('H:i:s', $time).'. '.$message;
        $message = str_pad($message, $terminalLength);

        // adding new lines if log is older than others.
        $lasttime = DEBUG::getLastLine($filename);
        $lasttime = strtotime(substr($lasttime, 0, strpos($lasttime, '.')));
        if ($lasttime >= $time + 5) {
            $message = str_repeat(PHP_EOL, 3).$message;
        }

        date_default_timezone_set('Europe/Berlin');
        $fd = fopen($filename, 'a');

        fwrite($fd, $message.' | '.$calledLine.''.PHP_EOL);
        fclose($fd);
    }

    /**
     * @param string $file
     * @return string
     */
    public static function getLastLine($file)
    {
        $line = '';

        $f      = fopen($file, 'r');
        $cursor = -1;

        fseek($f, $cursor, SEEK_END);
        $char = fgetc($f);

        /**
         * Trim trailing newline chars of the file
         */
        while ($char === "\n" || $char === "\r") {
            fseek($f, $cursor--, SEEK_END);
            $char = fgetc($f);
        }

        /**
         * Read until the start of file or first newline char
         */
        while ($char !== false && $char !== "\n" && $char !== "\r") {
            /**
             * Prepend the new char
             */
            $line = $char.$line;
            fseek($f, $cursor--, SEEK_END);
            $char = fgetc($f);
        }

        return $line;
    }

    /**
     * @param null|string $filename
     * @return array
     */
    public static function logtrace($filename = null)
    {
        $trace = debug_backtrace();

        $log = array();
        foreach ($trace as $data) {
            $d = array('class' => 'Unknown', 'function' => 'unknown', 'file' => 'unknown', 'line' => '?');
            foreach (array_keys($d) as $key) {
                if (array_key_exists($key, $data)) {
                    $d[$key] = $data[$key];
                }
            }
            $log[] = $d['class'].'::'.$d['function'].' ['.$d['file'].' : '.$d['line'].']';
        }

        $stripTags = true;
        DEBUG::log($log, $stripTags, $filename);

        return $log;
    }

    /**
     * @param object      $object
     * @param null|string $filename
     * @return array|void
     */
    public static function logmethods($object, $filename = null)
    {
        if (!\DEBUG::isEnabled()) {
            return;
        }

        $log = array(get_class($object), get_class_methods($object));
        DEBUG::log($log, $filename);

        return $log;
    }


    /**
     * Returns beutifyed array code, that you can paste directly into php script.
     * Is working only with string values.
     *
     * @param array    $array
     * @param bool     $sort
     * @param string   $spaceSymbol
     * @param int      $tabLength   optional <br/> or \n will be used accordingly
     * @param bool     $return
     * @param bool|int $recursion
     *
     * @return string|bool php script
     */
    public static function beutifyArray($array, $sort = false, $spaceSymbol = null, $tabLength = 4, $return = false, $recursion = false)
    {
        if (!is_array($array)) {
            return false;
        }

        if (!$spaceSymbol) {
            $spaceSymbol = empty($_SERVER['DOCUMENT_ROOT']) ? ' ' : ' ';
        }
        $newline = empty($_SERVER['DOCUMENT_ROOT']) ? PHP_EOL : '<br/>';


        // find longest key
        $longestKey = 0;
        foreach ($array as $key => $val) {
            if (strlen($key) > $longestKey) {
                $longestKey = strlen($key);
            }
        }

        if ($sort) {
            ksort($array);
        }

        $called = DEBUG::addCalledIn();
        $called = str_replace(array(__METHOD__, '(', ')'), '', $called[1]);
        if (strpos($called, ',') !== false) {
            $arrayVariableName = trim(substr($called, 0, strpos($called, ',')));
        } else {
            $arrayVariableName = trim($called);
        }
        $arrayVariableName = str_replace('unserialize', '', $arrayVariableName);

        if (!$recursion) {
            $out = array($arrayVariableName.$spaceSymbol.'='.$spaceSymbol.'array(');
        } else {
            $out = array('array(');
        }

        end($array); // move the internal pointer to the end of the array
        $lastKey = key($array); // fetches the key of the element pointed to by the internal pointer

        if (!count($array)) {
            $out[] = '';
        }

        foreach ($array as $key => $val) {
            if (is_numeric($val)) {
            } elseif (is_string($val)) {
                if (strpos($val, "'") !== false) {
                    $val = '"'.$val.'"';
                } else {
                    $val = "'".$val."'";
                }
            } elseif (is_bool($val)) {
                $val = $val == true ? 'true' : 'false';
            } elseif (is_null($val)) {
                $val = 'null';
            } elseif (is_array($val)) {
                $rec = 1;
                if ($recursion) {
                    $rec = $recursion + 2;
                }
                $val = DEBUG::beutifyArray($val, $sort, $spaceSymbol, $tabLength, true, $rec);
            } else {
                $val = "''".$spaceSymbol.' /* unknown */';
            }

            $coma = '';
            if ($key != $lastKey) {
                $coma = ',';
            }

            if (!$recursion) {
                $out[] = str_repeat($spaceSymbol, $tabLength).str_pad("'".$key."'", $longestKey + 2, $spaceSymbol).$spaceSymbol.'=>'.$spaceSymbol.$val.$coma;
            } else {
                $out[] = str_repeat($spaceSymbol, $recursion * $tabLength * 2).str_pad("'".$key."'", $longestKey + 2, $spaceSymbol).$spaceSymbol.'=>'.$spaceSymbol.$val.$coma;
            }
        }

        if (!$recursion) {
            $out[] = ');';
        } else {
            $len = (int) $recursion * $tabLength * 2 - $tabLength * 4;
            if ($len <= 0) {
                $len = $tabLength;
            }

            $out[] = str_repeat($spaceSymbol, $len).')';
        }

        $string = implode($newline, $out);
        if (!empty($_SERVER['DOCUMENT_ROOT']) && !$recursion) {
            $string = '<pre>'.$string.'</pre>';
        }

        if ($return) {
            return $string;
        }
        echo $string;
        exit;
    }

    /**
     *
     */
    public function resetTime()
    {
        static $microtimeStart = null;
        static $microtimeLast = null;

        $microtimeStart = null;
        $microtimeLast = null;
    }

    /**
     *
     * @staticvar null $microtime_start
     * @staticvar null $microtime_last
     *
     * @param bool $last
     * @param bool $html
     * @param bool $echo
     * @param int  $round
     *
     * @return string|void
     */
    public static function getExecutionTime($last = false, $html = true, $echo = true, $round = 4)
    {
        $html = $html ? '<br />' : '';

        static $microtimeStart = null;
        static $microtimeLast = null;
        date_default_timezone_set('Europe/Berlin');


        $called      = DEBUG::addCalledIn();
        $calledFrom = 'unknown';

        if (is_array($called) && array_key_exists(3, $called) && is_array($called[3]) && array_key_exists(2, $called[3])
            && array_key_exists(3, $called[3])
        ) {
            $calledFrom = $called[3][0].'::'.$called[3][1].' ['.$called[3][2].' : '.$called[3][3].']';
        }

        if ($microtimeStart === null) {
            $microtimeLast = $microtimeStart = microtime(true);
            $return         = 'START: '.date('Y-m-d H:i:s', $microtimeStart).' ('.$calledFrom.')'.$html;
            if ($echo) {
                echo $return.PHP_EOL;
            } else {
                return $return;
            }
        } else {
            if ($last) {
                DEBUG::getExecutionTime();
                $microtimeLast = $microtimeStart;
            }
            $ms = (microtime(true) - (float) $microtimeLast) * 1000;

            $sec = round($ms / 1000, $round);
            $min = round($sec / 60, $round);

            if ($min >= 1) {
                $val   = $min;
                $label = 'min';
            } elseif ($sec >= 1) {
                $val   = $sec;
                $label = 'sec';
            } else {
                $val   = $ms;
                $label = 'ms';
            }

            $microtimeLast = microtime(true);

            $lastlabel = '';
            if ($last) {
                $lastlabel = 'STOP: ';
            }

            $return = $lastlabel.sprintf('%0.'.$round.'f', $val).' '.$label.' ('.$calledFrom.')'.$html;
            if ($echo) {
                echo $return.PHP_EOL;
            } else {
                return $return;
            }
        }

        return;
    }
}
