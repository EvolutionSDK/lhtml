<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;
use e;

class Select extends Node {
	
	public function init() {
		$this->element = 'select';
	}
	
	public function prebuild() {

		/**
		 * @todo Fix local select types
		 * @author Nate Ferrero
		 */

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
		 * Cache the selected attribute to avoid re-parsing
		 * @author Nate Ferrero
		 */
		$this->attributes['selected'] = isset($this->attributes['selected']) ? 
			$this->_string_parse($this->attributes['selected']) : null;
		
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
				$tmp = e::$events->{'lhtmlNodeSelect'.ucwords(strtolower($atype))}($aopts, $selected);
				$tmp = count($tmp) < 1 ? '' : array_shift($tmp);
				if(!is_string($tmp)) throw new Exception("Select event must return a string.");
				else $output .= $tmp;
			} 
		}

	}

	/**
	 * Use before build instead of a custom build
	 * @author Nate Ferrero
	 */
	public function childNodeBeforeBuild(&$child) {
		if(!($child instanceof Node))
			return;
		$value = isset($child->attributes['value']) ? 
			$child->_string_parse($child->attributes['value']) : null;

		/**
		 * Since it's the same "Node" being iterated, we must set AND unset the selected attribute
		 * @author Nate Ferrero
		 */
		if(!is_null($value) && $value == $this->attributes['selected'])
			$child->attributes['selected'] = 'selected';
		else
			unset($child->attributes['selected']);
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