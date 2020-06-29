
document.addEventListener("DOMContentLoaded", function ()
{
	var $iframe = document.getElementById('iframe_main');
	var $base_href = window.$base_href;
	
	var $urlChangeCallback = function ()
	{
		var $rel_href = $iframe.contentWindow.location.pathname; // @TODO add the rest
		var $go_to = $base_href + "~dev/" + $rel_href.substr($base_href.length);
		var $frame_title = $iframe.contentWindow.document.title;
		window.document.title = $frame_title;
		var $state_obj = {title: $frame_title};
		window.history.pushState($state_obj, $frame_title, $go_to);
	};
	// $iframe.contentWindow.onpopstate | does not work!
	$iframe.onload = function () // DOMContentLoaded
	{
		// window["__ResponseId__"]
		var $resp_id = $iframe.contentWindow.__ResponseId__;
		if ($resp_id)
			qdev_log_response($resp_id);
		qdev_log("page loaded: " + $resp_id); 
		$urlChangeCallback;
	};
	
	$iframe.onreadystatechange = function()
	{
		qdev_log("onreadystatechange : " + $iframe.contentWindow.document.readyState);
	};
	
	$iframe.contentWindow.addEventListener('qajaxbefore', function ($event)
	{
		qdev_log('NEW AJAX REQUEST');
		// alert($event.ajax);
		// $event.ajax = $jqXHR;
	});

	$iframe.contentWindow.addEventListener('qajaxalways', function ($event)
	{
		// self::$AjaxResponse['__ResponseId__'] = self::GetRequestId();
		var $resp_id = null;
		try
		{
			$resp_id = JSON.parse($event.ajax.responseText).__ResponseId__;
			if ($resp_id)
				qdev_log_response($resp_id);
			// console.log(JSON.parse($event.ajax.responseText).__ResponseId__);
		}
		catch (ex)
		{
			
		}
		qdev_log('AJAX REQUEST DONE: ' + $resp_id);
		// alert('qajaxalways');
		// $event.ajax = $jqXHR;
	});
});

function qdev_log($message, $tags)
{
	var $iframe = document.getElementById('iframe_dev');
	$iframe.contentWindow.qdev_log($message, $tags);
}

function qdev_log_response($resp_id)
{
	// @TODO - ajax into temp/dev_resp/$resp_id
	jQuery.ajax('temp/dev_resp/' + $resp_id, {
		 success: function ($data)
		 {
			try
			{
				var $full_data = $data + "]";
				var $obj_data = JSON.parse($full_data);
				// data, depth, indepth, dump_depth, var_undef
				// dbg_panel.find(".responsePanel").append("<pre>" + escapeHtml(qbDebug(resps, 2, true)) + "</pre>");
				qdev_log(qbDebug("<pre>" + escapeHtml(qbDebug($obj_data, 2, true, undefined, true)) + "</pre>", 0, 0, 0, true));
			}
			catch (ex)
			{
				
			}
		 }
	});
}

