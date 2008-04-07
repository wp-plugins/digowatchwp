<?php
/*
Plugin Name: DigoWatchWP
Plugin URI: http://www.showhypnose.org/blog/2008/03/19/digowatchwp-an-anti-fraud-wordpress-plugin/
Description: An anti-fraud plugin for WordPress
Version: 0.9
Author: Wolfgang Digo Hollin
Author URI: http://www.showhypnose.org/blog/

Instructions

Copy this file you unzipped into the wp-content/plugins folder of WordPress, 
then go to Administration > Plugins, it should be in the list.
 
In the administration area click Activate for DigoWatchWP.
This will create the database table used by DigoWatchWP.

DigoWatchWP and it is now checking your posts and pages hourly.
Optional: go into Options > DigoWatchWP to modify some values

(c) 2008 by Wolfgang 'Digo' Hollin
Please do not remove the backlink to my blog - DigoWatchWP is "linkware"
*/

register_activation_hook(__FILE__, 'digo_watchwp_activate');
register_deactivation_hook(__FILE__,"digo_watchwp_deactivate");

add_action('admin_menu','digo_watchwp_menu');
add_action('digo_watchwp_my_hourly_event', 'digo_watchwp_do_this_hourly');

add_action('wp_footer', 'digo_watchwp_insert_footer', 3);

function digo_watchwp_activate() {	
	DigoWatchWP_CreateTables(1);
	add_option("digo_watchwp_report_email", "", "", "yes");
	add_option("digo_watchwp_footer_link", "1", "", "yes");
	// digo_watchwp_do_this_hourly();	
	wp_schedule_event(time(), 'hourly', 'digo_watchwp_my_hourly_event');	
}

function digo_watchwp_deactivate() {
   wp_clear_scheduled_hook('digo_watchwp_my_hourly_event');   
   DigoWatchWP_CreateTables(0);
   delete_option("digo_watchwp_report_email"); 
   delete_option("digo_watchwp_footer_link");
}


function digo_watchwp_do_this_hourly() {
   $wpdb =& $GLOBALS['wpdb'];
   $MyMailBody = "";
   
   // first of all let´s do some cleanup
   DigoWatchWP_FindDeletedPosts (false);	
	
   $BlogPostsTable = $wpdb->prefix . 'posts';
   $sql = "SELECT ID, post_content, post_title FROM $BlogPostsTable ORDER BY post_modified DESC";
   $dbresult = $wpdb->get_results($sql);
   
   if (!is_array($dbresult)) {
      return; // no posts
   }
	
   $i = 0;
   $num = count ($dbresult);
   
   while ($i < $num) {

      $BlogPost      = $dbresult[$i]->post_content;
      $BlogPostTitle = $dbresult[$i]->post_title;
      $BlogPostID    = $dbresult[$i]->ID;
      
      $BlogPost = md5("$BlogPost");
      
      $rc = DigoWatchCheckMD5InDB ($BlogPostID, $BlogPost);      
      if ($rc == 1) {
         $MyMailBody .= "\nadded new post (ID: $BlogPostID) $BlogPostTitle";	
      }
      
      if ($rc == 99) {
         $MyMailBody .= "\npost has been changed (ID: $BlogPostID) $BlogPostTitle";	      	
      }
      
      $i++;
   }
      
	$aktuell = date("Y-m-d H:i:s");
		
	$MyRecipient = DigoWatchWP_Recipient ();
	
	$MyBlogTitle = get_bloginfo('name');
	if (strlen($MyMailBody)) {
	   $MyMailBody .= "\n\nThanks for using DigoWatchWP-Plugin.\nPlease visit my homepage: http://www.showhypnose.org/blog/\n\nYou like this Wordpress-Plugin?\nYou can show it by donating: http://www.showhypnose.org/donate.php";
	   wp_mail($MyRecipient, "[$MyBlogTitle] DigoWatchWP-Report", "DigoWatchWP-Report from: $aktuell\n$MyMailBody");
	}


}

function DigoWatchWP_Recipient () {
	$MyRecipient = get_option('digo_watchwp_report_email');
	if (strlen($MyRecipient) == 0) {
	   $MyRecipient = get_option('admin_email');
	}
	
	return $MyRecipient;
}

