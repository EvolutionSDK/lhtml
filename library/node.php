<?php

namespace Bundles\LHTML;
use Bundles\Router\NotFoundException;
use Exception;
use e;

class Node {
	
	/**
	 * Parts of the element
	 */
	public $element;
	public $fake_element;
	public $attributes = array();
	public $children = array();
	public $can_preload = true;
	
	/**
	 * Iteration Variables
	 */
	public $loop_type;
	public $is_loop;
	public $scope_initialized = false;
	
	/**
	 * Parent in the Node Stack
	 */
	public $_;
	
	/**
	 * Source code information
	 */
	public $_code;

	/**
	 * Unique ID
	 */
	public $_idx;
	
	/**
	 * Tags that are complete
	 */
	public static $complete_tags = array('br','hr','link','img');
	
	public function __construct($element = false, &$parent = false) {

		$this->_idx = uniqid('node-', true);

		/**
		 * Initialize the element and set the parent if one exists
		 */
		$this->fake_element = $element;
		$this->element = $element;
		if($parent) $this->_ =& $parent;
		
		/**
		 * Initialize a new Scope if one does not exist
		 */
		if(!is_object($this->_)) $this->_data = new Scope($this);
	}

	public function __describe() {
		return $this->fake_element;
	}

	public $_ready = false;
	
	/**
	 * Ready waterfall function
	 * @author Nate Ferrero
	 */
	public final function _ready($doSelf = true) {

		if($this->canPreload() && !$this->_ready)
			$this->_init_scope();

		if($doSelf && !$this->_ready && method_exists($this, 'ready'))
			$this->ready();

		if(empty($this->_)) e\trace('Initializing LHTML Ready');

		foreach($this->children as $child) {
			if($child instanceof Node)
				$child->_ready();
		}

		/**
		 * Run another event after ready
		 * @author Kelly Becker
		 */
		if(empty($this->_)) $this->_afterReady();

		$this->_ready = true;

		if(empty($this->_)) e\trace('LHTML Ready Done');
	}

	/**
	 * Run after ready
	 * @author Kelly Becker
	 */
	public function _afterReady() {
		if(empty($this->_)) e\trace('Initializing LHTML afterReady');

		if(method_exists($this, 'afterReady'))
			$this->afterReady();

		foreach($this->children as $child) {
			if($child instanceof Node)
				$child->_afterReady();
		}
	}
	
	public function _error($err = 'Error') {
		throw $err;
		if($err instanceof NotFoundException)
			throw $err;
		if($err instanceof Exception)
			$err = $err->getMessage();
		$class = is_object($err) ? get_class($err) : 'Error';
			
		$err = "$err in tag `<$this->fake_element>` on line `".$this->_code->line.
			"` at column `".$this->_code->col."` in file `".$this->_code->file."`";
		$div = $this->_nchild('div'); // Not a good idea to use zwsp here :( &#8203;
		$div->_cdata("<div class='message error'><b>$class</b> ".preg_replace('/`([^`]*)`/x', '<code>$1</code>', str_replace('/', '/', htmlspecialchars($err))) . "</div>");
		return $div;
	}
	
	/**
	 * @attribute Tag name
	 * @attribute Source code, should include line and col properties
	 */
	public function _nchild($name, $code = null) {
		/**
		 * If is a lhtml tag create it in the stack
		 * @todo allow namespaced tags
		 */
		if(strpos($name, ':') === 0)
			$class_name = __NAMESPACE__."\\Nodes\\".substr($name, 1);
		else
			$class_name = __NAMESPACE__."\\Nodes\\$name";
		
		e\VerifyClass($class_name);

		if(strpos($name, ':') === 0) {
			try { $nchild = new $class_name($name, $this); }
			catch(NotFoundException $e) {
				e\trace_exception($e);
				throw $e;
			}
			catch(Exception $e) {
				e\trace_exception($e);
				if(strpos($class_name, ':') !== false) {
					$name_pos = strpos($class_name, ':');
					$str_len = strlen($class_name);
					$class_name = substr($class_name, 0, $name_pos - $str_len);
					try { $nchild = new $class_name($name, $this); }
					catch(NotFoundException $e) {
						e\trace_exception($e);
						throw $e;
					} 
					catch(Exception $n) {
						throw new Exception($e->getMessage());
					}
				}
				else throw new Exception($e->getMessage());
			}
		}
		
		/**
		 * If is a normal element create it in the stack
		 */
		else $nchild = new Node($name, $this);
		
		/**
		 * Save the source
		 */
		$nchild->_code = $code;
		
		/**
		 * Set the new child element to this object and return the new child
		 */
		$this->children[] =& $nchild;
		return $nchild;
	}

