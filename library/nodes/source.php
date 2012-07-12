<?php

namespace Bundles\LHTML\Nodes;
use Bundles\LHTML\Node;
use Exception;

/**
 * Empty Node
 */
class source extends Node {
	
	public function ready() {
		$this->element = false;

		if(!empty($this->attributes['var']) && !empty($this->attributes['as']))
			$this->source($this->attributes['as'], $this->_data()->{$this->attributes['var']});
	}
	
}