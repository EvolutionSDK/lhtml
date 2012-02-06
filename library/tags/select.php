<?php

namespace Bundles\LHTML;
use Exception;
use e;

class Node_Select extends Node {
	
	public function init() {
		$this->element = 'select';
	}
	
	public function build() {		
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
		 * Alternate TAG Info
		 */
		$atype = isset($this->attributes['type']) ? $this->attributes['type'] : '';
		$aopts = isset($this->attributes['opts']) ? $this->attributes['opts'] : '';
		unset($this->attributes['type'], $this->attributes['range']);
		if(strlen($atype) < 1) unset($atype, $aopts);

		/**
		 * Parse extended select options
		 */
		$a2opts = array();
		foreach(explode(':', $aopts) as $opt) {
			list($var, $val) = explode('=', $opt);
			$a2opts[$var] = $val;
		} $aopts = $a2opts;
			
		/**
		* If is a real element create the opening tag
		*/
		if($this->element !== '' && $this->element) $output .= "<$this->element".$this->_attributes_parse().'>';
		
		/**
		 * Render the code
		 */
		if(isset($atype)) {
			/**
			 * Run a local sub select tag
			 */
			if(method_exists($this, '_select_'.$atype)) $output .= $this->{'_select_'.$atype}($aopts);

			/**
			 * If it cant find a local select handler inside our tag then call an event
			 */
			else {
				$tmp = e::$events->{'lhtmlNodeSelect'.ucwords(strtolower($atype))}($aopts, $this->_string_parse($this->attributes['selected']));
				$tmp = count($tmp) < 1 ? '' : array_shift($tmp);
				if(!is_string($tmp)) throw new Exception("Select event must return a string.");
				else $output .= $tmp;
			} 
		}

		else if(!empty($this->children)) foreach($this->children as $child) {
			if($child->fake_element !== 'option') continue;
							
			if(isset($this->attributes['selected']) && $this->_string_parse($this->attributes['selected']) == $child->attributes['value'])
				$child->attributes['selected'] = 'selected';
			
			if(is_object($child)) $output .= $child->build();
		}
		
		/**
		 * Close the tag
		 */
		if($this->element !== '' && $this->element) $output .= "</$this->element>";
		
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

	private function _select_month($opts) {
		for($x=1;$x<13;$x++) {
			$date = mktime(0, 0, 0, $x, 1, date("Y"));
			$selected = $this->_string_parse($this->attributes['selected']) == (string) $x ? 'selected="selected"' : '';
			$output .= "<option value=\"$x\" $selected>".date("m - M",$date)."</option>";
		}

		return $output;
	}
	
	private function _select_year($opts) {
		$range = strlen($opts['range']) == 0 ? '0,10' : $opts['range']; 
		$range = explode(',', $range);
		array_walk($range,function(&$v){$v=(float)$v;});
		list($back, $forward) = $range;

		for($x=$back;$x<$forward;$x++) {
			$date = mktime(0, 0, 0, 1, 1, date("Y") + $x);
			$selected = $this->_string_parse($this->attributes['selected']) == (string) date("Y",$date) ? 'selected="selected"' : '';
			$output .= "<option value=\"".date("Y",$date)."\" $selected>".date("Y",$date)."</option>";
		}

		return $output;
	}
	
}