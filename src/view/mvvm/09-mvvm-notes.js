

// window.onfocus = omi.handleEvent;


// var parsed_sel = omi._parseSelector("Orders.*,Orders.Items.{Date,Product,Quantity},Orders.DeliveryAddresses.*");


/*

var sample_2 = document.getElementById("Sample_2");
var sample_3 = document.getElementById("Sample_3");

var bind_2 = new omi(sample_2);
var bind_3 = new omi(sample_3);

//bind_2.initView();
//bind_3.extractData();

// var tpl_for_3 = new omi("MyCompany\\Ecomm\\View\\Sample3");
// alert(tpl_for_3.dom);

var tpl_for_3 = '<div q-namespace="MyCompany\Ecomm\View" jsFunc="render($orders, $text)" style="width: 50%; float: left;" id="Sample_3">\
<table class="u-full-width">\
	<thead>\
		<tr>\
			<th></th>\
			<th>Order Id</th>\
			<th>Date</th>\
			<th>Customer</th>\
			<th>Items</th>\
			<th>Total</th>\
			<th>Actions</th>\
		</tr>\
	</thead>\
	<tbody>\
		<tr>\
			<td colspan="6"></td>\
			<td><a href="#">create</a></td>\
		</tr>\
		<tr q-each="$orders[] as $order" q-start>\
			<td></td>\
			<td q-var=".Id"></td>\
			<td q-var=".Date"></td>\
			<td q-var=".Customer"></td>\
			<td>@todo : count</td>\
			<td class="right">@todo: sum</td>\
			<td>\
				<a href="#"><i class="fa fa-eye"></i></a>\
				<a href="#"><i class="fa fa-edit"></i></a>\
				<a href="#"><i class="fa fa-trash-o"></i></a>\
			</td>\
		</tr>\
		<tr q-end>\
			<td colspan="7">\
				<table style="width: 70%; margin-left: 30%;">\
					<thead>\
						<th></th>\
						<th>Item</th>\
						<th>Quantity</th>\
						<th>Item Price</th>\
						<th>Price</th>\
					</thead>\
					<tbody>\
						<tr q-each=".Items as $o_item">\
							<td q-var=".Id" q-val=""></td>\
							<td q-var=".Product.Name"></td>\
							<td q-var=".Quantity"></td>\
							<td q-var=".UnitPrice"></td>\
							<td q-var=".Price"></td>\
						</tr>\
					</tbody>\
					<tfoot>\
						<td colspan="3"></td>\
						<th>Total</th>\
						<th>@todo: total</th>\
					</tfoot>\
				</table>\
			</td>\
		</tr>\
	</tbody>\
</table>\
</div>\
';

// alert(tpl_for_3);
var t0 = performance.now();
bind_2.initView();
var t1 = performance.now();
var data = bind_3.extractData();
var t2 = performance.now();

// alert("Extract data [" + data.$orders.__len__ + "]: " + (t2 - t1) + "\n\n" );

window._debug = true;

var t0 = performance.now();
var bind_x = new omi(tpl_for_3);
bind_x.initView();
// qvar_dump(bind_x.vars_path);
bind_x.data = data;
// alert(bind_x.dom.innerHTML);
var t1 = performance.now();
bind_x._bind(data, null, true);
var t2 = performance.now();

// alert("BIND data [" + data.$orders.__len__ + "]: " + (t2 - t1) + "\n\n" );


var orders = data.$orders;
//alert(orders.__len__);
orders[orders.__len__++] = {
		Id: 888, Date: "2015-04-19 00:00:00", Customer: "Alex 1", 
		Items: {
			0: {Id: 1, Product: {Name: "Prod - Alex 1"}, Quantity: 8, UnitPrice: 12.12, Price: (8 * 12.12)},
			1: {Id: 2, Product: {Name: "Prod - Alex 2"}, Quantity: 7, UnitPrice: 13.12, Price: (7 * 13.12)},
			2: {Id: 3, Product: {Name: "Prod - Alex 3"}, Quantity: 6, UnitPrice: 14.12, Price: (6 * 14.12)},
			3: {Id: 4, Product: {Name: "Prod - Alex 4"}, Quantity: 5, UnitPrice: 15.12, Price: (5 * 15.12)},
			4: {Id: 5, Product: {Name: "Prod - Alex 5"}, Quantity: 4, UnitPrice: 16.12, Price: (4 * 16.12)},
			__len__: 5
		}};

bind_x._bind(data, null, true);

var t1 = performance.now();

document.body.appendChild(bind_x.dom);
// alert("Simple init: " + (t1 - t0) + "\n\n" );
// alert(bind_x.dom.innerHTML);



// qvar_dump("haha");
//qvar_dump(data);

*/

/* alert("Simple init: " + (t1 - t0) + "\n\n" +
	"extractData init: " + (t2 - t1) + "\n\n" + 
	"Size: " + sample_3.innerHTML.length);
*/
// alert(sample_2);
// alert(sample_3);

// Super amazing, cross browser property function, based on http://thewikies.com/

/*
function addProperty(obj, name, onGet, onSet) {

    // wrapper functions
    var
        oldValue = obj[name],
        getFn = function () {
            return onGet.apply(obj, [oldValue]);
        },
        setFn = function (newValue) {
            return oldValue = onSet.apply(obj, [newValue]);
        };

    // Modern browsers, IE9+, and IE8 (must be a DOM object),
    if (Object.defineProperty) {

        Object.defineProperty(obj, name, {
            get: getFn,
            set: setFn
        });

    // Older Mozilla
    } else if (obj.__defineGetter__) {

        obj.__defineGetter__(name, getFn);
        obj.__defineSetter__(name, setFn);

    // IE6-7
    // must be a real DOM object (to have attachEvent) and must be attached to document (for onpropertychange to fire)
    } else {

        var onPropertyChange = function (e) {

            if (event.propertyName == name) {
                // temporarily remove the event so it doesn't fire again and create a loop
                obj.detachEvent("onpropertychange", onPropertyChange);

                // get the changed value, run it through the set function
                var newValue = setFn(obj[name]);

                // restore the get function
                obj[name] = getFn;
                obj[name].toString = getFn;

                // restore the event
                obj.attachEvent("onpropertychange", onPropertyChange);
            }
        };  

        obj[name] = getFn;
        obj[name].toString = getFn;

        obj.attachEvent("onpropertychange", onPropertyChange);

    }
}
*/

