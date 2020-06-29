
function QQuery(str, callback, callback_obj, callback_params, use_loader)
{
    var inst = new QModel();
    inst._ty = QApp.DataClass;
	inst.setId(QApp.DataId);
    inst.query(str, callback, callback_obj, callback_params, use_loader);
}

function is_string(variable)
{
	return (typeof(variable) == 'string');
}

function qis_array(variable)
{
	return ((variable !== null) && (typeof(variable) == 'object')) ? ((variable._ty == "QModelArray") || jQuery.isArray(variable)) : false;
}

window.TransformNoAction = 0;
window.TransformCreate = 1;
window.TransformDelete = 2;
/**
 * Append, Merge and Fix are all Update based
 */
window.TransformUpdate = 4;
window.TransformMerge  = 13; // 8 + 4 + 1 (4 = update, 1 = create, 8 = merge specific)
window.TransformAppend = 20; // 16 + 4 (4 = update, 16 = append specific)
window.TransformFix    = 36; // 32 + 4 (4 = update, 32 = fix specific)

QExtendClass("QModel", "QObject", 
{
	/* A few things any MODEL should have
	 * 
	 * $_ty The type of the model
	 * $_sc The storage container (must be stored with a role)
	 * $_id The id of the object (integer/string)
	 * 
	 * $_ts  This is the transform state of the object : No Action, Create / Update / ... + Custom
	 * # // NO LONGER USED $_tsp The transform state of the properties, key-value array at the moment
	 * 
	 * $_ols The old, changed values
	 * 
	 * $_lk True if the object is locked
	 * 
	 * $_qini True if init was called on this object, init can not be called more than once
	 */
	_qIndex : null,
	
	_id : null,

	/**
	the QModelType
	*/
	_qLink : null,

	/**
	the QModelDOMGroup
	NOT USED ATM !!!
	*/
	_qModelDomGroup : null,

	/**
	the QModelDOMInfo
	each model must have a qModelDomInfo
	NOT USED ATM !!!
	*/
	_qModelDomInfo : null,
	
	_ts : null,
	
	_listeners: null,
	
	init : function(type)
	{
		if (type || (!this._ty))
			this._ty = type || this._className || "QModel";
	},

	getId : function ()
	{
		return this._id;
	},
	
	addListener: function(listener, property, ref)
	{
		if (!property)
			property = "*";
		if (!this._listeners)
			this._listeners = {};
		var arr;
		if (!this._listeners[property])
			this._listeners[property] = arr = [];
		else
			arr = this._listeners[property];
		// check if it's not already there
		if (jQuery.inArray(ref || listener, arr) == -1)
			arr.push(listener);
	},
	
	removeListener: function(listener, property, ref)
	{
		if (!property)
			property = "*";
		if (!(this._listeners && this._listeners[property]))
			return false;
		var pos;
		var arr = this._listeners[property];
		if ((pos = jQuery.inArray(ref || listener, arr)) > -1)
		{
			arr.splice(pos, 1);
			return pos;
		}
		else
			return false;
	},
	/**
	 * The init function
	 * 
	 * @param boolean $recursive
	 */	
	initialize: function(recursive)
	{
		if (this._qini)
			return true;
		this._qini = true;
		
		if (recursive)
		{
			for (var k in this)
			{
				var obj = this[k];
				if ((typeof(obj) === "object") && (typeof(obj.isInstanceof) === "function") && obj.isInstanceof("QIModel"))
					obj.initialize(recursive);
			}
		}
	},

	getId: function()
	{
		return this._id;
	},

	setId : function (id)
	{
		this._id = id;
	},

	getTransformState : function()
	{
		return this._ts;
	},

	setTransformState : function(state)
	{
		this._ts = state;
	},
	
	get : function(property)
	{
		return this[property];
	},
	
	wasSet : function()
	{
		// TO DO
	},
	
	set: function(key, value, caller)
	{
		var old_val = this[key];
		var changed = (old_val !== value);
		this[key] = value;
		
		// notify listeners
		if (changed)
			this.notifyListeners(caller, key, value, old_val);
	},
	
	notifyListeners: function(caller, key, value, old_val)
	{
		var i, arr, listener;
		if (!this._listeners)
			return;
		if ((arr = this._listeners["*"]))
		{
			for (i = 0; i < arr.length; i++)
			{
				listener = arr[i];
				if (listener && (listener !== caller))
				{
					if (typeof(listener) == "function")
						listener(this, key, value, old_val);
					else if (jQuery.isArray(listener))
						listener[0].call(listener[1], this, key, value, old_val);
					else
						listener.onModelChanged(this, key, value, old_val);
				}
			}
		}
		if ((arr = this._listeners[key]))
		{
			for (i = 0; i < arr.length; i++)
			{
				listener = arr[i];
				if (listener && (listener !== caller))
				{
					if (typeof(listener) == "function")
						listener(this, key, value, old_val);
					else if (jQuery.isArray(listener))
						listener[0].call(listener[1], this, key, value, old_val);
					else
						listener.onModelChanged(this, key, value, old_val);
				}
			}
		}
	},
	
	getModelType : function()
	{
		//return (typeof(this._ty) == "string") ? (this._ty = QModel.GetTypeByName(this._ty)) : this._ty;
		return this._ty;
	},
	
	isInstanceof : function (class_or_iface)
	{
		if ((class_or_iface == "QIModel") || (class_or_iface == "QModel"))
			return true;
	},

	isQIModel: function()
	{
		return true;
	},
	
	transform : function(callback, callback_obj, callback_params, use_loader)
	{
        var s_cbk = callback;
		var $this = this;
        var clbk = function(resps, callback_params)
        {
            $this.attachIds(resps);
			
			if (callback)
			{
				if (callback_obj)
					callback.apply(callback_obj, [resps, callback_params]);
				else
					callback(resps, callback_params);
			}
        };

		this.ajax("transform", null, true, clbk, null, callback_params, use_loader);
	},
	
	query : function(query_str, callback, callback_obj, callback_params, use_loader)
	{
		this.ajax("query", [query_str], true, callback, callback_obj, callback_params, use_loader);
	},

	isQIModelArray: function()
	{
		return false;
	},



	ajax : function(method, parameters, send_instance, callback, callback_obj, callback_params, use_loader)
	{
		// TO DO: call function qbMakeCtrlRequest(url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, has_file)
		
		var url = window.location.href;
		var req_method = "fast-ajax";
		var meth_class = this._ty;
		var method_name = method;
		var method_params = parameters;
		var request_params = {};
		if (callback)
		{
			request_params.ajax = request_params.ajax ? request_params.ajax : {};
			request_params.ajax.success = callback;
		}
		var ctrl_id = this.getId();
		var ctrl = null;

		return qbMakeCtrlRequest(url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, null);
		
		/*
		var server_cb = {};
		server_cb.__class = "QModelCallback";
		server_cb.Id = this.getId();

		if (send_instance)
			server_cb.Instance = this.exportToJs();

		// we should also specify the container ?!
		server_cb.Method = method;
		server_cb.Parameters = parameters;
		var req_data = {_qCallbacks_ : JSON.stringify([server_cb])};

		var loader = use_loader ? QModel.setAjaxLoader() : null;
		var refThis = this;

		jQuery.ajax({
			type: "POST",
			data: req_data,
			error: function (jqXHR, textStatus, errorThrown)
			{
				//alert("error!");
				var isredirect = ((jqXHR.readyState < 4) && !jqXHR.status && !jqXHR.responseText && !errorThrown) ? true : false;
				if (isredirect)
				{
					if (use_loader)
						QModel.removeAjaxLoader(loader);
					return;
				}

				var txt = "Message</th><td valign='top'>";
				var resp = jqXHR.responseText;
				if (resp.indexOf(txt) > -1)
				{
					var data = resp.substr(resp.indexOf(txt) + txt.length);
					data = data.substr(0, data.indexOf("</td>"));
					if (window["qmodelerrrorfunc"] && (typeof(window["qmodelerrrorfunc"]) == "function"))
					{
						var func = window["qmodelerrrorfunc"];
						func(data);
					}
					else
						alert(data);
				}

				//else
				//	alert("AJAX error [" + textStatus + "]: " + errorThrown + "\n\n" + jqXHR.responseText);

				
				if (use_loader)
					QModel.removeAjaxLoader(loader);
			},
			success: function (data, textStatus, jqXHR)
			{
				// alert("AJAX done[" + textStatus + "]: \n" + data);
				// we should parse / interpret the data here
				//ajxdeb.append("<div style='border-bottom: 1px solid green; padding: 15px;'>" + data + "</div>");
				
				var resps = null;
				if (data)
				{
					try
					{
						resps = JSON.parse(data);
					}
					catch(err)
					{
						if (use_loader)
							QModel.removeAjaxLoader(loader);

						var errtxt = "Server error! Cannot process data!";
						if (window["qmodelerrrorfunc"] && (typeof(window["qmodelerrrorfunc"]) == "function"))
						{
							var func = window["qmodelerrrorfunc"];
							func("<div>" + errtxt + "</div><div class='qb_err' style='display: none;'>" + data + "</div>");
						}
						else
							alert(errtxt);
						return;
					}

					if (resps && resps._items && resps._items[0])
						resps = QModel.ExtractData(resps._items[0]);
				}

				if (callback)
				{
					if (callback_obj)
						callback.apply(callback_obj, [resps, callback_params]);
					else
						callback(resps, callback_params);
				}

				if (use_loader)
					QModel.removeAjaxLoader(loader);
			}
		});
		*/
	},
	
	getFullId : function()
	{
		// data._ppty = [parent._ty, prop];
		if (this._ty == "QModelArray")
		{
			return this._id ? this._id + "$" + this._ppty[0] + "." + this._ppty[1]  : 
				("?" + (this._tmpid ? this._tmpid : (this._tmpid = QModel.GetNextId())) + "$" + this._ppty[0] + "." + this._ppty[1]);
		}
		else
		{
			return this._id ? this._id + "$" + this._ty : 
				("?" + (this._tmpid ? this._tmpid : (this._tmpid = QModel.GetNextId())) + "$" + this._ty);
		}
	},
	
	getTemporaryId : function()
	{
		return this._tmpid ? this._tmpid : (this._tmpid = QModel.GetNextId());
		// we need to reindex as we add them in !!!
	},

	getClone : function (refs)
	{
		if (!refs)
			refs = {};

		var ret = new QModel();
		var fullid = this.getFullId();
		refs[fullid] = ret;

		for (var i in this)
		{
			var value = this[i];
			if ((i == "_id") || (i == "id") || (i == "__bindid") || (i == "_tmpid"))
				continue;

			if (!value || is_scalar(value))
				ret[i] = value;
			else if (value && (typeof(value) == "function"))
				ret[i] = value;
			else if (value)
				ret[i] = value.isQIModel ? (refs[value.getFullId()] ? refs[value.getFullId()] : value.getClone(refs)) : this.getObjectClone(value, refs);
		}
		return ret;
	},

	getObjectClone : function (obj, refs)
	{
		var ret = is_array(obj) ? [] : {};
		for (var i in obj)
		{
			var value = obj[i];
			if (!value || is_scalar(value))
				ret[i] = value;
			else if (value)
				ret[i] = (is_array(value) || !value.isQIModel) ? this.getObjectClone(value) : (refs[value.getFullId()] ? refs[value.getFullId()] : value.getClone());
		}
		return ret;
	},

	/**
	 * Outputs the content of the object into a JSON string. 
	 * The function avoids recursion
	 * 
	 * @param string $str Description
	 * @param QIModel[] $refs
	 * 
	 * @return string
	 */
	exportToJs : function(refs, parent, prop)
	{
		if (!refs)
			refs = {};
		
		var is_array = (this._ty == "QModelArray");
		if (is_array && (!this._ppty))
			this._ppty = [parent._ty + "", prop];

		var f_id = this.getFullId();
		// link it as a reference
		var str = "{\"_ty\":\"" + this._ty + "\"";
		if (this.getId() === null)
			str += ",\"_tmpid\":" + JSON.stringify(this.getTemporaryId());
		else
			str += ",\"_id\":" + JSON.stringify(this._id);

		var ts = this.getTransformState();
		str += ",\"_ts\":" + JSON.stringify(ts); //=== null ? "null" : ts);
		

		// added by Mihai
		if (this._ty == "QModelArray")
		{
			var tsp = this.getTransformState(null, true);
			str += ",\"_tsp\":" + JSON.stringify(tsp); //=== null ? "null" : ts);
		}

		if (refs[f_id] === this)
		{
			str += ",\"_isref\": true}\n";
			return str;
		}
		
		refs[f_id] = this;
		
		var comma = true;
		var in_data = this;
		if (is_array)
		{
			// we also need to add a bit of data
			if (this._rowi)
				str += ",\"_rowi\":" + JSON.stringify(this._rowi);
			str += ",\"_items\":{";
			comma = false;
			in_data = this["_items"];
		}
		
		for (var $k in in_data)
		{
			if ($k.charAt(0) == "_")
				continue;

			var $v = in_data[$k];
			var tyof = typeof($v);
			if (tyof == "function")
				continue;

			str += (comma ? "," : "") + "\n" + JSON.stringify($k) + ":";
			
			if ($v === null)
				str += "null";
			else if (tyof == "string")
				str += JSON.stringify($v);
			else if (tyof == "number")
				str += $v;
			else if (tyof == "boolean")
				str += $v ? "true" : "false";
			else if (tyof == "object")
			{
				if ($v._ty)
					str += $v.exportToJs(refs, this, $k);
				else
					str += JSON.stringify($v);
			}
			
			comma = true;
		}
		
		if (this._ty == "QModelArray")
			str += "}\n";
		
		str += "}\n";
		return str;
	},
	
	getBindValue: function()
	{
		return this._ty + "|" + this.getId();
	},
	
	attachIds: function(obj, refs)
	{
		if (!obj)
			return;

		if (!refs)
			refs = {};

		var is_array = (this._ty === "QModelArray");
		this._id = obj._id;
		if (is_array)
			this._rowi = obj._rowi;

		if (refs[obj.getFullId()])
			return;
		else
			refs[obj.getFullId()] = true;

		var items = is_array ? this._items : this;
		for (var k in items)
		{
			if ((!is_array) && (k.charAt(0) === "_"))
				continue;

			var o = items[k];
			if (o instanceof QModel)
				o.attachIds(obj.get(k), refs);
		}
	}
});

