<?php
/*
Plugin Name: JuLiA Beta
Plugin URI: http://adaptivesemantics.com/wordpress
Description: JuLiA is Just a Linguistic Algorithm. This plugin checks your comments against the JuLiA web service, which supplies an abusiveness score for each comment. Based on this score, comments are auto-published, auto-deleted, or marked for review. Over time, scores are aggregated by commenter in order to generate abusiveness rankings and other statistics.
Version: 0.9
Author: Jeff Revesz and Elena Haliczer
Author URI: http://adaptivesemantics.com
*/

// Updated 03-12-2009
if (!class_exists("julia")) {
	class julia {
					
		var $adminOptionsName = "juliaOptions";

    function julia() { //constructor
  	}

		function parseComment($comment_ID) {
			require_once('wp-load.php');
			global $wpdb;
			$devOptions = $this->getAdminOptions();
			$wp_prefix = $wpdb->prefix;
			$new_comments = $wpdb->get_results("SELECT * FROM ".$wp_prefix."comments WHERE comment_ID = ".$comment_ID);
			$flagged_authors = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
			$is_flagged_author = false;
			foreach ($new_comments as $new_comment) {
				$comment_content = $new_comment->comment_content;
				$comment_author = $new_comment->comment_author;
				foreach($flagged_authors as $flagged_author) {
					if ($comment_author == $flagged_author) {
						$is_flagged_author = true;
					}
				}
			}
			$trusted_authors = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
			$is_trusted_author = false;
			foreach ($new_comments as $new_comment) {
				$comment_content = $new_comment->comment_content;
				$comment_author = $new_comment->comment_author;
				foreach($trusted_authors as $trusted_author) {
					if ($comment_author == $trusted_author) {
						$is_trusted_author = true;
					}
				}
			}
			$api_key = $devOptions['api_key'];
			$result = XMLRPC_request('adaptivesemantics.com:8080', '/', 'api.julia_parse', array(XMLRPC_prepare($comment_content), XMLRPC_prepare($api_key)));
			$wpdb->query("UPDATE ".$wp_prefix."comments SET julia_abusive_score = ".$result[1]." WHERE comment_ID = ".$comment_ID);
			$publish_threshold = -(floatval($devOptions['publish_threshold'])/floatval(100));
			$delete_threshold = (floatval($devOptions['delete_threshold'])/floatval(100));
	    if (($result[1] < $publish_threshold && $is_flagged_author == false) || $is_trusted_author) {
       $wpdb->query("UPDATE ".$wp_prefix."comments SET comment_approved = 1 WHERE comment_ID = ".$comment_ID);
     	}
			else {
       $wpdb->query("UPDATE ".$wp_prefix."comments SET comment_approved = 0 WHERE comment_ID = ".$comment_ID);
			}
		}

		function juliaCommentDisplay($comment) {
//			require_once('wp-load.php');
			
			global $wpdb;
			$devOptions = $this->getAdminOptions();
			$comment_id = $comment->comment_ID;
			$content = $comment->comment_content;
			$current_url = $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]; 
			$comment_approved = 0;
			$publish_threshold = intval($devOptions['publish_threshold']);
			$delete_threshold = intval($devOptions['delete_threshold']);
			$comments_view = "";
			if (strpos($current_url, "edit-comments.php?comment_status=abusive")) {
				$comments_view = "abusive";
			}
			elseif (strpos($current_url, "edit-comments.php?comment_status=moderated")) {
				$comments_view = "pending";
			}
			elseif (strpos($current_url, "edit-comments.php") == false) {
				$comments_view = "public";
			}
			if (strpos($content, "<!-- Touched by JuLiA -->") == false && $content != NULL) {
				$wp_prefix = $wpdb->prefix;
				$comment_author = $comment->comment_author;
				$flagged_authors = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
				$is_flagged_author = false;
				foreach($flagged_authors as $flagged_author) {
					if ($comment_author == $flagged_author) {
						$is_flagged_author = true;
					}
				}
				$trusted_authors = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
				$is_trusted_author = false;
				foreach($trusted_authors as $trusted_author) {
					if ($comment_author == $trusted_author) {
						$is_trusted_author = true;
					}
				}
				$comments_show = $wpdb->get_results("SELECT * FROM ".$wp_prefix."comments WHERE comment_ID =".$comment_id);
				foreach ($comments_show as $comment_show) {
					$comment_approved = $comment_show->comment_approved;
					$julia_abusive_score = $comment_show->julia_abusive_score;
				}
				$julia_abusive_score_print = round($julia_abusive_score*100);
//			echo $julia_abusive_score_print;
				$site_url = get_option("siteurl");
				$images_url = $site_url."/wp-content/plugins/julia-beta/images";
				if ($devOptions['show_character'] == "yes") {
					$julia_approving_display = "<div style=\"visibility: visible; text-align: right; margin-top: -53px; \"><img height=\"50\" width=\"50\" src=\"".$images_url."/julia_approving.gif\" /></div>";
					$julia_cautious_display = "<div style=\"visibility: visible; text-align: right; margin-top: -53px; \"><img height=\"50\" width=\"50\" src=\"".$images_url."/julia_cautious.gif\" /></div>";
					$julia_disapproving_display = "<div style=\"visibility: visible; text-align: right; margin-top: -53px; \"><img height=\"50\" width=\"50\" src=\"".$images_url."/julia_disapproving.gif\" /></div>";
					if ($devOptions['score_format'] == "category") {
						$definitely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: green;\"><b>Definitely Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 62px; \"><i>Auto-Published</i></p>";
						$likely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: green;\"><b>Likely Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 62px; \"><i>Held For Review</i></p>";
						$definitely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: red;\"><b>Definitely Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 62px; \"><i>Quarantined</i></p>";
						$likely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: red;\"><b>Likely Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 62px; \"><i>Held For Review</i></p>";
						$possibly_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: goldenrod;\"><b>Possibly Abusive</b></p><p style=\"text-align: right; color: goldenrod; margin-top: -8px; margin-right: 62px; \"><i>Held For Review</i></p>";
					}
					else {
						$definitely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: green;\"><b>".abs($julia_abusive_score_print)."% Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 62px; \"><i>Auto-Published</i></p>";
						$likely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: green;\"><b>".abs($julia_abusive_score_print)."% Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 62px; \"><i>Held For Review</i></p>";
						$definitely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: red;\"><b>".abs($julia_abusive_score_print)."% Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 62px; \"><i>Quarantined</i></p>";
						$likely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: red;\"><b>".abs($julia_abusive_score_print)."% Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 62px; \"><i>Held For Review</i></p>";
						$possibly_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; margin-right: 60px; color: goldenrod;\"><b>".abs($julia_abusive_score_print)."% Abusive</b></p><p style=\"text-align: right; color: goldenrod; margin-top: -8px; margin-right: 62px; \"><i>Held For Review</i></p>";
					}
				}
				else {
					$julia_approving_display = "";
					$julia_cautious_display = "";
					$julia_disapproving_display = "";
					if ($devOptions['score_format'] == "category") {
						$definitely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; color: green;\"><b>Definitely Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 2px; \"><i>Auto-Published</i></p>";
						$likely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; color: green;\"><b>Likely Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 2px; \"><i>Held For Review</i></p>";
						$definitely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; color: red;\"><b>Definitely Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 2px; \"><i>Quarantined</i></p>";
						$likely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; color: red;\"><b>Likely Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 2px; \"><i>Held For Review</i></p>";
						$possibly_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; color: goldenrod;\"><b>Possibly Abusive</b></p><p style=\"text-align: right; color: goldenrod; margin-top: -8px; margin-right: 2px; \"><i>Held For Review</i></p>";
					}
					else {
						$definitely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; color: green;\"><b>".abs($julia_abusive_score_print)."% Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 2px; \"><i>Auto-Published</i></p>";
						$likely_clean_display = "<p>&nbsp;</p><p style=\"text-align: right; color: green;\"><b>".abs($julia_abusive_score_print)."% Clean</b></p><p style=\"text-align: right; color: green; margin-top: -8px; margin-right: 2px; \"><i>Held For Review</i></p>";
						$definitely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; color: red;\"><b>".abs($julia_abusive_score_print)."% Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 2px; \"><i>Quarantined</i></p>";
						$likely_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; color: red;\"><b>".abs($julia_abusive_score_print)."% Abusive</b></p><p style=\"text-align: right; color: red; margin-top: -8px; margin-right: 2px; \"><i>Held For Review</i></p>";
						$possibly_abusive_display = "<p>&nbsp;</p><p style=\"text-align: right; color: goldenrod;\"><b>".abs($julia_abusive_score_print)."% Abusive</b></p><p style=\"text-align: right; color: goldenrod; margin-top: -8px; margin-right: 2px; \"><i>Held For Review</i></p>";
					}
				}
				if ($julia_abusive_score_print < 0) {
					if (abs($julia_abusive_score_print) >= abs(floatval($publish_threshold))) {
						$content_add = $definitely_clean_display.$julia_approving_display;
					}
					elseif (abs($julia_abusive_score_print) >= abs(floatval($publish_threshold) / 2.0)) {
						$content_add = $likely_clean_display.$julia_approving_display;
					}
					else {
						$content_add = $possibly_abusive_display.$julia_cautious_display;
					}
				}
				else {
					if (abs($julia_abusive_score_print) >= abs(floatval($delete_threshold))) {
						$content_add = $definitely_abusive_display.$julia_disapproving_display;
					}
					elseif (abs($julia_abusive_score_print) >= abs(floatval($delete_threshold) / 2.0)) {
						$content_add = $likely_abusive_display.$julia_disapproving_display;
					}
					else {
						$content_add = $possibly_abusive_display.$julia_cautious_display;
					}
				}
				if ($is_flagged_author && strpos($content_add, "Auto-Published")) {
					$content_replace = str_replace("Auto-Published", "Held for Review", $content_add);
					$content_add = $content_replace;
				}
				if ($is_trusted_author && strpos($content_add, "Held For Review")) {
					$content_replace = str_replace("Held For Review", "Auto-Published", $content_add);
					$content_add = $content_replace;
				}
				if ($is_trusted_author && strpos($content_add, "Quarantined")) {
					$content_replace = str_replace("Quarantined", "Auto-Published", $content_add);
					$content_add = $content_replace;
				}
				if ($comments_view != "public" || $devOptions['public_display'] != "no") {
					$content .= $content_add;
				}
				$content .= "<!-- Touched by JuLiA -->";
				$comment->comment_content = $content;
				if ($comments_view == "abusive" && ($julia_abusive_score_print < abs($delete_threshold) || $comment_approved == 1 || $comment_approved == "spam" )) {
					$comment = NULL;
					$comment->comment_ID = "'style=\"position: absolute; visibility: hidden; margin-top: -50px;\"";
				}
				elseif ($comments_view == "pending" && $julia_abusive_score_print > abs($delete_threshold)) {
					$comment = NULL;
					$comment->comment_ID = "'style=\"position: absolute; visibility: hidden; margin-top: -50px;\"";
				}
			}
			if ($comment_id == NULL) {
				$comment = NULL;
				$comment->comment_ID = "'style=\"position: absolute; visibility: hidden; margin-top: -50px;\"";
			}
			return $comment;
		}

		function editAdminMenu($menu = '') {
			$devOptions = $this->getAdminOptions();
			$delete_threshold = (floatval($devOptions['delete_threshold'])/floatval(100));
			global $wpdb;
			$wp_prefix = $wpdb->prefix;
			$results = $wpdb->get_results("SELECT COUNT(*) AS HOW_MANY FROM ".$wp_prefix."comments WHERE comment_approved != 1 AND comment_approved != \"spam\" AND julia_abusive_score > ".$delete_threshold);
			foreach ($results as $result) {
				$julia_abusive_count = $result->HOW_MANY;
			}
			$results = $wpdb->get_results("SELECT COUNT(*) AS HOW_MANY FROM ".$wp_prefix."comments WHERE comment_approved != 1 AND comment_approved != \"spam\"");
			foreach ($results as $result) {
				$pending_count = $result->HOW_MANY;
			}
			$new_pending_count = $pending_count - $julia_abusive_count;
			if ($new_pending_count < 0) {
				$new_pending_count = 0;
			}
			$menu_keys = array_keys($menu);
			foreach($menu_keys as $menu_key) {
				$sub_menu = $menu[$menu_key];
				if (strpos($sub_menu[0], "omments")) {
					if ($new_pending_count > 0) {
						$menu_replace = str_replace(strval($pending_count), strval($new_pending_count), $menu[$menu_key][0]);
					}
					else {
						$menu_replace = str_replace(strval("<span class='pending-count'>".$pending_count."</span>"), "", $menu[$menu_key][0]);
					}
					$menu[$menu_key][0] = $menu_replace;
				}
			}
			return $menu;
		}

		function commentStatusLinks($comment_statii = '') {
			$current_url = $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]; 
			if (strpos($current_url, "edit-comments.php?comment_status=abusive")) {
				$comments_view = "abusive";
			}
			elseif (strpos($current_url, "edit-comments.php?comment_status=moderated")) {
				$comments_view = "pending";
			}
			else {
				$comments_view = "neither";
			}
			$devOptions = $this->getAdminOptions();
			$delete_threshold = (floatval($devOptions['delete_threshold'])/floatval(100));
			global $wpdb;
			$wp_prefix = $wpdb->prefix;
			$results = $wpdb->get_results("SELECT COUNT(*) AS HOW_MANY FROM ".$wp_prefix."comments WHERE comment_approved != 1 AND comment_approved != \"spam\" AND julia_abusive_score > ".$delete_threshold);
			foreach ($results as $result) {
				$julia_abusive_count = $result->HOW_MANY;
			}
			$results = $wpdb->get_results("SELECT COUNT(*) AS HOW_MANY FROM ".$wp_prefix."comments WHERE comment_approved != 1 AND comment_approved != \"spam\"");
			foreach ($results as $result) {
				$pending_count = $result->HOW_MANY;
			}
			$new_pending_count = $pending_count - $julia_abusive_count;
			if ($new_pending_count < 0) {
				$new_pending_count = 0;
			}
			if ($comments_view == "abusive") {
				$comment_statii[1] = "<li class='abusive'><a href='edit-comments.php?comment_status=moderated'>Pending (<span class=\"pending-count\">".$new_pending_count."</span>)</a>";
				$comment_statii[4] = "<li class='abusive'><b><a href='edit-comments.php?comment_status=abusive' style=\"color: black;\">Abusive (<span class=\"abusive-count\">".$julia_abusive_count."</span>)</a></b>";
			}
			elseif ($comments_view == "pending") {
				$comment_statii[1] = "<li class='abusive'><b><a href='edit-comments.php?comment_status=moderated' style=\"color: black;\">Pending (<span class=\"pending-count\">".$new_pending_count."</span>)</a></b>";
				$comment_statii[4] = "<li class='abusive'><a href='edit-comments.php?comment_status=abusive'>Abusive (<span class=\"abusive-count\">".$julia_abusive_count."</span>)</a>";
			}
			else {
				$comment_statii[1] = "<li class='abusive'><a href='edit-comments.php?comment_status=moderated' >Pending (<span class=\"pending-count\">".$new_pending_count."</span>)</a>";
				$comment_statii[4] = "<li class='abusive'><a href='edit-comments.php?comment_status=abusive'>Abusive (<span class=\"abusive-count\">".$julia_abusive_count."</span>)</a>";
			}
			return $comment_statii;
		}

		//Returns an array of admin options
		function getAdminOptions() {
			$juliaOptions = array('publish_threshold' => '75',
				'delete_threshold' => '90',
				'score_format' => 'category',
				'show_character' => 'yes',
				'public_display' => 'no',
				'report_timeframe' => '7');
			$devOptions = get_option($this->adminOptionsName);
			if (!empty($devOptions)) {
				foreach ($devOptions as $key => $option)
					$juliaOptions[$key] = $option;
			}
			update_option($this->adminOptionsName, $juliaOptions);
			return $juliaOptions;
		}

		function init() {
			global $wpdb;
			global $current_user;
			global $user_email;

			$site_url = get_option("siteurl");
			$current_user_name = $current_user->user_login;
			$devOptions = $this->getAdminOptions();
			$includes_abusive_field = false;
			$wp_prefix = $wpdb->prefix;
			$abusive_field_name = "julia_abusive_score";
			$wp_columns = $wpdb->get_results("SHOW COLUMNS FROM ".$wp_prefix."comments");
			foreach ($wp_columns as $wp_column) {
				if ($wp_column->Field == "julia_abusive_score") {
					$includes_abusive_field = true;
				}
			}
			if ($includes_abusive_field == false) {
				$wpdb->query("ALTER TABLE ".$wp_prefix."comments ADD julia_abusive_score float DEFAULT 0 NOT NULL");
			}
			$wpdb->query("CREATE TABLE IF NOT EXISTS ".$wp_prefix."julia_flagged_authors (id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY, author_name TINYTEXT)");
			$wpdb->query("CREATE TABLE IF NOT EXISTS ".$wp_prefix."julia_trusted_authors (id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY, author_name TINYTEXT)");
			$wpdb->query("INSERT INTO ".$wp_prefix."julia_trusted_authors (author_name) VALUE (\"".$current_user_name."\")");
			$new_key = XMLRPC_request('adaptivesemantics.com:8080', '/', 'api.generate_key', array(XMLRPC_prepare($site_url), XMLRPC_prepare($user_email)));
			$devOptions['api_key'] = $new_key[1];
			update_option($this->adminOptionsName, $devOptions);			
		}

		function printAbuseReport() {
			global $wpdb;
			$wp_prefix = $wpdb->prefix;
			$devOptions = $this->getAdminOptions();
			$report_timeframe = $devOptions['report_timeframe'];
			if (isset($_POST['update_juliaFlags'])) {
				$users = $wpdb->get_results("SELECT comment_author, COUNT(*) AS HOW_MANY, AVG(julia_abusive_score) AS SCORE FROM ".$wp_prefix."comments WHERE comment_date > SUBTIME(NOW(),'".$report_timeframe." 0:0:0') GROUP BY comment_author ORDER BY AVG(julia_abusive_score) DESC LIMIT 10");
				$i = 0;
				foreach($users as $user) {
					$i += 1;
					$author_name = $user->comment_author;
					$current_index = "flagUser".$i;
					if ($_POST[$current_index] == "on") {
						$author_exists = false;
						$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
						foreach($results as $result) {
							if ($result == $author_name) {
								$author_exists = true;
							}
						}
						if ($author_exists == false) {
							$wpdb->query("INSERT INTO ".$wp_prefix."julia_flagged_authors (author_name) VALUE (\"".$author_name."\")");
						}
					}
					else {
						$author_exists = false;
						$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
						foreach($results as $result) {
							if ($result == $author_name) {
								$author_exists = true;
							}
						}
						if ($author_exists != false) {
							$wpdb->query("DELETE FROM ".$wp_prefix."julia_flagged_authors WHERE author_name = \"".$author_name."\"");
						}
					}
				}
				?>
				<div class="updated"><p><strong><?php _e("Flags Updated", "julia"); ?></strong></p></div>
				<?php
			}
			$site_url = get_option("siteurl");
			$users = $wpdb->get_results("SELECT comment_author, COUNT(*) AS HOW_MANY, AVG(julia_abusive_score) AS SCORE FROM ".$wp_prefix."comments WHERE comment_date > SUBTIME(NOW(),'".$report_timeframe." 0:0:0') GROUP BY comment_author ORDER BY AVG(julia_abusive_score) DESC LIMIT 10");
			?>
			<div class=wrap>
			<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
			<h2>Most Abusive Commenters from the Past <?php _e(apply_filters('format_to_edit', $report_timeframe)) ?> Days</h2>
			<br>
			<table cellspacing="10">
			<tr>
			<td></td>
			<td style="padding-left:10px"><b><u>Commenter</u></b></td>
			<td style="padding-left:10px" align="center"><b><u># Comments</u></b></td>
			<td style="padding-left:10px" align="center"><b><u>Average JuLiA Score</u></b></td>
			<td style="padding-left:10px" align="center"><b><u>Flagged</u></b></td>
			</tr>
			<?php
			$i = 0;
			foreach($users as $user) {
				$i += 1;
				$author_flagged = "";
				$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");			
				foreach($results as $result) {
					if ($result == $user->comment_author) {
						$author_flagged = "checked=\"checked\"";
					}
				}
				if ($user->SCORE < 0) {
					$score_int = abs(round($user->SCORE*100));
					$score_print = "<span style=\"color: green;\">".$score_int."% Clean</span>";
				}
				else {
					$score_int = abs(round($user->SCORE*100));
					$score_print = "<span style=\"color: red;\">".$score_int."% Abusive</span>";
				}
				?>
				<tr><td><b><?php _e(apply_filters('format_to_edit', $i."."), 'julia') ?></b></td><td style="padding-left:10px" align="left"><?php _e(apply_filters('format_to_edit', $user->comment_author), 'julia') ?></td><td style="padding-left:10px" align="center"><?php _e(apply_filters('format_to_edit', $user->HOW_MANY)) ?></td><td style="padding-left:10px" align="center"><?php _e(apply_filters('format_to_edit', $score_print)) ?></td><td style="padding-left:10px" align="center"><input name=<?php _e(apply_filters('format_to_edit', "flagUser".$i)) ?> type="checkbox" <?php _e(apply_filters('format_to_edit', $author_flagged)) ?> /></td></tr>
				<?php
			}
			?>
			<tr></tr>
			<tr>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px" align="center">
			<span class="submit">
			<input type="submit" name="update_juliaFlags" value="<?php _e('Update Flags', 'julia') ?>" />
			</span>
			</td>
			</form>
 			</div>
			</table>
			<?php
			$results = $wpdb->get_results("SELECT COUNT(*) AS HOW_MANY, AVG(julia_abusive_score) AS SCORE FROM ".$wp_prefix."comments WHERE comment_date > SUBTIME(NOW(),'".$report_timeframe." 0:0:0')");
			foreach($results as $result) {
				$global_score = $result->SCORE;
				$total_count = $result->HOW_MANY;
			}
			if ($global_score < 0) {
				$score_int = abs(round($global_score*100));
				$score_print = "<span style=\"color: green;\">".$score_int."% Clean</span>";
			}
			else {
				$score_int = abs(round($global_score*100));
				$score_print = "<span style=\"color: red;\">".$score_int."% Abusive</span>";
			}
			?><p style="color: FireBrick; width: 600px">If a commenter is especially nasty or consistently appears in this list, then you may consider flagging that commenter as Abusive. As a basis for comparison, the average JuLiA score over all <span style="color: black;"><?php _e(apply_filters('format_to_edit', $total_count), 'julia') ?></span> comments posted to your blog in the past <span style="color:black;"><?php _e(apply_filters('format_to_edit', $report_timeframe)) ?></span> days is <b><?php _e(apply_filters('format_to_edit', $score_print)) ?></b>.<br><br>
				Flagging a commenter tells JuLiA to quarantine all submissions by that commenter, regardless of abusiveness score. To see all of your previously flagged commenters, or to flag a commenter who does not appear in this list, click <a href="<?php _e(apply_filters('format_to_edit', $site_url."/wp-admin/admin.php?page=julia-flagged-list")) ?>">here</a>.</p>
			<?php
		}

		function printFlaggedList() {
			global $wpdb;
			$wp_prefix = $wpdb->prefix;
			if (isset($_POST['update_juliaFlags'])) {
				$users = $wpdb->get_results("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
				$i = 0;
				foreach ($users as $user) {
					$i += 1;
					$author_name = $user->author_name;
					$current_index = "flagUser".$i;
					if ($_POST[$current_index] == "on") {
						$author_exists = false;
						$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
						foreach ($results as $result) {
							if ($result == $author_name) {
								$author_exists = true;
							}
						}
						if ($author_exists == false) {
							$wpdb->query("INSERT INTO ".$wp_prefix."julia_flagged_authors (author_name) VALUE (\"".$author_name."\")");
						}
					}
					else {
						$author_exists = false;
						$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
						foreach ($results as $result) {
							if ($result == $author_name) {
								$author_exists = true;
							}
						}
						if ($author_exists != false) {
							$wpdb->query("DELETE FROM ".$wp_prefix."julia_flagged_authors WHERE author_name = \"".$author_name."\"");
						}
					}
				}
			}
			if (isset($_POST['new_juliaFlags'])) {
				if (isset($_POST['flagNew'])) {
					$new_flagged_author = $_POST['flagNew'];
					$author_exists = false;
					$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
					foreach ($results as $result) {
						if ($result == $new_flagged_author) {
							$author_exists = true;
						}
					}
					if ($author_exists == false) {
						$wpdb->query("INSERT INTO ".$wp_prefix."julia_flagged_authors (author_name) VALUE (\"".$new_flagged_author."\")");
					}
				}
				?>
				<div class="updated"><p><strong><?php _e("Flags Updated", "julia"); ?></strong></p></div>
				<?php
			}
			$flagged_users = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_flagged_authors");
			?>
			<div class=wrap>
			<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
			<h2>Commenters Flagged as Abusive</h2>
			<div style="width: 500px; color: FireBrick; padding-left: 2px;">Flagging a commenter as abusive will ensure that none of their comments are auto-published, regardless of JuLiA score.</div>
			<br>
			<table cellspacing="10">
			<tr>
			<td></td>
			<td style="padding-left:10px"><b><u>Commenter</u></b></td>
			<td style="padding-left:10px" align="center"><b><u># Comments</u></b></td>
			<td style="padding-left:10px" align="center"><b><u>Average JuLiA Score</u></b></td>
			<td style="padding-left:10px" align="center"><b><u>Flagged</u></b></td>
			</tr>
			<?php
			$i = 0;
			foreach($flagged_users as $flagged_user) {
				$i += 1;
				$author_flagged = "checked=\"checked\"";
				$results = $wpdb->get_results("SELECT comment_author, COUNT(*) AS HOW_MANY, AVG(julia_abusive_score) AS SCORE FROM ".$wp_prefix."comments WHERE comment_author = \"".$flagged_user."\" GROUP BY comment_author");
				$j = 0;
				foreach($results as $result) {
					$user = $result; 
				}
				if ($user->SCORE < 0) {
					$score_int = abs(round($user->SCORE*100));
					$score_print = "<span style=\"color: green;\">".$score_int."% Clean</span>";
				}
				else {
					$score_int = abs(round($user->SCORE*100));
					$score_print = "<span style=\"color: red;\">".$score_int."% Abusive</span>";
				}
				?>
				<tr><td><b><?php _e(apply_filters('format_to_edit', $i."."), 'julia') ?></b></td><td style="padding-left:10px" align="left"><?php _e(apply_filters('format_to_edit', $flagged_user), 'julia') ?></td><td style="padding-left:10px" align="center"><?php _e(apply_filters('format_to_edit', $user->HOW_MANY)) ?></td><td style="padding-left:10px" align="center"><?php _e(apply_filters('format_to_edit', $score_print)) ?></td><td style="padding-left:10px" align="center"><input name=<?php _e(apply_filters('format_to_edit', "flagUser".$i)) ?> type="checkbox" <?php _e(apply_filters('format_to_edit', $author_flagged)) ?> /></td></tr>
				<?php
			}
			?>
			<?php
			if ($i == 0) {
				?>
				<tr>
				<td style="padding-left:10px"></td>
				<td style="padding-left:10px"></td>
				<td style="padding-left:10px; color: gray" colspan="2" align="center">
				(no commenters currently flagged)
				</td>
				</tr>
				<?php
			}
			?>
			<tr></tr>
			<tr>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px" align="right">
			<span class="submit">
			<input type="submit" name="update_juliaFlags" value="<?php _e('Update Flags', 'julia') ?>" />
			</span>
			</td>
			</div>
			<tr></tr>
			<tr></tr>
			<tr></tr>
			<tr>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px" colspan="2" align="right">
			<p align="left"><u>Flag a Commenter Not Listed Above:</u></p>
			<p><input name="flagNew" style="width: 150px" maxlength="255" type="text" id="flagNew" class="regular-text"></input>
			<span class="submit">
			<input type="submit" name="new_juliaFlags" value="<?php _e('Flag Commenter', 'julia') ?>" />
			</span>
			</form>
			</td>
			</tr>
			</table>
			<?php
		}

		function printTrustedList() {
			global $wpdb;
			$wp_prefix = $wpdb->prefix;
			if (isset($_POST['update_juliaTrusted'])) {
				$users = $wpdb->get_results("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
				$i = 0;
				foreach ($users as $user) {
					$i += 1;
					$author_name = $user->author_name;
					$current_index = "trustUser".$i;
					if ($_POST[$current_index] == "on") {
						$author_exists = false;
						$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
						foreach ($results as $result) {
							if ($result == $author_name) {
								$author_exists = true;
							}
						}
						if ($author_exists == false) {
							$wpdb->query("INSERT INTO ".$wp_prefix."julia_trusted_authors (author_name) VALUE (\"".$author_name."\")");
						}
					}
					else {
						$author_exists = false;
						$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
						foreach ($results as $result) {
							if ($result == $author_name) {
								$author_exists = true;
							}
						}
						if ($author_exists != false) {
							$wpdb->query("DELETE FROM ".$wp_prefix."julia_trusted_authors WHERE author_name = \"".$author_name."\"");
						}
					}
				}
			}
			if (isset($_POST['new_juliaTrusted'])) {
				if (isset($_POST['trustNew'])) {
					$new_trusted_author = $_POST['trustNew'];
					$author_exists = false;
					$results = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
					foreach ($results as $result) {
						if ($result == $new_trusted_author) {
							$author_exists = true;
						}
					}
					if ($author_exists == false) {
						$wpdb->query("INSERT INTO ".$wp_prefix."julia_trusted_authors (author_name) VALUE (\"".$new_trusted_author."\")");
					}
				}
				?>
				<div class="updated"><p><strong><?php _e("Trusted Authors Updated", "julia"); ?></strong></p></div>
				<?php
			}
			$trusted_users = $wpdb->get_col("SELECT author_name FROM ".$wp_prefix."julia_trusted_authors");
			?>
			<div class=wrap>
			<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
			<h2>Trusted Commenters</h2>
			<div style="width: 500px; color: FireBrick; padding-left: 2px;">Adding a commenter to the trusted list will ensure that all of their comments are auto-published, regardless of JuLiA score.</div>
			<br>
			<table cellspacing="10">
			<tr>
			<td></td>
			<td style="padding-left:10px"><b><u>Commenter</u></b></td>
			<td style="padding-left:10px" align="center"><b><u># Comments</u></b></td>
			<td style="padding-left:10px" align="center"><b><u>Average JuLiA Score</u></b></td>
			<td style="padding-left:10px" align="center"><b><u>Trusted</u></b></td>
			</tr>
			<?php
			$i = 0;
			foreach($trusted_users as $trusted_user) {
				$i += 1;
				$author_trusted = "checked=\"checked\"";
				$results = $wpdb->get_results("SELECT comment_author, COUNT(*) AS HOW_MANY, AVG(julia_abusive_score) AS SCORE FROM ".$wp_prefix."comments WHERE comment_author = \"".$trusted_user."\" GROUP BY comment_author");
				foreach($results as $result) {
					$user = $result; 
				}
				if ($user->SCORE < 0) {
					$score_int = abs(round($user->SCORE*100));
					$score_print = "<span style=\"color: green;\">".$score_int."% Clean</span>";
				}
				else {
					$score_int = abs(round($user->SCORE*100));
					$score_print = "<span style=\"color: red;\">".$score_int."% Abusive</span>";
				}
				?>
				<tr><td><b><?php _e(apply_filters('format_to_edit', $i."."), 'julia') ?></b></td><td style="padding-left:10px" align="left"><?php _e(apply_filters('format_to_edit', $trusted_user), 'julia') ?></td><td style="padding-left:10px" align="center"><?php _e(apply_filters('format_to_edit', $user->HOW_MANY)) ?></td><td style="padding-left:10px" align="center"><?php _e(apply_filters('format_to_edit', $score_print)) ?></td><td style="padding-left:10px" align="center"><input name=<?php _e(apply_filters('format_to_edit', "trustUser".$i)) ?> type="checkbox" <?php _e(apply_filters('format_to_edit', $author_trusted)) ?> /></td></tr>
				<?php
			}
			?>
			<?php
			if ($i == 0) {
				?>
				<tr>
				<td style="padding-left:10px"></td>
				<td style="padding-left:10px"></td>
				<td style="padding-left:10px; color: gray" colspan="2" align="center">
				(no trusted commenters)
				</td>
				</tr>
				<?php
			}
			?>
			<tr></tr>
			<tr>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px" align="right">
			<span class="submit">
			<input type="submit" name="update_juliaTrusted" value="<?php _e('Update Trusted', 'julia') ?>" />
			</span>
			</td>
			</div>
			<tr></tr>
			<tr></tr>
			<tr></tr>
			<tr>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px"></td>
			<td style="padding-left:10px" colspan="2" align="right">
			<p align="left"><u>Add a New Trusted Commenter:</u></p>
			<p><input name="trustNew" style="width: 150px" maxlength="255" type="text" id="trustNew" class="regular-text"></input>
			<span class="submit">
			<input type="submit" name="new_juliaTrusted" value="<?php _e('Trust Commenter', 'julia') ?>" />
			</span>
			</form>
			</td>
			</tr>
			</table>
			<?php
		}
		
		//Prints out the admin page
		function printAdminPage() {
			$devOptions = $this->getAdminOptions();
			if (isset($_POST['update_juliaSettings'])) {
				if ((is_numeric($_POST['publishThreshold'])) && (is_numeric($_POST['deleteThreshold'])) && (is_numeric($_POST['reportTimeframe']))) {
					$devOptions['publish_threshold'] = $_POST['publishThreshold'];
					$devOptions['delete_threshold'] = $_POST['deleteThreshold'];
					$devOptions['score_format'] = $_POST['scoreFormat'];
					$devOptions['show_character'] = $_POST['showCharacter'];
					$devOptions['public_display'] = $_POST['publicDisplay'];
					$devOptions['report_timeframe'] = $_POST['reportTimeframe'];
					update_option($this->adminOptionsName, $devOptions);
					?>
					<div class="updated"><p><strong><?php _e("Settings Updated", "julia");?></strong></p></div>
					<?php
				}
				else {
					?>
					<div class="updated" style="color: Red" ><p><strong><?php _e("Auto-Publish and Quarantine settings must be integers. Report Timeframe must be an integer.", "julia");?></strong></p></div>
					<?php
				}
			} ?>
			<div class=wrap>
			<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
			<h2>JuLiA Administrator Settings</h2>
			<p style="width: 600px; color: FireBrick;">JuLiA assigns a score to every comment on a scale of <b style="color: Red;">100% Abusive</b> to <b style="color: Green;">100% Clean</b>. Based on this score, she makes decisions to auto-publish or quarantine particular comments. Below, you can set the percentages at which auto-publish and quarantine decisions are made:</p>
			<p><b>Auto-Publish at </b><input name="publishThreshold" style="width: 30px" maxlength="2" type="text" id="publishThreshold" value=<?php _e(apply_filters('format_to_edit',$devOptions['publish_threshold']), 'julia') ?> class="regular-text"></input><b>% Clean or Higher</b></p>
			<p><b>Quarantine at </b><input name="deleteThreshold" style="width: 30px" maxlength="2" type="text" id="deleteThreshold" value=<?php _e(apply_filters('format_to_edit',$devOptions['delete_threshold']), 'julia') ?> class="regular-text"></input><b>% Abusive or Higher</b></p>
			<p>&nbsp;</p>
			<p style="width: 600px; color: FireBrick;">Below, you can set JuLiA display options:</p>
			<?php
			if ($devOptions['score_format'] == "category") {
				?>
				<p><b>Display Abusiveness Scores by:&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="scoreFormat" type="radio" value="category" checked="checked" /> Category&nbsp;&nbsp;<input name="scoreFormat" type="radio" value="percentage" /> Percentage</p>
				<?php
				}
			elseif ($devOptions['score_format'] == "percentage") {
				?>
				<p><b>Display Abusiveness Scores by:&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="scoreFormat" type="radio" value="category" /> Category&nbsp;&nbsp;<input name="scoreFormat" type="radio" value="percentage" checked="checked" /> Percentage</p>
				<?php
			}
			else {
				?>
				<p><b>Display Abusiveness Scores by:&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="scoreFormat" type="radio" value="category" /> Category&nbsp;&nbsp;<input name="scoreFormat" type="radio" value="percentage" /> Percentage</p>
				<?php
			}
			if ($devOptions['show_character'] == "yes") {
				?>
				<p><b>Show JuLiA Character?&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="showCharacter" type="radio" value="yes" checked="checked" /> Yes&nbsp;&nbsp;<input name="showCharacter" type="radio" value="no" /> No</p>
				<?php
			}
			elseif ($devOptions['show_character'] == "no") {
				?>
				<p><b>Show JuLiA Character?&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="showCharacter" type="radio" value="yes" /> Yes&nbsp;&nbsp;<input name="showCharacter" type="radio" value="no" checked="checked" /> No</p>
				<?php
			}
			else {
				?>
				<p><b>Show JuLiA Character?&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="showCharacter" type="radio" value="yes" /> Yes&nbsp;&nbsp;<input name="showCharacter" type="radio" value="no" /> No</p>
				<?php
			}
			if ($devOptions['public_display'] == "yes") {
				?>
				<p><b>Display Scores Publicly?&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="publicDisplay" type="radio" value="yes" checked="checked" /> Yes&nbsp;&nbsp;<input name="publicDisplay" type="radio" value="no" /> No</p>
				<?php
			}
			elseif ($devOptions['public_display'] == "no") {
				?>
				<p><b>Display Scores Publicly?&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="publicDisplay" type="radio" value="yes" /> Yes&nbsp;&nbsp;<input name="publicDisplay" type="radio" value="no" checked="checked" /> No</p>
				<?php
			}
			else {
				?>
				<p><b>Display Scores Publicly?&nbsp;&nbsp;&nbsp;&nbsp;</b><input name="publicDisplay" type="radio" value="yes" /> Yes&nbsp;&nbsp;<input name="publicDisplay" type="radio" value="no" /> No</p>
				<?php
			}
			?>
			<p><b>Run Abusiveness Report for the Past </b><input name="reportTimeframe" style="width: 30px" maxlength="2" type="text" id="reportTimeframe" value=<?php _e(apply_filters('format_to_edit', $devOptions['report_timeframe']), 'julia') ?> class="regular-text"></input><b> Days</b></p>
			<div class="submit">
			<input type="submit" name="update_juliaSettings" value="<?php _e('Update Settings', 'julia') ?>" /></div>
			</form>
 			</div>
			<?php
		}//End function printAdminPage()
  }
} //End Class julia

