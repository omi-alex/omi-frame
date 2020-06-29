
/**
 * Parses a string into a associative array that would describe an entity
 * ex: Orders.*,Orders.Items.{Date,Product,Quantity},Orders.DeliveryAddresses.*
 * The {} can be used to nest properties relative to the parent
 * 
 * @param {string} $str
 * @returns {object}
 */
omi._parseSelector = function($str)
{
	var $tokens = $str.match(/\,|\.|\{|\}|\:|[\w\\_]+|\*+/g, $str);
	// , -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
	
	var $entity = {};

	var $ctx_ent = $entity;
	var $ctx_prev = null;
	var $ctx_sel = $entity;
	
	// . => go deeper
	// , => reset to last `level` in this context
	// { => start a new context
	// } => exit current context
	var len = $tokens.length;
	var $tok, $frts;
	for (var i = 0; i < len; i++)
	{
		$tok = $tokens[i];
		$frts = $tok.charAt(0);
		switch ($frts)
		{
			case " ":
			case "\t":
			case "\n":
			case "\r":
			case "\v":
				break;
			case ".":
			{
				// there is nothing to do tbh
				break;
			}
			case ",":
			{
				$ctx_sel = $ctx_ent;
				break;
			}
			case "{":
			{
				// creates a new context
				$ctx_prev = [$ctx_ent, $ctx_prev];
				$ctx_ent = $ctx_sel;
				break;
			}
			case "}":
			{
				// closes the current context
				$ctx_ent = $ctx_prev[0];
				$ctx_prev = $ctx_prev[1];
				break;
			}
			default:
			{
				// identifier
				if ($ctx_sel[$tok] === undefined)
					$ctx_sel[$tok] = {};
				$ctx_sel = $ctx_sel[$tok];
				break;
			}
		}
	}

	return $entity;
};

omi.clone = function(data, selector)
{
	if (selector === undefined)
		selector = true; // go as deep as it's needed
	else if (typeof(selector) === "string")
		selector = omi._parseSelector(selector);
	
	alert("@todo: clone data limiting it to the selector if specified");
};