	/**
	 * Removes this element completely
	 * @author Nate Ferrero
	 */
	public function remove() {
		if(is_null($this->_))
			return;
		$index = array_search($this, $this->_->children);
		if($index !== false)
			array_splice($this->_->children, $index, 1);
	}

	/**
	 * Changes the parent element and adds the node to the children of the new parent
	 * @author Nate Ferrero
	 */
	public function appendTo(Node &$newParent) {
		$newParent->children[] = $this;
		$this->_ = $newParent;
	}

	/**
	 * Absorb all nodes in the passed array
	 * @author Nate Ferrero
	 */
	public function absorbAll($array) {
		foreach ($array as $key => $child) {
			if(!($child instanceof Node)) {
				$this->children[] = $child;
				continue;
			}
			$child->appendTo($this);
		}
	}

	/**
	 * Detach all current children from the node
	 * @author Nate Ferrero
	 */
	public function detachAllChildren() {
		$return = array();
		foreach ($this->children as $child) {
			if($child instanceof Node)
				$child->detach();
			$return[] = $child;
		}
		$this->children = array();
		return $return;
	}

	/**
	 * Make this node an orphan
	 */
	public function detach() {
		$this->_ = null;
	}

	/**
	 * Get the parent node
	 */
	function parent() {
		if(is_object($this->_))
			return $this->_;
		return null;
	}

	/**
	 * Deep cloning of a node
	 * @author Nate Ferrero
	 */
	public function __clone() {
		if(isset($this->_data))
			$this->_data = clone $this->_data;
		foreach($this->children as $index => $child) {
			if(!is_object($child)) continue;
			$this->children[$index] = clone $child;
			$this->children[$index]->_ = $this;
		}
	}

	/**
	 * Print Info
	 * @author Nate Ferrero
	 */
	public function printInfo() {
		$fake = ($this->element == $this->fake_element ? '' : "[$this->fake_element]");
		echo "<li><div>$this->element $fake</div><ul>";
		if(isset($this->_data)) {
			echo "<li><b>Data:</b> ";
			$this->_data->printInfo();
			echo "</li>";
		}
		foreach ($this->children as $child) {
			if(!($child instanceof Node)) continue;
			$child->printInfo();
		}
		echo "</ul></li>";
	}

	/**
	 * Search up the Node Stack for a parent element
	 * @author Kelly Becker
	 */
	public function findParent($tag) {
		$tag = strtolower($tag);

		$parent = $this->parent();

		if(!is_object($parent))
			return false;

		if($parent->fake_element === $tag)
			return $parent;

		return $parent->findParent($tag);
	}

	/**
	 * Search up the Node Stack for a child of a parent element
	 * @author Kelly Becker
	 */
	public function findChildOfParent($tag) {
		$tag = strtolower($tag);

		$parent = $this->parent();

		if(!is_object($parent))
			return false;

		$ret = $parent->returnChild($tag);
		if(!$ret) return $parent->findChildOfParent($tag);

		return $ret;
	}

	/**
	 * Return an immeditate child of an element
	 * @author Kelly Becker
	 */
	public function returnChild($tag) {
		$tag = strtolower($tag);

		foreach($this->children as $child) {
			if(!($child instanceof Node)) continue;
			if(strtolower($child->fake_element) === $tag)
				return $child;
		}
		return false;
	}

	/**
	 * Search by tag name
	 * @author Nate Ferrero
	 */
	public function getElementsByTagName($tag, $depth = 100) {
		$matches = array();
		$tag = strtolower($tag);
		foreach($this->children as $child) {
			if(!($child instanceof Node)) continue;
			if(strtolower($child->fake_element) === $tag)
				$matches[] = $child;
			if($depth > 1)
				$matches = array_merge($matches, $child->getElementsByTagName($tag, $depth - 1));
		}
		return $matches;
	}

	/**
	 * Check for presence of immediate child with tag name
	 * @author Nate Ferrero
	 */
	public function hasImmediateChild($tag) {
		$tag = strtolower($tag);
		foreach($this->children as $child) {
			if(!($child instanceof Node)) continue;
			if(strtolower($child->fake_element) === $tag)
				return true;
		}
		return false;
	}
	
