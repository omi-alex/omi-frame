

/**
 * Calls _processView to extract data 
 * 
 * @param {boolean} init_template Defaults to false
 * @return {object} Retuns the extracted data
 */
omi.prototype.extractData = function(init_template)
{
	return this._processView((init_template === undefined) ? false : init_template, true);
};

/**
 * Initializes the view
 * 
 * @param {boolean} init_template Defaults to true
 * @param {boolean} extract_data Defaults to false
 * 
 * @returns {undefined}
 */
omi.prototype.initView = function(init_template, extract_data)
{
	this._processView((init_template === undefined) ? true : init_template, (extract_data === undefined) ? false : extract_data);
	this.initiated = true;
};

/**
 * 
 * @returns {undefined|object}
 */
omi.prototype._processView = function(init_template, extract_data)
{
	var data = extract_data ? (this.data || (this.data = {})) : undefined;
	var var_aliases = {};
	var root_context_var_path = context_var_path = this.vars_path;
	this.vars_path.__a = var_aliases;
	
	var last_deep_each = {value: null};
	var last_each = {value: null};
	
	var ret = this._processViewRec([this.dom], init_template, extract_data, data, data, var_aliases, root_context_var_path, context_var_path, false, last_each, last_deep_each);
	
	if (extract_data)
		this._bind(this.data);
	
	console.log(this.data);
	
	return extract_data ? data : ret;
};

/**
 * Steps recursive into the DOM / HTMLElements to either extract data, initialize the template for MVVM or both
 * Extracting data is useful for SEO. It was design to be used only on initialization of the MVVM
 * 
 * @param {HTMLElement[]} doms The HTMLElement s to traverse
 * @param {boolean} do_init Init the template for MVVM
 * @param {boolean} extract_data Extract data from the template
 * @param {object} root_data The root DATA of this instance. omi.prototype.data
 * @param {object} rel_data The DATA relative to the current HTMLElement(s)
 * @param {object} var_aliases As we encounter expressions like "q-each" new variables are declared and referenced to previous ones. Here we keep a track
 * @param {object} root_context_var_path The binding between the MODEL and the VIEW is intermediated by the VAR_PATH(s). This is the reference to the root VAR_PATH
 * @param {object} context_var_path The VAR_PATH relative to the current HTMLElement(s)
 * @param {boolean} context_vp_is_leaf If true it means that we are doing a partial processing. For example when adding an elemen for a "q-each" declaration.
 * 
 * @returns {number} The number of child HTMLElement(s) that have MVVM markings
 */
