<?php
/**
* Tonic Template Engine
*
* All the templates are rendered by this
* template engine. Including Layout.php
*
*/
class Tonic{
    /**
    * If set to true, will try to encode all output tags with
    * htmlspecialchars()
    */
    const ESCAPE_TAGS_IN_VARS = false;
    /**
     * Include path
     * @var string
     */
    public $root='./';
    /**
     * Default path to search for includes
     * that require external controller
     * @var string
     */
    public $controller_root='./';
    /**
     * Default extension for includes
     * @var string
     */
    public $default_extension='.html';
    /**
     * Default controller root to use
     * @var <type>
     */
    public static $default_controller_root;
    /**
     * Default include path
     * @var <type>
     */
    public static $default_root;

    private static $globals=array();
    private $file;
    private $assigned=array();
    private $output="";
    private $source;
    private $content;
    private $is_php = false;
    public $enable_content_cache = false;
    public $cache_dir = "cache/";
    public $cache_lifetime = 86400;
    public $local_tz = 'GMT';

    /**
     * Object constructor
     * @param $file template file to load
     */
    public function __construct($file=NULL){
        if(!empty($file)){
            $this->file=$file;
            $this->load();
        }
    }

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
            $this->source=file_get_contents($this->file);
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
                // Includes after compile
                $this->handleNoRender();
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
        $cache=fopen($this->cache_dir.$file_name,'w');
        fwrite($cache,$this->content);
        fclose($cache);
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
        preg_match_all('/\{\s*include\s*:\s*(.+?)\s*}/',$this->content,$matches);
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