// activate == 1 --> create table
//          == 0 --> drop table
function DigoWatchWP_CreateTables ($activate) {
   $pre = $GLOBALS['table_prefix'];
   $wpdb =& $GLOBALS['wpdb'];
   
   $MyTableName = GetDigoWatchWPTable ();
   $sql = "SHOW TABLES LIKE \'$MyTableName\'";   
   $results = $wpdb->query($sql);
  
   
   $MyRecipient = DigoWatchWP_Recipient ();
   $aktuell = date("Y-m-d H:i:s");
   $MyBlogTitle = get_bloginfo('name');

   if ($results == 0) {
   	  if ($activate == 1) {
         $sql = "create table $MyTableName (
            id integer not null auto_increment,
            `post_id` bigint(20) unsigned NOT NULL,
          	post_md5 varchar(255) not null default 'unknown',          	
            primary key(id)
          )";

         $results = $wpdb->query($sql);

         $MyMailBody = "DigoWatchWP-Plugin has been activated - you should receive an initial-report soon.\n\n";
         $MyMailBody .= "Table $MyTableName has been created.\n\n";         
         $MyMailBody .= "\n\nThanks for using DigoWatchWP-Plugin.\nPlease visit my homepage: http://www.showhypnose.org/blog/\n\nYou like this Wordpress-Plugin?\nYou can show it by donating: http://www.showhypnose.org/donate.php";

         wp_mail($MyRecipient, "[$MyBlogTitle] DigoWatchWP-Report", "DigoWatchWP-Report from: $aktuell\n\n$MyMailBody");


   	  }
   }
   
   if ($activate == 0) {
      $sql = "DELETE FROM $MyTableName WHERE id > 0)";   
      $results = $wpdb->query($sql);
      
	  $MyMailBody = "DigoWatchWP-Plugin has been deactivated - so it is no longer watching\nyour posts and pages for changes.\n\n";
	  $MyMailBody .= "All data in $MyTableName has been removed, but the table is still there.\n\n";
	  $MyMailBody .= "What if I want to re-enable DigoWatchWP later?\n\nNo problem. Go back to the Plugins page and click *Activate* for DigoWatchWP.";
	  $MyMailBody .= "\n\nThanks for using DigoWatchWP-Plugin.\nPlease visit my homepage: http://www.showhypnose.org/blog/\n\nYou like this Wordpress-Plugin?\nYou can show it by donating: http://www.showhypnose.org/donate.php";
	  
      wp_mail($MyRecipient, "[$MyBlogTitle] DigoWatchWP-Report", "DigoWatchWP-Report from: $aktuell\n\n$MyMailBody");
      // wp_mail($MyRecipient, "test", "Plugin deactivated $MyRecipient");	  	         
   }
   	  
   
            
      
   
   
   
}

function GetDigoWatchWPTable() {
	return $GLOBALS['table_prefix'] . "Digo_Watch_WP";
}


function DigoWatchWP_FindDeletedPosts ($output) {
	$wpdb =& $GLOBALS['wpdb'];
	
	$out = "<br /><br /><strong>DigoWatchWP-Statistics</strong><br />";
	$MyTableName = GetDigoWatchWPTable();
	
	$sql = "SELECT post_id FROM $MyTableName";
	$dbresult = $wpdb->get_results($sql);
	
	if (!$dbresult) {
		return; // no records found
	}
	
   $i = 0;
   $num = count ($dbresult);
   
   $out .= "<br />DigoWatchWP is watching <strong>$num</strong> records<br /><br />";
   
   while ($i < $num) {
      $CheckThisID = $dbresult[$i]->post_id;
      $BlogPostsTable = $wpdb->prefix . 'posts';
      
      $sql = "SELECT ID, post_type, post_title FROM $BlogPostsTable WHERE ID = $CheckThisID";
      $postInDB = $wpdb->get_results($sql);
      
      $out .= "checking id: $CheckThisID ....";
      if (!$postInDB) { //ok, we have to delete this record
         $sql = "DELETE FROM $MyTableName WHERE post_id = $CheckThisID";
         $dbresult = $wpdb->get_results($sql);	         
         $out .= " not found - have to delete record<br />";
      
      }
      else {
      	$post_title = $postInDB[0]->post_title;
      	$post_type  = $postInDB[0]->post_type;
      	$out .= " found ($post_title [$post_type])<br />";
      }
      
      $i++;
   }
   
   if ($output == true) {
      echo ($out);
   }
	
	
	
}