omi.prototype._processViewRec = function(doms, do_init, extract_data, root_data, rel_data, var_aliases, 
											root_context_var_path, context_var_path, context_vp_is_leaf,
											last_each, last_deep_each)
{
	// handle DOM
	/**
	 * @type {DOMElement}
	 */
	var child;
	var 
		q_var, // string or null, the value of the "q-var" attribute or null if there is none
		q_var_i = null, // string or null, the value of the "q-var-i" attribute or null if there is none
		q_each,  // string or null, the value of the "q-each" attribute or null if there is none
		// q_js_template,  // boolean, if we have "q-js-template" we know it is from a JS template and we force c_extract_data to false
		sub_children, // DOMElement[], the child elements to be processed in the next step
		node_type, // number child.nodeType, the DOM nodeType
		path_data, // object[], data extracted by this._stepInPath
		c_var_aliases, // object, The var aliases to be used witing each for loop

		parent_data, // object, The parent data identified by this._stepInPath as related to the current HTMLElement or it's closest parent that has a MVVM bind
		idf_name, // string, either the name of the DATA property or the KEY in the DATA, when DATA is an ARRAY (may not be a javascript ARRAY)
		idf_is_increment, // boolean, TRUE when we have an ARRAY without associative keys
		
		c_rel_data, // object, The DATA associated with the current HTMLElement 
		q_start = null; // boolean, You can use the "q-start" and "q-end" when your MVVM expression needs to span over more than one HTMLElement
		
	// counting elements under it
	var child_vars = 0;
	// if we had a mark and it's different now, it will be processed, this is to avoid consecutive marks when the template has data
	var had_mark = null;
	// if we have "q-js-template" we know it is from a JS template and we force c_extract_data to false
	var child_comment;
	
	for (var i = 0; i < doms.length; i++)
	{
		// the current HTMLElement
		child = doms[i];
		child_comment = null;
		sub_children = idf_name = parent_data = null;
		node_type = child.nodeType;
		c_var_aliases = var_aliases;
		c_rel_data = rel_data;
		q_start = false;
		var c_extract_data = extract_data; // q_js_template ? false : extract_data;
		
		q_has_add = q_has_append = null;
		
		var child_var_path = context_var_path;
		var parent_var_path = null;
		
		if (node_type === 1) // element
		{
			q_var = child.getAttribute("q-var");
			q_var_i = child.getAttribute("q-var-i");
			q_each = child.getAttribute("q-each");
			q_start = child.hasAttribute("q-start");
			
			if (last_each.value && (child.hasAttribute("q-add") || child.hasAttribute("q-append")))
				child.__last_each = last_each.value;
			
			// in case of a element we use the .childNodes to continue the recursive process
			sub_children = child.childNodes;
		}
		// else if (child.nodeType === 3) //  Text
		else if (node_type === 8) // doc comment
		{
			// we need to keep marks for COLLECTIONS/ARRAYS within the template so we know where to add elements 
			// Example: <!-- OMI-MARK: <div q-each="$orders[] as $order"></div> -->
			// OmiFrame will autogenerate these
			var text = child.textContent;
			child_comment = child;
			if (text.substr(0, 11) === " OMI-MARK: ")
			{
				var html = child.textContent.substr(11);
				var e = document.createElement("div");
				e.innerHTML = html;
				e = e.firstElementChild || e.children[0];
				// we have a mark here
				// now use the attributes
				q_var = e.getAttribute("q-var");
				q_each = e.getAttribute("q-each");
				// if we have "q-js-template" we know it is from a JS template and we force c_extract_data to false
				// q_js_template = e.hasAttribute("q-js-template");
				// if (q_js_template)
				//	c_extract_data = false;
				// we don't extract data on there comments, so force to false
				// we need to reconsider this!
				// c_extract_data = false;
				
				q_var_i = null;
				// let's be explicit
				q_start = false;
				
				last_deep_each.value = child;
			}
		}
		else // set variables to null
			path_data = q_var = q_var_i = q_each = null;
		
		// we have MVVM processing to do
		if (q_var || q_each)
		{
			child.__omi = this;
			// get the count of HTMLElement(s) that have MVVM markings
			var child_vars_obj = {value: 0};
			// we may need to set the "q-var" attribute to watch for changes, for example in case of a "q-each"
			var set_q_var = {value: null};
			
			// now let's analyze the MVVM markings on the current element
			path_data = this._stepInPath(q_var, q_each, q_var_i, do_init, c_extract_data, (node_type === 8), root_data, rel_data, var_aliases, 
					root_context_var_path, context_var_path, set_q_var, child_vars_obj, context_vp_is_leaf);
			
			// increment count of HTMLElement(s) that have MVVM markings
			child_vars += child_vars_obj.value;
			
			// we may need to set the "q-var" attribute to watch for changes, for example in case of a "q-each"
			if ((!q_var) && set_q_var.value && (node_type === 1))
				child.setAttribute("q-var", set_q_var.value);
			
			// reference to the data if we extract (scalar values will be set after this, here we only get object types)
			if (c_extract_data)
				c_rel_data = path_data[0];
			
			// the name of the last propery in the MVVM marking, or the last key if it's an ARRAY
			if (path_data[1] !== undefined)
			{
				idf_name = path_data[1];
			}
			// parent data, we always bind the MVVM with a [property, object] or a [key, array] pair and not on the actual value
			if (path_data[2] !== undefined)
				parent_data = path_data[2];
			// link to the newer aliases information
			if (path_data[3] !== undefined)
				c_var_aliases = path_data[3];
			// if it was an ARRAY with unknown keys we auto increment
			if (path_data[4] !== undefined)
			{
				idf_is_increment = path_data[4];
				// set the determined increment if it's not a comment 
				if (idf_is_increment && (idf_name !== null) && (node_type === 1))
					child.setAttribute("q-var-i", idf_name);
			}
			if (path_data[6] !== undefined)
			{
				parent_var_path = path_data[6];
			}
			if (path_data[5] !== undefined)
			{
				child_var_path = path_data[5];
				if (node_type === 1)
				{
					// binding a child to a var path
					child_var_path.__v = child;
					if (c_extract_data && do_init)
					{
						if (!parent_data.__d)
							parent_data.__d = {};
						parent_data.__d[idf_name] = [[this, child_var_path]];
					}
				}
				
				if (q_each)
				{
					var comment;
					if (node_type === 8) // DOC COMMENT
					{
						comment = child;
						had_mark = q_each;
						child_var_path.__m = child;
					}
					else if ((node_type === 1) && (had_mark !== q_each)) // DOM Element
					{
						// create doc comment with mark, prepend it to this one
						// <!-- OMI-MARK: <div q-each="$orders[] as $order" q-start></div> -->
						comment = document.createComment(" OMI-MARK: <div q-each=\"" + addslashes(q_each) + "\"></div> ");
						child.parentNode.insertBefore(comment, child);
						child_var_path.__m = comment;
						
						had_mark = q_each;
						// increment position
						i++;
						
						child_comment = comment;
					}
					
					// alert("Setup comment on idf: " + q_each + "\n\n" + idf_name + "\n\n" + parent_data);
					
					if (!comment.__vp)
						comment.__vp = [parent_var_path];
					else if (comment.__vp.indexOf(parent_var_path) === -1)
						comment.__vp.push(parent_var_path);
				}
			}
		}
		
		var sub_child_vars = 0;
		var q_each_simblings = null;
		if (sub_children && (sub_children.length > 0))
		{
			// in this case we need to group things
			if (q_start)
			{
				q_each_simblings = [];
				// if q start all the other elements (up to q-end, will follow the same logic)
				var s_children = [];
				if (child_var_path !== context_var_path)
					child_var_path.__v = [child_var_path.__v];
				// adding initial ones
				for (var s = 0; s < sub_children.length; s++)
					s_children.push(sub_children[s]);
				// var start_i = i;
				i++; // jump to the next one
				for (; i < doms.length; i++)
				{
					var simbling = doms[i];
					
					q_each_simblings.push(simbling);
					if (simbling.nodeType === 1)
					{
						var s_child_nodes = simbling.childNodes;
						var s_len = s_child_nodes.length;
						for (var s = 0; s < s_len; s++)
							s_children.push(s_child_nodes[s]);
						if (child_var_path !== context_var_path)
							child_var_path.__v.push(simbling);
						if (simbling.hasAttribute("q-end"))
							break;
					}
				}
				sub_children = s_children;
			}
			
			if ((!c_extract_data) && q_each && ((q_var_i === null) || (q_var_i === undefined) || (q_var_i === "[]")))
			{
				var pn = child.parentNode;
				if (pn)
				{
					// reference them to the MARK
					child.parentNode.removeChild(child);
					if (q_each_simblings)
					{
						for (var s = 0; s < q_each_simblings.length; s++)
							pn.removeChild(q_each_simblings[s]);
					}
				}
			}
			else
				sub_child_vars = this._processViewRec(sub_children, do_init, c_extract_data, root_data, c_rel_data, 
											c_var_aliases, root_context_var_path, child_var_path, false, last_each, last_deep_each);
		}
		
		
		if ((q_var || q_each))
		{
			if (parent_data && c_extract_data && (idf_name !== null) && (sub_child_vars === 0) && (child.tagName === "INPUT"))
			{
				// scalar no data under (should check type in the future)
				// e, parent_data, idf_name
				var val = child.value || child.innerHTML;
				// alert(val);
				if (val !== undefined)
					parent_data[idf_name] = val;
			}
			
			if ((idf_name === undefined) || (idf_name === null))
				idf_name = "[]";
			
			// BINDINGS
			if (child.__v === undefined)
			{
				child.__v = {};
				child.__v[idf_name] = [parent_var_path];
			}
			else if (child.__v[idf_name] === undefined)
				child.__v[idf_name] = [parent_var_path];
			else 
			{
				var indx_of_data = child.__v[idf_name].indexOf(parent_var_path);
				if (indx_of_data < 0)
					child.__v[idf_name].push(parent_var_path);
			}

			if (parent_data && c_extract_data)
			{
				// alert("Binding: " + idf_name);
				if (parent_var_path.__b === undefined)
				{
					parent_var_path.__b = {};
					parent_var_path.__b[idf_name] = [parent_data];
				}
				else if (parent_var_path.__b[idf_name] === undefined)
					parent_var_path.__b[idf_name] = [parent_data];
				else 
				{
					var indx_of_data = parent_var_path.__b[idf_name].indexOf(parent_data);
					if (indx_of_data < 0)
						parent_var_path.__b[idf_name].push(parent_data);
				}
			}
		}
		
		/*
		if ((node_type === 1) && q_each) // element
		{
			// reset q_js_template for the next elements
			q_js_template = false;
		}
		*/
		
		if (child_comment && q_each)
			last_each.value = child_comment;
	}
	
	return child_vars;
};

