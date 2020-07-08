
window._QBPAGEIDS = 1;

/*  Error message (string)
    Url where error was raised (string)
    Line number where error was raised (number)
    Column number for the line where the error occurred (number) Requires Gecko 31.0
    Error Object (object) Requires Gecko 31.0
 */

window.onerror = function (err_msg, err_url, line, column, object)
{
	// column, object - may be missing
	// TO DO - integrate with the development panel
};

function defined($constant_name)
{
	return ((typeof($constant_name) === 'string') && window[$constant_name]) ? true : false;
}

if (!window.qJsInitBeforeReady)
{
	window.qJsInitBeforeReady = function(){};
}

if (!Array.isArray)
{
	Array.isArray = function(vArg)
	{
		return Object.prototype.toString.call(vArg) === "[object Array]";
	};
}

function reset($array)
{
	if (Array.isArray($array))
		return $array[0];
	else if (typeof($array) === 'object')
	{
		// this._ty = type || "QModelArray";
		if ($array._ty == 'QModelArray')
		{
			// return $array.first();
			if ($array._items && (typeof($array._items) === 'object'))
			{
				for (var $i in $array._items)
					return $array._items[$i];
			}
		}
		for (var $i in $array)
			return $array[$i];
	}
	else 
		return null;
}


// ensure we have Object.keys
if (!Object.keys)
{
	Object.keys = function(obj)
	{
		if (!obj)
			return undefined;
		var ret = [];
		for (var k in obj)
			ret.push(k);
		return ret;
	};
}

function qEmptyObject()
{
	return {};
}

function escapeHtml(text)
{
	var map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};
	
	return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function get_class(obj)
{
	if (obj && (typeof(obj) === "object"))
	{
		if (obj._ty)
			return obj._ty;
		var arr;
		if (obj.constructor && (obj !== this.window) && ((arr = obj.constructor.toString().match(/function\s*(\w+)/))) && (arr.length > 1))
			return arr[1];
	}
	return false;
}

