
var QViewBase_proto = {

	parent : null,
	children : null,
	name: null,
	dom : null,
	_maxindx : 0,
	_data: null,

	init : function(dom)
	{
		// constructor logic here
		this._super();
		if (!dom)
			return;
		
		this._data = {};

		this.dom = dom;
		dom._view = this;

		var full_id = this.jQuery().attr("id");
		this.setFullId(full_id);

		// split it 
		var parts = full_id ? full_id.split("-") : null;
		this.name = parts ? parts[parts.length - 1] : full_id;
	},
	
	addControl: function(ctrl, name)
	{
		return this.addChild(ctrl, name);
	},

	initialize : function()
	{
		if (this.children)
		{
			var k;
			if (jQuery.isArray(this.children))
			{
				for (k = 0; k < this.children.length; k++)
					this.children[k].initialize();
			}
			else
			{
				for (k in this.children)
				{
					if (this.children[k].initialize && (typeof(this.children[k].initialize) == "function"))
						this.children[k].initialize();
				}
			}
		}
	},
	
	call: function(method, c_arguments, callback, req_method, url)
	{
		url = url || window.location.pathname;
		req_method = req_method ? req_method : "fast-ajax";
		var ctrl = this.dom;
		var ctrl_id = null;
		var meth_class = this._ty;
		var method_name = method;
		var method_params = c_arguments;
		var request_params = {ajax: {success: callback}};
		
		return qbMakeCtrlRequest(url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, true);
	},
	
	ajax: function(method, c_arguments, callback, req_method, url)
	{
		url = url || window.location.pathname;
		req_method = req_method ? req_method : "ajax";
			
		return this.call(method, c_arguments, callback, req_method, url);
	},

	isWebPage: function()
	{
		return false;
	},
	
	jQuery: function(arg)
	{
		return arg ? jQuery(arg, this.dom) : jQuery(this.dom);
	},

	canUploadFile : function (jq, formjq)
	{
		return true;
	},
	
	addChild: function(child, index)
	{
		if ((index === undefined) && child.name)
			index = child.name;
			
		if (!this.children)
			this.children = {};
		if (index === undefined)
		{
			this.children["" + this._maxindx] = child;
			this._maxindx++;
		}
		else
		{
			this.children["" + index] = child;
			var tst = parseInt(index);
			if ((tst != "NaN") && (tst >= this._maxindx))
				this._maxindx = tst + 1;
		}
		
		child.parent = this;
	},

	getChildAtIndex : function (index)
	{
		return this.children ? this.children[index] : null;
	},
	
	removeChild: function(index)
	{
		if (this.children && this.children[index])
		{
			this.children[index].parent = null;
			delete this.children[index];
			return true;
		}
		else
			return false;
	},

	getRootDom: function()
	{
		if (this.dom)
			return this.dom;
		else if (this.parent)
			return this.parent.getRootDom();
		return null;
	},

	getWebPage : function ()
	{
		return this.isWebPage() ? this : (this.parent ? this.parent.getWebPage() : null);
	},

	bindVal: function(html, obj_instance, prop, radio_group, bind_opts)
	{
		return qBindVal(html, obj_instance, prop, radio_group, bind_opts);
	},
	
	bindAdd: function()
	{
		alert("TO DO : bindAdd");
	},
	
	bindRemove: function()
	{
		alert("TO DO : bindRemove");
	},
	
	updateBinds: function()
	{
		return qUpdateBinds(jQuery(".qvb_bind", this.getDom()));
	},

	refresh : function(node, data)
	{
		var jq;
		if (node)
		{
			jq = (node instanceof jQuery) ? node : jQuery(node);
			
			qSetupBindPaths(jq, data || this);
		}
		else
		{
			var html = this.renderInner();
			jQuery(this.getDom()).empty();
			jq = (html instanceof jQuery) ? html : jQuery(html);
			jQuery(this.getDom()).append(jq);
			
			qSetupBindPaths(jq, data || this);
		}
	},
	
	getDom : function()
	{
		return jQuery("#" + this.getId())[0];
	},
	
	setFullId: function(full_id)
	{
		this._ctrlfid = full_id;
	},
	
	getFullId: function()
	{
		return this._ctrlfid;
	},
	
	render: function(data)
	{
		var str = "<div id='" + this.getId() + "' class='qViewBaseInst'>";
		str += this.renderInner();
		str += "</div>";
		
		// if we are the top element
		if (!this.parent)
		{
			jQuery(this.dom).empty();
			jQuery(this.dom).append(str);
			// qBindCloseOpenedDropdowns();
			var jq = (str instanceof jQuery) ? str : jQuery(str);
			this.doAfterRender();
			// qSetupBindPaths(jq, data || this);
		}
		return str;
	},
			
	doAfterRender : function (refresh)
	{
	
	},

	renderInner : function()
	{
		var str = "";
		if (this.children)
		{
			var k;
			if (jQuery.isArray(this.children))
			{
				for (k = 0; k < this.children.length; k++)
					str += this.children[k].render();
			}
			else
			{
				for (k in this.children)
				{
					if (this.children[k].render && (typeof(this.children[k].render) == "function"))
						str += this.children[k].render();
				}
			}
		}
		return str;
	},

	makeUseOf : function (data, onactionjq)
	{
		
	},

	doOnBind : function (jq, obj_instance, prop, radio_group, bind_opts)
	{
		
	},

	setLastDomItem : function (last_dom_item, data, k, val)
	{
		return last_dom_item;
	},

	canTriggerDropDown : function (dom)
	{
		return !dom._blocked;
	},

	doAfterUploadFormAppend : function (appendintojq, formjq)
	{
		
	},

	resetDropdown : function (ddjq)
	{
		if (!ddjq.hasClass("qvb_bind_dd"))
			return;

		var valuejq = ddjq.find(".value");
		this.resetDropdownValue(valuejq);
		valuejq.attr("qvb_bind_val", null);
		var dropjq = ddjq.find(".drop");
		if (dropjq.is(":visible"))
			dropjq.hide();
		ddjq.removeClass("qvb_dd_expanded");
	},

	getValueToUpdateInDom : function (jq, str_val, obj_instance, prop)
	{
		return str_val;
	},

	resetDropdownValue : function (valuejq)
	{
		var noselectionjq = valuejq.find(".qvb_no_selection_val");
		valuejq.children().hide();
		noselectionjq.show();
	},

	doOnDropdownToogle : function (dom)
	{

	},

	doAfterDropDownToogle : function (dom)
	{
		
	},

	doOnFirstValueClick : function (dom, bind_obj)
	{

	},

	qJqFindCustomPath : function (data, k, use_dom, n_path, sn_path)
	{
		return null;
	},

	setupResults : function (results_html, data)
	{
		qSetupBindPaths(results_html, data);
	}
};

QExtendClass("QViewBase", "QModel", QViewBase_proto);

