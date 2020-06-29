/**
 * We want to be Object-Oriented all the way.
 * This is the basic implementation of an object in JS.
 * It supports some form of inheritance.
 */

(function(){
  var initializing = false, fnTest = /xyz/.test(function(){xyz;}) ? /\b_super\b/ : /.*/;
  
  // The base QObject implementation (does nothing)
  this.QObject = function(){
	  this._className = 'QObject';
  };
  
   // Create a new QObject that inherits from this class
  QObject.extend = function(_classname, prop, class_exists, prefix_patch) {
  	
    var _super = this.prototype;
   
	class_exists = class_exists || false;
    // Instantiate a base class (but only create the instance,
    // don't run the init constructor)
    initializing = true;
	if (class_exists && (!class_exists.prototype))
	{
		console.error("Missing prototype");
		console.error(class_exists);
		// alert(class_exists.prototype);
	}
    var prototype = class_exists && class_exists.prototype ? class_exists.prototype : new this();

	initializing = false;
	
	var __static_elems = null;
    
    // Copy the properties over onto the new prototype
    for (var name in prop)
	{
		var prop_inst = prop[name];
		var inst_type = typeof(prop_inst);
		
		if (name === "__static")
		{
			// implement static 
			if (inst_type !== "object")
				throw ("Your __static definition must be an object.");
			__static_elems = prop_inst;
			continue;
		}
		else
		{
			if (prop_inst && (inst_type === "object"))
			{
				throw ("You can't instantiate an object in the Class definition.\n\nClass: " + _classname + "\nProperty: " + name);
				// prop_inst = null;
			}
			else
			{
				var patch_meth = prototype[name];
				var has_patching = (prefix_patch && patch_meth);
				if (has_patching)
					prototype["patch__" + name] = patch_meth;
				
				var has_meth =  (inst_type === "function") && (typeof(_super[name]) === "function") && fnTest.test(prop_inst);
				// Check if we're overwriting an existing function
				prototype[name] = has_meth ?
				  (function(name, fn, has_patching, patch_meth){
					return function() {
					  var tmp = this._super;
					  var tmp_p = has_patching ? this._patch : null;
					  
					  // Add a new ._super() method that is the same method
					  // but on the super-class
					  // var _super = this.prototype; // see at the top
					  this._super = _super[name];
					  if (has_patching)
						this._patch = patch_meth;
					  
					  // The method only need to be bound temporarily, so we
					  // remove it when we're done executing
					  var ret = fn.apply(this, arguments);       
					  this._super = tmp;
					  if (has_patching)
						this._patch = tmp_p;

					  return ret;
					};
				  })(name, prop_inst, has_patching, patch_meth) : 
				  ((has_patching && (inst_type === "function")) ?
						(function(name, fn, patch_meth){
								  return function() {
									var tmp_p = this._patch;
									this._patch = patch_meth;

									var ret = fn.apply(this, arguments);
 								    this._patch = tmp_p;

									return ret;
								  };
								})(name, prop_inst, patch_meth)
					: prop_inst);
			}
		}
    }

    var newObject;
    if (!class_exists)
	{
		// The dummy class constructor
		function newObject()
		{
			// All construction is actually done in the init method
			if ( !initializing && this.init )
				this.init.apply(this, arguments);
		}
		prototype._className = _classname;
		newObject._className = _classname;
		// Populate our constructed prototype object
		newObject.prototype = prototype;
		// fix constructor property
		newObject.prototype.constructor = newObject;
		// Enforce the constructor to be what we expect
		newObject.constructor = newObject;
		// And make this class extendable
		newObject.extend = arguments.callee;
		newObject.parentClass = this._className;
		
		// @todo : have a general implementation to inherit statics
		// if (this._className === "omi")
		if (this.RenderS)
			newObject.RenderS = this.RenderS;
	}
	else
		newObject = class_exists;
	// attach static elements
	if (__static_elems)
	{
		if (this.__Statics)
		{
			for (var s in this.__Statics)
				newObject[s] = this.__Statics[s];
		}
		for (var sek in __static_elems)
			newObject[sek] = __static_elems[sek];
		newObject.__Statics = __static_elems;
	}
	
	return newObject;
	
	};
})();