omi.prototype._stepInPath = function(q_var, q_each, q_var_i, bind_mvvm, extract_data, do_not_increment_idf, data, rel_data, var_aliases, 
												root_context_var_path, context_var_path, set_q_var, child_vars, context_vp_is_leaf)
{
	var return_aliases = null;
	var use_aliases = var_aliases;
	if (q_each)
	{
		// we will need to define the alias/shortcut and link it
		// q-each=".Items[] as $item(OrderItem)"
		// q-each="$item(OrderItem) in .Items[]" // may continue with pipe symbol
		var q_each_parts = q_each.match(/[\w\$\\\.\(\)\[\]]+|\bin\b|\bas\b/g);
		if ((!q_each_parts) || (q_each_parts.length < 3))
		{
			console.error("parse error on q-each: " + q_each);
			return false;
		}
		var switcher = q_each_parts[1];
		var array_var = null;
		var item_var = null;
		if (switcher === "in")
		{
			array_var = q_each_parts[2];
			item_var = q_each_parts[0];
		}
		else if (switcher === "as")
		{
			array_var = q_each_parts[0];
			item_var = q_each_parts[2];
		}	
		else
		{
			console.error("parse error on q-each: " + q_each);
			return false;
		}
		
		// so $item is an alias for: .Items. (in our example)
		var q_item_parts = item_var.match(/\([\w\\\$]+\)|\[[\w\\\$]*\]|[\w\$]+|\./g);
		if (!(q_item_parts && q_item_parts.length))
		{
			console.error("parse error on q-each: " + q_each);
			return false;
		}
		
		var item_var_str = "";
		var item_meta_str = "";
		var ch_0;
		for (var p = 0; p < q_item_parts.length; p++)
		{
			var part = q_item_parts[p];
			if ((part === ".") || (((ch_0 = (part ? part.charAt(0) : null))) && (ch_0 !== "(") && (ch_0 !== "[")))
				item_var_str += part;
			else
				item_meta_str += part;
		}
		return_aliases = {};
		for (var k in var_aliases)
			return_aliases[k] = var_aliases[k];

		if (!q_var)
		{
			// q_var should be set to this
			q_var = array_var + "." + item_meta_str;
			if (set_q_var)
				set_q_var.value = q_var;
		}

		// @todo ... we must setup a full alias not a relative one !!!
		return_aliases[item_var_str] = array_var + "." + item_meta_str;
		use_aliases = return_aliases;
	}
	
	if (q_var)
	{
		var c_data = extract_data ? data : null;
		var ret_var_path = (bind_mvvm && (!context_vp_is_leaf)) ? root_context_var_path : null;
		
		q_var = q_var.trim();
		
		// parse q_var and extract information
		var q_var_parts = q_var.match(/\([\w\\\$]+\)|\[[\w\\\$]*\]|[\w\$]+|\./g);
		if (!(q_var_parts && q_var_parts.length))
		{
			console.error("parse error on q-var: " + q_var);
			return false;
		}
		
		var first_el = q_var_parts[0];
		var first_alias;
		if (first_el && (first_el.charAt(0) !== ".") && (first_alias = use_aliases[first_el]))
		{
			// remove the first element
			q_var_parts.shift();
			/*var alias_parts = first_alias.match(/\([\w\\\$]+\)|\[[\w\\\$]*\]|[\w\$]+|\./g);
			if (!(alias_parts && alias_parts.length))
			{
				console.error("parse error on q-var [alias]: " + q_var);
				return false;
			}
			
			q_var_parts.shift();
			Array.prototype.unshift.apply(q_var_parts, alias_parts);*/
		}
		
		// var last_identifier = null;
		var last_part = null;
		var ret_data = null;
		
		var c_idf = null; // current identifier
		var c_ty = null; // current type
		var c_aty = null; // current array type
		var c_isa = null; // current is array
		var is_rel = false;
		//var c_alias = null;
		var idf_is_increment = false;
		// var idf_is_for_array = false;
		
		// yes we are doing an invalid step to make sure we close things
		for (var p = 0; p < (q_var_parts.length + 1); p++)
		{
			var part = q_var_parts[p];
			var ch_0 = part ? part.charAt(0) : null;
			
			if ((p === 0) && (part === "."))
			{
				// is relative
				if (extract_data)
					c_data = rel_data;
				if (bind_mvvm && (!context_vp_is_leaf))
					ret_var_path = context_var_path;
				// incrementing child vars count
				if (child_vars)
					child_vars.value++;
				is_rel = true;
			}
			else if ((part === ".") || (part === undefined))
			{
				if (part === ".")
				{
					is_rel = true;
					if (last_part === ".")
					{
						// we need to close the last one 
						// we know that there was no identifier
						if (extract_data && c_data)
						{
							if (do_not_increment_idf)
								c_idf = null;
							else
							{
								if (c_data.__len__ === undefined)
									c_data.__len__ = 0;
								c_idf = c_data.__len__++;
							}
						}
						idf_is_increment = true; // idf_is_for_array = 
					}
				}
				
				// we are stepping in
				if (c_idf === null)
				{
					if ((part === undefined) && (q_var_i !== undefined) && (q_var_i !== null))
					{
						c_idf = q_var_i;
						// idf_is_for_array = true;
						idf_is_increment = false;
					}
					else
					{
						if (extract_data && c_data)
						{
							if (do_not_increment_idf)
								c_idf = null;
							else
							{
								if (c_data.__len__ === undefined)
									c_data.__len__ = 0;
								c_idf = c_data.__len__++;
							}
						}
						idf_is_increment = true; // idf_is_for_array = 
					}
				}
				else if (!is_rel)// && (c_alias = use_aliases[c_idf]))
				{
					//
				}
				
				if (extract_data)
				{
					if (c_idf === null)
					{
						// nothing !
						// c_data = null;
					}
					else if (c_data[c_idf] === undefined)
						c_data[c_idf] = {};
				}
				
				if (part === undefined) // we are done
				{
					if (bind_mvvm)
					{
						var ret_var_path_idf = (c_idf !== null) ? c_idf : "[]";
						if (context_vp_is_leaf)
							ret_var_path = context_var_path || root_context_var_path;
						var parent_ret_var_path = ret_var_path;
						ret_var_path = ret_var_path[ret_var_path_idf] || (ret_var_path[ret_var_path_idf] = {});
						ret_var_path.__a = use_aliases;
						/*if (extract_data)
						{
							// tick a bind on c_data[c_idf]
						}*/
					}
					
					ret_data = [(extract_data && c_data) ? c_data[c_idf] : undefined, c_idf, extract_data ? c_data : undefined, use_aliases, idf_is_increment, 
										bind_mvvm ? ret_var_path : undefined, bind_mvvm ? parent_ret_var_path : undefined];
				}
				else
				{
					if (extract_data)
					{
						if (c_idf === null)
						{
							c_data = null;
						}
						else
							c_data = c_data[c_idf]; // step deeper
					}
					if (bind_mvvm && (!context_vp_is_leaf))
					{
						var ret_var_path_idf = (c_idf !== null) ? c_idf : "[]";
						ret_var_path = ret_var_path[ret_var_path_idf] || (ret_var_path[ret_var_path_idf] = {});
						ret_var_path.__a = use_aliases;
						/*
						if (extract_data)
						{
							// do we bind on "non-last" elements ? - we may need to
						}*/
					}
					// reset things
					c_idf = c_ty = c_aty = c_isa = null;
					is_rel = false;
				}
			}
			else if (ch_0 === "(")
				c_ty = part.substr(1, part.length - 2);
			else if (ch_0 === "[")
			{
				c_isa = true;
				c_aty = part.substr(1, part.length - 2);
			}
			else
			{
				c_idf = part;
				idf_is_increment = false;
				// idf_is_for_array = false;
			}
			last_part = part;
		}
		return ret_data;
	}
	else 
		return [rel_data, null, null, use_aliases];
};