                $inc = new Tonic($include);
                $inc->setContext($this->assigned);
                $rep = $inc->render();
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
    }

    private function handleNoRender(){
        $matches=array();
        preg_match_all('/\{norender:(.+?)\}/',$this->output,$matches);
        if(!empty($matches)){
            foreach($matches[1] as $i => $include){
                $include=trim($include);
                //if(strpos($include,'.')===false)
                //    $include=$include.$this->default_extension;
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

                if(isset($params['find_controller'])){
                    $rep=self::loadExternalTemplate($include,$this->root,$this->controller_root,NULL,$params);
                }elseif(!empty($params['controller'])){
                    $rep=self::loadExternalTemplate($include,$this->root,$this->controller_root,$params['controller'],$params);
                }elseif(isset($params['external_url'])){
                    $file=@file_get_contents($include);
                    $rep=($file ? $file : '[unable to reach url: '.$include.']');
                }elseif(isset($params['content'])){
                    $content=url('contenido/view').$include;
                    $file=@file_get_contents($content);
                    $rep=($file ? $file : '[unable to find content: '.$content.']');
                }else{
                    $file=@file_get_contents($this->root.$include);
                    $rep=($file ? $file : '[include not found: '.$this->root.$include.']');
                }
                $this->output=str_replace($matches[0][$i],$rep,$this->output);
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

    private function applyModifiers(&$var,$mod){
        $ov=$var;
        foreach($mod as $name){
            $modifier=explode('(',$name,2);
            $name=$modifier[0];
            $params=substr($modifier[1],0,-1);
            $params=$this->getParams($params);

            switch($name){
                default:
                    $ov=$name.'('.implode(',',$params).')';
                    break;
                case 'upper':
                    $ov='strtoupper('.$ov.')';
                    break;
                case 'lower':
                    $ov='strtolower('.$ov.')';
                    break;
                case 'capitalize':
                    $ov='ucwords('.$ov.')';
                    break;
                case 'abs':
                    $ov='abs('.$ov.')';
                    break;
                case 'isEmpty':
                    $ov='empty('.$ov.')';
                    break;
                case 'truncate':
                    $ov='substr('.$ov.',0,'.$params[0].').(strlen('.$ov.') > '.$params[0].' ? "..." : "")';
                    break;
                case 'count':
                case 'length':
                    $ov='(is_string('.$ov.') ? strlen('.$ov.') : count('.$ov.'))';
                    break;
                case 'toLocalTime':
                case 'toLocalDate':
                case 'toLocal':
                    $ov='date_timezone_set('.$ov.', timezone_open("'.$this->local_tz.'"))';
                    break;
                case 'toTz':
                    $ov='date_timezone_set('.$ov.', timezone_open("'.$params[0].'"))';
                    break;
                case 'toGMT':
                    $ov='date_timezone_set('.$ov.', timezone_open("GMT"))';
                    break;
                case 'date':
                    $ov='date_format('.$ov.',"'.$params[0].'")';
                    break;
                case 'nl2br':
                    $ov='nl2br('.$ov.')';
                    break;
                case 'print_r':
                    $ov='print_r('.$ov.')';
                    break;
                case 'stripSlashes':
                    $ov='stripslashes('.$ov.')';
                    break;
                case 'sum':
                    $ov=''.$ov.'+'.(float)$params[0];
                    break;
                case 'substract':
                    $ov=''.$ov.'-'.(float)$params[0];
                    break;
                case 'multiply':
                    $ov=''.$ov.'*'.(float)$params[0];
                    break;
                case 'divide':
                    $ov=''.$ov.'/'.(float)$params[0];
                    break;
                case 'addSlashes':
                    $ov='addSlashes('.$ov.')';
                    break;
                case 'encodeTags':
                    $ov='htmlspecialchars('.$ov.',ENT_NOQUOTES)';
                    break;
                case 'decodeTags':
                    $ov='htmlspecialchars_decode('.$ov.')';
                    break;
                case 'stripTags':
                    $ov='strip_tags('.$ov.')';
                    break;
                case 'urldecode':
                    $ov='urldecode('.$ov.')';
                    break;
                case 'urlencode':
                    $ov='urlencode('.$ov.')';
                    break;
                case 'urlFriendly':
                    $ov="urlencode(self::removeSpecialChars(strtolower(".$ov.")))";
                    break;
                case 'trim':
                    $ov='trim('.$ov.')';
                    break;
                case 'sha1':
                    $ov='sha1('.$ov.')';
                    break;
                case 'numberFormat':
                    $ov='number_format('.$ov.','.(int)$params[0].')';
                    break;
                case 'lastKey':
                case 'lastIndex':
                    $ov='current(array_reverse(array_keys('.$ov.')))';
                    break;
                case 'last':
                case 'lastValue':
                    $ov='current(array_reverse('.$ov.'))';
                    break;
                case 'jsonEncode':
                    $ov='json_encode('.$ov.')';
                    break;
                case 'substr':
                    if(!isset($params[1]))
                        $ov='substr('.$ov.',"'.$params[0].'")';
                    else
                        $ov='substr('.$ov.',"'.$params[0].'","'.$params[1].'")';
                    break;
                case 'join':
                    $ov='implode("'.$params[0].'",'.$ov.')';
                    break;
                case 'split':
                    $ov='explode("'.$params[0].'",'.$ov.')';
                    break;
                case 'jsonEncode':
                    $ov='json_encode('.$ov.')';
                    break;
                case 'replace':
                    $params[1]=str_replace('$this','".'.$ov.'."',$params[1]);
                    $ov='str_replace("'.$params[0].'","'.$params[1].'",'.$ov.')';
                    break;
                case 'default':
                    $params[0]=str_replace('$this','".'.$ov.'."',$params[0]);
                    $ov='('.$ov.' == "" || '.$ov.' == null ? "'.$params[0].'" : '. $ov .')';
                    break;
                case 'ifEmpty':
                    $params[0]=str_replace('$this','".'.$ov.'."',$params[0]);
                    $params[1]=str_replace('$this','".'.$ov.'."',$params[1]);
                    $ov='('.$ov.' == "" || '.$ov.' == null ? "'.$params[0].'" : '.(!isset($params[1]) ? $ov : '"'.$params[1].'"').')';
                    break;
                case 'if':
                    $params[0]=str_replace('$this','".'.$ov.'."',$params[0]);
                    $params[1]=str_replace('$this','".'.$ov.'."',$params[1]);
                    if(isset($params[2]))
                        $params[2]=str_replace('$this','".'.$ov.'."',$params[2]);
                    switch(@$params[3]){
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
                    $ov='('.$ov.' '.$operator.' "'.$params[0].'" ? "'.$params[1].'" : '.(!isset($params[2]) ? $ov : '"'.$params[2].'"').')';
                    break;
                // Especiales para variables globales
                case 'path':
                    $ov='"'.self::getPath($params[0]).'"';
                    break;
                case 'msg':
                    $ov='"'.self::getSysMsg($params[0],(empty($params[1]) ? '' : $params[1])).'"';
                    break;
                case 'preventTagEncode':
                    break;
                case 'zeroFill':
                    $ov='self::zeroFill('.$ov.',"'.$params[0].'")';
                    break;
                case 'controller':
                    $ov='Sys::get("module_controller")';
                    break;
            }
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
                    if(self::ESCAPE_TAGS_IN_VARS == true && !$prev_tag)
                        $var_name = 'htmlspecialchars('.$var_name.',ENT_NOQUOTES)';
                    $mod=$this->applyModifiers($var_name,$mod);
                }else{
                    $var_name='$'.$var_name[0];
                    if(self::ESCAPE_TAGS_IN_VARS == true)
                        $var_name = 'htmlspecialchars('.$var_name.',ENT_NOQUOTES)';
                }
                $rep='<?php echo @'.$var_name.'; ?>';
                $this->content=str_replace($matches[0][$i],$rep,$this->content);
            }
        }
    }

    private function findVarInString(&$string){
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

    # Static helper functions
    /**
     * Utility date format function used in the class
     * @param <type> $date
     * @param <type> $format
     * @return <type>
     */
    public static function dateFormatCustom($date,$format){
        if(empty($date))
            return "";
        $rep_val=$date;
        $datetime=explode(' ',$rep_val);
        $date=@explode('-',$datetime[0]);
        $time=@explode(':',$datetime[1]);
        $timestamp=@mktime(intval($time[0]),intval($time[1]),intval($time[2]),intval($date[1]),intval($date[2]),intval($date[0]));
        if(empty($timestamp) || $timestamp<0 || ($date[0]=='0000' && $date[1]=='00' && $date[2]=='00')){
            $rep_val='';
        }else{
            $rep_val=date($format,$timestamp);
        }
        return $rep_val;
    }
    /**
     * Utility function to load external templates with
     * independant controllers
     * @param <type> $file
     * @param <type> $def_root
     * @param <type> $def_controller
     * @return <type>
     */
    public static function loadExternalTemplate($file,$def_root=NULL,$def_controller=NULL,$controller=NULL,$params=NULL){
        $_file=explode('.',$file);
        $ext=$_file[count($_file)-1];
        $tpl=new Tonic();
        if(!empty($def_controller))
            $tpl->controller_root=$def_controller;
        if(!empty($def_root))
            $tpl->root=$def_root;
        $tpl->load($def_root.$file);
        if($controller==NULL){
            $phpfile=substr($file,0,(strlen($ext)*(-1)))."php";
        }else{
            $phpfile=$controller;
        }
        if(file_exists($tpl->controller_root.$phpfile)){
            require_once($tpl->controller_root.$phpfile);
        }

        if(!empty($params)){
            foreach($params as $k => $v){
                $tpl->assign($k,true);
            }
        }

        $tpl->setContext(get_defined_vars());
        return $tpl->render();
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

}
