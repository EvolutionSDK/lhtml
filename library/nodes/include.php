<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Parser;
use Bundles\LHTML\Node;
use Exception;
use e;

class _Include extends Node {

	public function ready() {
		
		$this->element = false;
		$data = $this->_data();
		$dir = realpath(dirname($data->__file__));
		$v = $this->attributes['file'];	
		unset($this->attributes['file']);
		
		$vars = $this->extract_vars($v);
		if($vars) foreach($vars as $var) {
			$data_response = $data->$var;
			$v = str_replace('{'.$var.'}', $data_response, $v);
		}
		
		$v = "$dir/$v";
		
		if(pathinfo($v, PATHINFO_EXTENSION) !== 'lhtml')
			$v .= '.lhtml';

		/**
		 * Parse the children into this element
		 * @author Nate Ferrero
		 */
		e::$lhtml->file($v)->parse($this);
	}
	
}