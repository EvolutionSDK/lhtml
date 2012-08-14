<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;
use e;

class Select extends Node {

	private $selected = null;
	
	/**
	 * Switched to use prebuild() over ready() so the scope is initiated
	 * @author Kelly Becker
	 * @since July 13th 2012
	 */
	public function prebuild() {
		$this->element = 'select';
		$this->selected = null;

		/**
		 * Alternate TAG Info
		 */
		$atype = isset($this->attributes['type']) ? $this->attributes['type'] : '';
		$aopts = isset($this->attributes['opts']) ? $this->attributes['opts'] : '';
		unset($this->attributes['type'], $this->attributes['opts']);
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
		 * Cache the selected attribute to avoid re-parsing
		 * @author Nate Ferrero
		 */
		$this->selected = isset($this->attributes['selected']) ? $this->_string_parse($this->attributes['selected']) : null;
		
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
				$method = 'lhtmlNodeSelect'.ucwords(strtolower($atype));
				$tmp = e::$events->$method($aopts, $this->selected);
				$tmp = count($tmp) < 1 ? '' : array_shift($tmp);
				if(!is_string($tmp)) throw new Exception("Select event must return a string.");
				else $output .= $tmp;
			} 

			/**
			 * If there was output append to the children
			 */
			if(isset($output))
				e::$lhtml->string($output, "Select Tag")->parse($this);
		}

	}

	/**
	 * Use before build instead of a custom build
	 * @author Nate Ferrero
	 */
	public function childNodeBeforeBuild(&$child) {

		// If the child is not an instance of Node then don't bother
		if(!($child instanceof Node))
			return;

		// Get the value of the option tag
		$value = isset($child->attributes['value']) ? $child->_string_parse($child->attributes['value']) : null;

		/**
		 * Since it's the same "Node" being iterated, we must set AND unset the selected attribute
		 * @author Nate Ferrero
		 */
		if(!is_null($value) && $value == $this->selected)
			$child->attributes['selected'] = 'selected';
		else unset($child->attributes['selected']);
	}

	private function _select_month($opts) {
		for($x=1;$x<13;$x++) {
			$date = mktime(0, 0, 0, $x, 1, date("Y"));
			$selected = $this->_string_parse($this->selected) == (string) $x ? 'selected="selected"' : '';
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
			$selected = $this->_string_parse($this->selected) == (string) date("Y",$date) ? 'selected="selected"' : '';
			$output .= "<option value=\"".date("Y",$date)."\" $selected>".date("Y",$date)."</option>";
		}

		return $output;
	}
	
}