	public function _cdata($cdata) {
		if(!is_string($cdata)) return false;
		
		/**
		 * Save the string to the children array then return true
		 */
		$this->children[] = $cdata;  return true;
	}
	
	public function _attr($name, $value = null) {
		/**
		 * Check if the attributes array is setup
		 */
		if(!is_array($this->attributes)) {
			$this->attributes = array();
		}
		
		/**
		 * Save the attribute to the array
		 */
		$this->attributes[$name] = $value; return true;
	}
	
	public function _attrs($attrs) {
		/**
		 * If the attributes are already formatted as an array
		 * Save the attributes to the object attribute array
		 */
		if(is_array($attrs)) { $this->attributes = $attrs; return true; }
		
		/**
		 * If the attributes came in as a string reformat them into the proper array structure
		 */
		$attrs = explode(' ', $attrs);
		foreach($attrs as $key=>$attr) {
			list($key, $attr) = explode('=',str_replace("\"", "", $attr));
			$attrs[$key] = $attr;
		}
		
		/**
		 * Save the reformatted attributes to the object array
		 */
		$this->attributes = $attrs; return true;
	}
	
	public function build($pre = true) {
		$displayContent = true;

		/**
		 * Allow Events to be run right before build
		 * @author Kelly Becker
		 */
		if(isset($this->attributes[':trigger-event'])) {
			switch($this->attributes[':trigger-event']) {
				case 'reverse-children':
					$this->children = array_reverse($this->children);
				break;
				default:
					e::$events->lhtmlBuildEvent($this, $this->attributes[':trigger-event']);
				break;
			}
		}
		
		/**
		 * Load Sourcing
		 */
		if($pre) $this->_init_scope();

		/**
		 * Start the output string for rendered data
		 */
		$output = "";

		/**
		 * Check for debug dumps
		 * @author Nate Ferrero
		 */
		if(!empty($this->attributes[':dump']))
			$dump = $this->attributes[':dump'];
		else $dump = false;
		
		/**
		 * Run a debug dump
		 * @author Kelly Becker
		 */
		if(!empty($dump)) switch($dump) {
			case 'scope':
				dump($this->_data());
			break;
			case 'node':
			default:
				dump($this);
			break;
		}

		/**
		 * Are we in a loop
		 */
		if($this->is_loop) {
			$this->_data()->reset();
			e\trace_enter('LHTML Iteration Loop', $this->attributes[':load'], array(), 7);
		} else $once = 1;

		/**
		 * Start counting loops
		 */
		$loop = 0;

		/**
		 * Prepare loop dump information if requested
		 */
		if($dump == 'loop') $loopDebug = array('before' => array(
			'index' => $loop,
			'is_loop' => $this->is_loop,
			':load' => $this->attributes[':load'],
			'data' => $this->_data(),
			'iteratable' => $this->_data()->iteratable()
		));
		
		/**
		 * Start build loop
		 */
		while($this->is_loop ? $this->_data()->iteratable() : $once--) {

			/**
			 * Increment Loop Count
			 */
			$loop++;

			/**
			 * Prebuild
			 * Moved above child before
			 * @author Kelly Becker
			 * @since July 13 2012
			 */
			if(method_exists($this, 'prebuild'))
				$this->prebuild();
			
			/**
			 * Allow manipulation of child elements
			 * @author Nate Ferrero
			 */
			if($this->_ instanceof Node && method_exists($this->_, 'childNodeBeforeBuild')) {
				$this->_->childNodeBeforeBuild($this);
			}
			
			/**
			 * If is a complete tag render it and return
			 */
			if(in_array($this->element, self::$complete_tags)) return "<$this->element".$this->_attributes_parse().' />';
			
			/**
			 * If it's a doctype element TODO clean this up / standardize
			 */
			if(isset($this->_code) && isset($this->_code->special) && $this->_code->special === 'doctype') {
				return "<!$this->element>";
			}
			
			/**
			 * If is a real element create the opening tag - output when self looping or on first loop iteration
			 */
			else if($this->element !== '' && $this->element && ($this->loop_type !== 'contents' || $loop === 1))
				$output .= "<$this->element".$this->_attributes_parse().'>';
			
			/**
			 * Loop thru the children and populate this tag
			 */
			if(!empty($this->children)) foreach($this->children as $child) {
				
				if($child instanceof Node && $displayContent) {
					if($child->_ !== $this)
						$child->attributes['lhtml_node_warning'] = "Parent node is different than the node which included this node as a child, expect scope issues.";
					try {
						$output .= $child->build();
					}
					catch(NotFoundException $e) {
						throw $e;
					}
					catch(Exception $e) {
						e\trace_exception($e);
						$output .= $child->_error($e)->build();
					}
				}
				
				else if(is_string($child)) {
					
					/**
					 * Handle Comment Functions
					 */
					if(strpos($child, '<!--@') !== FALSE) {
						$child = substr(substr($child, 5), 0, -3);
						$child = trim($child);
						list($func, $args) = explode(' ', $child, 2);
						
						//dump(array($func, $args));
						
						/**
						 * Switch Functions
						 */
						switch($func) {
							case 'IF':
								$displayContent = $this->_data()->evaluate(trim($args));
								if($displayContent) $displayContentUsed = true;
							break;
							case 'ELSEIF':
								if($displayContent !== true && !$displayContentUsed) {
									$displayContent = $this->_data()->evaluate(trim($args));
									if($displayContent) $displayContentUsed = true;
								}
								
								else $displayContent = false;
							break;
							case 'ELSE':
								if($displayContent !== true && !$displayContentUsed) {
									$displayContent = true;
									$displayContentUsed = true;
								}
								
								else $displayContent = false;
							break;
							case 'ENDIF':
							default:
								$displayContent = true;
								$displayContentUsed = false;
							break;
						}
						
					}
					
					else if($displayContent)
						$output .= $this->element == 'script' && isset($this->attributes[':delimiter']) ? $this->_string_parse($child, false, true) : $this->_string_parse($child);

				}
			}
			
			/**
			 * If a loop increment the pointer
			 */
			if($this->is_loop) $this->_data()->next();
	
			/**
			 * Close the tag - output when self looping or on last loop iteration
			 */
			if($this->element !== '' && $this->element && ($this->loop_type !== 'contents' || !$this->_data()->iteratable()))
				$output .= "</$this->element>";
			
			/**
			 * End build loop
			 */
		}

		/**
		 * Dump loop information
		 * @author Nate Ferrero
		 */
		if($dump == 'loop')
			dump($loopDebug);

		/**
		 * Show empty content if loop did not iterate
		 * @author Kelly Becker
		 */
		if($loop === 0) foreach($this->children as $node)
			if($node->fake_element == ':empty') return $node->build(true);
		
		/**
		 * Exit and reset the loop
		 */
		if($this->is_loop) {
			e\trace_exit();
			$this->_data()->reset();
		}
		
		/**
		 * Return the rendered page
		 */
		return is_null($this->_) ? $this->remove_antiVar($output) : $output;
	}
	
