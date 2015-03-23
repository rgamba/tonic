<?php
/**
* tonic v2.1
*
* Lightweight PHP templating engine
*
* @author Ricardo Gamba <rgamba@gmail.com>
* @license BSD 3-Clause License
*/
namespace Tonic;
class Tonic{
    /**
    * If set to true, will try to encode all output tags with
    * htmlspecialchars()
    */
    public static $escape_tags_in_vars = false;
    /**
    * Enable template caching
    */
    public $enable_content_cache = false;
    /**
    * Caching directory (must have write permissions)
    */
    public $cache_dir = "cache/";
    /**
    * Cache files lifetime
    */
    public $cache_lifetime = 86400;
    /**
    * Local timezone (to use with toLocal() modifier)
    */
    public static $local_tz = 'GMT';
    /**
    * Include path
    * @var string
    */
    public static $root='';
    /**
    * Default extension for includes
    * @var string
    */
    public $default_extension='.html';

    private static $globals=array();
    private $file;
    private $assigned=array();
    private $output="";
    private $source;
    private $content;
    private $is_php = false;
    private static $modifiers = null;

    /**
    * Object constructor
    * @param $file template file to load
    */
    public function __construct($file=NULL){
        self::initModifiers();
        if(!empty($file)){
            $this->file=$file;
            $this->load();
        }
    }

    /**
    * Create a new custom modifier
    * @param Name of the modifier
    * @param Lambda function, modifier function
    */
    public static function extendModifier($name, $func){
        if(!is_callable($func))
            return false;
        self::$modifiers[$name] = $func;
        return true;
    }

    /**
    * Set the global environment variables for all templates
    * @param associative array with the global variables
    */
    public static function setGlobals($g=array()){
        if(!is_array($g))
            return false;
        self::$globals=$g;
    }

    /**
    * Load the desired template
    * @param <type> $file
    * @return <type>
    */
    public function load($file=NULL){
        if($file!=NULL)
            $this->file=$file;
        if(empty($this->file)) return false;
        $ext = explode('.',$file);
        $ext = $ext[count($ext)-1];
        if($ext == "php"){
            $this->is_php = true;
        }else{
            if(!file_exists(self::$root . $this->file)) {
                echo "<span style=\"display: inline-block; background: red; color: white; padding: 2px 8px; border-radius: 10px; font-family: 'Lucida Console', Monaco, monospace, sans-serif; font-size: 80%\"><b>tonic</b>: unable to load file '".self::$root . $this->file."'</span>";
                return false;
            }
            $this->source=file_get_contents(self::$root . $this->file);
            $this->content=&$this->source;
        }
        return $this;
    }

    /**
    * Load from string instead of file
    *
    * @param mixed $str
    */
    public function loadFromString($str){
        $this->source=$str;
        $this->content=&$this->source;
        return $this;
    }

    /**
    * Assign value to a variable inside the template
    * @param <type> $var
    * @param <type> $val
    */
    public function assign($var,$val){
        $this->assigned[$var]=$val;
        return $this;
    }

    public function getContext(){
        return $this->assigned;
    }

    /**
    * Magic method alias for self::assign
    * @param <type> $k
    * @param <type> $v
    */
    public function __set($k,$v){
        $this->assign($k,$v);
    }

    /**
    * Assign multiple variables at once
    * This method should always receive get_defined_vars()
    * as the first argument
    * @param <type> get_defined_vars()
    * @return <type>
    */
    public function setContext($vars){
        if(!is_array($vars))
            return false;
        foreach($vars as $k => $v){
            $this->assign($k,$v);
        }
        return $this;
    }

    /**
    * Return compiled template
    * @return <type>
    */
    public function render($replace_cache=false){
        if($replace_cache)
            if(file_exists($this->cache_dir.md5("template=".Tpl::get('ACTIVE')."&file=".$this->file)))
                unlink($this->cache_dir.md5("template=".Tpl::get('ACTIVE')."&file=".$this->file));
        if(!$this->is_php){
            if(!$this->getFromCache()){
                $this->assignGlobals();
                $this->handleIncludes();
                $this->handleLoops();
                $this->handleIfs();
                $this->handleVars();
                $this->handleSwitchs();
                $this->compile();
            }
        }else{
            $this->renderPhp();
        }
        return $this->output;
    }