if (class_exists("julia")) {
  $julia_var = new julia();
}

//Initialize the admin panel
if (!function_exists("julia_ap")) {
	function julia_ap() {
		global $julia_var;
		if (!isset($julia_var)) {
			return;
		}
		if (function_exists('add_menu_page')) {
			add_menu_page('JuLiA', 'JuLiA', 9, basename(__FILE__), array(&$julia_var, 'printAdminPage'));
		}
		if (function_exists('add_submenu_page')) {
			add_submenu_page(basename(__FILE__), 'Settings', 'Settings', 9, basename(__FILE__), array(&$julia_var, 'printAdminPage'));
		}
		if (function_exists('add_submenu_page')) {
			add_submenu_page(basename(__FILE__), 'Abusiveness Report', 'Abusiveness Report', 9, 'julia-abuse-report', array(&$julia_var, 'printAbuseReport'));
		}
		if (function_exists('add_submenu_page')) {
			add_submenu_page(basename(__FILE__), 'Flagged Commenters', 'Flagged Commenters', 9, 'julia-flagged-list', array(&$julia_var, 'printFlaggedList'));
		}
		if (function_exists('add_submenu_page')) {
			add_submenu_page(basename(__FILE__), 'Trusted Commenters', 'Trusted Commenters', 9, 'julia-trusted-list', array(&$julia_var, 'printTrustedList'));
		}
	}
}