function qParseUrl(url)
{
	url = url || window.location.search.substr(1);
	var parts = url.split('&');
	
	var ret = {};
	for (var i = 0; i < parts.length; ++i)
	{
		var p = parts[i].split('=');
		if (p.length !== 2)
			continue;
		
		var name_path = p[0].split(/[\[\]]+/g);
		
		if (name_path.length > 0)
		{
			var k = 0;
			if (name_path[k] === "")
				k++;
			var len = (name_path[name_path.length - 1] === "") ? (name_path.length - 1) : name_path.length;
			var obj = ret;
			for (; k < len; k++)
			{
				if (k < (len - 1))
					obj = obj[name_path[k]] || (obj[name_path[k]] = {});
				else
					obj[name_path[k]] = decodeURIComponent(p[1].replace(/\+/g, " "));
			}
		}
		else
			ret[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
	}
	
	return ret;
}

function qdeparam(params) {
	
	if (params === undefined)
		params = location.search.substr(1);
	
	var digitTest = /^\d+$/,
		keyBreaker = /([^\[\]]+)|(\[\])/g,
		plus = /\+/g,
		paramTest = /([^?#]*)(#.*)?$/;

if(! params || ! paramTest.test(params) ) {
	return {};
} 


var data = {},
	pairs = params.split('&'),
	current;

for(var i=0; i < pairs.length; i++){
	current = data;
	var pair = pairs[i].split('=');

	// if we find foo=1+1=2
	if(pair.length != 2) { 
		pair = [pair[0], pair.slice(1).join("=")]
	}

	var key = decodeURIComponent(pair[0].replace(plus, " "));
	if (!key)
		continue;
	var value = decodeURIComponent(pair[1].replace(plus, " "));
	var parts = key.match(keyBreaker);

	if (parts)
	{
		for ( var j = 0; j < parts.length - 1; j++ ) {
			var part = parts[j];
			if (!current[part] ) {
				// if what we are pointing to looks like an array
				current[part] = digitTest.test(parts[j+1]) || parts[j+1] == "[]" ? [] : {}
			}
			current = current[part];
		}
	}
	
	lastPart = parts[parts.length - 1];
	if(lastPart == "[]"){
		current.push(value)
	}else{
		current[lastPart] = value;
	}
}
return data;
}

if (typeof String.prototype.trim !== 'function')
{
	String.prototype.trim = function()
	{
		return this.replace(/^\s+|\s+$/g, ''); 
	};
}

function isset(arg)
{
	return ((arg !== undefined) && (arg !== null));
}


if (!window.count)
{
	function count(arg)
	{
		return (arg && (arg.length !== undefined)) ? arg.length : 0;
	}
}

function qSetXmlTemplate(path)
{
	alert("qSetXmlTemplate: " + path);
}

function qSetServerBindVal(js_path, obj_instance, property, bind_opts)
{
	alert("and so I was called");
	return qBindVal(jQuery(js_path), obj_instance, property, null, bind_opts);
}

function $ctrl(ref)
{
	if (typeof(ref) === "string")
	{
		var meth_class = ref;
		var $use_class = getJsClassForClass(meth_class);

		var jsCtrl = $use_class ? new window[$use_class]() : new QWebControl();
		jsCtrl._ty = meth_class;
		// jsCtrl.init(); // this is the constructor and it was already called in 
		
		return jsCtrl;
	}
	else
	{
		var jq_ctrl = jQuery(ref).closest(".QWebControl");
		var ctrl = jq_ctrl.length ? jq_ctrl[0] : null;
		if (!ctrl)
		{
			jq_ctrl = jQuery(document.documentElement);
			ctrl = jq_ctrl.length ? jq_ctrl[0] : null;
		}

		if (ctrl)
		{
			var meth_class = ctrl.attributes.qCtrl.value.match(/\((\w|\\)+\)/g);

			if (meth_class && meth_class[0])
			{
				meth_class = meth_class[0];
				meth_class = meth_class.substr(1, meth_class.length - 2);
			}
			else
			{
				meth_class = ctrl.attributes.qCtrl.value.match(/^(\w|\\)+$/g);
				if (meth_class && meth_class[0])
					meth_class = meth_class[0];
			}

			if (!meth_class)
				return null;

			// alert(meth_class + "" + window[meth_class]);
			if (!ctrl.jsCtrl)
			{
				var $use_class = getJsClassForClass(meth_class);
				ctrl.jsCtrl = $use_class ? new window[$use_class](ctrl) : new QWebControl(ctrl);
				ctrl.jsCtrl._ty = meth_class;
				ctrl.jsCtrl.dom = ctrl;
			}
			return ctrl.jsCtrl;
		}
		else
			return null;
	}
}

function getJsClassForClass(class_name)
{
	if (window[class_name])
		return class_name;
	else if (window.$_Q_FRAME_JS_CLASS_PARENTS && window.$_Q_FRAME_JS_CLASS_PARENTS[class_name])
		return getJsClassForClass(window.$_Q_FRAME_JS_CLASS_PARENTS[class_name]);
	return null;
}

// ============ BINDS =====================================

/* 
*		<div>
*			<div qb="methodName:">
*				<div qb=".param1[]">
*					<span qb=".key1[].key2">Some array variable</span><br/>
*					<span qb=".key1(QCompany|myid)">Some array object</span><br/>
*					<span qb=".key1(QCompany|myid).prop">Some array object - property</span><br/>
*				</div>
*				<div qb=".param2(QCompany)">
*					<span qb=".name">Name of the company</span>
*				</div>
*			</div>
*			<button qb="::methodName(#param1,125,#param2)" class="qbClick" qUrl="">Click Meeee !</button>
*		</div> 
*/

/*
ajax requests
-> update controls (gets the render and updates data)
-> get data (gets data - may update binds bi-directional)
*/

function qbClick(request_params, start_control)
{
	var triggerer = this;
	var method_info = qbParseBind(triggerer, true);
	
	// var closest_form = ;
	var ctrl = qbControl(triggerer);
	
	var method_params = qbGetBinds(ctrl, method_info, triggerer, start_control ? start_control : null);
	
	// qbDebug(method_params, 10);
	// retrun;
	
	// var_dump(method_params);
	qbMakeRequest(ctrl, triggerer, method_info, method_params, request_params);
}

function qbMakeRequest(ctrl, triggerer, method_info, method_params, request_params)
{
	var req_method = (triggerer && triggerer.attributes && triggerer.attributes.qbMethod) ? triggerer.attributes.qbMethod.value.trim().toLowerCase() : null;
	var url = (triggerer && triggerer.attributes && triggerer.attributes.qUrl) ? triggerer.attributes.qUrl.value : window.location.pathname;

	var meth_class = method_info["class"];
	var method_name = method_info.method;
	method_params = method_params || method_info.params;
	var ctrl_id = ctrl.attributes.qCtrl.value.match(/\w+/g)[0];
	
	qbMakeCtrlRequest(url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, method_info.has_file);
}

function $ajax(call_class, call_method, params, callback, req_method, request_params)
{
	req_method = req_method ? req_method : "ajax";
	return $call(call_class, call_method, params, callback, req_method, request_params);
}

function $call(call_class, call_method, params, callback, req_method, request_params)
{
	var url = window.location.pathname;
	req_method = req_method ? req_method : "fast-ajax";
	var meth_class = call_class;
	var method_name = call_method;
	var method_params = params;
	request_params = request_params ? request_params : {};
	if (callback)
	{
		request_params.ajax = request_params.ajax ? request_params.ajax : {};
		request_params.ajax.success = callback;
	}
	var ctrl_id = null;
	var ctrl = null;
	
	return qbMakeCtrlRequest(url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, null);
}


function qbMakeCtrlRequest(url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, has_file, extract_callback)
{
	var qbMethod = req_method;
	url = url || window.location.pathname;
	
	if ((!ctrl_id) && ctrl)
		ctrl_id = ctrl.attributes.qCtrl.value.match(/\w+/g)[0];
	
	var full_id = ctrl_id ? ctrl_id : "";
	
	var p_ctrls = jQuery(ctrl).parents(".QWebControl");
	for (var i = 0; i < p_ctrls.length; i++)
	{
		var p_ctrl = p_ctrls[i];
		if (p_ctrl.attributes && p_ctrl.attributes.qCtrl)
		{
			var p_ctrl_id = p_ctrl.attributes.qCtrl.value.match(/\w+/g);
			p_ctrl_id = p_ctrl_id ? p_ctrl_id[0] : null;
			if (p_ctrl_id)
				full_id = p_ctrl_id + "-" + full_id;
		}
	}
	
	var is_ajax = (qbMethod === "ajax") || (qbMethod === "fast-ajax");
	var use_form_data = has_file && is_ajax;
	var has_form_data = use_form_data && window.FormData;
	var post = ((qbMethod === "post") || (qbMethod === "ajax") || (qbMethod === "fast-ajax")) ? {} : null;
	var form_data = null;
	
	if (post && has_form_data && use_form_data)
	{
		post = form_data = new FormData();
	}
	
	if (!meth_class)
	{
		meth_class = ctrl.attributes.qCtrl.value.match(/\(\w+\)/g)[0];
		meth_class = meth_class.substr(1, meth_class.length - 2);
	}
	
	// var ctrl_id = ctrl.attributes.qCtrl.value.match(/\w+/g)[0];
	
	var ind_pos = "0";
	var indx = "_qb" + ind_pos;
	
	var str = null;
	if (form_data)
		form_data.append(indx + "[_q_]", (meth_class + "." + method_name + (full_id ? ("." + full_id) : "")));
	else if (post)
		post[indx + "[_q_]"] = (meth_class + "." + method_name + (full_id ? ("." + full_id) : ""));
	else
		str = indx + "[_q_]=" + encodeURIComponent(meth_class + "." + method_name + (full_id ? ("." + full_id) : ""));
	
	var skip_keys = {_maxpos: true,
						/* _rowi: true, */
						_className: true,
						_qIndex: true,
						_qLink: true,
						_qModelDomGroup: true,
						_qModelDomInfo: true,
						_listeners: true,
						__dom: true,
						_dom: true,
						__domk: true,
						_ty : true,
						_ts : true,
						_tsp : true,
						_id : true};

	for (var k in method_params)
	{
		if (skip_keys[k])
			continue;
		var check_has_file = {has: false};
		var ret_str = qbGetEncodedElement(method_params, k, indx, post, form_data, check_has_file);
		if (!post)
			str += ret_str;
		
		has_file = check_has_file.has;
		// update use_form_data and has_form_data
		use_form_data = has_file && is_ajax;
		has_form_data = use_form_data && window.FormData;
	}

	var user_func = request_params ? 
						((typeof(request_params) === "function") ? request_params : ((request_params.ajax && request_params.ajax.success) ? request_params.ajax.success : null)) : null;
						
	var user_ajax = request_params && request_params.ajax ? request_params.ajax : null;
	var ajax_params = user_ajax || {};


	var $callOnErr = false;
	if (ajax_params === user_ajax)
	{
		user_ajax.onErr = user_ajax.error;
		$callOnErr = true;
	}

			
	var success_func = function(data, textStatus, jqXHR)
					{
						jQuery(window).trigger("Q_Event_AjaxSuccess", [ajax_params, data, textStatus, jqXHR]);

						// TO DO ... improve this to avoid issues !!!!
						if (data && (data.match(/Fatal error\:/g) || (data.match(/xdebug\-error xe\-fatal\-error/g))))
						{
							q_devmode_handle_ajax(jqXHR, textStatus, data);
							
							if (user_ajax && user_ajax.error && (typeof(user_ajax.error) === 'function'))
								user_ajax.error(jqXHR, textStatus, data);
								
							printDump(data);
							return;
						}
						var response = null;
						try
						{
							response = JSON.parse(data);
						}
						catch(err)
						{
							if (user_ajax && user_ajax.error && (typeof(user_ajax.error) === 'function'))
								user_ajax.error(jqXHR, textStatus, err + "<br/>\n\n" + data);
							
							
							q_devmode_handle_ajax(jqXHR, textStatus, err);
							
							printDump(err + "<br/>\n\n" + data);
							return;

							/*
							jQuery(window).trigger("Q_Event_AjaxError", [ajax_params, jqXHR, textStatus, response.EXCEPTION]);
							$callOnErr ? user_ajax.onErr(jqXHR, textStatus, response.EXCEPTION) : user_ajax.error(jqXHR, textStatus, response.EXCEPTION);
							return;
							*/
						}
						
						q_devmode_handle_ajax(jqXHR, textStatus, false, response);
						
						if (response.EXCEPTION)
						{
							jQuery(window).trigger("Q_Event_AjaxError", [ajax_params, jqXHR, textStatus, response.EXCEPTION]);
							($callOnErr && user_ajax.onErr) ? 
								user_ajax.onErr(jqXHR, textStatus, response.EXCEPTION) : 
								user_ajax.error(jqXHR, textStatus, response.EXCEPTION);
							return;
						}

						var resps = (data === "null") ? null : (extract_callback ? extract_callback(response) : QModel.ExtractData(response));

						var incl_css = null;
						var incl_js = null;

						if (resps && (typeof(resps) === "object") && ((resps.___css) || (resps.___js)))
						{
							incl_css = resps.___css;
							incl_js = resps.___js;
							delete resps.___css;
							delete resps.___js;
						}

						var dbg_panel = null;
						if (resps && resps["__debugData__"])
						{
							dbg_panel = jQuery("#qDebugPanelCtrlLastAjaxId");
							var last_ajax = resps["__debugData__"];
							sessionStorage.lastAjax = last_ajax;
							sessionStorage.lastAjaxResp = resps;
							dbg_panel.html(last_ajax);
							delete(resps["__debugData__"]);
							dbg_panel.find(".responsePanel").append("<pre>" + escapeHtml(qbDebug(resps, 2, true)) + "</pre>");
						}
						if (resps && resps["__hiddenOutput__"])
						{
							qvar_dump(resps["__hiddenOutput__"]);
							qvar_dump("<h4>Server Unhandled Output</h4>");
						}
						if (resps && resps["__debugStack__"])
						{
							// printDump(resps["__debugStack__"]);
							// console.log(resps["__debugStack__"]);
							if (jQuery.isArray(resps["__debugStack__"]))
							{
								for (var ik = 0; ik < resps["__debugStack__"].length; ik++)
									printDump(resps["__debugStack__"][ik]);
							}
							else if (typeof(resps["__debugStack__"]) === "object")
							{
								for (var ik in resps["__debugStack__"])
									printDump(resps["__debugStack__"][ik]);
							}
							else
							{
								try
								{
									printDump(resps["__debugStack__"]);
								}
								catch ($pd_ex) {}
							}
						}
						// alert(user_ajax.success);
						// qbManageRenders(resps);

						var req_resp = (resps && resps[ind_pos]) ? resps[ind_pos] : null;
						if (dbg_panel)
						{
							dbg_panel.find(".responsePanel").append("<hr/>");
							dbg_panel.find(".responsePanel").append("<pre>" + escapeHtml(qbDebug(req_resp, 5, true)) + "</pre>");
							sessionStorage.lastAjaxReqResp = req_resp;
						}

						// do the extracts in a smart
						// resps = QModel.ExtractData(resps._items[0]);
						// alert(req_resp[1]._ty);

						// alert(request_params.ajax.success);
						// alert(user_func);
						var res_to_include = 0;
						if (incl_css || incl_js)
						{
							res_to_include = qIncludeResourcesIfNotIncluded(incl_css, incl_js, user_func, req_resp);
						}

						// if we need to load resources, the user function will be called after the resources are loaded
						if ((!res_to_include) && user_func)
						{
							// alert(user_func);
							user_func(req_resp);
						}
						
						if (response && response["__MultiResponseId"])
							// alert(response["__MultiResponseId"]);
							jQuery(window).trigger("MultiResponse", [response["__MultiResponseId"]]);
					};

	if (post)
	{
		if (is_ajax && ((!use_form_data) || (use_form_data && has_form_data)))
		{
			ajax_params.url = url;
			ajax_params.data = post;

			// alert("wtf: " + user_ajax.data.append);
			if (user_ajax && user_ajax.data)
			{
				// alert(user_ajax.data.constructor.toString());
				// ajax_params.data = user_ajax.data;
				for (var k in user_ajax.data)
				{
					var user_ajax_v = user_ajax.data[k];
					if (typeof(user_ajax_v) === "function")
						continue;
					if (form_data)
						ajax_params.data.append(k, user_ajax_v);
					else
						ajax_params.data[k] = user_ajax_v;
				}
			}
			
			if (qbMethod === "fast-ajax")
			{
				if (form_data)
					ajax_params.data.append("__qFastAjax__", "true");
				else
					ajax_params.data.__qFastAjax__ = true;
			}
			if (extract_callback)
			{
				if (form_data)
					ajax_params.data.append("__customExtract__", "true");
				else
					ajax_params.data.__customExtract__ = true;
			}
			
			ajax_params.type = user_ajax && user_ajax.type ? user_ajax.type : "POST";
			
			if (form_data)
			{
				ajax_params.cache = false;
				ajax_params.contentType = false;
				ajax_params.processData = false;
				ajax_params.type = 'POST';
			}
			
			//  type (default: 'GET')
			
			// error : Type: Function( jqXHR jqXHR, String textStatus, String errorThrown )
			// success : Type: Function( PlainObject data, String textStatus, jqXHR jqXHR )

			//user_ajax ? user_ajax && user_ajax.error
			ajax_params.error = (user_ajax && user_ajax.error) ? 
								function (jqXHR, textStatus, errorThrown)
								{
									var decoded_error_json = q_devmode_handle_ajax(jqXHR, textStatus, errorThrown);
									
									var is_redirect = ((jqXHR.readyState === 0) && (jqXHR.status === 0) && (jqXHR.statusText === "error"));
									if (is_redirect)
										return;
									
									if (decoded_error_json && decoded_error_json.EXCEPTION)
										errorThrown = decoded_error_json.EXCEPTION;

									jQuery(window).trigger("Q_Event_AjaxError", [ajax_params, jqXHR, textStatus, errorThrown]);
									$callOnErr ? user_ajax.onErr(jqXHR, textStatus, errorThrown) : user_ajax.error(jqXHR, textStatus, errorThrown);
								} : 
								// Function( jqXHR jqXHR, String textStatus, String errorThrown )
								function(jqXHR, textStatus, errorThrown)
								{
									var decoded_error_json = q_devmode_handle_ajax(jqXHR, textStatus, errorThrown);
									
									if (decoded_error_json && decoded_error_json.EXCEPTION)
										errorThrown = decoded_error_json.EXCEPTION;
									
									var is_redirect = ((jqXHR.readyState === 0) && (jqXHR.status === 0) && (jqXHR.statusText === "error"));
									if (is_redirect)
										return;
									
									jQuery(window).trigger("Q_Event_AjaxError", [ajax_params, jqXHR, textStatus, errorThrown]);
									// alert("error");
									printDump("" + textStatus + "<br/>\n" + errorThrown + "<br/>\n" + jqXHR.responseText);
									// TO DO 
									throw textStatus + "\n\n" + errorThrown;
								};

			ajax_params.success = success_func; // Type: Function( PlainObject data, String textStatus, jqXHR jqXHR )
							
			jQuery(window).trigger("Q_Event_BeforeAjax", [ajax_params]);	   

			if (ajax_params.beforeSend)
			{
				var $ajax_params_beforeSend = ajax_params.beforeSend;
				ajax_params.beforeSend = function ($jqXHR, $settings)
					{
						q_debug_attach_to_ajax.apply(this, [$jqXHR, $settings]);
						$ajax_params_beforeSend.apply(this, [$jqXHR, $settings]);
					};
			}
			else
				ajax_params.beforeSend = q_debug_attach_to_ajax;

			jQuery.ajax(ajax_params);
		}
		else
		{
			// if is_ajax we need to fix on an iframe
			if (qbMethod === "fast-ajax")
			{
				post.__qFastAjax__ = "true";
				post.__qAjax__ = "true";
				post.__qIframe__ = "true";
			}
			else if (qbMethod === "ajax")
			{
				post.__qAjax__ = "true";
				post.__qIframe__ = "true";
			}

			var post_form = qbMakePostForm(url, post, "multipart/form-data", is_ajax);
			document.body.appendChild(post_form);
			if (is_ajax)
			{
				var ifrm_name = "qbifrm" + window._QBPAGEIDS++;
				var iframe = jQuery("<iframe onload='' name='" + ifrm_name + "' style='display: none;' />");
				document.body.appendChild(iframe[0]);
				post_form.setAttribute("target", ifrm_name);
				
				var main_doc = jQuery(window.document);
				var jq_post_form = jQuery(post_form);
				
				ajax_params.url = url;
				ajax_params.data = post;
				
				jQuery(window).trigger("Q_Event_BeforeAjax", [ajax_params]);

				jQuery(iframe[0]).load(function()
					{
						// finally !!!
						
						// alert(iframe.contents().find("body textarea")[0].innerHTML);
						// success_func = function(data, textStatus, jqXHR)
						var jq_textarea = iframe.contents().find("body textarea");
						var resp_data = (jq_textarea.length > 0) ? jq_textarea[0].innerHTML : null;
						
						var placeholders_inps = jq_post_form.find("input[qbplaceholder-id]");
						for (var i = 0; i < placeholders_inps.length; i++)
						{
							var plc_input = placeholders_inps[i];
							var plc_id = jQuery(placeholders_inps[i]).attr("qbplaceholder-id");
							var pla_hld = main_doc.find("#" + plc_id);
							pla_hld[0].parentNode.insertBefore(plc_input, pla_hld[0]);
							
							plc_input.removeAttribute("qbplaceholder-id");
							plc_input.removeAttribute("__name");
							
							pla_hld.remove();
						}
						
						if (resp_data)
							success_func(resp_data, "200 OK", null);
						else
						{
							jQuery(window).trigger("Q_Event_AjaxError", [ajax_params, jqXHR, textStatus, errorThrown]);
							// alert("error");
							printDump("error via iframe, TO DO");
							// TO DO 
							throw "error via iframe, TO DO";
						}
					});
			}
			post_form.submit();
		}
	}
	else
	{
		window.location.href = url + ((url.indexOf("?") >= 0) ? "&" : "?") + str;
	}
}

function qbManageRenders(resps)
{
	if (!(resps && resps._qGq_))
		return;
	
	for (var ctrl_id in resps._qGq_)
	{
		// find control by id and apply new layout
		// TO DO !!!
		var ctrl_html = resps._qGq_[ctrl_id];
	}
	
	delete resps._qGq_;
}

function qbMakePostForm(url, params, enctype, for_ajax)
{
    var form = document.createElement('form');
    form.setAttribute("action", url);
    form.setAttribute("method", 'POST');
	form.setAttribute("style", 'display: none;');
	
	// enctype="multipart/form-data"
	if (enctype)
		form.setAttribute("enctype", enctype);

    for (var i in params)
	{
		var val = params[i];
		
		if (val instanceof HTMLInputElement)
		{
			if (for_ajax)
			{
				var placeholder = document.createElement("span");
				var placeholder_id = "qbplaceholder" + window._QBPAGEIDS++;
				placeholder.setAttribute("id", placeholder_id);
				val.parentNode.insertBefore(placeholder, val);
				
				val.setAttribute("qbplaceholder-id", placeholder_id);
				if (val.name)
					val.setAttribute("__name", val.name);
			}
			form.appendChild(val);
			val.name = i;
		}
		else
		{
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = i;
			input.value = val;
			form.appendChild(input);
		}
    }

    return form;
}

function qbGetEncodedElement(method_params, k, indx, post, form_data, check_has_file)
{
	return qbEncodeElement(method_params[k], indx + "[" + encodeURI(k) + "]", null, post, form_data, check_has_file);
}

function qbEncodeElement($v, indx, key, post, form_data, check_has_file)
{
	if (($v === null) || ($v === undefined))
		return form_data ? form_data.append(indx, "null") : (post ? (post[indx] = "null")
					: ("&" + indx + "=null"));
		// return "null";// "&" + indx + "=null";
	
	var ty = typeof($v);
	
	if (ty === "string")
	{
		//alert(key + ":" + $v);
		return form_data ? form_data.append(indx, ((key === "_ty") ? "" : "_") + /*encodeURIComponent*/($v))
					: (post ? post[indx] = ((key === "_ty") ? "" : "_") + /*encodeURIComponent*/($v)
					: "&" + indx + "=" + ((key === "_ty") ? "" : "_") + /*encodeURIComponent*/($v));
	}
	else if (ty === "number")
		return form_data ? form_data.append(indx, /*encodeURIComponent*/($v)) : 
				(post ? post[indx] = /*encodeURIComponent*/($v)
					: "&" + indx + "=" + /*encodeURIComponent*/($v));
	else if (ty === "boolean")
		return form_data ? form_data.append(indx, ($v ?  "true" :  "false")) : 
				(post ? post[indx] = ($v ?  "true" :  "false")
					: "&" + indx + ($v ? "=true" : "=false"));
	else if (ty === "object")
	{
		if (window.Node ? ($v instanceof Node) : ((typeof($v) === "object") && $v.nodeType))
			return form_data ? form_data.append(indx, "null") :
					(post ? (post[indx] = "null")
					: ("&" + indx + "=null"));
		
		var ret = "";
		
		if (Array.isArray($v))
		{
			for (var k = 0; k < $v.length; k++)
			{
				var $v_itm = ($v instanceof QModelArray) ? $v.get(k) : $v[k];
				if (typeof($v_itm) === "function")
					continue;
				var k_index = indx + "[" + encodeURI(k) + "]";
				var ret_v = qbEncodeElement($v_itm, k_index, k, post, form_data, check_has_file);
				if (!post)
					ret += ret_v;
			}
		}
		else
		{
			var $is_array = ($v instanceof QModelArray) && ($v._ty === "QModelArray");// || ($v._ty === "Array");
			var $data = $is_array ? $v._items : $v;
			
			//var dbg_first = $is_array && $v._items ? obj_first($v._items) : null;
			//alert($v._ty + " [k=" + key + "] : " + $is_array + ":" + (dbg_first ? dbg_first : " n/a"));
			
			if ($is_array)
			{
				var extra_keys = ["_id", "_ty", "_ts", "_tsp", "_tmpid", "_rowi"];
				for (var ki = 0; ki < extra_keys.length; ki++)
				{
					var k = extra_keys[ki];
					var $v_itm = $v[k];
					if ($v_itm === undefined)
						continue;
					var k_index = indx + "[" + encodeURI(k) + "]";
					var ret_v = qbEncodeElement($v_itm, k_index, k, post, form_data, check_has_file);
					if (!post)
						ret += ret_v;
				}
			}
			
			var skip_keys = {_maxpos: true,
							/* _rowi: true, */
							_className: true,
							_qIndex: true,
							_qLink: true,
							_qModelDomGroup: true,
							_qModelDomInfo: true,
							_listeners: true,
							__dom: true,
							_dom: true,
							__domk: true};
						
			// var $is_scalar = false;
						
			if (($data._ty === "QFile") && $data._dom)
			{
				if (form_data)
					form_data.append(indx + "[_dom]", $data._dom.files[0]);
				else if (post)
					post[indx + "[_dom]"] = $data._dom;
				if (check_has_file)
					check_has_file.has = true;
			}
			
			for (var k in $data)
			{
				if (skip_keys[k])
					continue;
				var $v_itm = $data[k];
				var $v_itm_ty = typeof($v_itm);
				if (($v_itm_ty === "function") || (window.Node ? ($v_itm instanceof Node) : ($v_itm_ty === "object") && $v_itm.nodeType))
					continue;

				var k_index = indx + "[" + encodeURI(k) + "]";

				var ret_v = qbEncodeElement($v_itm, k_index, k, post, form_data, check_has_file);
				if (!post)
					ret += ret_v;
			}
		}
		return ret;
	}
	else if (ty === "function")
	{
		
	}
	else 
	{
		throw "Unexpected type: " + ty;
	}
}

function qbEncodeRequestScalar(value)
{
	return value;
}

window.qb_parse_reg_exp = new RegExp("" + 
				"'(?:(?:\\\\.|[^\\\\\'])*)\'|" + // string single
				"\"(?:(?:\\\\.|[^\\\\\"])*)\"|" + // string double
				"[-+]?(?:[0-9]*\.?[0-9]+|[0-9]+)|" + // number (not full validation)
				// Keywords: AND/OR/...
				// "\bAS\b|\bSELECT\b|\bWHERE\b|\bORDER\\s+BY\b|\bHAVING\b|\bGROUP\\s+BY\b|\bAND\b|\bOR\b|\bBETWEEN\b|\bASC\b|\bDESC\b|\bLIMIT\b|".
					// "\bNULL\b|\bTRUE\b|\bFALSE\b|\bIS_A\b|\bIS\b|\bLIKE\b|\bCASE\b|\bBINARY\b|\bNOT\b|\bDIV\b|\bISNULL\b|\bSQL_CALC_FOUND_ROWS\b|".
				
				// bind method
				"\\:\\:\\w+\\s*|" + 
				
				// bind call params
				"\\#[\\w\\$]+|" + 
				
				"\\w+\\s*\\(|" + 
				// Identifiers/entities
				"[\\w\\$]+|" + // identifiers (can not start with a digit)

				"\\(\\s*\\)|" + // empty brackets
				"\\[\\s*\\]|" + // empty brackets
				"\\:\\:|" + 
				"\\|{2}|" +
				"\\&{2}|" + 
				"\\>{2}|" + 
				"\\<{2}|" + 
				"\\!\\=|" +
				"\\<\\>|" +
				"\\>\\=|" +
				"\\<\\=|" +
				"[\\!-\\/\\:-\\@\\[\\]-\\^\\`\\{-\\~]{1}" + // from ASCII table we have ranges there also

			"", "gi");

function initBinds(dom)
{
	return jQuery(".qbClick", (dom || document)).on("click", qbClick);
}

function qbParseBind(element, extract)
{
	var qb_attr = (element.attributes && element.attributes.qb) ? element.attributes.qb.value : null;
	if (!qb_attr)
		return null;
	
	// split and parse 
	var tokens = qb_attr.match(window.qb_parse_reg_exp);
	
	if (extract)
	{
		var ret = {};

		for (var i = 0; i < tokens.length; i++)
		{
			var e = tokens[i];
			if ((e.substr(0, 2) === "::") && (e.length > 2))
			{
				if (!ret.method)
					ret.method = e.substr(2);
				else
					ret["class"] = e.substr(2);
			}
			else if (((e.substr(0, 1) === "#") || (e.substr(0, 1) === "\$")) && (e.length > 1))
			{
				if (!ret.params)
					ret.params = {};
				ret.params[e.substr(1)] = e.substr(1);
			}
		}

		return ret;
	}
	else
		return tokens;
}

function qbControl(element)
{
	var ctrl = element ? (jQuery(element).closest(".QWebControl")) : null;
	return (ctrl && ctrl.length) ? ctrl[0] : document.body.parentNode;
}

function qbDebug(data, depth, indepth, dump_depth, var_undef)
{
	var dump = "";
	if (!dump_depth)
		dump_depth = 0;
	
	var pad = "";
	var pd = dump_depth;
	while (pd-- > 0)
		pad += "\t";
	if (typeof(data) === "object")
	{
		// return Object.prototype.toString.call(vArg) === "[object Array]";
		var proto = Object.prototype.toString.call(data);
		var is_arr = (proto === "[object Array]");
		dump = (is_arr ? "[array(" + data.length + ")]\n" : proto + "\n");
		if (depth > 0)
		{
			if (Array.isArray(data))
			{
				for (var i = 0; i < data.length; i++)
				{
					var item = data[i];
					var d_type = (item === null) ? "null" : typeof(item);
					if (d_type === "function")
						continue;
					if (d_type === "object")
						d_type = (item && item._ty) ? item._ty : d_type;
					dump += pad + i + " (" + d_type + "): " + qbDebug(item, depth - 1, (indepth || true), dump_depth + 1, 1) + "\n";
				}
			}
			else
			{
				var skip_keys = {_maxpos: true,
							/* _rowi: true, */
							_className: true,
							_qIndex: true,
							_qLink: true,
							_qModelDomGroup: true,
							_qModelDomInfo: true,
							__dom: true,
							_dom: true,
							__domk: true,
							_listeners: true};

				if (window.Node ? (data instanceof Node) : ((typeof(data) === "object") && data.nodeType))
				{
					// skip dom elements atm
				}
				else
				{
					for (var i in data)
					{
						if ((skip_keys[i]) || ((indepth === "no-meta") && (i.substr(0, 2) === "__")))
							continue;

						try
						{
							var item = data[i];
							var d_type = (item === null) ? "null" : typeof(item);
							if (d_type === "function")
								continue;
							if (d_type === "object")
								d_type = (item && item._ty) ? item._ty : d_type;
							dump += pad + i + " (" + d_type + "): " + qbDebug(item, depth - 1, (indepth || true), dump_depth + 1, 1) + "\n";
						}
						catch (ex)
						{

						}
					}
				}
			}
		}
	}
	else if (typeof(data) === "function")
	{
		dump += "[function]";
	}
	else
	{
		dump += data;
	}
		
	if (var_undef === undefined)
	{
		// show it
		printDump(dump, true);
		// alert(dump);	
	}
	
	return dump;
}

function printDump(dump)
{
	var panel = jQuery("#qb-dump-panel");
	if (!panel.length)
	{
		panel = jQuery(document.body).append("<div id='qb-dump-panel' class='qHideOnClickAway ' style='z-index: 100000; max-width: 90%; position: fixed; top: 0px; right: 0px; display: block; overflow: scroll; height: 100%; background-color: white; border: 1px solid gray; padding: 10px; margin: 0px;' >" + 
					"<a onclick='jQuery(this).parent().remove();' style='position: absolute; right: 5px; top: 3px; cursor: pointer; color: red;'>x</a>" + 
					"<a onclick='jQuery(this).next().empty(); jQuery(this).parent().hide();' style='position: absolute; right: 35px; top: 3px; cursor: pointer; color: blue;'>clear</a>" +
					"</div>");
		panel = jQuery("#qb-dump-panel");
	}
	
	// we need to avoid beeing in the same event buble
	setTimeout(function ()
	{
		panel.prepend("<pre>" + dump + "</pre><hr/>");
		panel.show();
	}, 50);
}

function qbBinds(doms, params, return_first_if_ontop)
{
	doms = doms ? (doms instanceof jQuery ? doms : jQuery(doms)) : jQuery(document.body.parentNode);
	var params_map = null;
	if (params && (params.length > 0))
	{
		params_map = {};
		for (var i = 0; i < params.length; i++)
		{
			var param = (params[i].charAt(0) !== '$') ? params[i] : params[i].substr(1);
			params_map[param] = param;
		}
	}
	
	return qbGetBinds(null, null, null, null, null, doms, params_map, return_first_if_ontop);
}

function qbGet(doms)
{
	return qbBinds(doms, null, true);
}

function qbGetBinds(control, method_info, triggerer, start_ctrl, filter, new_doms, new_params, return_first_if_ontop)
{
	var dom = null;
	var start_filters = null;
	
	var top_dom = null;
	
	if (new_doms)
	{
		dom = new_doms;
		by_method = false;
		meth_tag = "";
		start_filters = new_params;
		top_dom = new_doms;
	}
	else
	{
		start_ctrl = start_ctrl ? start_ctrl : null;

		var by_method = method_info && (typeof(method_info) === "object") && method_info.method;
		var meth_tag = by_method ? method_info.method + ":" : ((typeof(method_info) === "string") ? method_info : "");

		if (!start_ctrl)
		{
			var search_pattern = (meth_tag === "") ? "[qb]" : '[qb=\"' + meth_tag + '\"]';
			// var search_pattern = '[qb=\"' + meth_tag + '\"]';
			var quick_dom = jQuery(triggerer).closest(search_pattern);

			dom = (quick_dom && quick_dom.length) ? quick_dom :
					(control ? ((control instanceof jQuery) ? control : jQuery(control)) : jQuery(document.body.parentNode));
		}
		else
		{
			dom = (start_ctrl instanceof jQuery) ? start_ctrl : jQuery(start_ctrl);
		}
		
		top_dom = dom;
	}

	if (!dom.length)
		return null;

	// check if we should return first
	return_first_if_ontop = ((return_first_if_ontop ? true : false) && (new_doms && (dom.length === 1) && dom.attr("qb"))) ? true : false;
	
	var binds = dom.filter("[qb]");
	//alert(binds.length);
	if (filter)
		binds = binds.add(dom.find("[qb]").filter(filter));
	else
		binds = binds.add(dom.find("[qb]"));
	
	var data_raw = [];
	
	var undefined_index = 0;
	
	for (var i = 0; i < binds.length; i++)
	{
		var bind = binds[i];
		
		var qb = bind.attributes.qb.value;
		var qb_indx = 0;
		var attr_name = "qb";
		
		while (qb)
		{
			qb = qb.trim();

			if (qb.substr(0, 1) === ".")
			{
				// this is relative, loop parents
				var qb_full = qb;
				
				if (top_dom && top_dom.is(bind))
				{
					// we need to fix it a bit
					qb_full = qb = ("__var__" + (undefined_index++) + qb.substr(1));
					bind.setAttribute("qb", qb);
					// we are at the top, do not continue
					data_raw.push([qb_full, bind, qb, attr_name]);
				}
				else
				{
					var e = bind.parentNode;

					while (e)
					{
						var is_top_dom = top_dom && top_dom.is(e);

						if (e.attributes && e.attributes.qb && e.attributes.qb.value)
						{
							var e_v = e.attributes.qb.value.trim();
							if (e_v.substr(0,1) === ".")
							{
								// add parts
								qb_full = e_v + qb_full;
								if (is_top_dom)
								{
									data_raw.push([qb_full, bind, qb, attr_name]);
									break;
								}
							}
							else if (e_v.substr(0, 1) === "\$")
							{
								// add parts & done
								qb_full = meth_tag + e_v.substr(1) + qb_full;
								data_raw.push([qb_full, bind, qb, attr_name]);
								// $handle_qb(qb_full, bind, qb);
								break;
							}
							else if (e_v.substr(0, meth_tag.length) === meth_tag)
							{
								// done
								qb_full = e_v + qb_full;
								data_raw.push([qb_full, bind, qb, attr_name]);
								// $handle_qb(qb_full, bind, qb);
								break;
							}
							else
							{
								// not for this method
								break;
							}
						}
						
						if (is_top_dom)
						{
							qb_full = e_v + qb_full;
							data_raw.push([qb_full, bind, qb, attr_name]);
							break;
						}

						e = e.parentNode;
					}
				}
			}
			else if (qb.substr(0, 1) === "\$")
			{
				// ok manage this
				var qb_full = meth_tag + qb.substr(1);
				data_raw.push([qb_full, bind, qb, attr_name]);
				// $handle_qb(qb_full, bind, qb);
			}
			else if (meth_tag && (qb.substr(0, meth_tag.length) === meth_tag))
			{
				// ok manage this
				data_raw.push([qb, bind, qb, attr_name]);
				// $handle_qb(qb, bind);
			}
			// else ignore, not for this method
			else
				break;
			
			attr_name = "qb" + (qb_indx++);
			// see if we have a next one (or one more after)
			qb = bind.attributes[attr_name];
			if (!qb)
			{
				attr_name = "qb" + (qb_indx++);
				qb = bind.attributes[attr_name];
			}
			qb = qb ? qb.value : null;
		}
	}
	
	var data = {};
	
	for (var i = 0; i < data_raw.length; i++)
	{
		var parent = data;
		
		var qb = data_raw[i][0];
		var qb_dom = data_raw[i][1];
		var qb_rel = data_raw[i][2];
		var attr_name = data_raw[i][3];
		
		// alert(qb + "\n" + qb_rel);
		
		// make sure the bind is for this method
		var comp_with = meth_tag;
		if (comp_with && (comp_with !== qb.substr(0, comp_with.length)))
			continue;
		
		var tokens = qb.match(/\w+\:|\.?[\w\$]+|\([^\)]+\)|\[\s*\+?\s*\]|\./g);
		var rel_tokens = qb_rel.match(/\w+\:|\.?[\w\$]+|\([^\)]+\)|\[\s*\+?\s*\]|\./g);
		
		// skip the first element
		var k_offset = (meth_tag === "") ? 0 : 1;
		
		// var return_first_element = false;

		for (var k = k_offset; k < tokens.length; k++)
		{
			var identifier = (tokens[k].substr(0, 1) === ".") ? tokens[k].substr(1) : tokens[k];
			
			var type = null;
			var id = null;
			var rowid = null;
			
			// the type info is in the next token unless we are in an array type
			if (identifier === "")
			{
				// we need to save it somehow because we end up incrementing it all the time !!!!!!
				// keep in mind that those are stacking ... so if it's relative use last, if it's not increment
				// alert(tokens + "\n" + rel_tokens + "\nToken: " + k + " / " + tokens.length + " \nRel tokens: " + rel_tokens.length);
				// identifier = parent.length;
				
				if (parent.length === undefined)
				{
					// this means we have started from "."
					if (tokens[k] === ".")
					{
						// how should we handle multiples ?!
						// this means we have started from "."
						identifier = "";
						// return_first_element = (parent === data);
					}
					else
					{
						throw ("Parse error");
					}
				}
				else if ((tokens.length - rel_tokens.length) <= k)
					identifier = parent.length;
				else
					identifier = parent.length - 1;
			}
			
			var id_type_tok = (k < (tokens.length - 1)) ? tokens[k + 1] : null;
			var id_type_tok_ch_0 = id_type_tok ? id_type_tok.substr(0, 1) : null;
			if (id_type_tok_ch_0 && ((id_type_tok_ch_0 === "(") || (id_type_tok_ch_0 === "[")))
			{
				if (id_type_tok_ch_0 === "[")
				{
					type = (id_type_tok.substr(1, 1) === "+") ? "QModelArray" : "Array";
				}
				else
				{
					// we have type info
					var nfo = id_type_tok.substr(1, id_type_tok.length - 2);
					var nfo_parts = nfo.split("|");
					type = nfo_parts[0] || null;
					id = nfo_parts[1] || null;
					rowid = nfo_parts[2] || null;
				}
				
				k++;
			}
			
			var is_last_element = (k === (tokens.length - 1));
			// default type to string if last or array if it is not the last one
			if (!type)
				type = is_last_element ? "string" : "Array";
			
			var is_scalar = (type !== "object") && (type.substr(0, 1) === type.substr(0, 1).toLowerCase());
			
			var is_model_arr = ((parent._ty === "QModelArray") || (parent._ty === "Array"));
			var parent_data = is_model_arr ? ((parent._items !== null) ? parent._items : (parent._items = {})) : parent;
			
			//alert(parent_data);
			
			// skip if we don't need this param for this call
			if (method_info && (parent === data) && (!method_info.params[identifier]))
				break;
			
			var qbdom_ret = is_last_element ? qbDomToValue(qb_dom, type, id, is_scalar, method_info) : undefined;
			
			// qbDebug("Type: " + type + " / " + (is_last_element ? "last" : "not last"));
			
			// var dom_value = (qb_dom.attributes && qb_dom.attributes.qbValue) qbValue
			// if this element it is missing
			if ((!is_scalar) && (!parent_data[identifier]))
			{
				var i_val = qbdom_ret;
				if ((i_val === undefined) || (typeof(i_val) !== "object"))
				{
					var new_type = (type === "Array") ? "QModelArray" : type;
					var i_val = (window[new_type] && (typeof(window[new_type]) === "function")) ? new window[new_type]() : new QModel(new_type);
					if ((id !== undefined) && (id !== null))
						i_val._id = id;
					i_val._ty = type;
					qbdom_ret = i_val;
				}
				
				if (is_model_arr)
					parent.set(identifier, i_val);
				else
					parent_data[identifier] = i_val;
			}
			
			if (is_last_element)
			{
				var ext_attr = qb_dom.attributes ? qb_dom.attributes[attr_name + "-x"] : null;
				if (rowid && is_model_arr)
				{
					parent._rowi = parent._rowi || {};
					parent._rowi[identifier] = rowid;
				}
				if (ext_attr && ext_attr.value)
				{
					var ext_data = JSON.parse(ext_attr.value);
					if (ext_data && ext_data._tsp)
					{
						parent._tsp = parent._tsp || {};
						parent._tsp[identifier] = ext_data._tsp;
						// qbDebug("Setting _tsp: " + identifier + " := " + ext_data._tsp);
					}
					if (ext_data && ext_data._ts && qbdom_ret && (typeof(qbdom_ret) === "object"))
						qbdom_ret._ts = ext_data._ts;
				}
			}

			if (is_last_element || is_scalar)
			{
				if (qbdom_ret !== undefined)
				{
					if (is_model_arr)
						parent.set(identifier, qbdom_ret);
					else
						parent_data[identifier] = qbdom_ret;
					
					// make sure we are not at the very top of the arguments
					if (parent !== data)
					{
						if (!parent.__domk)
							parent.__domk = {};
						parent.__domk[identifier] = qb_dom;
						if ((!is_scalar) && qbdom_ret && (typeof(qbdom_ret) === "object"))
							qbdom_ret.__dom = qb_dom;
					}
				}
				break;
			}
			
			if (!is_last_element)
				// increment parent
				parent = parent_data[identifier];
			
			if ((parent === undefined) || ((parent === null) && (!is_last_element)))
			{
				alert("Undefined parent: " + qb + "[ " + tokens[k] + " ] " + (is_last_element ? "is_last_element" : "not last") + "\n\n" + tokens);
				break;
			}
		}
	}
	
	if ((method_info && method_info.params) || start_filters)
	{
		// order params 
		var _data = {};
		var _skiped = {};
		
		var meth_params = (method_info && method_info.params) ? method_info.params : start_filters;
		
		for (var p in meth_params)
		{
			// we skip at the first undefined to allow server side defaults to work
			if (data[p] === undefined)
				_skiped[p] = true;
			else
			{
				_data[p] = data[p];
				// fill skiped, if any, with null
				for (var sk in _skiped)
					_data[sk] = null;
				// reset skiped
				_skiped = {};
			}
		}

		if (return_first_if_ontop)
		{
			if (_data[0])
				return _data[0];
			else
			{
				for (var k in _data)
					return _data[k];
			}
		}
		// qbDebug(_data, 10);
		// if (return_first_element && count(_data) === 1))
		//	return _data[0];
		return _data;
	}
	else
	{
		if (return_first_if_ontop)
		{
			if (data[0])
				return data[0];
			else
			{
				for (var k in data)
					return data[k];
			}
		}
		
		return data;
	}
}

function qbDomToValue(dom, type, id, is_scalar, method_info)
{
	if (type === "null")
		return null;
	
	var tag = dom.tagName.toLowerCase();
	if (tag === "input")
		tag = "input/" + (dom.attributes.type ? dom.attributes.type.value.toLowerCase() : "text");
	
	var val = null;
	var from_std_input = false;
	
	if (typeof(dom.qbGet) === "function")
		return dom.qbGet();
	else if (dom.attributes && dom.attributes.qbValue && (dom.attributes.qbValue.value !== undefined))
		return qbDomToValueDecode(dom.attributes.qbValue.value, type, is_scalar);
	else if ((type === "QFile") || (type === "file") || (tag === "input/file"))
	{
		if (!(dom && dom.files && dom.files.length))
			return undefined;
		var d_type = (type === "QFile") ? "QFile" : "file";
		val = {_ty: "QFile", _ftype: d_type, _dom: dom, _file: ((dom.attributes && dom.attributes.name) ? dom.attributes.name.value : null)};
		method_info ? (method_info.has_file = true) : null;
		return val;
	}
	else
	{
		from_std_input = true;
		switch (tag)
		{
			case "input/text":
			case "input/hidden":
			case "input/password":
			case "input/number":
			case "input/datetime":
			case "input/date":
			case "input/time":
			{
				val = dom.value;
				break;
			}
			case "input/checkbox":
			{
				val = dom.checked ? (((dom.value !== undefined) && (dom.value !== null) && (dom.value !== "")) ? dom.value : 1)
									// we add the feature of setting a value for unchecked
								  : ((dom.attributes && dom.attributes.valueUnchecked) ? dom.attributes.valueUnchecked.value : null);
				return val;
			}
			case "input/radio":
			{
				// if it's not the one selected send undefined
				val = (dom.checked && dom.value) ? dom.value : undefined;
				break;
			}
			case "select":
			{
				val = (dom.selectedIndex >= 0) ? (dom.options[dom.selectedIndex].value || dom.options[dom.selectedIndex].text) : null;
				break;
			}
			case "textarea":
			{
				val = dom.value;
				break;
			}
			default:
			{
				val = dom.innerHTML;
				from_std_input = false;
				break;
			}
		}
		
		// this is for radio to make sure we only send for the one that is checked
		if (val === undefined)
			return val;

		if ((from_std_input || (jQuery(dom).children().length === 0)) && (typeof(val) === "string"))
		{
			return qbDomToValueParse(val, type);
		}
	}
}

function qbDomToValueDecode(val, type, is_scalar)
{
	if (typeof(val) === "string")
	{
		var extracted_id = null;
		var sep_pos = -1;
		
		if (val === "null")
			return null;
		else if (val === "true")
			return true;
		else if (val === "false")
			return false;
		else if (val.substr(0, 1) === "_")
			return val.substr(1);
		else if ((sep_pos = val.indexOf("|")) >= 0)
		{
			// reset type
			if (val.charAt(0) === "(")
			{
				// ignore round brackets, ex: (QModel|12345)
				type = val.substr(1, sep_pos - 1);
				extracted_id = val.substr(sep_pos + 1, val.length - (sep_pos + 2));
			}
			else
			{
				type = val.substr(0, sep_pos);
				extracted_id = val.substr(sep_pos + 1);
			}
		}
		else if (!is_scalar)
		{
			if (val.charAt(0) === "(")
				extracted_id = val.substr(1, val.length - 2);
			else
				extracted_id = val;
		}
		else
			return qbDomToValueParse(val, type, is_scalar);

		var new_type = (type === "Array") ? "QModelArray" : type;
		var i_val = (window[new_type] && (typeof(window[new_type]) === "function")) ? new window[new_type]() : {};
		if ((extracted_id !== undefined) && (extracted_id !== null))
			i_val._id = extracted_id;
		i_val._ty = type;

		return i_val;
	}
	else
		return val;
}

function qbDomToValueParse(val, type, is_scalar)
{
	type = (type || "string");
	var val_ty = typeof(val);
	switch (type)
	{
		case "string":
			return (val_ty === "string") ? val : val + "";
		case "int":
		case "integer":
		{
			// number
			if (val_ty === "string")
				val = parseInt(val);
			else if (val_ty === "boolean")
				val = val ? 1 : 0;
			return isNaN(val) ? null : val;
		}
		case "double":
		case "float":
		{
			val = parseFloat(val);
			return isNaN(val) ? null : val;
		}
		case "bool":
		case "boolean":
		{
			if (val_ty === "number")
				val = val ? true : false;
			else if (val_ty === "string")
				val = ((val === "0") || (val === "false") || (val === "")) ? false : true;
			return val;
		}
		default:
			return val;
	}
}

function qbUnset(dom, key)
{
	return qb(dom, undefined, key);
}

function qbTs(dom, ts, key)
{
	return qb(dom, undefined, key, {_ts: ts}, true);
}

function qbTsp(dom, tsp, key)
{
	return qb(dom, undefined, key, {_tsp: tsp}, true);
}

function qbDelete(dom)
{
	// this will work fine for a one to many element
	// what do we do on a many to many element ?!
	
	return qb(dom, undefined, undefined, {_ts: window.TransformDelete, _tsp: window.TransformDelete}, true);
}

function qbUnlink(dom)
{
	return qb(dom, undefined, undefined, {_tsp: window.TransformDelete}, true);
}

function obj_first(obj)
{
	if (typeof(obj) === "object")
	{
		for (var k in obj)
			return obj[k];
	}
}

/*
function qbRowi(dom, key)
{
	return qb(dom, undefined, key, {_rowi: tsp}, true);
}
*/

function qb(dom, value, key, ext, merge)
{
	dom = (dom instanceof jQuery) ? dom[0] : dom;

	var qb_pos = -1;
	var attr_name = "qb";
	var qb_attr = (dom.attributes && dom.attributes.qb) ? dom.attributes.qb : null;
	
	// set key as . if we have a value and no key
	if ((value !== undefined) && (key === undefined))
		key = ".";
	
	if (key !== undefined)
	{
		if ((key.charAt(0) !== "$") && (key.charAt(0) !== ".") && (key.charAt(key.length - 1) !== ":"))
			key = "." + key;

		// try to find it by key
		while (qb_attr)
		{
			// parse, compare
			if (!qb_attr.value)
			{
				qb_attr = null;
				break;
			}

			var m_key = qbDecodeAttr(qb_attr.value);
			if (m_key === key)
				break;

			attr_name = "qb" + (qb_pos++);
			qb_attr = dom.attributes[attr_name];
		}
	}
	
	if ((value === undefined) && key && qb_attr)
	{
		dom.removeAttribute(attr_name);
	}
	if (value !== undefined)
	{
		// set it at the first free position available
		dom.setAttribute(attr_name, qbEncode(value, key));
	}
	
	var ext_attr = dom.attributes[attr_name + "-x"];

	if (ext !== undefined)
	{
		// set the ext
		if (merge && ext_attr)
			qbMergeObj(ext, JSON.parse(ext_attr.value));

		dom.setAttribute(attr_name + "-x", JSON.stringify(ext));
	}
	
	if (ext)
	{
		if (!ext_attr)
			return undefined;
		else
			return JSON.parse(ext_attr.value);
	}
	else
	{
		if (!qb_attr)
			return undefined;
		else
			return qbDecodeValue(qb_attr.value);
	}
}

function qbKey(dom, key, new_key)
{
	throw ("to do");
}

function qbDecodeValue(value)
{
	if (((value === undefined) || (value === null)) || (typeof(value) !== "string"))
		return undefined;
	
	throw ("to do");
}

function qbDecodeAttr(attr_val)
{
	if (((attr_val === undefined) || (attr_val === null)) || (typeof(attr_val) !== "string"))
		return null;
	var m_key = attr_val.match(/^[^\(]+/g);
	return m_key ? m_key[0] : null;
}

function qbEncodeString(str)
{
	return JSON.stringify(str);
}

function qbEncodeIdScalar(scalar)
{
	if (scalar === undefined)
		return "";
	else if (scalar === null)
		return "null";
	else if (scalar === true)
		return "true";
	else if (scalar === false)
		return "false";
	
	var type = typeof(scalar);
	if (type === "string")
		return qbEncodeString(scalar);
	else if (type === "number")
		return scalar;
	else
		throw ("Unsupported input type: " + type);
}

function qbEncode(value, key)
{
	key = ((key === undefined) || (key === null)) ? "." : key;
	if (value === undefined)
		return key;
	else if (value === null)
		return key + "(null)";
	else if (value === true)
		return key + "(true)";
	else if (value === false)
		return key + "(false)";
	else
	{
		var type = typeof(value);
		if (type === "string")
			return key + "(" + qbEncodeString(value) + ")";
		else if (type === "number")
			return key + "(#" + value + ")";
		else if (type === "object")
		{
			if (typeof(value._ty) === "string")
			{
				if ((value._ty === "QModelArray") && (!value._id))
					return key + "[+]";
				else
					return key + "(" + value._ty + (value._id ? ("|" + qbEncodeIdScalar(value._id)) : "") + ")";
			}
			else if (Array.isArray(value))
				return key + "[]";
			else 
				return key + "{}";
		}
		else 
			return null;
	}
}

function qbMergeObj(dest, src, skipcheck)
{
	if (skipcheck || ((typeof(dest) === "object") && (typeof(src) === "object")))
	{
		for (var k in src)
		{
			var src_v = src[k];
			if (dest[k] === undefined)
				dest[k] = src_v;
			else if ((typeof(dest[k]) === "object") && (typeof(src_v) === "object"))
				qbMergeObj(dest[k], src_v, true);
		}
	}
	
	return dest;
}

function qbDecodeObject(obj, refs)
{
	var ty = typeof(obj);
	if ((!obj) || (ty !== "object"))
		return;
	var first = false;
	if (!refs)
	{
		refs = {};
		refs._incr = 1;
		first = true;
	}
	else if (obj._qtmpid && refs[obj._qtmpid])
	{
		return;
	}
	
	obj._qtmpid = refs._incr++;
	refs[obj._qtmpid] = obj;
	
	for (var k in obj)
	{
		if (k === "_qtmpid")
			continue;
		var value = obj[k];
		var v_ty = typeof(obj[k]);
		if (v_ty === "string")
		{
			if (value.charAt(0) === '_')
				obj[k] = value.substr(1);
			else if (value === "null")
				obj[k] = null;
			else if (value === "true")
				obj[k] = true;
			else if (value === "false")
				obj[k] = false;
			else
				obj[k] = Number(value);
		}
		else if (v_ty === "object")
			qbDecodeObject(value, refs);
	}
	
	if (first)
	{
		for (var k in refs)
		{
			if (k === "_qtmpid")
				continue;
			else 
				delete(refs[k]._qtmpid);
		}
	}
}

function qvar_dump(data, depth)
{
	qbDebug(data, depth || 10);
}

function qvardump(data, depth)
{
	qbDebug(data, depth || 6, "no-meta");
}

/**
 * This funcion will include specified resources that are not included, 
 * when the last JS is included "user_func" will be called with "user_func_param"
 * If there is no JS to be included "user_func" will NOT be called and the function will return zero
 * 
 * The function will return the number of NEW javasctipt files that were included
 *
 * @param {object} css
 * @param {object} js
 * @param {function} user_func
 * @param {object} user_func_param
 * @returns {Number}
 */
function qIncludeResourcesIfNotIncluded(css, js, user_func, user_func_param)
{
	// we need to return the no of resources that were not included
	var head_dom = document.head || document.getElementsByTagName('head')[0];
	var include_count = 0;
	var includes_remaining = 0;

	// first CSS
	if (!qObjEmpty(css))
	{
		var existing = {};
		var css_doms = jQuery("link[type='text/css']");
		for (var i = 0; i < css_doms.length; i++)
		{
			var href = css_doms[i].getAttribute("href");
			
			if (href && href.length)
			{
				// make sure that we remove prevent caching property from resource
				if (href.indexOf("?") > -1)
					href = href.substr(0, href.indexOf("?"));

				existing[href] = true;
			}
		}
		
		for (var k in css)
		{
			var href = css[k];
			
			var basehref = href;
			if (basehref.indexOf("?") > -1)
				basehref = basehref.substr(0, basehref.indexOf("?"));

			if (href && (!existing[basehref]))
			{
				var stylesheet = document.createElement('link');
                stylesheet.href = href;
                stylesheet.rel = 'stylesheet';
                stylesheet.type = 'text/css';
				/* this will not work in Safari, so we don't wait for CSS to load
				stylesheet.addEventListener('load', function() {
					
				  }, false);
				*/
                head_dom.appendChild(stylesheet);
			}
		}
	}

	if (!qObjEmpty(js))
	{
		var existing = {};
		var js_doms = jQuery("script[type=\"text/javascript\"]");
		for (var i = 0; i < js_doms.length; i++)
		{
			var src = js_doms[i].getAttribute("src");

			if (src && src.length)
			{
				// make sure that we remove prevent caching property from resource
				if (src.indexOf("?") > -1)
					src = src.substr(0, src.indexOf("?"));

				existing[src] = true;
			}
		}
		
		for (var k in js)
		{
			var src = js[k];
			
			var basesrc = src;
			if (basesrc.indexOf("?") > -1)
				basesrc = basesrc.substr(0, basesrc.indexOf("?"));
			
			if (src && (!existing[basesrc]))
			{
				var js_script = document.createElement('script');
                js_script.src = src;
                js_script.type = 'text/javascript';
				
				includes_remaining++;
				include_count++;
				
				js_script.addEventListener('load', function(){
					
					includes_remaining--;
					if (includes_remaining <= 0)
					{
						user_func(user_func_param);
					}
					}, false);
				  
				//qbDebug(js_script.src, 1);
                head_dom.appendChild(js_script);
			}
		}
	}

	return include_count;
}

function qObjEmpty(obj)
{
	if ((obj === undefined) || (obj === null))
		return true;
	for (var k in obj) 
		return false;
	return true;
}

/*
function qbModal(dom, url)
{
	// $ctrl(dom).call()
	// $( ".selector" ).dialog({ modal: true });
}
*/

/*
qbParseAttr("");

function qbParseAttr(value)
{
	if (!(typeof(value) === "string"))
		return null;
	// parse full or relative bind
	// with or without method
	// with or without start param
	
	// "'\\\\.|[^\\\\\']*\'|". # string
	// var reg = /'\\.'/g;
	
	
	var ret = value.match(/'\\.|[^\\']*'|"\\.|[^\\"]*"|\.?[\$\w]+\:?|/g);
	// \{|\}|\[|\]|\(|\)
	alert(ret);
}
*/

function getUrlVariable(name, url)
{
	url = url || window.location.href;
	var results = url.match('[\\?&]' + name + '=([^&#]*)');
	if (results && results[1])
		return decodeURIComponent(results[1]);
	else
		return null;
}

function qHideOnClickAway(e)
{
	// if not inside a .qHideOnClickAway
	/*var $e_parent = jQuery(e);
	var $do_action = false;
	do
	{
		if ($e_parent.hasClass('qHideOnClickAway') || $e_parent.hasClass('q-hide-on-click-away'))
			break;
		else if ($e_parent.hasClass('qHideOnClickAway-parent') || $e_parent.hasClass('q-hide-on-click-away-parent'))
		{
			with_parent = $e_parent;
			break;
		}
		$c_parent = $e_parent.parent();
	}
	while ($e_parent);
	*/

	var $search_within = jQuery(e.target).is('.qHideOnClickAway, .q-hide-on-click-away');
	$search_within = $search_within && $search_within.length ? $search_within : jQuery(e.target).closest('.qHideOnClickAway, .q-hide-on-click-away');
	if ($search_within && ($search_within.length === 0))
		$search_within = null;
	
	if (true) // (!jQuery(e.target).is(".qHideOnClickAway *, .qHideOnClickAway, .q-hide-on-click-away *, .q-hide-on-click-away"))
	{
		var elems = $search_within ? $search_within.find(".qHideOnClickAway, .q-hide-on-click-away") : jQuery(".qHideOnClickAway, .q-hide-on-click-away");
		// we also support .qHideOnClickAway without .qHideOnClickAway-container inside it
		
		for (var i = 0; i < elems.length; i++)
		{
			var elem_jq = jQuery(elems[i]);
			
			// form-row-margin row qc-xg-property js-container-BuyPriceProfile qc-prop-BuyPriceProfile form-input-focus 
			
			/* var $is_test_element = (elem_jq.closest('.qc-xg-property.qc-prop-BuyPriceProfile'));
			if ($is_test_element && ($is_test_element.length > 0))
				alert('$is_test_element');*/
			
			var with_container = 
						elem_jq.find(".qHideOnClickAway-container, .q-hide-on-click-away-container").
							not(elem_jq.find(".qHideOnClickAway,.q-hide-on-click-away").find(".qHideOnClickAway-container, .q-hide-on-click-away-container"));
			
			var with_parent = jQuery();
			// var with_parent = (with_container && with_container.length) ? jQuery() : // we don't want to search if we have container
			//					elem_jq.closest(".qHideOnClickAway-parent, .q-hide-on-click-away-parent");
			if (!(with_container && with_container.length))
			{
				var $c_parent = elem_jq.parent();
				do
				{
					if ($c_parent.hasClass('qHideOnClickAway') || $c_parent.hasClass('q-hide-on-click-away'))
						break;
					else if ($c_parent.hasClass('qHideOnClickAway-parent') || $c_parent.hasClass('q-hide-on-click-away-parent'))
					{
						with_parent = $c_parent;
						break;
					}
					$c_parent = $c_parent.parent();
				}
				while ($c_parent);
			}
			
			var ctrl = $ctrl(elem_jq);
			
			if (ctrl && ctrl.trigger)
			{
				ctrl.trigger("onHide", {"control" : ctrl});
			}
			if (with_parent.length > 0)
			{
				with_parent.hide();
				if (with_parent.is(".qHideOnClickAway-remove"))
					with_parent.remove();
			}
			else if (with_container.length > 0)
			{
				with_container.hide();
				if (with_container.is(".qHideOnClickAway-remove"))
					with_container.remove();
			}
			else
			{
				elem_jq.hide();
				if (with_parent.is(".qHideOnClickAway-remove"))
					with_parent.remove();
			}
		}
	}
}

var UUID = (function() {
  var self = {};
  var lut = []; for (var i=0; i<256; i++) { lut[i] = (i<16?'0':'')+(i).toString(16); }
  self.generate = function() {
    var d0 = Math.random()*0xffffffff|0;
    var d1 = Math.random()*0xffffffff|0;
    var d2 = Math.random()*0xffffffff|0;
    var d3 = Math.random()*0xffffffff|0;
    return lut[d0&0xff]+lut[d0>>8&0xff]+lut[d0>>16&0xff]+lut[d0>>24&0xff]+'-'+
      lut[d1&0xff]+lut[d1>>8&0xff]+'-'+lut[d1>>16&0x0f|0x40]+lut[d1>>24&0xff]+'-'+
      lut[d2&0x3f|0x80]+lut[d2>>8&0xff]+'-'+lut[d2>>16&0xff]+lut[d2>>24&0xff]+
      lut[d3&0xff]+lut[d3>>8&0xff]+lut[d3>>16&0xff]+lut[d3>>24&0xff];
  };
  return self;
})();

function q_devmode_handle_ajax(jqXHR, textStatus, errorThrown, json_response)
{
	if (errorThrown)
	{
		// there is an error
		if ((!json_response) && jqXHR.responseText)
		{
			// try to get it
			try
			{
				json_response = JSON.parse(jqXHR.responseText);
			}
			catch (err) {}
		}
	}
	
	// if (errorThrown) - there is an error
	// only in development mode
	if (!(json_response && json_response['__devmode__']))
		return json_response;
	
	if (json_response["__error__"])
	{
		qvar_dump(json_response["__error__"]);
	}
	if (json_response["__hiddenOutput__"])
	{
		qvar_dump(json_response["__hiddenOutput__"]);
	}
	
	return json_response;
}

function q_debug_attach_to_ajax($jqXHR, $settings)
{
	var $event = new Event('qajaxbefore');
	$event.ajax = $jqXHR;
	window.dispatchEvent($event);
	
	$jqXHR.always(function($data_or_jqXHR, $textStatus, $jqXHR_or_errorThrown)
	{
		// var $resp_code = $jqXHR.status;
		// alert($data_or_jqXHR); // data
		// alert(typeof($resp_code)); // data
		var $event = new Event('qajaxalways');
		$event.ajax = $jqXHR;
		window.dispatchEvent($event);
	});
}

// ============ END BINDS =====================================

/**
 * TRANSLATE 
 * 
 * @param {type} $uid
 * @param {type} $defaultText
 * @returns {unresolved} */
function _T($uid, $defaultText)
{
	var lang_data = window.__lang_data__ ? window.__lang_data__ : null;
	if (!lang_data)
		return $defaultText;
	
	return lang_data && lang_data[$uid ? $uid : $defaultText] ? lang_data[$uid ? $uid : $defaultText] : $defaultText;
}

function _L($text)
{
	return _T($text, $text);
}