    private function getFromCache(){
        if($this->enable_content_cache!=true || !file_exists($this->cache_dir.md5("template=".$this->file)))
            return false;
        $file_expiration = filemtime($this->cache_dir.md5("template=".$this->file)) + (int)$this->cache_lifetime;
        if($file_expiration < time()){
            unlink($this->cache_dir.md5("template=".$this->file));
            return false;
        }
        $this->assignGlobals();
        foreach($this->assigned as $var => $val)
            ${$var}=$val;
        ob_start();
        include_once($this->cache_dir.md5("template=".$this->file));
        $this->output=ob_get_clean();
        return true;
    }

    private function renderPhp(){
        $this->assignGlobals();
        if(!file_exists($this->file))
            die("TemplateEngine::renderPhp() - File not found (".$this->file.")");
        ob_start();
        Sys::get('module_controller')->includeView($this->file,$this->assigned);
        $this->output=ob_get_clean();
        return true;
    }

    private function assignGlobals(){
        self::$globals['system']['session'] = @$_SESSION;
        $this->setContext(self::$globals);
    }

    private function compile(){
        foreach($this->assigned as $var => $val){
            ${$var}=$val;
        }
        if($this->enable_content_cache==true){
            $this->saveCache();
        }
        ob_start();
        $e=eval('?>'.$this->content);
        $this->output=ob_get_clean();
        if($e===false){
            die("Error: ".$this->output." <hr />".$this->content);
        }
    }

    private function saveCache(){
        $file_name=md5("templat=".$this->file);
        $cache=@fopen($this->cache_dir.$file_name,'w');
        @fwrite($cache,$this->content);
        @fclose($cache);
    }

    private function removeWhiteSpaces($str) {
        $in = false;
        $escaped = false;
        $ws_string = "";
        for($i = 0; $i <= strlen($str)-1; $i++) {
            $char = substr($str,$i,1);
            $je = false;
            $continue = false;
            switch($char) {
                case '\\':
                    $je = true;
                    $escaped = true;
                    break;
                case '"':
                    if(!$escaped) {
                        $in = !$in;
                    }
                    break;
                case " ":
                    if(!$in) {
                        $continue = true;
                    }
                    break;
            }
            if (!$je) {
                $escaped = false;
            }
            if(!$continue) {
                $ws_string .= $char;
            }
        }
        return $ws_string;
    }

