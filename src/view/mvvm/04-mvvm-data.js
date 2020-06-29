
omi.splice = function($data, $offset, $length, $replacement, sender)
{
	return omi.set($data, $offset, $replacement, sender, ($length === undefined) ? 0 : $length);
};

omi.empty = function($data, sender)
{
	return omi.splice($data, 0, ($data.__len__ !== undefined) ? $data.__len__ : ($data.length || 0), undefined, sender);
};

omi.append = function($data, $append, sender)
{
	alert("omi.append");
	return omi.splice($data, ($data.__len__ !== undefined) ? $data.__len__ : ($data.length || 0), 0, $append, sender);
};

omi.prepend = function($data, $prepend, sender)
{
	return omi.splice($data, 0, 0, $prepend, sender);
};

omi.set = function($data, $key, $val, sender, $splice_length, parent_binds, notify_bag)
{
	var do_notify = false;
	if (notify_bag === undefined)
	{
		do_notify = true;
		notify_bag = [];
	}
	
	var $splice_offset, $splice_replacement;
	var $splice_mode = (($splice_length !== undefined) && ($splice_length !== null));
	if ($splice_mode)
	{
		// we are in splice mode
		$splice_offset = $key;
		$splice_replacement = $val;
	}
	
	var binds = $data.__d;
	if (!binds)
		binds = $data.__d = {};
	
	parent_binds = parent_binds || (parent_binds = $data.__dp);
	// handle $data, $key, $val ok
	// options for us
	var i;
	var k_ty = typeof($key);
	var $new_data = null;
	var $actions;
	var $gap_size = 0;
	var repl_len = 0;
	var $gap_offset;
	if ($splice_mode)
	{
		$actions = {};
		$new_data = {};
		var repl_pos = 0;
		var s_len = $splice_offset + $splice_length;
		if ($splice_replacement)
			repl_len = Array.isArray($splice_replacement) ? $splice_replacement.length : ($splice_replacement.__len__ || 0);
		
		$gap_size = $splice_length - repl_len;
		$gap_offset = $splice_offset + repl_len;
		
		for (i = $splice_offset; i < s_len; i++)
		{
			if (repl_len > repl_pos)
			{
				$new_data[i] = $splice_replacement[repl_pos++];
				$actions[i] = 1; // 1: replace
			}/*
			else
			{
				$new_data[i] = null;
				$actions[i] = 2; // 2: remove
			}*/
		}
		for (; repl_pos < repl_len; repl_pos++)
		{
			$new_data[i] = $splice_replacement[repl_pos];
			$actions[i] = 1; // 3: insert
			i++;
		}	
	}
	else if (k_ty === "string")
	{
		$new_data = {};
		$new_data[$key] = $val;
	}
	else if ((k_ty === "undefined") || ($key === null))
	{
		$new_data = $data;
	}
	else if (k_ty === "object")
	{
		if (Array.isArray($key))
		{
			$new_data = {};
			for (var i = 0; i < $key.length; i++)
				$new_data[$key[i]] = $val[i];
		}
		else
			$new_data = $key;
	}
	
	var action;
	var $data_isArray = Array.isArray($data);
	if ($data_isArray)
		$data.__len__ = $data.length;
	
	if (($gap_size !== 0) && (($data.__len__ !== undefined) || $data_isArray))
	{
		// create or reduce gap to accomodate data in
		// $gap_size = $splice_length - repl_len;
		// on positive gap we need to reduce
		// console.log($gap_size);
		if ($gap_size > 0)
		{
			var op_start_pos = $splice_offset + repl_len;
			// WE MOVE DATA UP
			var copy_offset, val;
			var c_len = $data.__len__;
			for (i = op_start_pos; i < c_len; i++)
			{
				copy_offset = i + $gap_size;
				if (copy_offset < c_len)
				{
					val = $data[i];
					// (i < (op_start_pos + $gap_size)) => cleanup DOM
					omi._handleBinds($data, i, val, typeof(val), sender, binds, parent_binds, notify_bag, 2, copy_offset, (i < (op_start_pos + $gap_size))); // 2 - move bind over
					$data[i] = $data[copy_offset];
				}
				else
				{
					// cleanup duplicate data 
					if (!$data_isArray)
						delete $data[i];
					// we need to cleanup DOM
					if (i < (op_start_pos + $gap_size))
						omi._handleBinds($data, i, val, typeof(val), sender, binds, parent_binds, notify_bag, 2, undefined, true); // 2 - move bind over
				}
			}
			if ($data_isArray)
				$data.splice(op_start_pos, $gap_size);
			
			if ($data.__len__ !== undefined)
				$data.__len__ -= $gap_size;
		}
		// on negative gap we need to create space
		else // ($gap_size < 0)
		{
			var len = ($data.__len__ !== undefined) ? $data.__len__ : $data.length;
			var stop_at = $splice_offset + $splice_length;
			// WE MOVE/PUSH DATA DOWN, as a copy
			// console.log("from: " + (len - 1) + " to " + stop_at);
			for (i = len - 1; i >= stop_at; i--)
			{
				omi._handleBinds($data, i - $gap_size, null, null, sender, binds, parent_binds, notify_bag, 2, i, false); // 2 - move bind over
				// $gap_size is negative
				$data[i - $gap_size] = $data[i];
				// console.log("We copy from: " + i + " to " + (i - $gap_size));
				// $splice_replacement[repl_pos];
				// $actions[i] = 4; // do nothing
			}
			
			$data.__len__ -= $gap_size;
		}
	}
	
	// there will be 4 modes: merge, replace, insert, remove
	for (var k in $new_data)
	{
		if (k.charAt(0) === "_")
		{
			if (!(k.charAt(1) === "_"))
				// silent assigment
				$data[k] = $new_data[k];
			continue;
		}
		
		// var old_val = $data[k];
		var val = $new_data[k];
		var ty = typeof(val);
		if (ty === "function")
			continue;
		
		action = $actions ? ($actions[k] || 0) : 0; // 0: merge
		
		var k_binds = omi._handleBinds($data, k, val, ty, sender, binds, parent_binds, notify_bag, action);
		
		if ($splice_mode)
		{
			// fss ... no binds maman
			alert(k + "\n\n" + k_binds);
		}
		
		if (val === undefined)
		{
			delete $data[k];
		}
		else if (val === null)
		{
			// unlink
			$data[k] = null;
		}
		else if (ty === "object")
		{
			omi.set(val, null, null, sender, undefined, k_binds, notify_bag);
			if ((action === 1) || ($data[k] === undefined))
				$data[k] = val;
		}
		else
		{
			// no binds ... pffffffff
			// scalar
			$data[k] = val;
		}
	}
	
	if (do_notify)
	{
		// console.log($data);
		// console.log($new_data);
		// trigger onset events
		for (var i = 0; i < notify_bag.length; i++)
		{
			var omi_obj = notify_bag[i];
			if (omi_obj.onset)
				omi_obj.onset($key, $data, null, sender);
		}
	}
};

