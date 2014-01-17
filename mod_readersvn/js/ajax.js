/* AJAX Script - http://difour.org/ */

window.loading = "<img src='pix/ajax-loader.gif' alt='loading...'/>";
window.req = false;

function request(url, target, callback) {
	var obj = document.getElementById(target);
	if (obj) {
        if (loading) {
            obj.innerHTML = loading;
        }
	    obj = null;

        if (window.ActiveXObject) {
            req = new ActiveXObject("Microsoft.XMLHTTP");
        } else if (window.XMLHttpRequest) {
            req = new XMLHttpRequest();
        }

        if (req) {
            if (callback) {
                req.onreadystatechange = eval(callback);
            } else {
                req.onreadystatechange = function() { response(url, target); }
            }
            req.open("GET", url, true);
            req.send(null);
        }
	}
}

function response(url, target) {
	var obj = document.getElementById(target);
	if (obj) {
        if (req.readyState == 4) {
            obj.innerHTML = (req.status==200 ? req.responseText : ("An error was encountered: " + req.status));
        }
	    obj = null;
	}
}

function setLoadMessage(msg) {
	loading = msg;
}
