/* AJAX Script - http://difour.org/ */

var loading = "<img src='pix/ajax-loader.gif' alt='loading...'/>";

function request(url, target, callback) 
{
	if ( ! document.getElementById)
	{
		return false;
	}

	if (loading != null)
	{
		document.getElementById(target).innerHTML = loading;
	}

	if (window.ActiveXObject) 
	{
		req = new ActiveXObject("Microsoft.XMLHTTP");
	}
	else if (window.XMLHttpRequest) 
	{
		req = new XMLHttpRequest();
	} 
	
	if (req == undefined)
	{
		return false;
	}
		
	if (callback != undefined) 
	{
		req.onreadystatechange = eval(callback);
	}
	else
	{
		req.onreadystatechange = function() { response(url, target); }
	}
		
	req.open("GET", url, true);	
	req.send(null);
}

function response(url, target) 
{
	if (req.readyState == 4) 
	{
		document.getElementById(target).innerHTML = (req.status == 200) ? req.responseText : "An error was encountered: " + req.status;
	}
}

function setLoadMessage(msg)
{
	loading = msg;
}
