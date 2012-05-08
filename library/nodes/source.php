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
	}
	
}