QModel.ExtractData = function (data, $refs, parent, prop)
{
	// handle standard input
	if (data === null)
		return null;
	else if (!(typeof(data) === "object"))
		return data;
	else if (!data._ty)
	{
		var ret = null;
		// is native array and no type was specified
		if (Array.isArray(data))
		{
			var ret = [];
			for (var k = 0; k < data.length; k++)
				ret[k] = QModel.ExtractData(data[k]);
		}
		else
		{
			var ret = {};
			for (var k in data)
				ret[k] = QModel.ExtractData(data[k]);
		}
		return ret;
	}
	
	var first = false;
	var is_array = (data._ty === "QModelArray");
	if (!$refs)
	{
		$refs = {};
		first = true;
	}

	var obj = is_array ? new QModelArray() : (data._ty ? ((typeof(window[data._ty]) === "function") ? new window[data._ty]() : new QModel()) : {});
	var $f_idObj = is_array ? new QModelArray() : new QModel();
	
	if (is_array)
	{
		if (!first)
		{
			if (!parent)
				throw "missing parent for QModelArray";
			obj._ppty = [parent._ty + "", prop];
		}
		obj._rowi = data._rowi ? data._rowi : null;
		obj._qc = (data._qc || (data._qc === 0)) ? data._qc : null;
	}
	if (data._ty)
		obj._ty = data._ty;
	if (data._id)
		obj._id = (data._id !== undefined) ? data._id : null;
	if (data._tmpid)
	{
		obj._tmpid = (data._tmpid !== undefined) ? data._tmpid : null;
		if (obj._tmpid >= QModel.$NextId)
			QModel.$NextId = obj._tmpid + 1;
	}
	if (obj._ts)
		obj._ts = data._ts !== undefined ? data._ts : null;

	// copy data for fid
	for (var k in obj)
	{
		if (typeof(obj[k]) === "function")
			continue;
		$f_idObj[k] = obj[k];
	}
	if (parent || (!is_array))
	{
		//var $f_id = (obj.getFullId && (typeof(obj.getFullId) === "function")) ? obj.getFullId() : null;
		var $f_id = $f_idObj.getFullId();
		//alert(obj._ty + "  ::  " + $f_id);
		if ($f_id)
		{
			if ($refs[$f_id])
			{
				delete obj;
				// we do not return as we want this object "completed"
				obj = $refs[$f_id];
			}
			else 
			{
				$refs[$f_id] = obj;
				// do quick fix for bind obj - should be replaced later
				var $bind_obj_fid = $f_id.substr(0, $f_id.indexOf("$")) + "$QViewBind.obj";
				$refs[$bind_obj_fid] = obj;
			}
		}
	}
	if (data._isref)
		return obj;
	
	var in_data = is_array ? data["_items"] : data;

	for (var p in in_data)
	{
		var pv = in_data[p];
		
		if (pv === null)
		{
			obj.set(p, null);
			continue;
		}
		var ty = typeof(pv);
		if ((ty === "object") && (pv["_ty"]))
			obj.set(p, QModel.ExtractData(pv, $refs, obj, p));
		else
			obj.set(p, pv);
	}
	
	return obj;
};

