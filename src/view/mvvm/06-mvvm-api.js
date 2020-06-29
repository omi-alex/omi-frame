
/**
 * Run an API with a flexible number of arguments
 * 
 * @param {string} call A string like ClassName::CalledMethod
 * @param {Array} params An array of parameters to be passed to the called method
 * @param {function} callback A function to be called on success
 * @param {function} callback_on_error A function to be called on error
 * @param {function} callback_common_before A function to be called on both error or success (it's called before)
 * @param {function} callback_common_after A function to be called on both error or success (it's called after)
 * @param {string} delay_tag Used by omi.apiDelayCall to ask for a release on a delay tag
 * @param {boolean} has_files
 */
omi.api = function(call, params, callback, callback_on_error, callback_common_before, callback_common_after, delay_tag, has_files)
{
	if (!call)
	{
		console.error("Missing `call` parameter");
		return;
	}
	
	call = call.replace(/^\s+|\s+$/g, '');
	// now we support ClassName::Method 
	var r = call.match(/^([\w\\]+)\:\:(\w+)$/);
	if (!(r && r[1] && r[2]))
	{
		console.error("bad request: " + call);
		return;
	}
	
	var call_class = r[1];
	var call_method = r[2];

	var request_params = {ajax: {}};
	if (callback)
	{
		request_params.ajax.success = (callback_common_before || callback_common_after || delay_tag) ? 
										function(response)
										{
											if (delay_tag && omi._delay[delay_tag])
												// release the delay handle
												delete(omi._delay[delay_tag]);

											if (callback_common_before)
											{
												if (typeof(callback_common_before) === "function")
													callback_common_before(response);
												else
													callback_common_before[1].apply(callback_common_before[0], [response]);
											}
											
											if (typeof(callback) === "function")
												callback(response);
											else
												callback[1].apply(callback[0], [response]);
											
											if (callback_common_after)
											{
												if (typeof(callback_common_after) === "function")
													callback_common_after(response);
												else
													callback_common_after[1].apply(callback_common_after[0], [response]);
											}
										} : ((typeof(callback) === "function") ? callback : function (response) {callback[1].apply(callback[0], [response]);} );
	}
	if (callback_on_error)
	{
		request_params.ajax.error = (callback_common_before || callback_common_after || delay_tag) ? 
										function(response)
										{
											if (delay_tag && omi._delay[delay_tag])
												omi._delay[delay_tag].inListRequest = false;
											if (callback_common_before)
											{
												if (typeof(callback_common_before) === "function")
													callback_common_before(response);
												else
													callback_common_before[1].apply(callback_common_before[0], [response]);
											}
											
											if (typeof(callback_on_error) === "function")
												callback_on_error(response);
											else
												callback_on_error[1].callback_on_error(callback[0], response);
											
											if (callback_common_after)
											{
												if (typeof(callback_common_after) === "function")
													callback_common_after(response);
												else
													callback_common_after[1].apply(callback_common_after[0], [response]);
											}
										} : ((typeof(callback_on_error) === "function") ? callback_on_error : function (response) {callback_on_error[1].apply(callback_on_error[0], [response]);} );
	}

	if (!params)
		params = [];
	// @todo : simplify and don't use this, make MVVM frame independent
					// url, req_method, ctrl, ctrl_id, meth_class, method_name, method_params, request_params, has_file, extract_callback
	var extract_callback = function (data)
	{
		return data;
	};
	qbMakeCtrlRequest(null, "fast-ajax", null, null, call_class, call_method, params, request_params, has_files, extract_callback);
};

omi.ajax = omi.api;

omi._delay = {};