	public function _data() {
		/**
		 * Grab the next instance of Scope in line
		 */
		if(isset($this->_data)) return $this->_data;
		else {
			if(!($this->_ instanceof Node))
				throw new Exception("Parent of `&lt;$this->fake_element ".$this->_attributes_parse()."&gt;` is not a Node");
			return $this->_->_data();
		}
	}
	
	public function _init_scope($new = false) {

		if(!$new) {
			$var = false;
			/**
			 * If there is a load attribute load load the var as this var
			 */
			if(isset($this->attributes[':load']))
				$var = $this->attributes[':load'];
			if(!$var) return false;
			
			/**
			 * Instantiate a new scope for the children of this element
			 */
			list($source, $as) = explode(' as ', $var);

			// Get sub-sources first like :load=":e.app.model({:url.1})"
			$vars = $this->extract_vars($source);
			if($vars) foreach($vars as $var) {
				$source = str_replace('{' . $var . '}', $this->_data()->$var, $source);
			}

			// Evaluate the source 
			$source = '{'.$source.'}';
			$vars = $this->extract_vars($source);
			if($vars) foreach($vars as $var) {
				$source = $this->_data()->$var;
			}
			
		}
		
		/**
		 * Load IXML Iterate
		 */
		if(isset($this->attributes[':load']) && isset($this->attributes[':iterate'])) {
			$this->loop_type = $this->attributes[':iterate'];
			$this->is_loop = true;
			$this->can_preload = false;
		}

		/**
		 * Instantiate a new scope for the children of this element
		 */
		if(!isset($this->_data))
			$this->_data = new Scope($this);
		
		if(isset($source) && isset($as)) $this->_data()->source($source, $as);

		/**
		 * Ready Scope
		 */
		if(!$this->scope_initialized) {
			$this->scope_initialized = true;
		}
	}