//Actions and Filters   
if (isset($julia_var)) {
  //Actions
	add_action('admin_menu', 'julia_ap');
	add_action('activate_julia-beta/julia.php', array(&$julia_var, 'init'), 1);
	add_action('comment_post', array(&$julia_var, 'parseComment'));
	//Filters
	add_filter('get_comment', array(&$julia_var, 'juliaCommentDisplay'));
	add_filter('comment_status_links', array(&$julia_var, 'commentStatusLinks'));
	add_filter('add_menu_classes', array(&$julia_var, 'editAdminMenu'));
}

/*
An XML-RPC implementation by Keith Devens, version 2.5f.
http://www.keithdevens.com/software/xmlrpc/

Release history available at:
http://www.keithdevens.com/software/xmlrpc/history/

This code is Open Source, released under terms similar to the Artistic License.
Read the license at http://www.keithdevens.com/software/license/

Note: this code requires version 4.1.0 or higher of PHP.
*/

function & XML_serialize($data, $level = 0, $prior_key = NULL){
	#assumes a hash, keys are the variable names
	$xml_serialized_string = "";
	while(list($key, $value) = each($data)){
		$inline = false;
		$numeric_array = false;
		$attributes = "";
		#echo "My current key is '$key', called with prior key '$prior_key'<br>";
		if(!strstr($key, " attr")){ #if it's not an attribute
			if(array_key_exists("$key attr", $data)){
				while(list($attr_name, $attr_value) = each($data["$key attr"])){
					#echo "Found attribute $attribute_name with value $attribute_value<br>";
					$attr_value = &htmlspecialchars($attr_value, ENT_QUOTES);
					$attributes .= " $attr_name=\"$attr_value\"";
				}
			}

			if(is_numeric($key)){
				#echo "My current key ($key) is numeric. My parent key is '$prior_key'<br>";
				$key = $prior_key;
			}else{
				#you can't have numeric keys at two levels in a row, so this is ok
				#echo "Checking to see if a numeric key exists in data.";
				if(is_array($value) and array_key_exists(0, $value)){
				#	echo " It does! Calling myself as a result of a numeric array.<br>";
					$numeric_array = true;
					$xml_serialized_string .= XML_serialize($value, $level, $key);
				}
				#echo "<br>";
			}

			if(!$numeric_array){
				$xml_serialized_string .= str_repeat("\t", $level) . "<$key$attributes>";

				if(is_array($value)){
					$xml_serialized_string .= "\r\n" . XML_serialize($value, $level+1);
				}else{
					$inline = true;
					$xml_serialized_string .= htmlspecialchars($value);
				}

				$xml_serialized_string .= (!$inline ? str_repeat("\t", $level) : "") . "</$key>\r\n";
			}
		}else{
			#echo "Skipping attribute record for key $key<bR>";
		}
	}
	if($level == 0){
		$xml_serialized_string = "<?xml version=\"1.0\" ?>\r\n" . $xml_serialized_string;
		return $xml_serialized_string;
	}else{
		return $xml_serialized_string;
	}
}