function QExtendClass_EnsureIsLoaded(loadClass, call_on_load, call_on_error)
{
	if (window[loadClass] && (typeof(window[loadClass]) === "function"))
	{
		if (call_on_load)
			call_on_load(loadClass, window[loadClass]);
		return "loaded";
	}
	var src = window.$_Q_FRAME_JS_PATHS ? window.$_Q_FRAME_JS_PATHS[loadClass] : null;
	if (!src)
	{
		// try to load parent
		var parent_class = loadClass;
		var clones = [loadClass];
		while ((parent_class = window.$_Q_FRAME_JS_CLASS_PARENTS[parent_class]))
		{
			src = window.$_Q_FRAME_JS_PATHS ? window.$_Q_FRAME_JS_PATHS[parent_class] : null;
			if (src)
			{
				// we have a source
				QExtendClass_EnsureIsLoaded(parent_class, function (loaded_class_name, loaded_class_instance)
				{
					var clone_parent = parent_class;
					for (var i = (clones.length - 1); i >= 0; i--)
					{
						QExtendClass(clones[i], clone_parent, {});
						clone_parent = clones[i];
					}
					call_on_load(loadClass, window[loadClass]);
				}, call_on_error);
				break;
			}
			else
				clones.push(parent_class);
		}
		
		if (!src)
		{
			var err_str = "No info for the location of the class: " + loadClass;
			console.log(err_str);
			if (call_on_error)
				call_on_error(loadClass, err_str);
			return "error";
		}
	}
	
	var page_ready = (document.readyState === "loaded") || (document.readyState === "interactive") || (document.readyState === "complete");
	if (page_ready)
	{
		var js_doms = jQuery("script[type=\"text/javascript\"][src=\"" + src + "\"]");
		if (js_doms.length === 0)
		{
			to_be_loaded = true;

			var head_dom = document.head || document.getElementsByTagName('head')[0];
			var js_script = document.createElement('script');
			js_script.src = src;
			js_script.type = 'text/javascript';
			js_script.addEventListener('load', function(){
				QExtendClass_EnsureIsLoaded(loadClass, call_on_load, call_on_error);
				}, false);
			// console.log('Setup the class to be loaded: ' + src);
			head_dom.appendChild(js_script);
		}
		else if (call_on_load)
		{
			// we need to trigger call_on_load when the class is loaded
			if (!window.QExtendClass_EnsureIsLoaded_listeners)
			{
				window.QExtendClass_EnsureIsLoaded_listeners = {};
				window.QExtendClass_EnsureIsLoaded_listeners[loadClass] = [call_on_load];
			}
			else if (!window.QExtendClass_EnsureIsLoaded_listeners[loadClass])
				window.QExtendClass_EnsureIsLoaded_listeners[loadClass] = [call_on_load];
			else
				window.QExtendClass_EnsureIsLoaded_listeners[loadClass].push(call_on_load);
		}

		var href = window.$_Q_FRAME_CSS_PATHS ? window.$_Q_FRAME_CSS_PATHS[loadClass] : null;

		if (href)
		{
			var css_doms = jQuery("link[type=\"text/css\"][href=\"" + href + "\"]");
			if (css_doms.length === 0)
			{
				var head_dom = document.head || document.getElementsByTagName('head')[0];
				var stylesheet = document.createElement('link');
				stylesheet.href = href;
				stylesheet.rel = 'stylesheet';
				stylesheet.type = 'text/css';
				head_dom.appendChild(stylesheet);
			}
		}
	}
	else
	{
		// we must wait until the page is ready so we can append js && css
		document.addEventListener("readystatechange", function ()
		{
			/*
			uninitialized - Has not started loading yet
			loading - Is loading
			loaded - Has been loaded
			interactive - Has loaded enough and the user can interact with it
			complete - Fully loaded
			*/
			if ((document.readyState === "loaded") || (document.readyState === "interactive") || (document.readyState === "complete"))
				QExtendClass_EnsureIsLoaded(loadClass, call_on_load, call_on_error);
		});
	}
}

