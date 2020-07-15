

// alert('debug dev');
window.qdev_log = function($message, $tags)
{
	jQuery(document.body).append("<div>" + $message + "</div>");
}