class XML {
	var $parser; #a reference to the XML parser
	var $document; #the entire XML structure built up so far
	var $current; #a pointer to the current item - what is this
	var $parent; #a pointer to the current parent - the parent will be an array
	var $parents; #an array of the most recent parent at each level

	var $last_opened_tag;

	function XML($data=null){
		error_reporting(0);			
		$this->parser = xml_parser_create();

		xml_parser_set_option ($this->parser, XML_OPTION_CASE_FOLDING, 0);
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "open", "close");
		xml_set_character_data_handler($this->parser, "data");
#		register_shutdown_function(array(&$this, 'destruct'));
	}

	function destruct(){
		xml_parser_free($this->parser);
	}

	function parse($data){
		$this->document = array();
		$this->parent = &$this->document;
		$this->parents = array();
		$this->last_opened_tag = NULL;
		xml_parse($this->parser, $data);
		return $this->document;
	}

	function open($parser, $tag, $attributes){
		#echo "Opening tag $tag<br>\n";
		$this->data = "";
		$this->last_opened_tag = $tag; #tag is a string
		if(array_key_exists($tag, $this->parent)){
			#echo "There's already an instance of '$tag' at the current level ($level)<br>\n";
			if(is_array($this->parent[$tag]) and array_key_exists(0, $this->parent[$tag])){ #if the keys are numeric
				#need to make sure they're numeric (account for attributes)
				$key = count_numeric_items($this->parent[$tag]);
				#echo "There are $key instances: the keys are numeric.<br>\n";
			}else{
				#echo "There is only one instance. Shifting everything around<br>\n";
				$temp = &$this->parent[$tag];
				unset($this->parent[$tag]);
				$this->parent[$tag][0] = &$temp;

				if(array_key_exists("$tag attr", $this->parent)){
					#shift the attributes around too if they exist
					$temp = &$this->parent["$tag attr"];
					unset($this->parent["$tag attr"]);
					$this->parent[$tag]["0 attr"] = &$temp;
				}
				$key = 1;
			}
			$this->parent = &$this->parent[$tag];
		}else{
			$key = $tag;
		}
		if($attributes){
			$this->parent["$key attr"] = $attributes;
		}

		$this->parent[$key] = array();
		$this->parent = &$this->parent[$key];
		array_unshift($this->parents, $this->parent);
	}

	function data($parser, $data){
		#echo "Data is '", htmlspecialchars($data), "'<br>\n";
		if($this->last_opened_tag != NULL){
			$this->data .= $data;
		}
	}

	function close($parser, $tag){
		#echo "Close tag $tag<br>\n";
		if($this->last_opened_tag == $tag){
			$this->parent = $this->data;
			$this->last_opened_tag = NULL;
		}
		array_shift($this->parents);
		$this->parent = &$this->parents[0];
	}
}