	public function inLoop() {
		if($this->is_loop)
			return true;
		if(is_object($this->_))
			return $this->_->inLoop();
		return false;
	}

	public function canPreload() {
		if(!$this->can_preload)
			return false;
		if(is_object($this->_))
			return $this->_->canPreload();
		return true;
	}

	/**
	 * Simple sourcing of an array
	 * @author Kelly Becker
	 */
	public function source($as, $source) {

		if(!isset($this->_data))
			$this->_data = new Scope($this);

		if($this->_data() instanceof \stdClass) dump($this->_data());
		return $this->_data()->source($source, $as);
	}
	
	/**
	 * Parse the variables in a string
	 *
	 * @param string $value 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function _string_parse($value, $returnObjects = false, $js = false) {

		$delim = $js ? array('%','%') : array('{','}');
		$vars = $this->extract_vars($value, $delim);

		/**
		 * Only allow returning objects when the entire string is used for the object alone
		 * @author Nate Ferrero
		 */
		if($returnObjects && count($vars) === 1 && $value === "$delim[0]".$vars[0]."$delim[1]");
		else $returnObjects = false;

		if($vars) foreach($vars as $var) {
			$data_response = ($this->_data()->$var);
			if(is_object($data_response)) {
				if($returnObjects)
					return $data_response;
				$data_response = $this->describe($data_response);
			}
				
			$value = str_replace("$delim[0]".$var."$delim[1]", $data_response, $value);
		}
	
