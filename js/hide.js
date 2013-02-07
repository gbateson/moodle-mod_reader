<script type="text/javascript">
//<![CDATA[
var vh_content = new Array();
function getspan(spanid) {
  if (document.getElementById) {
    return document.getElementById(spanid);
  } else if (window[spanid]) {
    return window[spanid];
  }
  return null;
}
function toggle(spanid) {
  if (getspan(spanid).innerHTML == "") {
    getspan(spanid).innerHTML = vh_content[spanid];
    getspan(spanid + "indicator").innerHTML = '<img src="<?php echo $CFG->wwwroot; ?>/mod/reader/pix/open.gif" alt="Opened folder" />';
  } else {
    vh_content[spanid] = getspan(spanid).innerHTML;
    getspan(spanid).innerHTML = "";
    getspan(spanid + "indicator").innerHTML = '<img src="<?php echo $CFG->wwwroot; ?>/mod/reader/pix/closed.gif" alt="Closed folder" />';
  }
}
function collapse(spanid) {
  if (getspan(spanid).innerHTML !== "") {
    vh_content[spanid] = getspan(spanid).innerHTML;
    getspan(spanid).innerHTML = "";
    getspan(spanid + "indicator").innerHTML = '<img src="<?php echo $CFG->wwwroot; ?>/mod/reader/pix/closed.gif" alt="Closed folder" />';
  }
}
function expand(spanid) {
  getspan(spanid).innerHTML = vh_content[spanid];
  getspan(spanid + "indicator").innerHTML = '<img src="<?php echo $CFG->wwwroot; ?>/mod/reader/pix/open.gif" alt="Opened folder" />';
}
function expandall() {
  for (i = 1; i <= vh_numspans; i++) {
    expand("comments_" + String(i));
  }
}
function collapseall() {
  for (i = vh_numspans; i > 0; i--) {
    collapse("comments_" + String(i));
  }
}
//]]>
</script>
