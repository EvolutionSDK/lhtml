<?php

namespace Bundles\LHTML;
use Exception;
use Closure;
use e;

class Scope {
	
	public $owner;
	
	private $source_as = false;
	private $source_data = false;
	private $source_pointer = false;
	private $source_count = false;
	private $deferred_sources = array();
	
	public $timers = array();

	public static function hookExists($name) {
		$hooks = e::configure('lhtml')->hook;
		return isset($hooks[$name]);
	}

	public static function getHook($name) {
		$hooks = e::configure('lhtml')->hook;
		if(isset($hooks[$name])) {
			if(is_array($hooks[$name]) && isset($hooks[$name]['--reference']))
				return $hooks[$name]['--reference'];
			return $hooks[$name];
		}
		return false;
	}

	public function sourceData() {
		return $this->source_data;
	}

	public function addDeferredSource($name, $value) {
		$this->deferred_sources[$name] = $value;
	}

	public function absorb(Scope $scope) {
		foreach($scope->sourceData() as $var => $value)
			$this->source_data[$var] = $value;
	}
	
	public function __construct($owner = false) {
		$this->timers['scope->map'] = 0;
		$this->timers['scope->get'] = 0;
		
		/**
		 * Set the parent scope
		 */
		$this->owner = $owner;
		
		/**
		 * Prepare URL Hook
		 */
		$url = explode('/', $_SERVER['REDIRECT_URL']);
		$url = array_filter($url, function($val) {
			if(strlen($val) > 0 || is_array($val) || is_object($val))
				return true;
		});
		$url['last'] = end($url);
		$url['first'] = reset($url);
		$url['current'] = $_SERVER['REQUEST_URI'];
		$url['referer'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

		/**
		 * Bind URL hook
		 */
		e::configure('lhtml')->activeAddKey('hook', ':url', array('--reference' => &$url));
	}
	
	public function get($var_map, $depth = 0, $ztrace = false, $zsteps = array()) {
	
		if($var_map === 'true') return true;
		if($var_map === 'false') return false;

		/**
		 * Add a condition to trace a source variable
		 * @author Nate Ferrero
		 */
		if(isset($_GET['--lhtml-scope']) && strpos($var_map, $_GET['--lhtml-scope']) !== false)
			$ztrace = true;

		if($depth > 100) {
			if(is_array($var_map))
				$var_map = e\stylize_array($var_map);
			if(is_object($var_map))
				$var_map = '[Object ' . get_class($var_map) . ']';

			/**
			 * Trace!
			 * @author Nate Ferrero
			 */
			if($ztrace) {
				$zsteps[] = 'Too much recursion looking for variable `' . $var_map . '`';
				dump($zsteps);
			}

			if(isset($_GET['--scope-debug-recursion']))
				$this->owner->__debugStack();
			throw new Exception("Source recursion while looking for `$var_map` <a href='?--scope-debug-recursion'>Debug</a>");
		}

		$source = false;
		$tt = microtime(true);
		$deferred = false;

		if($ztrace)
			$zsteps[] = array('var' => $var_map);

		// Check for deferred sources
		if(isset($this->deferred_sources[$var_map])) {
			$var_map = $this->deferred_sources[$var_map];
			$deferred = true;

			if($ztrace)
				$zsteps[] = array('var' => $var_map, 'deferred' => true);
		}
		
		// strip special char for embedded JS vars
		if(is_string($var_map) AND strpos($var_map, '%') === 0) $var_map = substr($var_map, 1);
		
		$allmap = is_string($var_map) ? $this->parse($var_map, $depth + 1) : $var_map;
		$filters = $allmap['filters'];
		$map = $allmap['vars'];

		// Allmap calculates deferred variables (don't know why)
		if($deferred) {
			$source = implode('.', $map);

			/**
			 * Perform Filters
			 */
			if(is_array($filters)) foreach($filters as $filter) {
				if(!is_array($filter)) $source = e::filters($this, $filter, $source);
				else $source = e::filters($filter['func'], $source, $filter['args']);
			}

			if($ztrace) {
				$zsteps[] = array('var' => $var_map, 'source' => $source, 'deferred' => true);
				dump($zsteps);
			}

			return $source;
		}
		
		$flag_first = false;

		/**
		 * Loop source index
		 */
		if($map[0] == ':index') {
			$source = $this->source_pointer;
			$flag_first = 1;
		}

		/**
		 * Hook functions
		 */
		else if(is_array($map[0]) && isset($map[0]['func']) && strpos($map[0]['func'],':')===0) {

			if(!self::hookExists($map[0]['func']))
				;//throw new Exception("Hook `" . $map[0]['func'] . "` does not exist");
			else {
				$hook = self::getHook($map[0]['func']);

				if(is_callable($hook)) {
					$source = call_user_func_array($hook, $map[0]['args']);
					$flag_first=1;

					if($ztrace)
						$zsteps[] = array('hook function' => $hook, 'args' => $map[0]['args']);
				}
				else {
					$func = $map[0]['func'];
					throw new Exception("LHTML hook `$func` is not callable");
				}
			}

		}

		/**
		 * Hook simple call
		 */
		else if(is_string($map[0]) && strpos($map[0],':')===0) {

			if(!self::hookExists($map[0]))
				;//throw new Exception("Hook `$map[0]` does not exist");
			else {
				$hook = self::getHook($map[0]);

				if(is_callable($hook)) {

					if($ztrace)
						$zsteps[] = array('hook function' => $hook);

					$source = $hook();
					$flag_first=1;
				}
				else {

					if($ztrace)
						$zsteps[] = array('hook value' => $hook);

					$source = $hook;
					$flag_first=1;
				}
			}
			
		}
		
		if(!$flag_first) {

			if($ztrace)
				$zsteps[] = array('flag_first' => $flag_first);

			/**
			 * Check if traversable
			 */
			$traversable = isset($this->source_data[$map[0]]) && $this->source_data[$map[0]] instanceof \Traversable;

			if($ztrace)
				$zsteps[] = array('traversable' => $traversable);

			/**
			 * Literal string
			 */
			if(is_string($map[0]) && strpos($map[0],"'") === 0) {
				$source = trim($map[0],"'");

				if($ztrace) {
					$zsteps[] = array('literal string' => $source);
				}

				$flag_first = 1;
			}

			/**
			 * Literal number
			 */
			else if(is_string($map[0]) && is_numeric($map[0])) {

				/**
				 * Decimal Support
				 */
				if(count($map) === 2) $source = implode('.', $map);
				else $source = $map[0];

				if($ztrace) {
					$zsteps[] = array('number' => $source);
				}

				$flag_first = 1;
			}
			
			/**
			 * Pass on traversable object (i.e. allow loopable source when not in a loop)
			 * @author Nate Ferrero
			 */
			else if($this->source_pointer === false && is_string($map[0]) && $traversable) {
				$source = $this->source_data[$map[0]];

				$flag_first = 1;
			}

			/**
			 * Traversable Object
			 */
			else if($this->source_pointer !== false && is_string($map[0]) && $traversable) {

				$tr_obj = $this->source_data[$map[0]];

				# artificially boost the pointer without having to iterate through each item again.
				if(isset($tr_obj->position) ) {
					if($tr_obj->position == 0 ) $tr_obj->rewind();

					if($this->source_pointer < count($tr_obj)) {
						$tr_obj->position = $this->source_pointer;
						$source = $tr_obj->current();
					}
					else unset($source);
				}
				else {
					$i=0;
					foreach($tr_obj as $source) {
						if($i === $this->source_pointer) break;
						$i++;
						unset($source);
					}

				}
				if(isset($source)) $flag_first = 1;
			}
			
			/**
			 * Array
			 */
			else if($this->source_pointer !== false && is_string($map[0]) && isset($this->source_data[$map[0]]) && is_array($this->source_data[$map[0]])) {
				$i=0; foreach($this->source_data[$map[0]] as $source) {
					if($i === $this->source_pointer) break;
					unset($source);
					$i++;
				}
				
				if(isset($source)) $flag_first = 1;
			}
			
			/**
			 * Object
			 */
			else if(is_string($map[0]) && isset($this->source_data[$map[0]]) && !($this->source_data[$map[0]] instanceof \Traversable)) {
				$tmp = $this->source_data[$map[0]];
				
				if(is_array($source) && $this->source_pointer !== false) {
					$i=0; foreach($tmp as $source) {
						if($i === $this->source_pointer) break;
						unset($source);
						$i++;
					}
				} else $source = $tmp;
				
				if(isset($source)) $flag_first = 1;
			}
			
			else if(is_string($map[0]) && !isset($this->source_data[$map[0]])) {
				if(is_object($this->owner)) {
					$parent = $this->owner->parent();
					if(is_object($parent)) {
						$data = $parent->_data();
						if($data instanceof Scope)
							return $data->get($var_map, $depth + 1, $ztrace, $zsteps);
					}
				}
			}
			//else throw new \Exception("IXML Scope no function was called when calling {$var_map}");
		}

		if($ztrace)
			$zsteps[] = array("map" => $map);

		foreach($map as $i=>$var) {

			if($ztrace)
				$zsteps[] = array('source' => $source, 'processing #' . $i => $var);

			if($source instanceof Closure)
				$source = $source();

			if($flag_first && $i < $flag_first) {

				if($ztrace)
					$zsteps[] = array('continuing because:' => " \$i < \$flag_first ($i < $flag_first) ");

				continue;
			}
			if(!isset($source) || (!$source && !is_array($source))) {

				if($ztrace)
					$zsteps[] = array('breaking because:' => "source is empty", 'source' => $source);

				break;
			}

			if(is_array($var) && is_object($source)) {
				/**
				 * Allow catching of exceptions by prepending @ to a method
				 * @author Nate Ferrero
				 */
				$throw = true;
				if($var['func'][0] === '@') {
					$throw = false;
					$var['func'] = substr($var['func'], 1);
				}

				if($ztrace)
					$zsteps[] = array('function' => $var['func'], 'arguments' => $var['args'], 'throw any exceptions' => $throw);

				try {
					if(method_exists($source, $var['func'])) $source = call_user_func_array(array($source, $var['func']), $var['args']);
					else if(method_exists($source, '__call')) $source = call_user_func_array(array($source, $var['func']), $var['args']);
					else $source = false;
 				} catch(Exception $e) {
 					e\Trace_Exception($e);
 					if($throw)
 						throw $e;
 				}
 			}

			else if(is_object($source)) {

				if($ztrace)
					$zsteps[] = array(
						'var' => $var,
						'var isset' => isset($source->$var),
						'method exists' => method_exists($source, $var),
						'__call exists' => method_exists($source, '__call')
					);

				try {
					if(isset($source->$var)) $source = $source->$var;
					else if(!is_null($var) && method_exists($source, $var)) $source = $source->$var();
					else if(!is_null($var) && method_exists($source, '__call')) $source = $source->$var();
					else $source = false;

				}
				catch(Exception $e) {
					if(!isset($e->severity) || $e->severity < 3)
						throw $e;
					else e\Trace_Exception($e);
				}
			}
			
			else if(is_array($source)) {
				if(!$flag_first && $this->source_pointer !== false && $map[0] == $this->source_as && !$iterated) {
					$iterated = true;

					if($ztrace)
						$zsteps[] = array('next iteration of' => $map[0], 'current index' => $map[1]);

					$source = $source[$map[1]];
				}
				else if(isset($source[$var])) {

					if($ztrace)
						$zsteps[] = array('array index' => $var);

					$source = $source[$var];
				} else {
					$source = false;
				}
			}

			else if(is_numeric($source)) {
				$source = (float) $source;
			}

			else $source = false;
		}

		if($ztrace)
			$zsteps[] = array('source' => $source, 'filters' => $filters);
		
		/**
		 * Perform Filters
		 */
		if(is_array($filters)) foreach($filters as $filter) {
			if(!is_array($filter)) {
				$source = e::filters($this, $filter, $source);

				if($ztrace)
					$zsteps[] = array('filter' => $filter, 'source' => $source);
			}
			else {
				$source = e::filters($this, $filter['func'], $source, $filter['args']);

				if($ztrace)
					$zsteps[] = array('filter' => $filter['func'], 'arguments' => $filter['args'], 'source' => $source);
			}
		}
		
		$this->timers['scope->get'] += microtime(true) - $tt;
		
		if($ztrace)
			dump($zsteps);

		return $source;
	}
	
	/*
	 * Evaluate any condition string and return true or false based off the conditions.
	 * @author David Boskovic
	 * Examples: (:member.id > 1 || (:member.id == -5 && :member.status != 4 )) && :member.name|length > 1
	 */
	public function evaluate($string) {
	
		$string = '('.$string.')';
		$match = 1;
		while($match) {
			
			$matches = $this->_get_matches("/\([^\(\)]*\)/", $string);
			if(!$matches) $match = false;
			foreach($matches as $match) {
				$cmatch = trim($match,'()');
				$logical_ops = array('AND', 'OR', '&&', '||');
				$logical_segs = $this->_divide_string($cmatch, $logical_ops);
				if(count($logical_segs) === 1) {
					$a = $this->_evaluate_comparison($logical_segs[0]);
				} else {
					if(!(count($logical_segs)%2)) throw new Exception($string." can't be evaluated because of a bad logical operator structure. Please review and try again.");
					
					$a = false;
					$op = false;
					$b = false;
					foreach($logical_segs as $item) {
						if(in_array($item, $logical_ops)) {
							$op = $item;
						} elseif($a) {
							$b = $item;
							// compare now
							switch($op) {
								case 'AND':
								case '&&':
									if($this->_evaluate_comparison($a) && $this->_evaluate_comparison($b)) $a = 'true';
									else $a = 'false';
								break;
								case 'OR':
								case '||':
									if($this->_evaluate_comparison($a) || $this->_evaluate_comparison($b)) $a = 'true';
									else $a = 'false';
								break;
							}
						} else {
							$a = $item;
						}
					}
				}
				$string = str_replace($match, $a, $string);
			}

		}		

		return $this->_evaluate_comparison($string);
		
		//	evaluate open conditions
		
		
	}
	
	private function _evaluate_comparison($comp) {
		
				$ops = array('==', '!=', '===', '!==','<>','<','>','<=','>=');
				$p = false;
				foreach($ops as $i) {
					if(strpos($comp, " $i ") !== false) $p = true;
				}
				if($p == false) {
					$a = $comp;
					$op = '==';
					$b = 'true';
				}
				else
					list($a, $op, $b) = $this->_divide_string($comp, $ops);

				$eval_a = $this->get($a);
				$eval_b = $this->get($b);
				
				switch($op) {
					case '==':
						$result = $eval_a == $eval_b;
					break;
					case '!=':
					case '<>':
						$result = $eval_a != $eval_b;
					break;
					case '===':
						$result = $eval_a === $eval_b;
					break;
					case '!==':
						$result = $eval_a !== $eval_b;
					break;
					case '<':
						$result = $eval_a < $eval_b;
					break;
					case '>':
						$result = $eval_a > $eval_b;
					break;
					case '<=':
						$result = $eval_a <= $eval_b;
					break;
					case '>=':
						$result = $eval_a >= $eval_b;
					break;
				}
				return $result;
	}
	
	private function _divide_string($string, $array) {
		$string = " $string ";
	
		/**
		 * Determine where the operators are
		 */
		$index = array();
		foreach($array as $split) {
			$split = ' '.$split.' ';
		
			$pos = 0;
			while(($pos = strpos($string, $split, $pos)) !== FALSE) {
				$index[$pos] = $split;
				$pos += strlen($split);
			}
			
		}
		
		/**
		 * Sort the array by index (character positions)
		 */
		ksort($index);
		
		/**
		 * Prepare the output of the divided string
		 */
		$output = array();
		$lp = 0;
		foreach($index as $pos => $delim) {
			$output[] = substr($string, $lp, $pos - $lp);
			$output[] = $delim;
			$lp = $pos + strlen($delim);
		}
		
		/**
		 * Append the last part of the condition
		 */
		if(substr($string, $lp)) $output[] = substr($string, $lp);
		
		/**
		 * Trim the array strings
		 */
		foreach($output as &$trim)
			$trim = trim($trim);
				
		return $output;
	
	}
	
	private function _get_matches($regex, $string) {
		preg_match_all(
				$regex, //regex
				$string, // source
				$matches_vars, // variable to export results to
				PREG_SET_ORDER // settings
			);
			
			foreach((array)$matches_vars as $var) {
				$vars[] = $var[0];
			}
			return $vars;
	}
	
	public function parse($var, $depth = 0) {
		$tt = microtime(true);
		$original = $var;
		
		$extract_vars = $this->extract_vars($var);
		if(!empty($extract_vars)) foreach($extract_vars as $rv) {
			$val = (string) $this->get($rv, $depth);
			$var = str_replace('{'.$rv.'}', $val, $var);
		}
		
		$subvars = false;
		$extract_subvars = $this->extract_subvars($var);
		if(!empty($extract_subvars)) {
			$subvars = true;
			foreach($extract_subvars as $rv) {
				$val = (string) $this->get($rv, $depth);
				$var = str_replace('['.$rv.']', $val, $var);
			}
		}
		
		/**
		 * Re-extract variables that have been populated with subvars
		 * Allows for "variable variables" like : {[varname].something}
		 * @author Nate Ferrero
		 * @todo Implement this properly
		 *
		if($subvars && !empty($val)) {
			$replace = $this->get($var, $depth);
			/*if(!is_string($replace))
				$replace = e\json_encode_safe($replace);*//*
			if(is_string($replace)) {
				$var = str_replace($var, $replace, $var);
				var_dump($var);
			}
		}
		*/
		if(strpos($var, ' ? ') !== false) {
			list($cond, $result) = explode(' ? ', $var);
			$else = false;
			
			if(strpos($result, ' : ') !== false) list($result, $else) = explode(' : ', $result);
			
			if(strpos($cond, ' + ') !== false) {
				list($cond1, $cond2) = explode(' + ', $cond);
				$var = $cond1 + $cond2;
			}
			else if(strpos($cond, ' - ') !== false) {
				list($cond1, $cond2) = explode(' - ', $cond);
				$var = $cond1 - $cond2;
			}
			else if(strpos($cond, ' / ') !== false) {
				list($cond1, $cond2) = explode(' / ', $cond);
				$var = $cond1 / $cond2;
			}
			else if(strpos($cond, ' * ') !== false) {
				list($cond1, $cond2) = explode(' * ', $cond);
				$var = $cond1 * $cond2;
			}
			else if(strpos($cond, ' == ') !== false) {
				list($cond, $compare) = explode(' == ', $cond);
				$val = $this->get($cond, $depth);
				$cval = $this->get($compare, $depth);
				
				/**
				 * Make sure the values are not empty
				 * @author Kelly Becker
				 */
				if($val == $cval && !empty($val) && !empty($cval)) $var = $result;
				else $var = $else;
			}
			else if(strpos($cond, ' != ') !== false) {
				list($cond, $compare) = explode(' != ', $cond);
				$val = $this->get($cond, $depth);
				$cval = $this->get($cond, $depth);
				
				if($val != $cval) $var = $result;
				else $var = $else;
			}
			else if(strpos($cond, ' () ') !== false) {
				list($cond, $compare) = explode(' () ', $cond);
				$val = $this->get($cond, $depth);
				$cval = explode(',', $this->get($compare, $depth));
				$retval = false;
				foreach($cval as $tmp) if($val == trim($tmp)) $retval = true;
				if($retval) $var = $result;
				else $var = $else;
			}
			else if(strpos($cond, ' )( ') !== false) {
				list($cond, $compare) = explode(' )( ', $cond);
				$val = $this->get($cond, $depth);
				$cval = explode(',', $this->get($compare, $depth));
				$retval = true;
				foreach($cval as $tmp) if($val == trim($tmp)) $retval = false;
				if($retval) $var = $result;
				else $var = $else;
			}
			else {
				$val = $this->get($cond, $depth);
				$val = is_string($val) ? trim($val) : $val;
				if($val) $var = $result;
				else $var = $else;
			}
		}
		
		$ef = $this->extract_funcs($var);
		if(is_array($ef)) foreach($ef as $k=>$f) {
			$ef[$k]['key'] = '%F'.$k;
			$var = str_replace($f['string'], '%F'.$k, $var);
		}
		
		if(strpos($var, '|') !== false) {
			$a = explode('|', $var);
			$var = (strlen($a[0]) > 0 ? $a[0] :false);
			$filters = array_slice($a, 1);
		}
		else $filters = array();
		
		$vars = explode('.', $var);
		foreach($vars as &$v) {
			if(substr($v, 0, 2) == '%F') $v = $ef[substr($v, 2)];
		}
		
		if(is_array($filters)) foreach($filters as &$filter) {
			if(substr($filter, 0, 2) == '%F') $filter = $ef[substr($filter, 2)];
		}
		
		$this->timers['scope->map'] += microtime(true) - $tt;
		
		return array('vars' => $vars, 'filters' => $filters);
	}
	
	/**
	 * Get parsed variable
	 */
	public function __get($v) {
		return $this->get($v);
	}
	
	/**
	 * Load a literal variable into the scope
	 */
	public function __set($var, $value) {
		$this->source_data[$var] = $value;
	}
	
	/**
	 * Load source into the scope
	 */
	public function source($source, $as = false) {
		/**
		 * Set the source as
		 */
		if(!$as) $as = 'i';
		$this->source_as = $as;
				
		/**
		 * Load the source into the scope
		 */
		$this->source_data[$this->source_as] = $source;
		
		/**
		 * If string or non traversable object
		 */
		if(!(is_array($source) || $source instanceof \Traversable))
			$this->source_count = 1;
		
		/**
		 * Else count the iterations
		 */
		else {
			if($source instanceof \Traversable) $source->rewind();
			$this->source_count = count($source);
		}

		
		/**
		 * Reset the pointer
		 */
		$this->source_pointer = false;
	}
	
	/**
	 * Reset Iterations
	 */
	public function reset() {
		$this->source_pointer = 0;
		
		return $this;
	}
	
	/**
	 * Next Source
	 */
	public function next() {
		if($this->source_pointer < $this->source_count)
			$this->source_pointer++;
		
		return $this;
	}
	
	/**
	 * Is still in a safe zone
	 */
	public function iteratable() {
		if($this->source_pointer >= 0 && $this->source_pointer < $this->source_count)
			return true;
		else
			return false;
	}
	
	/**
	 * Back One Source
	 */
	public function back() {
		if($this->source_pointer !== 0)
			$this->source_pointer--;
		
		return $this;
	}
	
	/**
	 * Count the Sources
	 */
	public function count() {
		return $this->source_count;
	}
	
	/**
	 * Extract all variables Below Here
	 */
	private function extract_vars($content) {
		
		if(strpos($content, '{') === false) return array();
		// parse out the variables
		preg_match_all(
			"/{([\w:@|.\,\(\)\/\-\% \[\]\?'=]+?)}/", //regex
			$content, // source
			$matches_vars, // variable to export results to
			PREG_SET_ORDER // settings
		);
		
		foreach((array)$matches_vars as $var) {
			$vars[] = $var[1];
		}
		
		return $vars;
		
	}
	
	private function extract_subvars($content) {
		
		if(strpos($content, '[') === false) return array();
		// parse out the variables
		preg_match_all(
			"/\[([\w:@|.\,\(\)\/\-\% \[\]\?'=]+?)\]/", //regex
			$content, // source
			$matches_vars, // variable to export results to
			PREG_SET_ORDER // settings
		);
		
		foreach((array)$matches_vars as $var) {
			$vars[] = $var[1];
		}
		
		return $vars;
		
	}
	
	private function extract_funcs($content) {
		if(strpos($content, '(') === false) return array();
		// parse out the variables
		preg_match_all(
			"/([\w\:@]+?)\(([\w:@|.\,=@\(\)\/\-\%& ]*?)\)/", //regex
			$content, // source
			$matches_vars, // variable to export results to
			PREG_SET_ORDER // settings
		);
		
		foreach((array)$matches_vars as $var) {
			$vars[] = array('func' => $var[1], 'string' => $var[0], 
				'args' => ($var[2] == '' ? array() : explode(',', $var[2]))
			);
		}
		
		return $vars;
	}
	
	/**
	 * Print Info
	 * @author Nate Ferrero
	 */
	public function printInfo() {
		echo "<ul>";
		foreach ($this->source_data as $var => $val) {
			echo "<li>$var: $val</li>";
		}
		echo "</ul>";
	}
}