omi.apiDelayCall = function(call, params, callback, callback_on_error, callback_common_before, callback_common_after, delay_tag, delay_timeout)
{
	if (delay_tag === undefined)
		delay_tag = "1";
	
	if (omi._delay[delay_tag] === undefined)
		omi._delay[delay_tag] = {};
	if (delay_timeout === undefined)
		delay_timeout = omi._setup.delay_timeout || 500;
	var delay_retry_timeout = omi._setup.delay_retry_timeout || 50;
	var ctrl = omi._delay[delay_tag];
	
	if (ctrl.hasListTimeout)
		return;
	else if (ctrl.inListRequest || (ctrl.lastListCalled && (ctrl.lastListCalled > (Date.UTC() - delay_timeout /* ms */))))
	{
		ctrl.hasListTimeout = true;
		var timeout_func = function () {
				if (!ctrl.inListRequest)
				{
					ctrl.hasListTimeout = false;
					
					// call here
					this.lastListCalled = Date.UTC();
					this.inListRequest = true;
					omi.api(call, params, callback, callback_on_error, callback_common_before, callback_common_after, delay_tag);
				}
				else
					setTimeout(timeout_func, delay_retry_timeout);
			};
		setTimeout(timeout_func, delay_retry_timeout);
	}
	else if (!ctrl.inListRequest)
	{
		// call here
		this.lastListCalled = Date.UTC();
		this.inListRequest = true;
		ctrl.updateByFilter(this);
		omi.api(call, params, callback, callback_on_error, callback_common_before, callback_common_after, delay_tag);
	}
};

/**
 * Make a data query to the DATA API interface
 * 
 * @param {string} from
 * @param {string|object} selector
 * @param {Array} binds
 * @param {function} callback
 * @param {function} callback_on_error
 * @param {function} callback_common
 */
omi.apiQuery = function(from, selector, binds, callback, callback_on_error, callback_common)
{
	// (call, params, callback, callback_on_error, callback_common_before, callback_common_after)
	return omi.api("QApi::Query", [from, selector, binds], callback, callback_on_error, callback_common);
};

/**
 * Save data to the DATA API interface 
 * 
 * @param {string|object} $selector
 * @param {mixed} $data
 * @param {number} $state
 * @param {string} $destinations
 * @param {function} callback
 * @param {function} callback_on_error
 * @param {function} callback_common
 */
omi.apiSave = function($selector, $data, $state, $destinations, callback, callback_on_error, callback_common)
{
	return omi.api("QApi::Save", [$selector, $data, $state, $destinations], callback, callback_on_error, callback_common);
};

/**
 * Insert data into the DATA API interface 
 * 
 * @param {string|object} $selector
 * @param {mixed} $data
 * @param {number} $state
 * @param {string} $destinations
 * @param {function} callback
 * @param {function} callback_on_error
 * @param {function} callback_common
 */
omi.apiInsert = function($selector, $data, $destinations, callback, callback_on_error, callback_common)
{
	return omi.api("QApi::Insert", [$selector, $data, $destinations], callback, callback_on_error, callback_common);
};

/**
 * Merge data into the DATA API interface 
 * 
 * @param {string|object} $selector
 * @param {mixed} $data
 * @param {number} $state
 * @param {string} $destinations
 * @param {function} callback
 * @param {function} callback_on_error
 * @param {function} callback_common
 */
omi.apiMerge = function($selector, $data, $destinations, callback, callback_on_error, callback_common)
{
	return omi.api("QApi::Merge", [$selector, $data, $destinations], callback, callback_on_error, callback_common);
};

/**
 * Update data into the DATA API interface 
 * 
 * @param {string|object} $selector
 * @param {mixed} $data
 * @param {number} $state
 * @param {string} $destinations
 * @param {function} callback
 * @param {function} callback_on_error
 * @param {function} callback_common
 */
omi.apiUpdate = function($selector, $data, $destinations, callback, callback_on_error, callback_common)
{
	return omi.api("QApi::Update", [$selector, $data, $destinations], callback, callback_on_error, callback_common);
};

/**
 * Delete data in the DATA API interface 
 * 
 * @param {string|object} $selector
 * @param {mixed} $data
 * @param {number} $state
 * @param {string} $destinations
 * @param {function} callback
 * @param {function} callback_on_error
 * @param {function} callback_common
 */
omi.apiDelete = function($selector, $data, $destinations, callback, callback_on_error, callback_common)
{
	return omi.api("QApi::Delete", [$selector, $data, $destinations], callback, callback_on_error, callback_common);
};

omi.QApi = {
	Query: omi.apiQuery
	
};

