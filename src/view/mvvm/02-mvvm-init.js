
/**
 * This is the global init
 * @param {function} omi_class
 */
omi.InitGlobal = function(omi_class)
{
	// avoid that nasty overwrite by elements with ids
	window.omi = omi_class;
	window.QApi = omi.QApi;
	if (omi._InitGlobalDone)
		return;
	omi._InitGlobalDone = true;
	if (!omi._ctrls)
		omi._ctrls = {};
	
	var controls = document.getElementsByClassName("omi-control");
	if (controls)
	{
		for (var i = 0; i < controls.length; i++)
			omi.InitDom(controls[i], omi.InitControls);
	}	
};

omi.InitDom = function(dom_element, callback)
{
	if (!omi._ctrls)
		omi._ctrls = {};

	var ctrl = dom_element;
	var class_name = ctrl.getAttribute("q-ctrl");
	if (!class_name)
	{
		class_name = ctrl.getAttribute("qCtrl");
		if (class_name)
		{
			class_name = class_name.match(/\(([^\)]+)\)/);
			if (class_name && class_name[1])
				class_name = class_name[1];
		}
	}
	if (!class_name)
		class_name = "omi";
	if (!omi._ctrls[class_name])
		omi._ctrls[class_name] = [];
	omi._ctrls[class_name].push(ctrl);
	class_name = class_name.replace(/\./g, "\\");
	
	if (window[class_name])
	{
		omi.InitControl(dom_element, class_name, window[class_name]);
	}
	else
		// if (class_name)
		omi.LoadClass(class_name, function ()
		{
			omi.InitControl(dom_element, class_name);
			callback();
		});
};

omi.RenderS = function($method, $arg_1, $arg_2, $arg_3, $arg_4, $arg_5, $arg_6, $arg_7, $arg_8, $arg_9, $arg_10, $arg_11, $arg_12)
{
	var $cc = this._className;
	if (!window[$cc])
	{
		console.error("Missing class: " + $cc);
		return null;
	}
	var $ctrl = new window[$cc]();
	if ($ctrl.oninit)
		$ctrl.oninit();
	
	$method = $method.charAt(0).toUpperCase() + $method.slice(1);
	return $ctrl["render" + $method]($arg_1, $arg_2, $arg_3, $arg_4, $arg_5, $arg_6, $arg_7, $arg_8, $arg_9, $arg_10, $arg_11, $arg_12);
};

omi.HasClass = function(dom, className)
{
	if (dom.classList)
		return dom.classList.contains(className);
	else
		return (new RegExp('(^| )' + className + '( |$)', 'gi')).test(dom.className);
};

/**
 * Takes a HTMLElement and created a MVVM object out of it
 * 
 * @param {HTMLElement} domCtrl
 * @param {string} class_name
 * @param {function} class_obj
 * @returns {omi}
 */
omi.InitControl = function(domCtrl, class_name, class_obj)
{
	if ((!domCtrl) || (!omi.HasClass(domCtrl, "omi-control")))
		return;
	
	if (domCtrl.jsCtrl)
		return;
	
	// alert("InitControl");
	if (!class_name)
		class_name = domCtrl.getAttribute("q-ctrl");
	if (!class_name)
		class_name = "omi";
	if (!class_name)
	{
		console.error("Missing class name on DOM:");
		console.error(domCtrl);
		return null;
	}
	if (!class_obj)
		class_obj = window[class_name];
	if (!class_obj)
	{
		console.error("Missing class object on DOM:");
		console.error(domCtrl);
		return null;
	}

	// alert(class_name);
	var jsCtrl = new class_obj(domCtrl);
	jsCtrl._ty = class_name;
	
	jsCtrl.dom = domCtrl;
	// alert(domCtrl + "\n\n" + domCtrl.innerHTML + "\n\n" + class_name);
	domCtrl.jsCtrl = jsCtrl;

	var q_props = domCtrl.getAttribute("q-props");
	if (q_props)
	{
		// attach properties
		var js_props = null;
		try
		{
			js_props = JSON.parse(q_props);
		}
		catch (ex)
		{
			console.error("Parse error on: " + q_props);
			console.error(ex);
		}
	}

	/*
	var q_data = domCtrl.getAttribute("q-data");
	if (q_data)
	{
		// alert(q_data);
	}
	*/
   
	/*
	// @todo : need to merge data sometimes
	var inner_html = (jsCtrl.render) ? jsCtrl.render() : "";
	var de = document.createElement("div");
	de.innerHTML = inner_html;
	// alert(inner_html);
	// domCtrl.outerHTML = inner_html;
	domCtrl.innerHTML = "";
	var children = de.firstElementChild.childNodes;
	while (children.length)
		domCtrl.appendChild(children[0]);
	
	if (jsCtrl.initView)
	{
		jsCtrl.initView(true, true);
		omi.set(jsCtrl.data);
	}
	*/
	if (jsCtrl.oninit)
		jsCtrl.oninit();
	
	return jsCtrl;
};

/**
 * Inits all the controls indexed by the specifed class name
 * 
 * @param {string} class_name
 * @param {function} class_obj
 */
omi.InitControls = function(class_name, class_obj)
{
	if (!(class_name && class_obj))
	{
		// it will always end up here - it is a callback and it is called without params
		return;
		//console.error("Invalid arguments", class_name, class_obj);
	}

	var controls = omi._ctrls ? omi._ctrls[class_name] : null;
	if (!controls)
		return;
	for (var i = 0; i < controls.length; i++)
	{
		var domCtrl = controls[i];
		omi.InitControl(domCtrl, class_name, class_obj);
	}
};

/**
 * Ensures a certain JS Class is loaded and calls the callbacks when it's ready
 * 
 * @param {type} class_name
 * @param {type} call_on_load
 * @param {type} call_on_error
 */
omi.LoadClass = function(class_name, call_on_load, call_on_error)
{
	var loaded_classes = omi._loaded_classes = omi._loaded_classes || {};
	// console.log("omi.LoadClass : " + class_name + " | " + (window[class_name] ? "exists" : "to load"));
	// console.log(call_on_load);
	if (window[class_name])
	{
		loaded_classes[class_name] = true;
		call_on_load(class_name, window[class_name]);
	}
	else if (!loaded_classes[class_name])
	{
		/*if (!window.$_Q_FRAME_JS_PATHS[class_name])
			console.error("No info for the location of the class: " + class_name);
		else
		{
		*/
		// we make sure the class gets loaded
		QExtendClass_EnsureIsLoaded(class_name, call_on_load, call_on_error);
		loaded_classes[class_name] = true;
		// }
	}
};

/**
 * Makes sure the HTML element was initialized and linked to a MVVM instance
 * @param {HTMLElement} dom
 */
omi.Init = function(dom)
{
	if (!dom._omi)
		omi.InitControl(dom);
	if (!dom._omi.initiated)
		dom._omi.initView();
};