function & XML_unserialize($xml){
	$xml_parser = new XML();
	$data = &$xml_parser->parse($xml);
	$xml_parser->destruct();
	return $data;
}

function & XMLRPC_parse($request){
	if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
		XMLRPC_debug('XMLRPC_parse', "<p>Received the following raw request:</p>" . XMLRPC_show($request, 'print_r', true));
	}
	$data = &XML_unserialize($request);
	if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
		XMLRPC_debug('XMLRPC_parse', "<p>Returning the following parsed request:</p>" . XMLRPC_show($data, 'print_r', true));
	}
	return $data;
}

function & XMLRPC_prepare($data, $type = NULL){
	if(is_array($data)){
		$num_elements = count($data);
		if((array_key_exists(0, $data) or !$num_elements) and $type != 'struct'){ #it's an array
			if(!$num_elements){ #if the array is empty
				$returnvalue =  array('array' => array('data' => NULL));
			}else{
				$returnvalue['array']['data']['value'] = array();
				$temp = &$returnvalue['array']['data']['value'];
				$count = count_numeric_items($data);
				for($n=0; $n<$count; $n++){
					$type = NULL;
					if(array_key_exists("$n type", $data)){
						$type = $data["$n type"];
					}
					$temp[$n] = XMLRPC_prepare($data[$n], $type);
				}
			}
		}else{ #it's a struct
			if(!$num_elements){ #if the struct is empty
				$returnvalue = array('struct' => NULL);
			}else{
				$returnvalue['struct']['member'] = array();
				$temp = &$returnvalue['struct']['member'];
				while(list($key, $value) = each($data)){
					if(substr($key, -5) != ' type'){ #if it's not a type specifier
						$type = NULL;
						if(array_key_exists("$key type", $data)){
							$type = $data["$key type"];
						}
						$temp[] = array('name' => $key, 'value' => XMLRPC_prepare($value, $type));
					}
				}
			}
		}
	}else{ #it's a scalar
		if(!$type){
			if(is_int($data)){
				$returnvalue['int'] = $data;
				return $returnvalue;
			}elseif(is_float($data)){
				$returnvalue['double'] = $data;
				return $returnvalue;
			}elseif(is_bool($data)){
				$returnvalue['boolean'] = ($data ? 1 : 0);
				return $returnvalue;
			}elseif(preg_match('/^\d{8}T\d{2}:\d{2}:\d{2}$/', $data, $matches)){ #it's a date
				$returnvalue['dateTime.iso8601'] = $data;
				return $returnvalue;
			}elseif(is_string($data)){
				$returnvalue['string'] = htmlspecialchars($data);
				return $returnvalue;
			}
		}else{
			$returnvalue[$type] = htmlspecialchars($data);
		}
	}
	return $returnvalue;
}

