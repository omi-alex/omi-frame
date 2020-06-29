
omi.handleEvent = function(event, sender, node, mv_event)
{
	// preventDefault()
	var event_type = event.type;
	sender = sender || event.target;
	
	var meth_name = "on" + event_type;
	
	// alert(event_type);
	// bubble event to objects
	node = node || sender;
	
	mv_event = mv_event || false;
	
	while (node && node.tagName)
	{
		var ctrl = node.jsCtrl;
		var q_var = node.getAttribute("q-var");
		
		if (q_var && (node === sender))
			mv_event = q_var;
		else if (node.hasAttribute("q-append") || node.hasAttribute("q-add"))
			mv_event = "q-append";

		if ((!ctrl) && (node.hasAttribute("q-ctrl") || node.hasAttribute("qCtrl")) && omi.HasClass(node, "omi-control"))
		{
			// dynamic loading, then callback to continue the event
			// callback = function () {omi.handleEvent(event, sender, node, mv_event)}
			omi.InitDom(node, function() {
					omi.handleEvent(event, sender, node, mv_event);
				});
			break;
		}
		
		if (ctrl)
		{
			//alert("Has ctrl: " + meth_name + "\n\n" + ctrl[meth_name] + "\n\n" + ctrl.dom.innerHTML);
			var meth = ctrl[meth_name];
			if (meth)
				// sender, sender_id, event
				meth.apply(ctrl, [sender, null, event]);
			/*
			if (mv_event)
				omi._HandleMV_Event(ctrl, node, sender, event, event_type, mv_event);
			*/
		}
			
		node = node.parentNode;
	}
};

omi._HandleMV_Event = function(omi_obj, omi_dom, dom_sender, event, event_type, mv_event)
{
	if ((!omi_obj) || (!omi_obj.initiated))
		omi_obj = omi.Init(omi_obj ? omi_obj.dom : omi_dom);

	var vars_path = dom_sender.__v;
	var $bind = vars_path ? vars_path.__b : null;
	var $data, $data_list;
	
	switch (event_type)
	{
		case "input":
		{
			if (!$bind)
				return false;
	
			var $val = dom_sender.value || dom_sender.innerHTML;
			for (var $key in $bind)
			{
				$data_list = $bind[$key];
				for (var i = 0; i < $data_list.length; i++)
				{
					$data = $data_list[i];
					omi.set($data, $key, $val, dom_sender);
				}
			}
			
			break;
		}
		case "click":
		{
			if (mv_event === "q-append")
			{
				// foreach var path create a new entry
				// alert(dom_sender.__last_each.__vp);
				// alert("qvar_dump");
				// qvar_dump(omi_obj.data.$items);
				
				// alert("omi.append");
				
				// try to find the closest q-each comment node
				var dom_next = dom_sender;
				var look_inside = false;
				var comment_each_dom = null;
				while (((look_inside = true) && (dom_next = dom_next.previousElementSibling)) || ((look_inside = false) || (dom_next = dom_next.parentNode)))
				{
					if (look_inside)
					{
						var treeWalker;
						if (NodeFilter && NodeFilter.SHOW_COMMENT)
							treeWalker = document.createTreeWalker(dom_next, NodeFilter.SHOW_COMMENT);
						else
							treeWalker = document.createTreeWalker(dom_next);
						while (treeWalker.nextNode())
						{
							if (treeWalker.currentNode.__vp)
							{
								// we found it
								comment_each_dom = treeWalker.currentNode;
								break;
							}
						}
						if (comment_each_dom)
							break;
					}
				}
				
				if (comment_each_dom)
				{
					alert(comment_each_dom);
					console.log(comment_each_dom.__vp);
				}
				
				// omi.set(omi_obj.data, {"$items": [{}]}, dom_sender);
				// alert("omi.append");
				
				/*
				var c_search_node = dom_sender;
				// ok we're back in biz
				while ((c_search_node = (c_search_node.previousElementSibling || 
						((c_search_node.previousSibling && (c_search_node.previousSibling.nodeType === 1)) ? c_search_node.previousSibling : null))))
				{
					// find a q-each inside it
					alert(c_search_node.getByTagName("").length);
				}
				*/
			}
		}
		default:
			break;
	}
};