function QPatchClass(newClassName, parentClass, properties)
{
	return QExtendClass(newClassName, parentClass, properties, true);
}

/**
 * This is the function that extends objects.
 * 
 * @todo In the future we should generated extends for better performance.
 * 
 * @param {string} newClassName The new class to be created
 * @param {string} parentClass The class that is extended
 * @param {object} properties New properties and methods to be added to the new class
 * 
 * @returns {undefined}
 */
function QExtendClass(newClassName, parentClass, properties, prefix_patch)
{
	var class_obj = window[newClassName];
	
	if (!parentClass)
	{
		parentClass = (class_obj && class_obj.parentClass) ? class_obj.parentClass : "QObject";
	}
	else if (typeof(parentClass) === "object")
	{
		properties = parentClass;
		parentClass = "QObject";
	}

	if (class_obj && (typeof(class_obj) === "function"))
	{
		// we apply new items if exists
		if (properties)
		{
			// alert(class_obj._className);
			// alert(getCallStack());

			/*
			if (!window[class_obj.parentClass])
			{
				console.log(class_obj, newClassName, parentClass);
			}
			*/

			if (!window[class_obj.parentClass])
			{
				console.warn('Missing class prototype: ' + class_obj.parentClass + " | newClassName=" + newClassName + " | parentClass=" +  parentClass, class_obj);
				// console.trace();
				// alert((class_obj.parentClass ? class_obj.parentClass : parentClass) + " | " + newClassName);
			}
			
			window[class_obj.parentClass ? class_obj.parentClass : parentClass].extend(newClassName, properties, class_obj, prefix_patch);
			
			if (properties.__ClassLoaded)
			{
				properties.__ClassLoaded();
				delete properties.__ClassLoaded;
			}
		}
		return class_obj;
	}

	if (window[parentClass] === undefined)
	{
		// alert("We got listed: " + newClassName + " | missing: " + parentClass);
		QExtendClass_EnsureIsLoaded(parentClass, 
			function () {
				// alert("Resolved: " + newClassName);
				QExtendClass(newClassName, parentClass, properties, prefix_patch);
			});
	}
	else
	{
		var static_init = null;
		if (properties.__ClassLoaded)
		{
			static_init = properties.__ClassLoaded;
			delete properties.__ClassLoaded;
		}

		var class_instance =  window[newClassName] = window[parentClass].extend(newClassName, properties, class_obj, prefix_patch);
		var ns_parts = newClassName.split(/\\/);
		if (ns_parts.length > 1)
		{
			// MyCompany,Util,View,DropDown
			var ref = window;
			for (var i = 0; i < (ns_parts.length - 1); i++)
				ref = ref[ns_parts[i]] = ref[ns_parts[i]] ? ref[ns_parts[i]] : {};
			ref[ns_parts[ns_parts.length - 1]] = class_instance;
		}
		window[newClassName.replace(/\\/g, ".")] = class_instance;

		var load_listeners;
		if (window.QExtendClass_EnsureIsLoaded_listeners && (load_listeners = window.QExtendClass_EnsureIsLoaded_listeners[newClassName]))
		{
			for (var i = 0; i < load_listeners.length; i++)
			{
				// alert(load_listeners[i]);
				load_listeners[i](newClassName, class_instance);
			}
			delete window.QExtendClass_EnsureIsLoaded_listeners[newClassName];
		}

		if (static_init)
			static_init();

		return class_instance;
	}
};

