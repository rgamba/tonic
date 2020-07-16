<?php
	/**
	 * tonic v3.3.1
	 *
	 * Lightweight PHP templating engine
	 *
	 * @author  Ricardo Gamba <rgamba@gmail.com>
	 *          Kurt Höblinger <office@nitricware.com>
	 * @license BSD 3-Clause License
	 *
	 * Localization Support by NitricWare (Kurt Höblinger)
	 *
	 */
	
	namespace NitricWare;
	
	use Exception;
	use DateTime;
	use Countable;
	
	class Tonic {
		/**
		 * Enable context awareness
		 */
		public static bool $context_aware = true;
		/**
		 * Local timezone (to use with toLocal() modifier)
		 */
		public static string $local_tz = 'GMT';
		/**
		 * Include path
		 */
		public static string $root = '';
		/**
		 * Enable template caching
		 */
		public static bool $enable_content_cache = false;
		/**
		 * Caching directory (must have write permissions)
		 */
		public static string $cache_dir = "cache/";
		/**
		 * Cache files lifetime
		 */
		public int $cache_lifetime = 86400;
		/**
		 * Default extension for includes
		 */
		//public string $default_extension = '.html';
		/**
		 * Localized array
		 */
		public array $localized = array();
		
		private string $file;
		private array $languageFiles = array();
		private array $assigned = array();
		private string $output = "";
		private string $source = "";
		private string $content = "";
		private string $or_content = "";
		private $cur_context = null;
		private static $modifiers = null;
		private static array $globals = array();
		private array $blocks = array();
		private array $blocks_override = array();
		private $base = null;
		
		/**
		 * Object constructor
		 *
		 * @param string       $file template file to load
		 * @param string|array $languages
		 */
		public function __construct ($file = NULL, $languages = NULL) {
			self::initModifiers();
			if (!empty($file)) {
				$this->file = $file;
				$this->load();
			}
			if (!empty($languages)) {
				if (is_array($languages)) {
					foreach ($languages as $language) {
						if (file_exists($language)) {
							$this->languageFiles[] = $language;
						}
					}
				} else {
					$this->languageFiles[] = $languages;
				}
				
				$this->loadLanguage();
			}
		}
		
		/**
		 * Create a new custom modifier
		 *
		 * @param string $name of the modifier
		 * @param object $func function, modifier function
		 *
		 * @return bool
		 */
		public static function extendModifier (string $name, object $func): bool {
			if (!empty(self::$modifiers[$name]))
				return false;
			if (!is_callable($func))
				return false;
			self::$modifiers[$name] = $func;
			return true;
		}
		
		/**
		 * Set the global environment variables for all templates
		 *
		 * @param array $g associative array with the global variables
		 *
		 * @return bool
		 * @noinspection PhpUnused
		 */
		public static function setGlobals (array $g = array()): bool {
			if (!is_array($g))
				return false;
			self::$globals = $g;
			return true;
		}
		
		/**
		 * Load the desired template
		 *
		 * @param string|NULL $file
		 *
		 * @return Tonic|bool
		 */
		public function load (string $file = NULL) {
			if ($file != NULL)
				$this->file = $file;
			if (empty($this->file)) return false;
			if (!file_exists(self::$root . $this->file)) {
				echo "<span style=\"display: inline-block; background: red; color: white; padding: 2px 8px; border-radius: 10px; font-family: 'Lucida Console', Monaco, monospace, sans-serif; font-size: 80%\"><b>tonic</b>: unable to load file '" . self::$root . $this->file . "'</span>";
				return false;
			}
			$this->source = file_get_contents(self::$root . $this->file);
			$this->content =& $this->source;
			$this->or_content = $this->content;
			return $this;
		}
		
		/**
		 * Load from string instead of file
		 *
		 * @param $str
		 *
		 * @return Tonic
		 * @noinspection PhpUnused
		 */
		public function loadFromString ($str): Tonic {
			$this->source = $str;
			$this->content =& $this->source;
			return $this;
		}
		
		/**
		 * @param string $var
		 * @param mixed  $val
		 *
		 * @return Tonic
		 */
		public function assign (string $var, $val): Tonic {
			$this->assigned[$var] = $val;
			return $this;
		}
		
		/**
		 * @return array
		 * @noinspection PhpUnused
		 */
		public function getContext (): array {
			return $this->assigned;
		}
		
		/**
		 * Magic method alias for self::assign
		 *
		 * @param string $k
		 * @param mixed  $v
		 */
		public function __set (string $k, $v): void {
			$this->assign($k, $v);
		}
		
		/**
		 * Assign multiple variables at once
		 * This method should always receive get_defined_vars()
		 * as the first argument
		 *
		 * @param array $vars
		 *
		 * @return Tonic
		 */
		public function setContext (array $vars): Tonic {
			foreach ($vars as $k => $v) {
				$this->assign($k, $v);
			}
			return $this;
		}
		
		/**
		 * Return compiled template
		 *
		 * @param bool $print
		 * @param bool $replace_cache
		 *
		 * @return string
		 * @throws Exception
		 */
		public function render (bool $print = false, bool $replace_cache = false): string {
			if ($replace_cache)
				if (file_exists(self::$cache_dir . sha1($this->file)))
					unlink(self::$cache_dir . sha1($this->file));

			if (!$this->getFromCache()) {
				$this->assignGlobals();
				$this->handleExtends();
				$this->handleBlockMacros();
				$this->handleBlocks();
				$this->handleIncludes();
				$this->handleIfMacros();
				$this->handleLoopMacros();
				$this->handleLoops();
				$this->handleIfs();
				$this->handleVars();
				$this->compile();
			}
			
			if ($this->base != null) {
				// This template has inheritance
				$parent = new Tonic($this->base);
				$parent->setContext($this->assigned);
				$parent->overrideBlocks($this->blocks);
				return $parent->render();
			}
			
			if ($print) {
				echo $this->output;
			}
			
			return $this->output;
		}
		
		/**
		 * For internal use only for template inheritance.
		 *
		 * @param mixed $blocks
		 *
		 * @return void
		 */
		public function overrideBlocks ($blocks): void {
			$this->blocks_override = $blocks;
		}
		
		/**
		 * Backwards compatibility for cache.
		 */
		
		/**
		 * Backwards compatibility for cache.
		 *
		 * @param string $var
		 *
		 * @return bool|string
		 * @throws Exception
		 */
		public function __get (string $var) {
			switch ($var) {
				case 'enable_content_cache':
					// Backwards compatibility support
					return self::$enable_content_cache;
					break;
				case 'cache_dir':
					return self::$cache_dir;
					break;
				default:
					throw new Exception("Tried to access invalid property " . $var);
			}
		}
		
		/**
		 * @return bool
		 */
		private function getFromCache (): bool {
			if (self::$enable_content_cache != true || !file_exists(self::$cache_dir . sha1($this->file)))
				return false;
			$file_expiration = filemtime(self::$cache_dir . sha1($this->file)) + (int)$this->cache_lifetime;
			if ($file_expiration < time()) {
				unlink(self::$cache_dir . sha1($this->file));
				return false;
			}
			$this->assignGlobals();
			foreach ($this->assigned as $var => $val)
				${$var} = $val;
			ob_start();
			/** @noinspection PhpIncludeInspection */
			include_once(self::$cache_dir . sha1($this->file));
			$this->output = ob_get_clean();
			return true;
		}
		
		/**
		 * @return void
		 */
		private function assignGlobals (): void {
			self::$globals['__func'] = null;
			$this->setContext(self::$globals);
		}
		
		/**
		 * @return void
		 */
		private function compile (): void {
			foreach ($this->assigned as $var => $val) {
				${$var} = $val;
			}
			if (self::$enable_content_cache == true) {
				$this->saveCache();
			}
			ob_start();
			$e = eval('?>' . $this->content);
			$this->output = ob_get_clean();
			if ($e === false) {
				die("Error: " . $this->output . " <hr />" . $this->content);
			}
		}
		
		/**
		 * @return void
		 */
		private function saveCache (): void {
			$file_name = sha1($this->file);
			$cache = @fopen(self::$cache_dir . $file_name, 'w');
			@fwrite($cache, $this->content);
			@fclose($cache);
		}
		
		/**
		 * @param string $str
		 *
		 * @return string
		 */
		private function removeWhiteSpaces (string $str): string {
			$in = false;
			$escaped = false;
			$ws_string = "";
			for ($i = 0; $i <= strlen($str) - 1; $i++) {
				$char = substr($str, $i, 1);
				$je = false;
				$continue = false;
				switch ($char) {
					case '\\':
						$je = true;
						$escaped = true;
						break;
					case '"':
						if (!$escaped) {
							$in = !$in;
						}
						break;
					case " ":
						if (!$in) {
							$continue = true;
						}
						break;
				}
				if (!$je) {
					$escaped = false;
				}
				if (!$continue) {
					$ws_string .= $char;
				}
			}
			return $ws_string;
		}
		
		/**
		 * @throws Exception
		 */
		private function handleIncludes (): void {
			$matches = array();
			preg_match_all('/{\s*include\s*(.+?)\s*}/', $this->content, $matches);
			if (!empty($matches)) {
				foreach ($matches[1] as $i => $include) {
					$include = trim($include);
					
					$include = explode(',', $include);
					$params = array();
					if (count($include) > 1) {
						$inc = $include[0];
						unset($include[0]);
						foreach ($include as $kv) {
							@list($key, $val) = @explode('=', $kv);
							$params[$key] = empty($val) ? true : $val;
						}
						$include = $inc;
					} else
						$include = $include[0];
					
					if (substr($include, 0, 4) == "http") {
						$rep = file_get_contents($include);
					} else {
						ob_start();
						$inc = new Tonic($include);
						$inc->setContext($this->assigned);
						$rep = $inc->render();
						$err = ob_get_clean();
						if (!empty($err))
							$rep = $err;
					}
					$this->content = str_replace($matches[0][$i], $rep, $this->content);
				}
			}
		}
		
		/**
		 * @param string $params
		 *
		 * @return array
		 */
		private function getParams (string $params): array {
			$i = 0;
			$p = array();
			$escaped = false;
			$in_str = false;
			$act = "";
			while ($i < strlen($params)) {
				$char = substr($params, $i, 1);
				$i++;
				switch ($char) {
					case "\\":
						if ($escaped == true) {
							$escaped = false;
							$act .= $char;
						} else
							$escaped = true;
						break;
					case '"':
						if ($escaped == true) {
							$act .= $char;
							break;
						}
						$in_str = ($in_str == false ? true : false);
						break;
					case ',':
						if ($in_str == true) {
							$act .= $char;
							break;
						}
						$p[] = $act;
						$act = "";
						break;
					default:
						$escaped = false;
						$act .= $char;
						break;
				}
			}
			$p[] = $act;
			return $p;
		}
		
		/**
		 * @return mixed|string
		 * @throws Exception
		 * @noinspection PhpUnusedPrivateMethodInspection
		 */
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
			} catch(Exception $e){
				throw new Exception("<span style=\"display: inline-block; background: red; color: white; padding: 2px 8px; border-radius: 10px; font-family: 'Lucida Console', Monaco, monospace, sans-serif; font-size: 80%\"><b>$args[0]</b>: ".$e->getMessage()."</span>");
			}
			return $ret;
		}
		
		/**
		 * @param string $var
		 * @param array  $mod
		 * @param string $match
		 */
		private function applyModifiers (string &$var, array $mod, string $match = ""): void {
			$context = null;
			if (self::$context_aware == true) {
				if (!empty($match) && !in_array("ignoreContext()", $mod)) {
					$context = $this->getVarContext($match, $this->cur_context);
					switch ($context["tag"]) {
						default:
							if ($context["in_tag"]) {
								array_push($mod, "contextTag(" . $context["in_str"] . ")");
							} else {
								array_push($mod, "contextOutTag()");
							}
							break;
						case "script":
							array_push($mod, 'contextJs(' . $context["in_str"] . ')');
							break;
					}
					
				}
			}
			$this->cur_context = $context;
			if (count($mod) <= 0) {
				return;
			}
			$ov = $var;
			foreach ($mod as $name) {
				$modifier = explode('(', $name, 2);
				$name = $modifier[0];
				$params = substr($modifier[1], 0, -1);
				$params = $this->getParams($params);
				foreach (self::$modifiers as $_name => $mod) {
					if ($_name != $name)
						continue;
					$ov = 'self::callModifier("' . $_name . '",' . $ov . (!empty($params) ? ',"' . implode('","', $params) . '"' : "") . ')';
				}
				continue;
			}
			$var = $ov;
		}
		
		/**
		 * @param string     $str
		 * @param array|null $context
		 *
		 * @return array|bool
		 */
		private function getVarContext (string $str, $context = null) {
			if ($context == null) {
				$cont = $this->content;
				$in_str = false;
				$str_char = "";
				$in_tag = false;
				$prev_tag = "";
				$prev_char = "";
				$context = ["offset" => 0];
			} else {
				$cont = substr($this->content, $context['offset']);
				$in_str = $context["in_str"];
				$str_char = $context["str_char"];
				$in_tag = $context["in_tag"];
				$prev_tag = $context["tag"];
				$prev_char = $context["prev_char"];
			}
			
			$i = strpos($cont, $str);
			if ($i === false) {
				return false;
			}
			$escaped = false;
			$capturing_tag_name = false;
			$char = "";
			for ($j = 0; $j <= $i; $j++) {
				$prev_char = $char;
				$char = substr($cont, $j, 1);
				switch ($char) {
					case "\\":
						$escaped = true;
						//continue;
						break;
					case "'":
					case '"':
						if (!$escaped) {
							if ($in_str && $char == $str_char) {
								$str_char = $char;
							}
							$in_str = !$in_str;
						}
						break;
					case ">":
						if (!$in_str) {
							if ($prev_char == "?") continue 2;
							$in_tag = false;
							if ($capturing_tag_name) {
								$capturing_tag_name = false;
							}
						}
						break;
					case "<":
						if (!$in_str) {
							if (substr($cont, $j + 1, 1) == "?") continue 2;
							$prev_tag = "";
							$in_tag = true;
							$capturing_tag_name = true;
							continue 2;
						}
						break;
					case " ":
						if ($capturing_tag_name) {
							$capturing_tag_name = false;
						}
						break;
					default:
						if ($capturing_tag_name) {
							$prev_tag .= $char;
						}
				}
				if ($escaped) {
					$escaped = false;
				}
			}
			return array(
				"tag" => $prev_tag,
				"in_tag" => $in_tag,
				"in_str" => $in_str,
				"offset" => $i + (int)$context['offset'],
				"str_char" => $str_char,
				"prev_char" => $prev_char
			);
		}
		
		/**
		 * @param string $str
		 * @param string $escapeChar
		 * @param string $repChar
		 * @param string $strDelimiter
		 *
		 * @return string
		 */
		private function escapeCharsInString (string $str, string $escapeChar, string $repChar, string $strDelimiter = '"'): string {
			$ret = "";
			$inQuote = false;
			$escaped = false;
			for ($i = 0; $i <= strlen($str); $i++) {
				$char = substr($str, $i, 1);
				switch ($char) {
					case '\\':
						$escaped = true;
						$ret .= $char;
						break;
					case $strDelimiter:
						if (!$escaped) {
							$inQuote = !$inQuote;
						}
						$ret .= $char;
						break;
					default:
						if ($inQuote && $char == $escapeChar) {
							$ret .= $repChar;
						} else {
							$ret .= $char;
						}
				}
				if ($escaped) {
					$escaped = false;
				}
			}
			return $ret;
		}
		
		/**
		 * @return void
		 */
		private function handleVars (): void {
			$matches = array();
			preg_match_all('/{\s*\$(.+?)\s*}/', $this->content, $matches);
			if (!empty($matches)) {
				foreach ($matches[1] as $i => $var_name) {
					$var_name = $this->escapeCharsInString($var_name, '.', '**dot**');
					$var_name = explode('.', $var_name);
					if (count($var_name) > 1) {
						$vn = $var_name[0];
						if (empty($vn)) {
							$vn = "__func";
						}
						unset($var_name[0]);
						$mod = array();
						foreach ($var_name as $j => $index) {
							$index = str_replace('**dot**', '.', $index);
							$index = explode('->', $index, 2);
							$obj = '';
							if (count($index) > 1) {
								$obj = '->' . $index[1];
								$index = $index[0];
							} else
								$index = $index[0];
							if (substr($index, -1, 1) == ")") {
								$mod[] = $index . $obj;
							} else {
								if (substr($index, 0, 1) == '$')
									$vn .= "[$index]$obj";
								else
									$vn .= "['$index']$obj";
							}
						}
						$var_name = '$' . $vn;
						$this->applyModifiers($var_name, $mod, $matches[0][$i]);
					} else {
						$var_name = '$' . $var_name[0];
						$this->applyModifiers($var_name, array(), $matches[0][$i]);
					}
					$rep = '<?php try{ echo @' . $var_name . '; } catch(\Exception $e) { echo $e->getMessage(); } ?>';
					$this->content = $this->str_replace_first($matches[0][$i], $rep, $this->content);
				}
			}
		}
		
		/**
		 * @param string $find
		 * @param string $replace
		 * @param string $string
		 *
		 * @return string
		 */
		private function str_replace_first (string $find, string $replace, string $string): string {
			$pos = strpos($string, $find);
			if ($pos !== false) {
				return substr_replace($string, $replace, $pos, strlen($find));
			}
			return "";
		}
		
		/**
		 * @return bool|void
		 */
		private function handleIfMacros () {
			$match = $this->matchTags('/<([a-xA-Z_\-0-9]+).+?tn-if\s*=\s*"(.+?)".*?>/', '{endif}');
			if (empty($match)) {
				return false;
			}
			$this->content = preg_replace('/<([a-xA-Z_\-0-9]+)(.+?)tn-if\s*=\s*"(.+?)"(.*?)>/', '{if $3}<$1$2$4>', $this->content);
		}
		
		/**
		 * @return void|bool
		 */
		private function handleLoopMacros () {
			$match = $this->matchTags('/<([a-xA-Z_\-0-9]+).+?tn-loop\s*=\s*"(.+?)".*?>/', '{endloop}');
			if (empty($match)) {
				return false;
			}
			$this->content = preg_replace('/<([a-xA-Z_\-0-9]+)(.+?)tn-loop\s*=\s*"(.+?)"(.*?)>/', '{loop $3}<$1$2$4>', $this->content);
		}
		
		/**
		 * @return void|bool
		 */
		private function handleBlockMacros () {
			$match = $this->matchTags('/<([a-xA-Z_\-0-9]+).+?tn-block\s*=\s*"(.+?)".*?>/', '{endblock}');
			if (empty($match)) {
				return false;
			}
			$this->content = preg_replace('/<([a-xA-Z_\-0-9]+)(.+?)tn-block\s*=\s*"(.+?)"(.*?)>/', '{block $3}<$1$2$4>', $this->content);
		}
		
		/**
		 * @param string $regex
		 * @param string $append
		 *
		 * @return array|bool
		 */
		private function matchTags (string $regex, string $append = "") {
			$matches = array();
			if (!preg_match_all($regex, $this->content, $matches)) {
				return false;
			}
			$offset = 0;
			$ret = array();
			foreach ($matches[0] as $k => $match) {
				$_cont = substr($this->content, $offset);
				$in_str = false;
				$escaped = false;
				$i = strpos($_cont, $match);
				$tag = $matches[1][$k];
				$len_match = strlen($match);
				$offset += $i + $len_match;
				$str_char = "";
				$lvl = 1;
				$prev_tag = "";
				$struct = "";
				$in_tag = false;
				$capturing_tag_name = false;
				$_m = array();
				$break = false;
				foreach ($matches as $z => $v) {
					$_m[$z] = $matches[$z][$k];
				}
				
				$ret[$k] = array(
					"match" => $match,
					"matches" => $_m,
					"all" => $match,
					"inner" => "",
					"starts_at" => $offset - $len_match,
					"ends_at" => 0,
				);
				
				for ($j = $i + strlen($match); $j <= strlen($_cont); $j++) {
					$char = substr($_cont, $j, 1);
					$prev_char = $char;
					$struct .= $char;
					$break = false;
					switch ($char) {
						case "\\":
							$escaped = true;
							continue 2;
							break;
						case "'":
						case '"':
							if (!$escaped) {
								if ($in_str && $char == $str_char) {
									$str_char = $char;
								}
								$in_str = !$in_str;
							}
							break;
						case ">":
							if (!$in_str) {
								if ($in_tag) {
									$in_tag = false;
									if ($prev_tag == "/" . $tag) {
										$lvl--;
										if ($lvl <= 0) {
											$break = true;
										}
									} else if (substr($prev_tag, 0, 1) == "/") {
										$lvl--;
									} else {
										if ($prev_char != "/" && !in_array(str_replace("/", "", $prev_tag), array('area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'))) {
											$lvl++;
										}
									}
									if ($capturing_tag_name) {
										$capturing_tag_name = false;
									}
								}
							}
							break;
						case "<":
							if ($in_tag) {
								continue 2;
							}
							if (!$in_str) {
								$prev_tag = "";
								$in_tag = true;
								$capturing_tag_name = true;
								continue 2;
							}
							break;
						case " ":
							if ($capturing_tag_name) {
								$capturing_tag_name = false;
							}
							break;
						default:
							if ($capturing_tag_name) {
								$prev_tag .= $char;
							}
					}
					if ($escaped) {
						$escaped = false;
					}
					if ($break) {
						break;
					}
				}
				$ret[$k]["all"] .= $struct;
				$struct_len = strlen($struct);
				$ret[$k]["inner"] = substr($struct, 0, $struct_len - strlen($tag) - 3);
				$ret[$k]["ends_at"] = $ret[$k]["starts_at"] + $struct_len + $len_match;
				if ($break && !empty($append)) {
					$this->content = substr_replace($this->content, $append, $ret[$k]["ends_at"], 0);
				}
			}
			return $ret;
		}
		
		/**
		 * @throws Exception
		 */
		private function handleExtends (): void {
			$matches = array();
			preg_match_all('/{\s*(extends )\s*(.+?)\s*}/', $this->content, $matches);
			$base = $matches[2];
			if (count($base) <= 0)
				return;
			if (count($base) > 1)
				throw new Exception("Each template can extend 1 parent at the most");
			$base = $base[0];
			if (substr($base, 0, 1) == '"') {
				$base = substr($base, 1);
			}
			if (substr($base, -1) == '"') {
				$base = substr($base, 0, -1);
			}
			$base = self::$root . $base;
			if (!file_exists($base)) {
				throw new Exception("Unable to extend base template " . $base);
			}
			$this->base = $base;
			$this->content = str_replace($matches[0][0], "", $this->content);
		}
		
		/**
		 * @return void
		 */
		private function handleIfs (): void {
			$matches = array();
			preg_match_all('/{\s*(if|elseif)\s*(.+?)\s*}/', $this->content, $matches);
			if (!empty($matches)) {
				foreach ($matches[2] as $i => $condition) {
					$condition = trim($condition);
					$condition = str_replace(array(
						' eq ',
						' gt ',
						' lt ',
						' neq ',
						' or ',
						' gte ',
						' lte '
					), array(
						' == ',
						' > ',
						' < ',
						' != ',
						' || ',
						' >= ',
						' <= '
					
					), $condition);
					$var_match = array();
					preg_match_all('/\$([a-zA-Z0-9_\-().]+)/', $condition, $var_match);
					if (!empty($var_match)) {
						foreach ($var_match[1] as $j => $var) {
							$var_name = explode('.', $var);
							if (count($var_name) > 1) {
								$vn = $var_name[0];
								unset($var_name[0]);
								$mod = array();
								foreach ($var_name as $k => $index) {
									$index = explode('->', $index, 2);
									$obj = '';
									if (count($index) > 1) {
										$obj = '->' . $index[1];
										$index = $index[0];
									} else
										$index = $index[0];
									if (substr($index, -1, 1) == ")") {
										$mod[] = $index . $obj;
									} else {
										$vn .= "['$index']$obj";
									}
								}
								$var_name = '$' . $vn;
								$this->applyModifiers($var_name, $mod);
							} else {
								$var_name = '$' . $var_name[0];
							}
							$condition = str_replace(@$var_match[0][$j], $var_name, $condition);
						}
					}
					$rep = '<?php ' . $matches[1][$i] . '(@' . $condition . '): ?>';
					$this->content = str_replace($matches[0][$i], $rep, $this->content);
				}
			}
			$this->content = preg_replace('/{\s*(\/if|endif)\s*}/', '<?php endif; ?>', $this->content);
			$this->content = preg_replace('/{\s*else\s*}/', '<?php else: ?>', $this->content);
			
		}
		
		/**
		 * @return void
		 */
		private function handleBlocks (): void {
			$matches = array();
			preg_match_all('/{\s*(block)\s*(.+?)\s*}/', $this->content, $matches);
			$blocks = $matches[2];
			if (count($blocks) <= 0)
				return;
			foreach ($blocks as $i => $block) {
				$block = trim($block);
				$rv = '<?php ob_start(array(&$this, "ob_' . $block . '")); ?>';
				$this->content = str_replace($matches[0][$i], $rv, $this->content);
			}
			$this->content = preg_replace('/{\s*endblock\s*}/', '<?php ob_end_flush(); ?>', $this->content);
		}
		
		/**
		 * @param string $name
		 * @param array  $args
		 *
		 * @return mixed|string
		 */
		public function __call (string $name, array $args) {
			$n = explode('_', $name);
			if ($n[0] == 'ob') {
				$this->blocks[$n[1]] = $args[0];
			}
			if ($this->base != null)
				return "";
			
			return empty($this->blocks_override[$n[1]]) ? $args[0] : $this->blocks_override[$n[1]];
		}
		
		/**
		 * @return void
		 */
		private function handleLoops () {
			$matches = array();
			preg_match_all('/{\s*(loop|for)\s*(.+?)\s*}/', $this->content, $matches);
			if (!empty($matches)) {
				foreach ($matches[2] as $i => $loop) {
					$loop = str_replace(' in ', '**in**', $loop);
					$loop = $this->removeWhiteSpaces($loop);
					$loop_det = explode('**in**', $loop);
					$loop_name = $loop_det[1];
					unset($loop_det[1]);
					$loop_name = explode('.', $loop_name);
					if (count($loop_name) > 1) {
						$ln = $loop_name[0];
						unset($loop_name[0]);
						foreach ($loop_name as $j => $suffix)
							$ln .= "['$suffix']";
						$loop_name = $ln;
					} else {
						$loop_name = $loop_name[0];
					}
					$key = NULL;
					$val = NULL;
					
					$loop_vars = explode(",", $loop_det[0]);
					if (count($loop_vars) > 1) {
						$key = $loop_vars[0];
						$val = $loop_vars[1];
					} else {
						$val = $loop_vars[0];
					}
					
					foreach ($loop_det as $j => $_val) {
						@list($k, $v) = explode(',', $_val);
						if ($k == "key") {
							$key = $v;
							continue;
						}
						if ($k == "item") {
							$val = $v;
							continue;
						}
					}
					$rep = '<?php foreach(' . $loop_name . ' as ' . (!empty($key) ? $key . ' => ' . $val : ' ' . $val) . '): ?>';
					$this->content = str_replace($matches[0][$i], $rep, $this->content);
				}
			}
			$this->content = preg_replace('/{\s*(\/loop|endloop|\/for|endfor)\s*}/', '<?php endforeach; ?>', $this->content);
		}
		
		/**
		 * @param string $text
		 *
		 * @return string
		 */
		public static function removeSpecialChars (string $text): string {
			$find = array('á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', ' ', '"', "'");
			$rep = array('a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N', '-', "", "");
			return str_replace($find, $rep, $text);
		}
		
		/**
		 * @param string $text
		 * @param int    $digits
		 *
		 * @return string
		 * @noinspection PhpUnused
		 */
		public static function zeroFill (string $text, int $digits): string {
			$ret = "";
			if (strlen($text) < $digits) {
				$ceros = $digits - strlen($text);
				for ($i = 0; $i <= $ceros - 1; $i++) {
					$ret .= "0";
				}
				$ret = $ret . $text;
				return $ret;
			} else {
				return $text;
			}
		}
		
		/**
		 * @return void|string
		 */
		private static function initModifiers () {
			self::extendModifier("upper", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return strtoupper($input);
			});
			self::extendModifier("lower", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return strtolower($input);
			});
			self::extendModifier("capitalize", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return ucwords($input);
			});
			self::extendModifier("abs", function (string $input) {
				if (!is_numeric($input)) {
					return $input;
				}
				return abs($input);
			});
			self::extendModifier("isEmpty", function (string $input) {
				return empty($input);
			});
			self::extendModifier("truncate", function (string $input, $len) {
				if (empty($len)) {
					throw new Exception("length parameter is required");
				}
				return substr($input, 0, $len) . (strlen($input) > $len ? "..." : "");
			});
			self::extendModifier("count", function (Countable $input) {
				return count($input);
			});
			self::extendModifier("length", function (Countable $input) {
				return count($input);
			});
			self::extendModifier("toLocal", function (DateTime $input) {
				if (!is_object($input)) {
					throw new Exception("variable is not a valid date");
				}
				return date_timezone_set($input, timezone_open(self::$local_tz));
			});
			self::extendModifier("toTz", function (DateTime $input, $tz) {
				if (!is_object($input)) {
					throw new Exception("variable is not a valid date");
				}
				return date_timezone_set($input, timezone_open($tz));
			});
			self::extendModifier("toGMT", function (DateTime $input, $tz) {
				if (!is_object($input)) {
					throw new Exception("variable is not a valid date");
				}
				if (empty($tz)) {
					throw new Exception("timezone is required");
				}
				return date_timezone_set($input, timezone_open("GMT"));
			});
			self::extendModifier("date", function (string $input, $format) {
				if (!is_object($input)) {
					throw new Exception("variable is not a valid date");
				}
				if (empty($format)) {
					throw new Exception("date format is required");
				}
				return date_format($input, $format);
			});
			self::extendModifier("nl2br", function (string $input) {
				return nl2br($input);
			});
			self::extendModifier("stripSlashes", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return stripslashes($input);
			});
			self::extendModifier("sum", function (string $input, $val) {
				if (!is_numeric($input) || !is_numeric($val)) {
					throw new Exception("input and value must be numeric");
				}
				return $input + (float)$val;
			});
			self::extendModifier("substract", function (string $input, $val) {
				if (!is_numeric($input) || !is_numeric($val)) {
					throw new Exception("input and value must be numeric");
				}
				return $input - (float)$val;
			});
			self::extendModifier("multiply", function (string $input, $val) {
				if (!is_numeric($input) || !is_numeric($val)) {
					throw new Exception("input and value must be numeric");
				}
				return $input * (float)$val;
			});
			self::extendModifier("divide", function (string $input, $val) {
				if (!is_numeric($input) || !is_numeric($val)) {
					throw new Exception("input and value must be numeric");
				}
				return $input / (float)$val;
			});
			self::extendModifier("mod", function (string $input, $val) {
				if (!is_numeric($input) || !is_numeric($val)) {
					throw new Exception("input and value must be numeric");
				}
				return $input % (float)$val;
			});
			self::extendModifier("encodeTags", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return htmlspecialchars($input, ENT_NOQUOTES);
			});
			self::extendModifier("decodeTags", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return htmlspecialchars_decode($input);
			});
			self::extendModifier("stripTags", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return strip_tags($input);
			});
			self::extendModifier("urlDecode", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return urldecode($input);
			});
			self::extendModifier("addSlashes", function (string $input) {
				return addslashes($input);
			});
			self::extendModifier("urlFriendly", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return urlencode(self::removeSpecialChars(strtolower($input)));
			});
			self::extendModifier("trim", function (string $input) {
				if (!is_string($input)) {
					return $input;
				}
				return trim($input);
			});
			self::extendModifier("sha1", function (string $input) {
				if (!is_string($input)) {
					throw new Exception("input must be string");
				}
				return sha1($input);
			});
			self::extendModifier("safe", function (string $input) {
				return htmlentities($input, ENT_QUOTES);
			});
			self::extendModifier("numberFormat", function (string $input, $precision = 2) {
				if (!is_numeric($input)) {
					throw new Exception("input must be numeric");
				}
				return number_format($input, (int)$precision);
			});
			self::extendModifier("lastIndex", function (string $input) {
				if (!is_array($input)) {
					throw new Exception("input must be an array");
				}
				return current(array_reverse(array_keys($input)));
			});
			self::extendModifier("lastValue", function (string $input) {
				if (!is_array($input)) {
					throw new Exception("input must be an array");
				}
				return current(array_reverse($input));
			});
			self::extendModifier("jsonEncode", function (string $input) {
				return json_encode($input);
			});
			self::extendModifier("substr", function (string $input, $a = 0, $b = 0) {
				return substr($input, $a, $b);
			});
			self::extendModifier("join", function (string $input, $glue) {
				if (!is_array($input)) {
					throw new Exception("input must be an array");
				}
				if (empty($glue)) {
					throw new Exception("string glue is required");
				}
				return implode($glue, $input);
			});
			self::extendModifier("explode", function (string $input, $del) {
				if (!is_string($input)) {
					throw new Exception("input must be a string");
				}
				if (empty($del)) {
					throw new Exception("delimiter is required");
				}
				return explode($del, $input);
			});
			self::extendModifier("replace", function (string $input, $search, $replace) {
				if (!is_string($input)) {
					throw new Exception("input must be a string");
				}
				if (empty($search)) {
					throw new Exception("search is required");
				}
				if (empty($replace)) {
					throw new Exception("replace is required");
				}
				return str_replace($search, $replace, $input);
			});
			self::extendModifier("preventTagEncode", function (string $input) {
				return $input;
			});
			self::extendModifier("default", function (string $input, $default) {
				return (empty($input) ? $default : $input);
			});
			self::extendModifier("contextJs", function (string $input, $in_str) {
				if ((is_object($input) || is_array($input)) && !$in_str) {
					return json_encode($input);
				} else if (is_numeric($input) || is_bool($input)) {
					return $input;
				} else if (is_null($input)) {
					return "null";
				} else {
					if (!$in_str) {
						return '"' . addslashes($input) . '"';
					} else {
						if (is_object($input) || is_array($input)) {
							$input = json_encode($input);
						}
						return addslashes($input);
					}
					
				}
			});
			self::extendModifier("contextOutTag", function (string $input) {
				if (is_object($input) || is_array($input)) {
					return var_dump($input);
				} else {
					return htmlentities($input, ENT_QUOTES);
				}
			});
			self::extendModifier("contextTag", function (string $input, $in_str) {
				if ((is_object($input) || is_array($input)) && $in_str) {
					return http_build_query($input);
				} else {
					if ($in_str) {
						return urlencode($input);
					} else {
						return htmlentities($input, ENT_QUOTES);
					}
					
				}
			});
			self::extendModifier("addDoubleQuotes", function (string $input) {
				return '"' . $input . '"';
			});
			self::extendModifier("ifEmpty", function (string $input, $true_val, $false_val = null) {
				if (empty($true_val)) {
					throw new Exception("true value is required");
				}
				$ret = $input;
				if (empty($ret)) {
					$ret = $true_val;
				} else if ($false_val) {
					$ret = $false_val;
				}
				return $ret;
			});
			
			self::extendModifier("if", function (string $input, $condition, $true_val, $false_val = null, $operator = "eq") {
				if (empty($true_val)) {
					throw new Exception("true value is required");
				}
				switch ($operator) {
					case '':
					case '==':
					case '=':
					case 'eq':
					default:
						$operator = "==";
						break;
					case '<':
					case 'lt':
						$operator = "<";
						break;
					case '>':
					case 'gt':
						$operator = ">";
						break;
					case '<=':
					case 'lte':
						$operator = "<=";
						break;
					case '>=':
					case 'gte':
						$operator = ">=";
						break;
					case 'neq':
						$operator = "!=";
						break;
				}
				$ret = $input;
				
				$tmp_func = "return $condition$operator$input ? true : false;";
				
				if(eval($tmp_func)) {
					$ret = $true_val;
				} else if($false_val) {
					$ret = $false_val;
				}
				return $ret;
			});
		}
		
		/**
		 * @return bool
		 */
		public function loadLanguage (): bool {
			foreach ($this->languageFiles as $languageFile) {
				$pathInfo = pathinfo($languageFile);
				$fileName = $pathInfo["filename"];
				$XMLFile = simplexml_load_file($languageFile);
				$result = $XMLFile->xpath("/Strings/string");
				foreach ($result as $value) {
					$this->localized[$fileName][(string)$value->key] = (string)$value->value;
				}
			}
			$this->assign("localized", $this->localized);
			return true;
		}
	}