omi._handleBinds = function($data, k, val, val_ty, sender, binds, parent_binds, notify_bag, action, move_offset, remove_dom)
{
	// action = 0 : merge
	// action = 1 : replace
	// action = 2 : move (bind) - use move_offset
	// action = 3 : insert
	// action = 4 : remove (dom)
	
	// (action === 2) => bind was moved from "move_offset" to "k"
	if (action === 2)
	{
		var d_binds = $data.__d;
		if (!d_binds)
			return;
		
		var clean_binds;
		if ((clean_binds = $data.__dp))
		{
			// delete d_binds[k];
			// cleanup these binds 
			for (var b = 0; b < clean_binds.length; b++)
			{
				var p_var_paths = clean_binds[b][1];
				var c_var_paths = p_var_paths[k];
				if (remove_dom && c_var_paths && c_var_paths.__v)
				{
					var doms = c_var_paths.__v;
					if (Array.isArray(doms))
					{
						for (var i = 0; i < doms.length; i++)
							doms[i].parentNode.removeChild(doms[i]);
					}
					else
						doms.parentNode.removeChild(doms);
				}
				var new_var_paths = p_var_paths[move_offset];
				if (new_var_paths && new_var_paths.__v)
				{
					var doms = new_var_paths.__v;
					if (Array.isArray(doms))
					{
						for (var i = 0; i < doms.length; i++)
							doms[i].setAttribute("q-var-i", k);
					}
					else
						doms.setAttribute("q-var-i", k);
				}
				if ((move_offset !== undefined) && p_var_paths[move_offset])
				{
					p_var_paths[k] = p_var_paths[move_offset];
					delete p_var_paths[move_offset];
				}
			}
		}
		
		if ((move_offset !== undefined) && d_binds[move_offset])
		{
			d_binds[k] = d_binds[move_offset];
			delete d_binds[move_offset];
		}
	}
	else if (action === 4)
	{
		return;
	}
	else
	{
		var k_binds = binds[k];
		if (k_binds)
		{
			for (var b = 0; b < k_binds.length; b++)
				k_binds[b][0]._onset(sender, k_binds[b][1], null, $data, k, val, val_ty, notify_bag, action);
		}
		else if (parent_binds)
		{
			// the binds will be setup & used via parent
			for (var g = 0; g < parent_binds.length; g++)
				parent_binds[g][0]._onset(sender, null, parent_binds[g][1], $data, k, val, val_ty, notify_bag, action);
			k_binds = binds[k];
		}
		return k_binds;
	}
};