function & XMLRPC_adjustValue($current_node){
	if(is_array($current_node)){
		if(isset($current_node['array'])){
			if(!is_array($current_node['array']['data'])){
				#If there are no elements, return an empty array
				return array();
			}else{
				#echo "Getting rid of array -> data -> value<br>\n";
				$temp = &$current_node['array']['data']['value'];
				if(is_array($temp) and array_key_exists(0, $temp)){
					$count = count($temp);
					for($n=0;$n<$count;$n++){
						$temp2[$n] = &XMLRPC_adjustValue($temp[$n]);
					}
					$temp = &$temp2;
				}else{
					$temp2 = &XMLRPC_adjustValue($temp);
					$temp = array(&$temp2);
					#I do the temp assignment because it avoids copying,
					# since I can put a reference in the array
					#PHP's reference model is a bit silly, and I can't just say:
					# $temp = array(&XMLRPC_adjustValue(&$temp));
				}
			}
		}elseif(isset($current_node['struct'])){
			if(!is_array($current_node['struct'])){
				#If there are no members, return an empty array
				return array();
			}else{
				#echo "Getting rid of struct -> member<br>\n";
				$temp = &$current_node['struct']['member'];
				if(is_array($temp) and array_key_exists(0, $temp)){
					$count = count($temp);
					for($n=0;$n<$count;$n++){
						#echo "Passing name {$temp[$n][name]}. Value is: " . show($temp[$n][value], var_dump, true) . "<br>\n";
						$temp2[$temp[$n]['name']] = &XMLRPC_adjustValue($temp[$n]['value']);
						#echo "adjustValue(): After assigning, the value is " . show($temp2[$temp[$n]['name']], var_dump, true) . "<br>\n";
					}
				}else{
					#echo "Passing name $temp[name]<br>\n";
					$temp2[$temp['name']] = &XMLRPC_adjustValue($temp['value']);
				}
				$temp = &$temp2;
			}
		}else{
			$types = array('string', 'int', 'i4', 'double', 'dateTime.iso8601', 'base64', 'boolean');
			$fell_through = true;
			foreach($types as $type){
				if(array_key_exists($type, $current_node)){
					#echo "Getting rid of '$type'<br>\n";
					$temp = &$current_node[$type];
					#echo "adjustValue(): The current node is set with a type of $type<br>\n";
					$fell_through = false;
					break;
				}
			}
			if($fell_through){
				$type = 'string';
				#echo "Fell through! Type is $type<br>\n";
			}
			switch ($type){
				case 'int': case 'i4': $temp = (int)$temp;    break;
				case 'string':         $temp = (string)$temp; break;
				case 'double':         $temp = (double)$temp; break;
				case 'boolean':        $temp = (bool)$temp;   break;
			}
		}
	}else{
		$temp = (string)$current_node;
	}
	return $temp;
}

