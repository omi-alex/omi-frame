
/**
 * Create omi's class definition based on QObject so that we will be able to inherit
 */
QExtendClass("omi", "QObject", {
	
	savedArgs: null,
	/**
	 * This is the constructor for the omi class
	 * If we supply a string it will try to convert it into HTML and create the HTMLElement
	 * 
	 * @param {string|HTMLElement} dom A HTMLElement or string from where we can build a HTMLElement
	 */
	init: function(dom) //, init_template, extract_data)
	{
		// alert("bla bla: " + dom + "\n\n" + dom.innerHTML);
		// x@param {boolean} init_template Defaults to true, asks to init the MVVM on the HTML
		// x@param {boolean} extract_data Defaults to true, asks to extract data from HTML
		if (typeof(dom) === "string")
		{
			var e = document.createElement("div");
			e.innerHTML = dom;
			dom = e.firstElementChild || e.children[0];
		}
		if ((!dom) || (typeof(dom) !== "object") || (dom.nodeType !== 1) || (!dom.tagName))
		{
			console.error("missing or bad dom argument");
			// alert(getCallStack());
			return;
		}
		// reference it to the HTMLElement
		this.dom = dom;
		// reference the HTMLElement to the omi object
		dom._omi = this;
		// we keep a flag that the template was not initialized yet
		this.initiated = false;
		// vars path will link the DATA to VARS_PATHs and the HTMLElements to VARS_PATHs, creating a flexible link between model and view
		this.vars_path = {};
		// here we will reference/store the DATA in our mvvm implementation
		this.data = {};
		
		/*
		// init_template defaults to true
		init_template = (init_template === undefined) ? true : init_template;
		// extract_data defaults to false
		extract_data = (extract_data === undefined) ? true : extract_data;
		// init if requested
		if (init_template || extract_data)
			this.initView(init_template, extract_data);
		*/
	},
	
	trigger: function(event_name, args)
	{
		if (this.onevent)
			this.onevent(event_name, this, args);

		var responders = this.$().parents(".omi-control");
		
		for (var i = 0; i < responders.length; i++)
		{
			var ctrl = $ctrl(responders[i]);
			if (ctrl && ctrl.onevent)
				ctrl.onevent(event_name, this, args);
		}
		// alert((this.eventIdf ? this.eventIdf : "qc-ctrl") + event_name);
		// jQuery(window).trigger(, args);
		var dom_event_name = (this.eventIdf ? this.eventIdf : "qc-ctrl") + ":" + event_name;
		var dom_event = document.createEvent('Event');
		dom_event.initEvent(dom_event_name, true, true);
		dom_event.target = this;
		this.dom.dispatchEvent(dom_event);
	},
	
	$: function(arg)
	{
		return arg ? jQuery(arg, this.dom) : jQuery(this.dom);
	},
	
	hasClass: function(class_name, dom_element)
	{
		dom_element = dom_element || this.dom;
		if (dom_element.classList)
			return dom_element.classList.contains(class_name);
		else
			return new RegExp('(^| )' + class_name + '( |$)', 'gi').test(dom_element.className);
	},
	
	saveArgs: function($method, $args)
	{
		if (!this.savedArgs)
			this.savedArgs = {};
		this.savedArgs[$method] = $args;
	},
	
	ajax: function(method, params, callback, callback_on_error, callback_common_before, callback_common_after)
	{
		// alert('You called me ? ' + this._className + "::" + method );
		var dyn_inst = this.dom.getAttribute('q-dyn-inst');
		
		var call_on_element = this._className + "::" + method;
		if (dyn_inst && (dyn_inst.trim().length > 0))
		{
			// we need to call on the dynamic instance
			call_on_element = this.dom.getAttribute('q-dyn-parent').trim() + "::ApiDynamic_Ctrl";
			// GetDynamic_Ctrl_Country
			// ApiDynamic_Ctrl($name, $args)
			params = [dyn_inst, method, params];
		}
		
		return omi.api(call_on_element, params, callback, callback_on_error, callback_common_before, callback_common_after);
	},
	
	getArgs: function($method, $key)
	{
		if (!$method)
			return this.savedArgs;
		else if (!$key)
			return this.savedArgs ? this.savedArgs[$method] : null;
		else 
		{
			var m_args;
			return (this.savedArgs && (m_args = this.savedArgs[$method])) ? m_args[$key] : null;
		}
	},
	
	__extractValue: function()
	{
		return undefined;
	}
});

// fix constructor property on some browsers
if (!omi.prototype.constructor)
	omi.prototype.constructor = omi;

