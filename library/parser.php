<?php

namespace Bundles\LHTML;
use Bundles\Text\Lexer;
use Exception;
use e;

/**
 * LHTML Language Parser
 * @author Nate Ferrero
 */
class Parser {
	
	/**
	 * The entire syntax is defined here as what token the next char implies
	 */
	private static $grammar = array(
		
		# _
		'default' 			=> array(	'<' => 'tag-start' 				),
		
		# <_
		'tag-start' 		=> array(	' ' => '#error',
										'<' => '#error',
										'>' => '#error',
										'"' => '#error',
										"'" => '#error',
										'/' => 'tag-close',
										'*' => 'tag-open-name',
										'!' => 'tag-special'			),
		# <!_
		'tag-special'		=> array(	'!' => '#error',
										'd' => 'tag-doctype',
										'D' => 'tag-doctype',
										'-' => '&tag-comment',
										'*' => '&tag-contents'			),
										
		# <!-_
		'tag-comment'		=> array(	'extended' => array(
											'-->' => 'tag-end-outside'
										)
																		),
		
		# <!d_
		'tag-doctype'		=> array(	'>' => 'tag-end-outside'		),
		
		# </_					
		'tag-close'			=> array(	' ' => '#error',
										'/' => '#error',
										'<' => '#error',
										'>' => '#error',
										'"' => '#error',
										"'" => '#error',
										'*' => '&tag-close-name'		),
		# <a_								
		'tag-open-name' 	=> array(	' ' => 'tag-open-body',
										'<' => '#error',
										'>' => 'tag-end-inside',
										'"' => '#error',
										"'" => '#error',
										'/' => 'tag-end-close'			),
		# </a_							
		'tag-close-name' 	=> array(	' ' => '#error',
										'<' => '#error',
										'>' => 'tag-end-outside',
										'"' => '#error',
										"'" => '#error',
										'/' => '#error'					),
		# <a ... _						
		'tag-open-body'		=> array(	' ' => '#self',
										'<' => '#error',
										'>' => 'tag-end-inside',
										'"' => '#error',
										"'" => '#error',
										'/' => 'tag-end-close',
										'*' => 'tag-attr-name'			),
		# <a ... b_						
		'tag-attr-name'		=> array(	' ' => '#error',
										'<' => '#error',
										'>' => '#error',
										'"' => '#error',
										"'" => '#error',
										'/' => '#error',
										'=' => 'tag-attr-equal'			),
		# <a ... b=_						
		'tag-attr-equal'	=> array(	'"' => 'tag-attr-quote',
										'*' => '#error'					),
		# <a ... b="_						
		'tag-attr-quote'	=> array(	'"' => 'tag-attr-qend',
										'*' => 'tag-attr-value'	),
		# <a ... b="c_						
		'tag-attr-value'	=> array(	'escape' => '\\',
										'"' => 'tag-attr-qend'			),
		# <a ... b="c"_						
		'tag-attr-qend'		=> array(	'*' => '&tag-open-body'			),
		
		# <a ... /_								
		'tag-end-close' 	=> array(	' ' => '#error',
										'<' => '#error',
										'>' => 'tag-end-outside',
										'"' => '#error',
										"'" => '#error',
										'/' => '#error'					),
		# <a ... />_ or </a>_							
		'tag-end-outside'	=> array(	'*' => '&default'				),
		
		# <a ... >_ or <a>_							
		'tag-end-inside'	=> array(	'*' => '&tag-contents',
										'<' => 'tag-start'				),
						
		# <a>_						
		'tag-contents' 		=> array(	'type' => 'conditional',
		
			# <script...>_
			array(	'match-sequence' 	=> array(
						'tag-start' 		=> '<',
						'tag-open-name' 	=> 'script',
						'*' 				=> '*', # Allow attributes
						'tag-end-inside'	=> '>',
					),
					
					'token' 	=> 'cdata-block',
					'end'		=> '</script>'
			),
			
			# <style...>_
			array(	'match-sequence' 	=> array(
						'tag-start' 		=> '<',
						'tag-open-name' 	=> 'style',
						'*' 				=> '*', # Allow attributes
						'tag-end-inside'	=> '>',
					),
					
					'token' 	=> 'cdata-block',
					'end'		=> '</style>'
			),
			
			/* For Later?
					
					'default'		=> array(
						'</script>'	=> '!default',
						'//'		=> 'cdata-line-comment',
						'/*'		=> 'cdata-block-comment',
						'"' 		=> 'cdata-string-double',
						"'" 		=> 'cdata-string-single'
					)
			),*/
					
			# <other...>_
			array(	'*' => '&default'									)
		),
		
		# cdata
		'cdata-block' 		=> array(	'<' => 'tag-start'				)
	);

