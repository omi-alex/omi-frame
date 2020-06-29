
// DO THE INIT -----------------------
if (!document)
	console.error("missing referece to document");
else if ((document.readyState === "loaded") || (document.readyState === "interactive") || (document.readyState === "complete"))
	omi.InitGlobal(omi);
else
	document.addEventListener("readystatechange", function ()
	{
		/*
		uninitialized - Has not started loading yet
		loading - Is loading
		loaded - Has been loaded
		interactive - Has loaded enough and the user can interact with it
		complete - Fully loaded */

		if ((document.readyState === "loaded") || (document.readyState === "interactive") || (document.readyState === "complete"))
			omi.InitGlobal(omi);
	});

/**
 * Setup object
 */
omi._setup = {
	// the events we will be registering to
	events: ["click", "dblclick", "submit", "input", "change", "focus", "focusin", "focusout", "DOMFocusIn", "mouseover"],
	delay_timeout: 500, // min time in ms between retries
	delay_retry_timeout: 50 // time between rechecks 
};

/**
 * Overwrite some settings if you need to
 * @param {object} options
 */
omi.setup = function(options)
{
	if (!options)
		return;
	for (var k in options)
		omi._setup[k] = options[k];
};

if (omi._setup.events)
{
	var r_events = omi._setup.events;
	for (var i = 0; i < r_events.length; i++)
	{
		var event = r_events[i];
		window.addEventListener(event, omi.handleEvent, ((event === "focus") || (event === "focusin") || (event === "focusout")));
	}
}