omi.prototype._onset = function(sender, var_paths, parent_var_paths, $data, k, val, ty, notify_bag)
{
	var is_array = ($data.__len__ !== undefined);
	if ((!var_paths) && parent_var_paths)
	{
		if ((var_paths = parent_var_paths[k]))
		{
			// ok, we have them already
		}
		else if (is_array)
		{
			var prototype_vp = parent_var_paths["[]"];
			var prototype_tpl = prototype_vp.__v;
			if (!prototype_tpl)
			{
				qvar_dump(parent_var_paths);
				console.log(parent_var_paths);
				alert("this is baaad");
			}
			var insert_after = prototype_vp.__m;
			// alert("Insert after [key: " + k + "]: " + insert_after);
			// .nextElementSibling
			var n = insert_after.nextSibling;
			var last_q_var_i = -1;
			var k_numeric = parseInt(k);
			k_numeric = (k_numeric === (+k)) ? k_numeric : false;
			
			while (n)
			{
				if (n.nodeType === 1)
				{
					var q_var_i = n.getAttribute("q-var-i");
					// console.log("looking for " + k + " | " + q_var_i);
					if ((q_var_i !== undefined) && (q_var_i !== null))
					{
						q_var_i = parseInt(q_var_i);
						if ((k_numeric !== false) && (k_numeric < q_var_i))
							break;
						if (q_var_i <= last_q_var_i)
							break; // it's possible that we have another q-each in the same block
						last_q_var_i = q_var_i;
						insert_after = n;
						
						var q_start = n.hasAttribute("q-start");
						if (q_start) // if q-start ... jump until q-end
						{
							n = n.nextElementSibling;
							while (n)
							{
								if ((n.nodeType === 1) && n.hasAttribute("q-end"))
								{
									insert_after = n;
									break;
								}
								n = n.nextElementSibling;
							}
						}
					}
				}
				n = n.nextElementSibling;
			}
			
			var new_doms = [prototype_vp.__m];

			if (!Array.isArray(prototype_tpl))
				prototype_tpl = [prototype_tpl];
			var ins_after = insert_after;
			// alert("<tbody>" + insert_after.parentNode.innerHTML + "</tbody>");
			for (var i = 0; i < prototype_tpl.length; i++)
			{
				var ins_dom = prototype_tpl[i].cloneNode(true);
				// alert("prototype_tpl: " + (prototype_tpl[i].outerHTML || (prototype_tpl[i] + " : " + prototype_tpl[i].textContent)));
				if ((ins_dom.nodeType === 1) && ins_dom.hasAttribute("q-each"))
					ins_dom.setAttribute("q-var-i", k);
				new_doms.push(ins_dom);
				insert_after.parentNode.insertBefore(ins_dom, ins_after.nextSibling);
				// jQuery(insert_after.parentNode).css("background-color", "red");
				// console.log(insert_after.parentNode);
				// console.log(ins_dom);
				ins_after = ins_dom;
			}
			
			// we sould call this for all
			var last_deep_each = {value: null};
			var last_each = {value: null};
			this._processViewRec(new_doms, true, false, null, null, parent_var_paths.__a, this.vars_path, parent_var_paths, true, last_each, last_deep_each);
		}
		/*else
		{
			// not everyting is binded
		}*/
		
		var_paths = parent_var_paths[k];
		if (var_paths)
		{
			$data.__d[k] = [[this, var_paths]];
			$data.__dp = [[this, parent_var_paths]];
		}
		/*
		if (!var_paths) // not everyting is binded
		{
			qvar_dump(parent_var_paths);
			qvar_dump(k);
			alert("we ... here");
			throw "ex";
		}
		*/
		//throw "ex";
	}
	else if (!var_paths)
	{
		alert("not good for us !");
	}
	
	if (notify_bag.indexOf(this) < 0)
		notify_bag.push(this);

	if (var_paths)
	{		
		var doms = var_paths.__v;
		var is_scalar = (ty !== "object");
		if (doms)
		{
			if (Array.isArray(doms))
			{
				for (var i = 0; i < doms.length; i++)
				{
					var dom = doms[i];
					if (is_scalar && val && (dom !== sender))
					{
						if (dom.tagName === "INPUT")
							dom.value = val;
						else
							dom.innerHTML = val;
					}
				}
			}
			else
			{
				if (is_scalar && val && (doms !== sender))
				{
					if (doms.tagName === "INPUT")
						doms.value = val;
					else
						doms.innerHTML = val;
				}
			}
		}
	}
	/*
	else
	{
		// not everyting is binded
		// qvar_dump($data[k]);
		// alert("not binded");
	}*/
};


/**
 * We link the DATA to the VARS PATH
 * 
 * @param {object} $vars
 * @param {object} vars_path
 * @param {boolean} do_set
 */
omi.prototype._bind = function($vars, vars_path, do_set)
{
	if ((vars_path === undefined) || (vars_path === null))
		vars_path = this.vars_path;
	
	for (var k in $vars)
	{
		var vp = vars_path[k];
		if (!vp)
			continue;
		
		if (!$vars.__d)
			$vars.__d = {};
		$vars.__d[k] = [[this, vp]];
	}
	$vars.__dp = [[this, vars_path]];
	
	if (do_set)
		omi.set($vars);
};
