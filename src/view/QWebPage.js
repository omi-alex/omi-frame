
// this is a very useful way to hide popups and drop downs on click away
jQuery(document.body).click(qHideOnClickAway);

jQuery(document).ready(function ()
{
	if (window["__MultiResponseId"])
		jQuery(window).trigger("MultiResponse", [window["__MultiResponseId"]]);
});


/* this can be anoying !!
jQuery(document).keyup(function (e)
{
	if (e.keyCode === 27) // esc
	{
		qHideOnClickAway(e);
	}
});*/

QExtendClass("QWebPage", "QWebControl",
{
	indexedViews : null,
	
	wpCtrl : null,

	setView : function (view)
	{
		if (!this.indexedViews)
			this.indexedViews = {};
		this.indexedViews[view._id] = view;
	},

	isWebPage: function()
	{
		return true;
	},

	getViewById : function (id)
	{
		return (this.indexedViews && this.indexedViews[id]) ? this.indexedViews[id] : null;
	}
});
