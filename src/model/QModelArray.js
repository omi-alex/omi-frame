

QExtendClass("QModelArray", "QModel", 
{
	_items : null,
	_tsp : null,
	_maxpos : 0,
	length : 0,
	_rowi : null,

	init : function(type)
	{
		this._items = {};
		if (type || (!this._ty))
			this._ty = type || "QModelArray";
	},

	each: function (callback, callback_obj)
	{
		if (!(this._items && callback))
			return;
		
		for (var k in this._items)
		{
			if (callback_obj)
				callback.apply(callback_obj, [k, this._items[k]]);
			else
				callback(callback_obj, [k, this._items[k]]);
		}
	},

	items: function()
	{
		return this._items;
	},

	first: function()
	{
		for (var i in this._items)
			return this._items[i];
		return null;
	},
	
	foreach: function(callback, callback_obj)
	{
		this.each(callback, callback_obj);
	},
	
	get: function(key)
	{
		return this._items[key];
	},
	
	set: function(key, value, caller)
	{
		var old_val = this._items[key];
		if (old_val === undefined)
			this.length++;
		this._items[key] = value;
		var changed = (old_val !== value);
		// if key is numeric push maxpos if needed
		if (((typeof(key) === "number") || (!isNaN(key = parseInt(key)))) && (key >= this._maxpos))
			this._maxpos++;
		
		// notify listeners
		if (changed)
			this.notifyListeners(caller, key, value, old_val);
	},

	/**
	 * Gets the row id for the element at the specified index/key
	 * 
	 * @param string $key
	 * 
	 * @return integer
	 */
	getRowIdAtIndex: function($key)
	{
		return this._rowi[$key];
	},
	/**
	 * Sets the row id for the element at the specified index/key
	 * 
	 * @param string $key
	 * @param integer $row_id
	 * 
	 */
	setRowIdAtIndex: function($key, $row_id)
	{
		this._rowi[$key] = $row_id;
	},
	
	unset: function(key, caller)
	{
		if (this._items[key] !== undefined)
		{
			var old_val = this._items[key];
			delete this._items[key];
			if (key == this._maxpos)
				this._maxpos--;
			this.length--;
			
			// notify listeners
			this.notifyListeners(caller, key, undefined, old_val);
			
			return true;
		}
		else
			return false;
	},

	getTransformState : function(key, forProps)
	{
		// return this._ts;
		
		return ((key !== undefined) && (key !== null)) ? (this._tsp ? this._tsp[key] : null) : (forProps ? this._tsp : this._ts);
	},

	setTransformState : function(state, key)
	{
		//this._ts = state;
		if ((key !== undefined) && (key !== null))
		{
			if (!this._tsp)
				this._tsp = {};
			this._tsp[key] = state;
		}
		else
			this._ts = state;
	},

	append: function(value)
	{
		// this._items[this._maxpos] = value;
		this.set(this._maxpos, value);
		
		this._maxpos++;
		this.length++;
	},
	
	isQIModelArray: function()
	{
		return true;
	},
	
	count: function()
	{
		return this.length;
	},
	
	getQueryCount: function()
	{
		return this._qc;
	},
	
	toString: function()
	{
		var pos = 0;
		var ret = "";
		this.each(function (k, v) {
			//alert(k + "  :: " + v);
				/*
				if (pos > 0)
					ret += ",";
				*/
				ret += v;
				pos++;
			});

		if (ret.substring(ret.length - 1) == ",")
			ret = ret.substring(0, ret.length - 1);
		return ret;
	}
});
