<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;

class _If extends Node {
	
	public function ready() {

		$this->can_preload = false;
		$this->element = false;
	}
	
	public function build($pre = true) {
		if($pre)
			$this->_init_scope();
		$output = "";
		
		/**
		 * If requires iteration
		 * else just execute the loop once
		 */
		if($this->is_loop) {
			$this->_data()->reset();
		} else {
			$once = 1;
		}
		
		/**
		 * Start build loop
		 */
		while($this->is_loop ? $this->_data()->iteratable() : $once--) {
		
		/**
		 * Render the code
		 */
		if($this->process()) {
			if(!empty($this->children)) foreach($this->children as $child) {
				/**
				 * Skip else if true
				 */
				if($child instanceof Node && $child->fake_element == ':else')
					continue;
				if(is_object($child)) $output .= $child->build();
				else if(is_string($child)) $output .= $this->_string_parse($child);
			}
		}
		else {
			if(!empty($this->children)) foreach($this->children as $child) {
				if(is_object($child)) {
					if($child->fake_element == ':else') $output .= $child->build();
				}
			}
		}
		
		/**
		 * If a loop increment the pointer
		 */
		if($this->is_loop) $this->_data()->next();
		
		/**
		 * End build loop
		 */
		}
		
		if($this->is_loop) $this->_data()->reset();
		
		/**
		 * Return the rendered page
		 */
		return $output;
	}
	