    private function handleIncludes(){
        $matches=array();
        preg_match_all('/\{\s*include\s*(.+?)\s*}/',$this->content,$matches);
        if(!empty($matches)){
            foreach($matches[1] as $i => $include){
                $include=trim($include);

                $include=explode(',',$include);
                $params=array();
                if(count($include)>1){
                    $inc=$include[0];
                    unset($include[0]);
                    foreach($include as $kv){
                        @list($key,$val)=@explode('=',$kv);
                        $params[$key]=empty($val) ? true : $val;
                    }
                    $include=$inc;
                }else
                    $include=$include[0];

                if (substr($include,0,4) == "http") {
                    $rep = file_get_contents($include);
                } else {
                    ob_start();
                    $inc = new Tonic($include);
                    $inc->setContext($this->assigned);
                    $rep = $inc->render();
                    $err = ob_get_clean();
                    if(!empty($err))
                        $rep = $err;
                }
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
    }

    private function getParams($params){
        $i=0;
        $p=array();
        $escaped=false;
        $in_str=false;
        $act="";
        while($i<strlen($params)){
            $char=substr($params,$i,1);
            $i++;
            switch($char){
                case "\\":
                    if($escaped==true){
                        $escaped=false;
                        $act.=$char;
                    }else
                        $escaped=true;
                    break;
                case '"':
                    if($escaped==true){
                        $act.=$char;
                        break;
                    }
                    $in_str=($in_str==false ? true : false);
                    break;
                case ',':
                    if($in_str==true){
                        $act.=$char;
                        break;
                    }
                    $p[]=$act;
                    $act="";
                    break;
                default:
                    $escaped=false;
                    $act.=$char;
                    break;
            }
        }
        $p[]=$act;
        return $p;
    }

    private static function callModifier() {
        $args = func_get_args();
        if(empty($args[0])){
            return "[empty modifier]";
        }
        if(empty(self::$modifiers[$args[0]])){
            return "[invalid modifier '$args[0]']";
        }
        try {
            $ret = call_user_func_array(self::$modifiers[$args[0]],array_slice($args,1));
        } catch(\Exception $e){
            throw new \Exception("<span style=\"display: inline-block; background: red; color: white; padding: 2px 8px; border-radius: 10px; font-family: 'Lucida Console', Monaco, monospace, sans-serif; font-size: 80%\"><b>$args[0]</b>: ".$e->getMessage()."</span>");
        }
        return $ret;
    }

    private function applyModifiers(&$var,$mod){
        $ov=$var;
        foreach($mod as $name){
            $modifier=explode('(',$name,2);
            $name=$modifier[0];
            $params=substr($modifier[1],0,-1);
            $params=$this->getParams($params);
            foreach(self::$modifiers as $_name => $mod) {
                if($_name != $name)
                    continue;
                $ov = 'self::callModifier("'.$_name.'",'.$ov.(!empty($params) ? ',"'.implode('","',$params).'"' : "").')';
            }
            continue;
        }
        $var=$ov;
    }

    private function handleVars(){
        $matches=array();
        preg_match_all('/\{\s*\$(.+?)\s*\}/',$this->content,$matches);
        if(!empty($matches)){
            foreach($matches[1] as $i => $var_name){
                $prev_tag=strpos($var_name,'preventTag') === false ? false : true;
                $var_name=explode('.',$var_name);
                if(count($var_name)>1){
                    $vn=$var_name[0];
                    unset($var_name[0]);
                    $mod=array();
                    foreach($var_name as $j => $index){
                        $index=explode('->',$index,2);
                        $obj='';
                        if(count($index)>1){
                            $obj='->'.$index[1];
                            $index=$index[0];
                        }else
                            $index=$index[0];
                        if(substr($index,-1,1)==")"){
                            $mod[]=$index.$obj;
                        }else{
                            if(substr($index,0,1)=='$')
                                $vn.="[$index]$obj";
                            else
                                $vn.="['$index']$obj";
                        }
                    }
                    $var_name='$'.$vn;
                    if(self::$escape_tags_in_vars == true && !$prev_tag)
                        $var_name = 'htmlspecialchars('.$var_name.',ENT_NOQUOTES)';
                    $mod=$this->applyModifiers($var_name,$mod);
                }else{
                    $var_name='$'.$var_name[0];
                    if(self::$escape_tags_in_vars == true)
                        $var_name = 'htmlspecialchars('.$var_name.',ENT_NOQUOTES)';
                }
                $rep='<?php try{ echo '.$var_name.'; } catch(\Exception $e) { echo $e->getMessage(); } ?>';
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
    }

    private function findVarInString(&$string){
        return self::findVariableInString($string);
    }

    private static function findVariableInString(&$string){
        $var_match=array();
        preg_match_all('/\$([a-zA-Z0-9_\-\(\)\.\",>]+)/',$string,$var_match);
        if(!empty($var_match[0])){
            foreach($var_match[1] as $j => $var){
                $_var_name=explode('.',$string);
                if(count($_var_name)>1){
                    $vn=$_var_name[0];
                    unset($_var_name[0]);
                    $mod=array();
                    foreach($_var_name as $k => $index){
                        $index=explode('->',$index,2);
                        $obj='';
                        if(count($index)>1){
                            $obj='->'.$index[1];
                            $index=$index[0];
                        }else
                            $index=$index[0];
                        if(substr($index,-1,1)==")"){
                            $mod[]=$index.$obj;
                        }else{
                            $vn.="['$index']$obj";
                        }
                    }
                    $_var_name='$'.$vn;
                    $this->applyModifiers($_var_name,$mod);
                }else{
                    $_var_name='$'.$_var_name[0];
                }
                $string=str_replace(@$var_match[0][$j],'".'.$_var_name.'."',$string);
            }
        }
    }

    private function handleSwitchs(){
        $matches=array();
        preg_match_all('/\{\s*(switch|case)\s*(.+?)\s*\}/',$this->content,$matches);
        if(!empty($matches)){
            foreach($matches[2] as $i => $condition){
                $var_match=array();
                preg_match_all('/\$([a-zA-Z0-9_\-\(\)\.]+)/',$condition,$var_match);
                if(!empty($var_match)){
                    foreach($var_match[1] as $j => $var){
                        $var_name=explode('.',$var);
                        if(count($var_name)>1){
                            $vn=$var_name[0];
                            unset($var_name[0]);
                            $mod=array();
                            foreach($var_name as $k => $index){
                                $index=explode('->',$index,2);
                                $obj='';
                                if(count($index)>1){
                                    $obj='->'.$index[1];
                                    $index=$index[0];
                                }else
                                    $index=$index[0];
                                if(substr($index,-1,1)==")"){
                                    $mod[]=$index.$obj;
                                }else{
                                    $vn.="['$index']$obj";
                                }
                            }
                            $var_name='$'.$vn;
                            $this->applyModifiers($var_name,$mod);
                        }else{
                            $var_name='$'.$var_name[0];
                        }
                        $condition=str_replace(@$var_match[0][$j],$var_name,$condition);
                    }
                }
                $rep='<?php '.$matches[1][$i].($matches[1][$i]=="switch" ? '(' : ' ').$condition.($matches[1][$i]=="switch" ? ')' : '').($matches[1][$i]=="switch" ? '{' : ':').' ?>';
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
        $this->content=preg_replace('/\{\s*(\/switch|endswitch)\s*\}/','<?php } ?>',$this->content);
        $this->content=preg_replace('/\{\s*default\s*\}/','<?php default: ?>',$this->content);
        $this->content=preg_replace('/\{\s*(\/case|endcase)\s*\}/','<?php break; ?>',$this->content);
    }

    private function handleIfs(){
        $matches=array();
        preg_match_all('/\{\s*(if|elseif)\s*(.+?)\s*\}/',$this->content,$matches);
        if(!empty($matches)){
            foreach($matches[2] as $i => $condition){
                $condition=trim($condition);
                $condition=str_replace(array(
                    'eq',
                    'gt',
                    'lt',
                    'neq',
                    'or',
                    'gte',
                    'lte'
                ),array(
                    '==',
                    '>',
                    '<',
                    '!=',
                    '||',
                    '>=',
                    '<='

                ),$condition);
                $var_match=array();
                preg_match_all('/\$([a-zA-Z0-9_\-\(\)\.]+)/',$condition,$var_match);
                if(!empty($var_match)){
                    foreach($var_match[1] as $j => $var){
                        $var_name=explode('.',$var);
                        if(count($var_name)>1){
                            $vn=$var_name[0];
                            unset($var_name[0]);
                            $mod=array();
                            foreach($var_name as $k => $index){
                                $index=explode('->',$index,2);
                                $obj='';
                                if(count($index)>1){
                                    $obj='->'.$index[1];
                                    $index=$index[0];
                                }else
                                    $index=$index[0];
                                if(substr($index,-1,1)==")"){
                                    $mod[]=$index.$obj;
                                }else{
                                    $vn.="['$index']$obj";
                                }
                            }
                            $var_name='$'.$vn;
                            $this->applyModifiers($var_name,$mod);
                        }else{
                            $var_name='$'.$var_name[0];
                        }
                        $condition=str_replace(@$var_match[0][$j],$var_name,$condition);
                    }
                }
                $rep='<?php '.$matches[1][$i].'(@'.$condition.'): ?>';
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
        $this->content=preg_replace('/\{\s*(\/if|endif)\s*\}/','<?php endif; ?>',$this->content);
        $this->content=preg_replace('/\{\s*else\s*\}/','<?php else: ?>',$this->content);

    }

    private function handleLoops(){
        $matches=array();
        preg_match_all('/\{\s*(loop|for)\s*(.+?)\s*\}/',$this->content,$matches);
        if(!empty($matches)){
            foreach($matches[2] as $i => $loop){
                $loop = $this->removeWhiteSpaces($loop);
                $loop_det=explode('in',$loop);
                $loop_name=$loop_det[1];
                unset($loop_det[1]);
                $loop_name=explode('.',$loop_name);
                if(count($loop_name)>1){
                    $ln=$loop_name[0];
                    unset($loop_name[0]);
                    foreach($loop_name as $j => $suffix)
                        $ln.="['$suffix']";
                    $loop_name=$ln;
                }else{
                    $loop_name=$loop_name[0];
                }
                $key=NULL;
                $val=NULL;

                $loop_vars = explode(",",$loop_det[0]);
                if (count($loop_vars) > 1) {
                    $key = $loop_vars[0];
                    $val = $loop_vars[1];
                } else {
                    $val = $loop_vars[0];
                }

                foreach($loop_det as $j => $_val){
                    list($k,$v)=explode(',',$_val);
                    if($k=="key"){
                        $key=$v;
                        continue;
                    }
                    if($k=="item"){
                        $val=$v;
                        continue;
                    }
                }
                $rep='<?php foreach('.$loop_name.' as '.(!empty($key) ? $key.' => '.$val : ' '.$val).'): ?>';
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
        $this->content=preg_replace('/\{\s*(\/loop|endloop|\/for|endfor)\s*\}/','<?php endforeach; ?>',$this->content);
    }

    public static function removeSpecialChars($text){
        $find = array('á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ',' ','"',"'");
        $rep  = array('a','e','i','o','u','A','E','I','O','U','n','N','-',"","");
        return str_replace($find,$rep,$text);
        return(strtr($text,$tofind,$replac));
    }

    public static function zeroFill($text,$digits){
        if(strlen($text)<$digits){
            $ceros=$digits-strlen($text);
            for($i=0;$i<=$ceros-1;$i++){
                $ret.="0";
            }
            $ret=$ret.$text;
            return $ret;
        }else{
            return $text;
        }
    }

    private static function initModifiers(){
        if(self::$modifiers != null)
            return;
        self::extendModifier("upper", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return strtoupper($input);
        });
        self::extendModifier("lower", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return strtolower($input);
        });
        self::extendModifier("capitalize", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return ucwords($input);
        });
        self::extendModifier("abs", function($input) {
            if(!is_numeric($input)){
                return $input;
            }
            return abs($input);
        });
        self::extendModifier("isEmpty", function($input) {
            return empty($input);
        });
        self::extendModifier("truncate", function($input,$len) {
            if(empty($len)) {
                throw new \Exception("length parameter is required");
            }
            return substr($input,0,$len).(strlen($input) > $len ? "..." : "");
        });
        self::extendModifier("count", function($input) {
            return count($input);
        });
        self::extendModifier("length", function($input) {
            return count($input);
        });
        self::extendModifier("toLocal", function($input) {
            if(!is_object($input)){
                throw new \Exception("variable is not a valid date");
            }
            return date_timezone_set($input, timezone_open(self::$local_tz));
        });
        self::extendModifier("toTz", function($input,$tz) {
            if(!is_object($input)){
                throw new \Exception("variable is not a valid date");
            }
            return date_timezone_set($input, timezone_open($tz));
        });
        self::extendModifier("toGMT", function($input,$tz) {
            if(!is_object($input)){
                throw new \Exception("variable is not a valid date");
            }
            if(empty($tz)){
                throw new \Exception("timezone is required");
            }
            return date_timezone_set($input, timezone_open("GMT"));
        });
        self::extendModifier("date", function($input,$format) {
            if(!is_object($input)){
                throw new \Exception("variable is not a valid date");
            }
            if(empty($format)){
                throw new \Exception("date format is required");
            }
            return date_format($input,$format);
        });
        self::extendModifier("nl2br", function($input) {
            return nl2br($input);
        });
        self::extendModifier("stripSlashes", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return stripslashes($input);
        });
        self::extendModifier("sum", function($input,$val) {
            if(!is_numeric($input) || !is_numeric($val)){
                throw new \Exception("input and value must be numeric");
            }
            return $input + (float)$val;
        });
        self::extendModifier("substract", function($input,$val) {
            if(!is_numeric($input) || !is_numeric($val)){
                throw new \Exception("input and value must be numeric");
            }
            return $input - (float)$val;
        });
        self::extendModifier("multiply", function($input,$val) {
            if(!is_numeric($input) || !is_numeric($val)){
                throw new \Exception("input and value must be numeric");
            }
            return $input * (float)$val;
        });
        self::extendModifier("divide", function($input,$val) {
            if(!is_numeric($input) || !is_numeric($val)){
                throw new \Exception("input and value must be numeric");
            }
            return $input / (float)$val;
        });
        self::extendModifier("mod", function($input,$val) {
            if(!is_numeric($input) || !is_numeric($val)){
                throw new \Exception("input and value must be numeric");
            }
            return $input % (float)$val;
        });
        self::extendModifier("encodeTags", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return htmlspecialchars($input,ENT_NOQUOTES);
        });
        self::extendModifier("decodeTags", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return htmlspecialchars_decode($input);
        });
        self::extendModifier("stripTags", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return strip_tags($input);
        });
        self::extendModifier("urlDecode", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return urldecode($input);
        });
        self::extendModifier("urlFriendly", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return urlencode(self::removeSpecialChars(strtolower($input)));
        });
        self::extendModifier("trim", function($input) {
            if(!is_string($input)){
                return $input;
            }
            return trim($input);
        });
        self::extendModifier("sha1", function($input) {
            if(!is_string($input)){
                throw new \Exception("input must be string");
            }
            return sha1($input);
        });
        self::extendModifier("numberFormat", function($input,$precision = 2) {
            if(!is_numeric($input)){
                throw new \Exception("input must be numeric");
            }
            return number_format($input,(int)$precision);
        });
        self::extendModifier("lastIndex", function($input) {
            if(!is_array($input)){
                throw new \Exception("input must be an array");
            }
            return current(array_reverse(array_keys($input)));
        });
        self::extendModifier("lastValue", function($input) {
            if(!is_array($input)){
                throw new \Exception("input must be an array");
            }
            return current(array_reverse($input));
        });
        self::extendModifier("jsonEncode", function($input) {
            return json_encode($input);
        });
        self::extendModifier("substr", function($input,$a = 0,$b = 0) {
            return substr($input,$a,$b);
        });
        self::extendModifier("join", function($input,$glue) {
            if(!is_array($input)){
                throw new \Exception("input must be an array");
            }
            if(empty($glue)){
                throw new \Exception("string glue is required");
            }
            return implode($glue,$input);
        });
        self::extendModifier("explode", function($input,$del) {
            if(!is_string($input)){
                throw new \Exception("input must be a string");
            }
            if(empty($del)){
                throw new \Exception("delimiter is required");
            }
            return explode($del,$input);
        });
        self::extendModifier("replace", function($input,$search,$replace) {
            if(!is_string($input)){
                throw new \Exception("input must be a string");
            }
            if(empty($search)){
                throw new \Exception("search is required");
            }
            if(empty($replace)){
                throw new \Exception("replace is required");
            }
            return str_replace($search,$replace,$input);
        });
        self::extendModifier("preventTagEncode", function($input) {
            return $input;
        });
        self::extendModifier("default", function($input,$default) {
            return (empty($input) ? $default : $input);
        });
        self::extendModifier("ifEmpty", function($input,$true_val, $false_val = null) {
            if(empty($true_val)){
                throw new \Exception("true value is required");
            }
            $ret = $input;
            if(empty($ret)) {
                $ret = $true_val;
            } else if($false_val) {
                $ret = $false_val;
            }
            return $ret;
        });
        self::extendModifier("if", function($input, $condition, $true_val, $false_val = null, $operator = "eq") {
            if(empty($true_val)){
                throw new \Exception("true value is required");
            }
            switch($operator){
                case '':
                case '==':
                case '=':
                case 'eq':
                default:
                    $operator="==";
                    break;
                case '<':
                case 'lt':
                    $operator="<";
                    break;
                case '>':
                case 'gt':
                    $operator=">";
                    break;
                case '<=':
                case 'lte':
                    $operator="<=";
                    break;
                case '>=':
                case 'gte':
                    $operator=">=";
                    break;
                case 'neq':
                    $operator = "!=";
                    break;
            }
            $ret = $input;
            if(eval('return ("'.$condition.'"'.$operator.'"'.$input.'");')) {
                $ret = $true_val;
            } else if($false_val) {
                $ret = $false_val;
            }
            return $ret;
        });

    }
}