QModel.$NextId = 1;

QModel.GetNextId = function()
{
	return QModel.$NextId++;
};

QModel.setAjaxLoader = function (appendTo)
{
	if (!appendTo)
		appendTo = jQuery(document.body);

	var loader = jQuery("<div class='qLoader'></div>");
	appendTo[0]._pos = appendTo.css("position");
	appendTo.css({"position" : "relative"});
	appendTo.append(loader);
	loader.bwLoader();
	return loader;
};
QModel.removeAjaxLoader = function (loader)
{
	var appendTo = loader.parent();
	appendTo.css({"position" : appendTo[0]._pos});
	loader.remove();
};

QModel.DumpIt = function($self, $refs, $depth, parent, prop)
{
	if (!$depth)
		$depth = 0;
	var $pad = str_pad("", ($depth * 4), " ");
	$pad_p = $pad + "    ";
	var str = "";

	var first = false;
	if (!$refs)
	{
		$refs = {};
		first = true;
	}
	
	var $is_array = ($self._ty == "QModelArray");
	
	if (($is_array) && (!first))
		$self._ppty = [parent._ty + "", prop];

	var $f_id = (first && $is_array) ? "[]" : $self.getFullId();
	if ($refs[$f_id])
	{
		str += $pad + "[REF " + $self._ty + " #" + $self.getId() + "]\n";
		return str;
	}

	$refs[$f_id] = $self;

	str += $pad + $self._ty  + ($is_array ? "[" + 0 + "]" : "") + " #" + $self.getId() + "\n"; // $self._rowi.length
	var data_in = $is_array ? $self["_items"] : $self;
	for (var $k in data_in)
	{
		if ($k.charAt(0) == "_")
			continue;

		$v = data_in[$k];
		var ty = typeof($v);
		
		if (ty == "function")
			continue;
		
		if ($is_array)
			str += $pad_p + "[" + $k + "] => ";
		else
			str += $pad_p + "." + $k + ": ";

		if ($v === null)
			str += "[NULL]\n";
		else if (ty == "string")
			str += "\"" + $v + "\"\n";
		else if (ty == "number")
			str += $v + "\n";
		else if (ty == "boolean")
			str += ($v ? "true" : "false") + "\n";
		else if (ty == "object")
		{
			str += "\n";
			if ($v._ty || $v._className)
				str += QModel.DumpIt($v, $refs, $depth + 1, $self, $k);
			else
				str += JSON.stringify($v);
		}
	}
	return str;
};