function XMLRPC_getParams($request){
	if(!is_array($request['methodCall']['params'])){
		#If there are no parameters, return an empty array
		return array();
	}else{
		#echo "Getting rid of methodCall -> params -> param<br>\n";
		$temp = &$request['methodCall']['params']['param'];
		if(is_array($temp) and array_key_exists(0, $temp)){
			$count = count($temp);
			for($n = 0; $n < $count; $n++){
				#echo "Serializing parameter $n<br>";
				$temp2[$n] = &XMLRPC_adjustValue($temp[$n]['value']);
			}
		}else{
			$temp2[0] = &XMLRPC_adjustValue($temp['value']);
		}
		$temp = &$temp2;
		return $temp;
	}
}

function XMLRPC_getMethodName($methodCall){
	#returns the method name
	return $methodCall['methodCall']['methodName'];
}

function XMLRPC_request($site, $location, $methodName, $params = NULL, $user_agent = NULL){
	$site = explode(':', $site);
	if(isset($site[1]) and is_numeric($site[1])){
		$port = $site[1];
	}else{
		$port = 80;
	}
	$site = $site[0];

	$data["methodCall"]["methodName"] = $methodName;
	$param_count = count($params);
	if(!$param_count){
		$data["methodCall"]["params"] = NULL;
	}else{
		for($n = 0; $n<$param_count; $n++){
			$data["methodCall"]["params"]["param"][$n]["value"] = $params[$n];
		}
	}
	$data = XML_serialize($data);

	if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
		XMLRPC_debug('XMLRPC_request', "<p>Received the following parameter list to send:</p>" . XMLRPC_show($params, 'print_r', true));
	}
	$conn = fsockopen ($site, $port); #open the connection
	if(!$conn){ #if the connection was not opened successfully
		if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
			XMLRPC_debug('XMLRPC_request', "<p>Connection failed: Couldn't make the connection to $site.</p>");
		}
		return array(false, array('faultCode'=>10532, 'faultString'=>"Connection failed: Couldn't make the connection to $site."));
	}else{
		$headers =
			"POST $location HTTP/1.0\r\n" .
			"Host: $site\r\n" .
			"Connection: close\r\n" .
			($user_agent ? "User-Agent: $user_agent\r\n" : '') .
			"Content-Type: text/xml\r\n" .
			"Content-Length: " . strlen($data) . "\r\n\r\n";

		fputs($conn, "$headers");
		fputs($conn, $data);

		if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
			XMLRPC_debug('XMLRPC_request', "<p>Sent the following request:</p>\n\n" . XMLRPC_show($headers . $data, 'print_r', true));
		}

		#socket_set_blocking ($conn, false);
		$response = "";
		while(!feof($conn)){
			$response .= fgets($conn, 1024);
		}
		fclose($conn);

		#strip headers off of response
		$data = XML_unserialize(substr($response, strpos($response, "\r\n\r\n")+4));

		if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
			XMLRPC_debug('XMLRPC_request', "<p>Received the following response:</p>\n\n" . XMLRPC_show($response, 'print_r', true) . "<p>Which was serialized into the following data:</p>\n\n" . XMLRPC_show($data, 'print_r', true));
		}
		if(isset($data['methodResponse']['fault'])){
			$return =  array(false, XMLRPC_adjustValue($data['methodResponse']['fault']['value']));
			if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
				XMLRPC_debug('XMLRPC_request', "<p>Returning:</p>\n\n" . XMLRPC_show($return, 'var_dump', true));
			}
			return $return;
		}else{
			$return = array(true, XMLRPC_adjustValue($data['methodResponse']['params']['param']['value']));
			if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
				XMLRPC_debug('XMLRPC_request', "<p>Returning:</p>\n\n" . XMLRPC_show($return, 'var_dump', true));
			}
			return $return;
		}
	}
}

