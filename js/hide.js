var vh_content = new Array();
function mygetspan(spanid) {
  if (document.getElementById) {
    return document.getElementById(spanid);
  } else if (window[spanid]) {
    return window[spanid];
  }
  return null;
}
function mytoggle(spanid) {
  if (mygetspan(spanid).innerHTML == "") {
    mygetspan(spanid).innerHTML = vh_content[spanid];
    mygetspan(spanid + "indicator").innerHTML = '<img src="' + mywwwroot() + '/mod/reader/pix/open.gif" alt="Opened folder" />';
  } else {
    vh_content[spanid] = mygetspan(spanid).innerHTML;
    mygetspan(spanid).innerHTML = "";
    mygetspan(spanid + "indicator").innerHTML = '<img src="' + mywwwroot() + '/mod/reader/pix/closed.gif" alt="Closed folder" />';
  }
}
function mycollapse(spanid) {
  if (mygetspan(spanid).innerHTML !== "") {
    vh_content[spanid] = mygetspan(spanid).innerHTML;
    mygetspan(spanid).innerHTML = "";
    mygetspan(spanid + "indicator").innerHTML = '<img src="' + mywwwroot() + '/mod/reader/pix/closed.gif" alt="Closed folder" />';
  }
}
function myexpand(spanid) {
  mygetspan(spanid).innerHTML = vh_content[spanid];
  mygetspan(spanid + "indicator").innerHTML = '<img src="' + mywwwroot() + '/mod/reader/pix/open.gif" alt="Opened folder" />';
}
function myexpandall() {
  for (i = 1; i <= vh_numspans; i++) {
    myexpand("comments_" + String(i));
  }
}
function mycollapseall() {
  for (i = vh_numspans; i > 0; i--) {
    mycollapse("comments_" + String(i));
  }
}
function mywwwroot() {
    return location.href.replace(new RegExp('^(.*?)/mod/reader/.*$'), '$1');
}