// --------------------------------------
// function returns 1 for new record,
//                 0 if check is ok
//                 99 if check is not ok
// --------------------------------------- 
function DigoWatchCheckMD5InDB ($BlogPostID, $BlogPostMD5) {
	$wpdb =& $GLOBALS['wpdb'];
	
	$MyTableName = GetDigoWatchWPTable();
	

	// Is there a record for BlogPostID?		
	$sql = "SELECT post_md5 FROM $MyTableName WHERE post_id = $BlogPostID";
	$dbresult = $wpdb->get_results($sql);


   if (!$dbresult) { // no record found, we have to insert
      $sql = "INSERT INTO $MyTableName (post_id, post_md5) VALUES ($BlogPostID, '$BlogPostMD5')";
      $wpdb->get_results($sql);
      return 1; // 1 .... new record added
   }
   
   
   $StoredMD5 = $dbresult[0]->post_md5;
      
   
   if ($StoredMD5 == $BlogPostMD5) {
      return 0; // everything is ok
   }
   else {
      $sql = "UPDATE $MyTableName SET post_md5 = '$BlogPostMD5' WHERE post_id = $BlogPostID";
      $wpdb->get_results($sql);
   	  return 99; // post has been changed since last check
   }
   
	
	
}

/**
 * digo_watchwp_menu() - Function that creates the Administration Menu for digo_watchwp
 * 
 */
function digo_watchwp_menu() {
     add_options_page(
                      'DigoWatchWP',		//Title
                      'DigoWatchWP',		//Sub-menu title
                      'manage_options',		//Security
                      __FILE__,			//File to open
                      'digo_watchwp_options'	//Function to call
                     );  
}

/**
 * digo_watchwp_options() - 
 * 
 */
function digo_watchwp_options () {
     echo '<div class="wrap"><h2>DigoWatchWP Options</h2>';
     if ($_REQUEST['submit']) {
	    digo_watchwp_updateOptions();
     }
     
     digo_watchwp_form();
     DigoWatchWP_FindDeletedPosts (true);
     echo '</div>';
}

/**
 * privatePlus_form() - 
 * 
 */
function digo_watchwp_form () {
	
	$MyNewsContent = digo_watchwp_url_fopen("http://www.showhypnose.org/DigoWatchWP.txt", false);
	
	$ReportEmail = get_option('digo_watchwp_report_email');
	
	// if it´s empty use default (admin_email) 
	if (!strlen($ReportEmail)) {
		$ReportEmail = get_option('admin_email');
	}
?>
<div style="width: 200px; float: right; border: 1px solid #14568A;">
  <div style="width: 195px; background: #0D324F; color: white; padding: 0 0 0 5px;">About this Plugin:</div>
  <div style="width: 180px; padding: 10px;">
    <a href="http://www.showhypnose.org/blog/2008/03/19/digowatchwp-an-anti-fraud-wordpress-plugin/" target="_blank">Plugin Homepage</a><br />
    <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=paypal%40digo%2ecc&item_name=Donation%20for%20DigoWatchWP%20Wordpress%20Plugin&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=AT&bn=PP%2dDonationsBF&charset=UTF%2d8" target="_blank">Donate with PayPal</a><br />
  </div>
</div>

<p>DigoWatchWP scans your blog posts and pages for changes. Whenever an entry has been changed it informs you via email.<br /><br />So if you receive an email and you have nothing changed you should have a closer look at your post or page. Maybe somebody changed your post or page to include a spam-link (e.g. links to OnlineCasino, adult-content are very popular).</p>

<?php

    echo ("$MyNewsContent<br /><br />");

	echo ' <form method="post"> ';
	echo ' <label><b>Send DigoWatchWP-Reports to:</b></label><br />';
    echo ' <input type="text" name="digo_watchwp_report_email" value="' . $ReportEmail . '" /> ';
    
	echo ' <br /><br /> ';
	echo ' <input type="submit" name="submit" value="Submit" /> ';
	echo ' </form> ';
	
	
	echo ' <br /><br /> ';	
    echo ' <form method="post"> ';
	echo ' <label><b>Run DigoWatchWP now:</b></label><br />';
    echo ' <input type="hidden" name="digo_watchwp_run_now" value="1" /> ';	
	echo ' <input type="submit" name="submit" value="Run DigoWatchWP now" /> ';
	echo ' </form> ';
	
	echo ' <br /><br /> ';
	echo ' <form method="post"> ';
	echo ' <label><b>If you want to clear the DigoWatchDB-Table press the button:</b></label><br />';
    echo ' <input type="hidden" name="digo_watchwp_reset_db" value="1" /> ';	
	echo ' <input type="submit" name="submit" value="Clear DigoWatchWP-Table" /> ';
	echo ' </form> ';
		
	// Get current backlink value
	$BackLinkInFooter = get_option('digo_watchwp_footer_link');
	$DigoBackLinkCheckBox = "";
	if ($BackLinkInFooter != 0) {
		$DigoBackLinkCheckBox = "checked";
	}
	
	echo ' <br /><br /> ';
	echo ' <form method="post"> ';
	echo ' <label><b> Show backlink in footer: </b></label><br />';
    echo '<input type="checkbox" name="BackLinkInFooterForm" ' . $DigoBackLinkCheckBox . ' />Show backlink in footer';
    
	echo ' <br /> ';
	echo ' <input type="submit" name="submit" value="Save backlink option" /> ';
	echo ' </form> ';	

}