function XMLRPC_response($return_value, $server = NULL){
	$data["methodResponse"]["params"]["param"]["value"] = &$return_value;
	$return = XML_serialize($data);

	if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
		XMLRPC_debug('XMLRPC_response', "<p>Received the following data to return:</p>\n\n" . XMLRPC_show($return_value, 'print_r', true));
	}

	header("Connection: close");
	header("Content-Length: " . strlen($return));
	header("Content-Type: text/xml");
	header("Date: " . date("r"));
	if($server){
		header("Server: $server");
	}

	if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
		XMLRPC_debug('XMLRPC_response', "<p>Sent the following response:</p>\n\n" . XMLRPC_show($return, 'print_r', true));
	}
	echo $return;
}

function XMLRPC_error($faultCode, $faultString, $server = NULL){
	$array["methodResponse"]["fault"]["value"]["struct"]["member"] = array();
	$temp = &$array["methodResponse"]["fault"]["value"]["struct"]["member"];
	$temp[0]["name"] = "faultCode";
	$temp[0]["value"]["int"] = $faultCode;
	$temp[1]["name"] = "faultString";
	$temp[1]["value"]["string"] = $faultString;

	$return = XML_serialize($array);

	header("Connection: close");
	header("Content-Length: " . strlen($return));
	header("Content-Type: text/xml");
	header("Date: " . date("r"));
	if($server){
		header("Server: $server");
	}
	if(defined('XMLRPC_DEBUG') and XMLRPC_DEBUG){
		XMLRPC_debug('XMLRPC_error', "<p>Sent the following error response:</p>\n\n" . XMLRPC_show($return, 'print_r', true));
	}
	echo $return;
}

function XMLRPC_convert_timestamp_to_iso8601($timestamp){
	#takes a unix timestamp and converts it to iso8601 required by XMLRPC
	#an example iso8601 datetime is "20010822T03:14:33"
	return date("Ymd\TH:i:s", $timestamp);
}

function XMLRPC_convert_iso8601_to_timestamp($iso8601){
	return strtotime($iso8601);
}

function count_numeric_items($array){
	return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
}

function XMLRPC_debug($function_name, $debug_message){
	$GLOBALS['XMLRPC_DEBUG_INFO'][] = array($function_name, $debug_message);
}

function XMLRPC_debug_print(){
	if($GLOBALS['XMLRPC_DEBUG_INFO']){
		echo "<table border=\"1\" width=\"100%\">\n";
		foreach($GLOBALS['XMLRPC_DEBUG_INFO'] as $debug){
			echo "<tr><th style=\"vertical-align: top\">$debug[0]</th><td>$debug[1]</td></tr>\n";
		}
		echo "</table>\n";
		unset($GLOBALS['XMLRPC_DEBUG_INFO']);
	}else{
		echo "<p>No debugging information available yet.</p>";
	}
}

function XMLRPC_show($data, $func = "print_r", $return_str = false){
	ob_start();
	$func($data);
	$output = ob_get_contents();
	ob_end_clean();
	if($return_str){
		return "<pre>" . htmlspecialchars($output) . "</pre>\n";
	}else{
		echo "<pre>", htmlspecialchars($output), "</pre>\n";
	}
}

?>