	public static function parseString($string, $description, &$stack) {
		
		/**
		 * Load lexer
		 */
		$lexer = e::$lexer->grammar(self::$grammar)->description($description)->sourceString($string);
		
		/**
		 * Parse lexer
		 */
		return self::parseLexer($lexer, $description, $stack);
	}

	public static function parseFile($file, $description, &$stack) {

		/**
		 * Load lexer
		 */
		$lexer = e::$lexer->grammar(self::$grammar)->description($description)->sourceFile($file);
		
		/**
		 * Parse lexer
		 */
		return self::parseLexer($lexer, $description, $stack);
	}
		
	public static function parseLexer(&$lexer, $description, &$stack) {

		/**
		 * Ensure we are parsing into a node
		 * @author Nate Ferrero
		 */
		if(!($stack instanceof Node))
			throw new Exception("Stack is not an instance of Node");
		
		/**
		 * Debug if set
		 */
		if(isset($_GET['--lhtml-tokens'])) {
			echo $lexer->debugHTML();
			e\complete();
		}
		
		/**
		 * Load tokens
		 */
		$tokens = $lexer->tokenize();
		
		/**
		 * Track open tags
		 */
		$openTags = array();
		$openTagsDepth = -1;
		
		/**
		 * Add file to scope
		 */
		if(!is_object($stack->_data))
			$stack->_data = new Scope($stack);
		$openFile = $stack->_data->__file__ = $lexer->getFile();
		
		/**
		 * Source code positions
		 */
		$openLine = 0;
		$openCol = 0;
		
		/**
		 * Loop through tokens
		 */
		foreach($tokens as $token) {
			
			/**
			 * Decide what to do based on token
			 */
			switch($token->name) {
				
				/**
				 * Doctype tag works
				 * @todo possible improvement
				 */
				case 'tag-doctype':
					$stack->_nchild($token->value, (object) array(
						'line' => $openLine,
						'col' => $openCol,
						'file' => $openFile,
						'special' => 'doctype'
					));
					break;
				
				/**
				 * Start tag, just to record line and column
				 */
				case 'tag-start':
					$openLine = $token->line;
					$openCol = $token->col;
					break;
				
				/**
				 * Open tag
				 */
				case 'tag-open-name':
					
					/**
					 * Record open tag
					 */
					$openTagsDepth++;
					$openTags[$openTagsDepth] = $token->value;
					
					/**
					 * Add element to the node stack
					 */
					$stack = $stack->_nchild($token->value, (object) array(
						'line' => $openLine,
						'col' => $openCol,
						'file' => $openFile
					));
					break;
					
				/**
				 * Close tag
				 */
				case 'tag-end-close':
				case 'tag-close-name':
					
					/**
					 * Check for long (full) tag
					 */
					$long = $token->name === 'tag-close-name';
					
					/**
					 * Check that this matches the currently open tag
					 */
					$oname = $openTags[$openTagsDepth];
					if($long && $oname !== $token->value)
						throw new Exception("LHTML Parse Error: Found closing tag `&lt;/$token->value&gt;`
						when `&lt;$oname&gt;`still needs to be closed
						on line $token->line at character $token->col in $description");
					
					/**
					 * Close the tag
					 */
					unset($openTags[$openTagsDepth]);
					$openTagsDepth--;
					
					/**
					 * Move up the stack
					 */
					$stack = $stack->_;
					break;
				
				/**
				 * Tag attribute name
				 */
				case 'tag-attr-name':
					$attr = $token->value;
					break;
					
				/**
				 * Tag attribute value
				 */
				case 'tag-attr-value':
					$stack->_attr($attr, $token->value);
					break;
					
				/**
				 * Tag contents
				 */
				case 'default':
				case 'tag-contents':
				case 'cdata-block':
					
					/**
					 * Save the string as a child
					 */
					$stack->_cdata($token->value);
					break;

				/**
				 * Comment passthrough
				 */
				case 'tag-comment':
					
					/**
					 * Save the comment as a child
					 */
					if($token->value[1] !== '#')
						$stack->_cdata("<!-".$token->value."-->");
					break;
					
				default:
					continue;
			}
		}
		
		/**
		 * Return the stack
		 */
		return $stack;
	}
}