		return $value;
	}
	
	/**
	 * Parse the variables in a string
	 *
	 * @param string $value 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 * @author Nate Ferrero
	 */
	public function _string_parse_with_subvars($value) {
		$vars = $this->extract_subvars($value);
		if($vars) {
			foreach($vars as $var) {
				$data_response = ($this->_data()->$var);
				if(is_object($data_response))
					$data_response = $this->describe($data_response);
				$value = str_replace('['.$var.']', "$data_response", $value);
			}
		}

		$vars = $this->extract_vars($value);
		if($vars) foreach($vars as $var) {
			$data_response = ($this->_data()->$var);
			if(is_object($data_response))
				$data_response = $this->describe($data_response);
				
			$value = str_replace('{'.$var.'}', $data_response, $value);
		}
	
		return $value;
	}
	
	/**
	 * Parse the variables in an attribute then return them properly formatted
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function _attributes_parse() {
		
		$protocol = empty($_SERVER['HTTPS'])? 'http': 'https';
		$static_protocol = empty($_SERVER['HTTPS'])? 'http://assets': 'https://secure';
		$html = '';
		foreach($this->attributes as $attr => $value) {
			$vars = $this->extract_vars($value);
			if($vars) {
				foreach($vars as $var) {
					$data_response = ($this->_data()->$var);
					if(is_object($data_response))
						$data_response = $this->describe($data_response);
					$value = str_replace('{'.$var.'}', $data_response, $value);
				}
			}
			if(substr($attr,0,1) == ':') continue;
			
			/**
			 * Disable this for now - need to consider speed etc.
			 * @author Nate Ferrero
			 * $response = e::$events->{"attribute_$attr"}($value);
			 *
			 * if(count($response) > 0)
			 * 	$value = array_pop($response);
			 */

			if(strlen($value) > 0) $html .= " $attr=\"$value\"";
		}
		return $html;
	}
	
	/**
	 * Extract Variables
	 */
	protected function extract_vars(&$content, $delim = array('{', '}')) {
		
		if(strpos($content, $delim[0]) === false) return array();
		// parse out the variables
		preg_match_all(
			"/(?<!%)$delim[0]([\w:@|.\,\(\)\/\-\% \[\]\?'=]+?)$delim[1]/", //regex
			$content, // source
			$matches_vars, // variable to export results to
			PREG_SET_ORDER // settings
		);
		
		$vars = array();
		
		foreach((array)$matches_vars as $var) {
			$vars[] = $var[1];
		}
		
		return $vars;
		
	}
	
	protected function extract_subvars($content) {
		
		if(strpos($content, '[') === false) return array();
		// parse out the variables
		preg_match_all(
			"/\[([\w:@|.\,\(\)\/\-\% \[\]\?'=]+?)\]/", //regex
			$content, // source
			$matches_vars, // variable to export results to
			PREG_SET_ORDER // settings
		);
		
		$vars = array();
		
		foreach((array)$matches_vars as $var) {
			$vars[] = $var[1];
		}
		
		return $vars;
		
	}

	protected function remove_antiVar($content) {
		return preg_replace("/%({[\w:@|.\,\(\)\/\-\% \[\]\?'=]+?})/", "$1", $content);
	}
	
	protected function extract_funcs($content) {
		if(strpos($content, '(') === false) return array();
		// parse out the variables
		preg_match_all(
			"/([\w\:@]+?)\(([\w:@|.\,=@\(\)\/\-\%& ]*?)\)/", //regex
			$content, // source
			$matches_vars, // variable to export results to
			PREG_SET_ORDER // settings
		);
		
		$vars = array();
		
		foreach((array)$matches_vars as $var) {
			$vars[] = array('func' => $var[1], 'string' => $var[0], 'args' => explode(',', $var[2]));
		}
		
		return $vars;
	}
	
	/**
	 * @todo this is a bad method...  $object->name  there's no required ->name vars on bundles
	 */
	protected function describe(&$object) {
		if(method_exists($object, '__toString'))
			return $object->__toString();
		$class = get_class($object);
	    $xtra = '';
	    $xtra .= $object->name;
	    if(strlen($xtra) < 1 && method_exists($object, 'name'))
	        $xtra .= $object->name();
	    if(strlen($xtra) > 0)
	        $xtra = ': ' . $xtra;
		return "[$class$xtra]";
	}
	
	public function __debugStack() {
		echo <<<_
<!doctype html>
<html>
	<head>
		<title>Debug Stack</title>
		<style>
			body {
				font-size: 11px;
				font-family: Sans, Lucida Grande, Tahoma, Verdana, Helvetica, sans-serif;
				color: #333;
			}
			h1 {
				font-size: 14px;
				margin-top: 0;
			}
			h1, p, pre {
				margin-bottom: 0;
			}
			.key:after {
				content: '=';
			}
			.func, 
			.function 	{	color: darkblue;	}
			.array 		{	color: orange;		}
			.string 	{	color: green;		}
			.boolean 	{	color: orange;		}
			.number 	{	color: red;			}
			.object 	{	color: darkred;		}
			.class 		{	color: darkred;		}
			.line 		{	color: purple;		}
			.invisibles {
				color: gray; padding: 0 2px;
			}
			.node {
				float: left;
				position: relative;
				border: 1px solid #888;
				margin: 10px 0 10px 10px;
				padding-right: 10px;
				box-shadow: inset 0 0 2px #444;
				background: #fff;
			}
			.node:hover {
				background: #ace;
			}
			.node .lbox {
				margin: 0 auto;
				width: 200px;
			}
			.node label {
				position: relative;
				margin: 0 auto;
				top: -1px;
				display: block;
				width: 194px;
				background: #ffe;
				border: 1px solid #cc8;
				text-align: center;
				overflow: hidden;
				padding: 8px;
				left: -1px;
				margin-bottom: -2px;
				box-shadow: 0 1px 2px #444;
			}
			.node.duplicate label {
				background: #fee;
				border: 1px solid #c88;
			}
			.text {
				background: #eee;
				border: 1px solid #ccc;
				text-align: center;
				padding: 8px;
				width: 200px;
				overflow: hidden;
			}
		</style>
	</head>
	<body>
_;

		echo $this->__debugNode();

		echo '
	</body>
</html>';

		exit;
	}

	public function __debugChildren() {
		$cs = array();
		foreach($this->children as $child) {
			if(is_string($child) && strlen(trim($child) == 0)) continue;
			$cs[] = (
				$child instanceof Node ? $child->__debugNode() : '<pre class="text">' . trim($child) . '</pre>'
			);
		}

		return implode('', $cs);
	}

	private $__nodeList = array();

	public function __debugNode() {
		static $nodeList = array();
		if(!$this->_idx)
			$this->_idx = uniqid('xnode-', true);
		if(isset($this->__nodeList[$this->_idx]))
			$class = 'duplicate';
		else
			$class = '';
		$this->__nodeList[$this->_idx] = true;
		$attrs = array();
		foreach(e\stylize_array($this->attributes) as $item)
			$attrs[] = '<pre>' . $item . '</pre>';
		return '<div class="node '.$class.'"><div class="lbox"><label><h1>&lt;'.$this->fake_element.'&gt;</h1>'.
			implode('', $attrs) . '</label></div>'. $this->__debugChildren() . '</div>';
	}
}