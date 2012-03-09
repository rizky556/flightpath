<?php
/*
	This is very similar to the fp_template, except this is formatted
	to be used with content which is supposed to get printed out.
*/

if ($pageTitle == "")
{ // By default, page title is this...
	$pageTitle = $GLOBALS["fpSystemSettings"]["schoolInitials"] . " FlightPath";
}

$themeLocation = $GLOBALS["fpSystemSettings"]["baseURL"] . "/" . $GLOBALS["fpSystemSettings"]["theme"];

print "
<meta name='viewport' id='view' content='width=device-width;'/>
<meta name='format-detection' content='telephone=no'>                
<link rel='stylesheet' type='text/css' href='$themeLocation/style.css'>
<link rel='stylesheet' type='text/css' href='$themeLocation/mobile.css'>";

// Load any extra CSS files which addon modules might have added.
if (is_array($pageExtraCssFiles) && count($pageExtraCssFiles) > 0) {
 foreach ($pageExtraCssFiles as $cssFileName) {
   print "<link rel='stylesheet' type='text/css' href='$cssFileName'>";
 }
}


print "
<title>$pageTitle</title>
";

?>

<script src="<?php print $GLOBALS["fpSystemSettings"]["basePath"]; ?>/inc/jquery-1.3.2.min.js" type="text/javascript"></script>

<script type='text/javascript'>

function defaultOnLoad()
		{
			<?php

			print $pageOnLoad;
			// If the page had a scrollTo set, we should also
			// perform that here...
			if ($pageScrollTo != "")
			{
				print "location.href = \"#$pageScrollTo\"; \n";
			}

			?>

		}

function popuphelp(topic)
		{ // open a popup help window...
			help_window = window.open("./help.php?topic=" + topic + "",
			"helpwindow","toolbar=no,status=2,scrollbars=yes,resizable=yes,width=650,height=500");

			help_window.focus();  // make sure the popup window is on top.


		}

		function popupreportcontact()
		{
			err_window = window.open("./popupReportContact.php",
			"errwindow","toolbar=no,status=2,scrollbars=yes,resizable=yes,width=500,height=400");

			err_window.focus();  // make sure the popup window is on top.

		}


		function doSearch()
		{
			//document.getElementById("da_search_for").value = document.getElementById("search_bar_value").value;
			//document.getElementById("da_advisee_action").value = "search";
			//document.getElementById("da_from_search_button").value = "true";

			//document.mainform.action = "studentsearch.php";
			//document.mainform.submit();

			window.location="studentsearch.php?didSearch=true&searchFor=" + document.getElementById("search_bar_value").value;

			return true;
		}

		function searchKeyPress(e)
		{
			// If they typed an ENTER key, then call doSearch...
			if(e){
				e = e
			} else {
				e = window.event
			}

			if(e.which){
				var keycode = e.which
			} else {
				var keycode = e.keyCode
			}

			if(keycode == 13) {
				doSearch();
			}
		}

		function showUpdate(boolShowLoad)
		{
			var scrollTop = document.body.scrollTop;
			var updateMsg = document.getElementById("updateMsg");
			if (boolShowLoad == true)
			{
				updateMsg = document.getElementById("loadMsg");
			}
			var w = document.body.clientWidth;
			//var h = document.body.clientHeight;
			//var t = scrollTop + (h/2);
			var t = scrollTop;
			updateMsg.style.left = "" + ((w/2) - 120) + "px";
			updateMsg.style.top = "" + t + "px";

			updateMsg.style.position = "absolute";  // must use absolute for ie.
			updateMsg.style.display = "";
		}  
</script>

<?php
	$scroll = "";
	if (trim($pageScrollTop != ""))
	{
		$pageScrollLeft = 0;
		$scroll = " scrollTo($pageScrollLeft, $pageScrollTop);";
	}
	$onclose = "";
	if (trim($pageOnUnload != ""))
	{
		$onclose = "onUnload='$pageOnUnload'";
	}


	print "<body onLoad='defaultOnLoad(); $scroll' $onclose>";

	?>
<!-- LOGO -->
<img src='<?php print $themeLocation; ?>/images/fp_banner_default_226px.png' alt='FlightPath' title='FlightPath'>
		<div id='updateMsg' class='updateMsg' style='display: none;'>Updating...</div>
		<div id='loadMsg' class='updateMsg' style='display: none;'>Loading...</div>

<table border='0' width='100%' cellspacing='0' cellpadding='0'>

<?php
if ($pageTabs) {
  print "
  <tr>
   <td>
			<table width='100%' cellpadding='0' cellspacing='0' align='left'>
				<tr>
					<td align='left'>
					$pageTabs										
					</td>
				</tr>
			</table>
  </td>
  </tr>
  ";
  
}
?>
<tr>
<td valign='top'>
<!-- PAGE CONTENT -->
<?php print $pageContent; ?>
</td> 
</tr>
</table>

<!--
<div class='fpcopy-notice'>
&copy; <a href='http://www.ulm.edu' class='nounderline'>University of Louisiana at Monroe</a>
</div>
-->

</body>