QModel.GetTypeByName = function(type)
{
	return QModel.Types.get(type);
};

/**
* The object is in a unknown sync state
* TO DO in the future
*/
QModel.SyncStateUnknown = 0;
/**
* The object is in sync with the Storage
* This may only happen when a LOCK was acquired !
* TO DO in the future
*/
QModel.SyncStateInSync = 1;

/**
* Basic Transform options
*/
QModel.TransformNoAction = 0;
QModel.TransformCreate = 1;
QModel.TransformDelete = 2;
/**
* Append, Merge and Fix are all Update based
*/
QModel.TransformUpdate = 4;
QModel.TransformMerge  = 13; // 8 + 4 + 1 (4 = update, 1 = create, 8 = merge specific)
QModel.TransformAppend = 20; // 16 + 4 (4 = update, 16 = append specific)
QModel.TransformFix    = 36; // 32 + 4 (4 = update, 32 = fix specific)

/**
* When the object is not processed it's state should be TransformStateNormal
*/
QModel.TransformStateNormal = 1;
/**
* When the object is transformed/processed it's state should be TransformStateProcessing
*/
QModel.TransformStateProcessing = 2;

/**
* A MODEL will usualy have a Storage container as a DB object that duplicates the LIVE Model 
* in order to spped up things. In this case when we ask for a container, we should explain it's role
*
* If the QIModel::getContainers($use_for = StorageContainerForRead) then it will be used for READ operations
*/
QModel.StorageContainerForRead = 1;
/**
* If the QIModel::getContainers($use_for = StorageContainerForWrite) then it will be used for WRITE operations
*/
QModel.StorageContainerForWrite = 2;