/**
 * digo_watchwp_updateOptions() - 
 * 
 */
function digo_watchwp_updateOptions() {
	$wpdb =& $GLOBALS['wpdb'];
	
     $updated = false;
     if ($_REQUEST['digo_watchwp_report_email']) {
          update_option('digo_watchwp_report_email', $_REQUEST['digo_watchwp_report_email']);
          $MyMsg = "Options updated";
          $updated = true;
     }
     if ($_REQUEST['digo_watchwp_reset_db']) {
     	$MyTableName = GetDigoWatchWPTable ();
        $sql = "DELETE FROM $MyTableName WHERE id > 0";
        $wpdb->get_results($sql);
        $MyMsg = "Table cleared - please start DigoWatchWP now";
        $updated = true;
     }
     
     if ($_REQUEST['digo_watchwp_run_now']) {
     	digo_watchwp_do_this_hourly();
     	$MyMsg = "Script started";
        $updated = true;
     }
     
     if ($_REQUEST['submit'] == "Save backlink option") {     	
     	if ($_REQUEST['BackLinkInFooterForm']) {
     		$BackLinkValue = 1;
     		$MyMsg = "Options updated";
     	}
     	else {
     		$BackLinkValue = 0;
     		$MyMsg = "Backlink in footer has been removed.<br /><br /><strong>Please remember:</strong><br />DigoWatchWP is linkware - so please <strong>do not forget to place a backlink to my homepage</strong> within your blog!";
     	}
     	
     	update_option('digo_watchwp_footer_link', $BackLinkValue);     	
     	$updated = true;
     }
     
     if ($updated) {
           echo '<div id="message" class="updated fade">';
           echo "<p>$MyMsg</p>";
           echo '</div><br /><br />';
      } else {
           echo '<div id="message" class="error fade">';
           echo '<p>Unable to update options</p>';
           echo '</div><br /><br />';
      }
 }

function digo_watchwp_insert_footer() {	
   $BackLinkInFooter = get_option('digo_watchwp_footer_link');
   if ($BackLinkInFooter != 0) {					
      echo ('This blog uses <a href="http://www.showhypnose.org/blog/2008/03/19/digowatchwp-an-anti-fraud-wordpress-plugin/">DigoWatchWP an anti-fraud plugin</a> for Wordpress.');
   }
} 


function digo_watchwp_url_fopen($url, $convert_case = false, $postinfo = array()) {

	$curl_error = 0;
	$file_content = "";

	if(ini_get('allow_url_fopen') && (empty($postinfo)) && ($file = @fopen ($url, "rb")) )	{
		$i = 0;
		while (!feof($file) && $i++ < 1000) {
			if ($convert_case)
			$file_content .= strtolower(fread($file, 4096));
			else
			$file_content .= fread($file, 4096);
		}
		fclose($file);
	}
	else if (function_exists("curl_init")) {
		$curl_handle=curl_init();
		curl_setopt($curl_handle,CURLOPT_URL, $url);
		curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
		curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl_handle,CURLOPT_FAILONERROR,1);
		curl_setopt($curl_handle,CURLOPT_USERAGENT, "PHP DigoWatchWP Wordpress Plugin ");
		if (!empty($postinfo)) {
			curl_setopt($curl_handle, CURLOPT_POST, true);
			curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $postinfo);
		}
		$file_content = curl_exec($curl_handle);
		$curl_error = curl_errno($curl_handle);

		curl_close($curl_handle);
	}
	else {
		$file_content = "";
	}

	return $file_content;
}
 
?>