	public function process() {
		if(isset($this->attributes['cond'])) {
		
			$v = $this->attributes['cond'];

			$vars = $this->extract_vars($v);

			if($vars) foreach($vars as $var) {
				$data_response = $this->_data()->$var;
				
				if(is_object($data_response))
					$data_response = $this->describe($data_response);
								
				$v = str_replace('{'.$var.'}', $data_response, $v);
			}

			$v = str_replace('\'', '', $v);

			$v = explode(' ', $v);

			if($v[0] === '') $v[0] = null;
			if($v[2] === '') $v[2] = null;
			if($v[0] === 'null') $v[0] = null;
			if($v[2] === 'null') $v[2] = null;
			if($v[0] === 'true') $v[0] = true;
			if($v[2] === 'true') $v[2] = true;
			if($v[0] === 'false') $v[0] = false;
			if($v[2] === 'false') $v[2] = false;

			if(is_string($v[0])) $v[0] = "'".$v[0]."'";
			if(is_string($v[2])) $v[2] = "'".$v[2]."'";

			if($v[0] === null) $v[0] = 'null';
			if($v[2] === null) $v[2] = 'null';
			if($v[0] === true) $v[0] = 'true';
			if($v[2] === true) $v[2] = 'true';
			if($v[0] === false) $v[0] = 'false';
			if($v[2] === false) $v[2] = 'false';

			$v = array($v[0], $v[1], $v[2]);

			$v = implode(' ', $v);

			try {
				eval("\$retval = ".$v.';');
			} catch(Exception $e) {}

		}
		
		if(isset($this->attributes['count'])) {
		
			$v = $this->attributes['count'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			if($v instanceof \Traversable || is_array($v) || empty($v)) $v = count($v);
			else $v = 1;
			
			if(isset($this->attributes['gt']) && is_numeric($this->attributes['gt'])) {
				if($v > $this->attributes['gt'])$retval = true;
				else $retval = false;
			}
			
			else if(isset($this->attributes['lt']) && is_numeric($this->attributes['lt'])) {
				if($v < $this->attributes['lt']) $retval = true;
				else $retval = false;
			}
			
		}
		
		if(isset($this->attributes['equals'])) {
		
			$v = $this->attributes['var'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			$retval = $v == $this->attributes['equals'];
			
		}
		
		/**
		 * Compare first char
		 * @author Nate Ferrero
		 */
		if(isset($this->attributes['firstChar'])) {
		
			$v = $this->attributes['var'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}

			$retval = substr($v, 0, 1) == $this->attributes['firstChar'];
			
		}
		
		if(isset($this->attributes['not'])) {
		
			$v = $this->attributes['var'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			$retval = $v != $this->attributes['not'];
			
		}
		
		if(isset($this->attributes['true'])) {
		
			$v = $this->attributes['true'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			$retval = $v ? true : false;
			
		}
		
		if(isset($this->attributes['false'])) {
		
			$v = $this->attributes['false'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			$retval = $v ? false : true;
			
		}
		
		if(isset($this->attributes['num'])) {
		
			$v = $this->attributes['num'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}

			if($v == false)
				$v = 0;

			if(!is_numeric($v)) {
				$retval = false;
			}

			else if(isset($this->attributes['gt']) && is_numeric($this->attributes['gt'])) {
				if($v > $this->attributes['gt']) $retval = true;
				else $retval = false;
			}
			
			else if(isset($this->attributes['lt']) && is_numeric($this->attributes['lt'])) {
				if($v < $this->attributes['lt']) $retval = true;
				else $retval = false;
			}
			
		}
		
		if(isset($this->attributes['empty'])) {
		
			$v = $this->attributes['empty'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			if(!isset($v) || empty($v)) $retval = true;
			else $retval = false;
			
		}
		
		if(isset($this->attributes['not_empty'])) {
		
			$v = $this->attributes['not_empty'];
			
			if(strpos($v, '{') === false) $v = '{'.$v.'}';

			$vars = $this->extract_vars($v);
			if($vars) foreach($vars as $var) {
				$v = $this->_data()->$var;
			}
			
			if(!empty($v)) $retval = true;
			else $retval = false;
			
		}
		
		if(isset($this->attributes['browser'])) {
		
			$v = $this->attributes['browser'];
			
			if($v == 'IE' && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) $retval = true;
			else $retval = false;

			if($v == 'Smartphone' && isset($_SERVER['HTTP_USER_AGENT'])) {
				$iPod = stripos($_SERVER['HTTP_USER_AGENT'],"iPod") !== FALSE;
				$iPhone = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone") !== FALSE;
				$iPad = stripos($_SERVER['HTTP_USER_AGENT'],"iPad") !== FALSE;
				$Android= stripos($_SERVER['HTTP_USER_AGENT'],"Android") !== FALSE;
				if($iPod || $iPhone || $iPad || $Android) $retval = true;
				else $retval = false;
			}
			
		}
		
		if(isset($this->attributes['time'])) {
		
			$time = $this->attributes['time'];
			$df = isset($this->attributes['df']) ? $this->attributes['df'] : 'Y-m-d H:i:s';
			$of = (float) (isset($this->attributes['offset']) ? $this->attributes['offset'] : 0);
			
			$of = $of * 3600;
			
			if($time == date($df, time() - $of)) $retval = true;
			else $retval = false;
			
		}

		if(isset($this->attributes['time_between'])) {
		
			$time = $this->attributes['time_between'];
			$time = explode(' & ', $time);
			$time1 = strtotime(array_shift($time));
			$time2 = strtotime(array_pop($time));

			$df = isset($this->attributes['df']) ? $this->attributes['df'] : 'Y-m-d H:i:s';
			$of = (float) (isset($this->attributes['offset']) ? $this->attributes['offset'] : 0);
			
			$of = $of * 3600;

			$time3 = strtotime(date($df, time() - $of));

			if($time1 < $time3 && $time2 > $time3) $retval = true;
			else $retval = false;
			
		}
		
		if(isset($retval) && !$retval) foreach($this->children as $child) {
			if(isset($child->fake_element) && $child->fake_element == ':else') $child->show_else = 1;
		}
		
		if(isset($retval)) return $retval;
		
		throw new \Exception("Missing conditions");
		
	}
	
}


class _Else extends Node {
	public $show_else = 0;
	public function __construct($element = false, $parent = false) {
		parent::__construct($element, $parent);
		$this->element = false;
		
		if($this->show_else == 1) {
			$this->show_else = 0;
		}
	}
	
	public function build($pre = true) {
		if($pre)
			$this->_init_scope();
		$output = "";
		
		/**
		 * If requires iteration
		 * else just execute the loop once
		 */
		if($this->is_loop) {
			$this->_data()->reset();
		} else {
			$once = 1;
		}
		
		/**
		 * Start build loop
		 */
		while($this->is_loop ? $this->_data()->iteratable() : $once--) {
		
		/**
		 * Render the code
		 */
		if($this->show_else) {
			if(!empty($this->children)) foreach($this->children as $child) {
				if(is_object($child)) $output .= $child->build();
				else if(is_string($child)) $output .= $this->_string_parse($child);
			}
		}
		
		/**
		 * If a loop increment the pointer
		 */
		if($this->is_loop) $this->_data()->next();
		
		/**
		 * End build loop
		 */
		}
		
		/**
		 * Return the rendered page
		 */
		return $output;
	}
	
}