<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;

class _Switch extends Node {
	
	public function ready() {
		$this->element = false;
	}
	
	public function build() {
		$var = isset($this->attributes['var']) ? $this->_string_parse($this->attributes['var']) : 'default';
		foreach($this->children as $key=>$child) {
			if(!($child instanceof Node)) continue;
			if(substr($child->fake_element, 0, 6) !== 'switch')
				continue;

			$child->element = false;
			$match = explode(',', $child->attributes['vars']);
			if(in_array($var, $match)) return $child->build();
			if(in_array('default', $match)) $default = $child;
		}

		if(isset($default)) return $default->build();
		return;
	}
	
}