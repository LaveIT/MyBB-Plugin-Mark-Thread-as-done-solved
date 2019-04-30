<?php
/*
Plugin "Thread solved" 2.1
2008 (c) MyBBoard.de
2019 (c) MyBB.de - Plugin changed and modified by itsmeJAY
Version tested: 1.8.20 by itsmeJAY
*/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("forumdisplay_thread", "threadsolved");
$plugins->add_hook("search_results_thread", "threadsolved");
//$plugins->add_hook("search_results_post", "threadsolved");
$plugins->add_hook("showthread_linear", "threadsolved");
$plugins->add_hook("showthread_threaded", "threadsolved");

function threadsolved_info() {
    // Sprachdatei laden
    global $lang;
    $lang->load("tsthreadsolved");

	return array(
		"name"			=> "$lang->ts_title",
		"description"	=> "$lang->ts_desc",
		"website"		=> "http://www.mybb.de",
		"author"		=> "MyBB.de - Changed and modified by itsmeJAY",
		"authorsite"	=> "http://www.mybb.de",
		"version"		=> "2.2.1",
	);
}

function threadsolved_install() {
    global $db, $lang;

    // Sprachdatei laden
    $lang->load("tsthreadsolved");
    
    if(!$db->field_exists('threadsolved', "threads")) {
        $db->query("ALTER TABLE `".TABLE_PREFIX."threads` ADD `threadsolved` INT( 1 ) NOT NULL DEFAULT '0';");
    }

    $setting_group = array(
        'name' => 'ts_tns',
        'title' => "$lang->ts_title_set",
        'description' => "$lang->ts_title_desc",
        'disporder' => 5,
        'isdefault' => 0
    );
    
    $gid = $db->insert_query("settinggroups", $setting_group);
    
    // Einstellungen
  
    $setting_array = array(
      // Welche Usergruppe darf als erledigt markieren?
      'ts_group_select' => array(
          'title' => "$lang->ts_group_select_title",
          'description' => "$lang->ts_group_select_desc",
          'optionscode' => 'groupselect',
          'value' => '3,4', // Default
          'disporder' => 1
      ),
      'ts_threadowner' => array(
        'title' => "$lang->ts_threadowner_title",
        'description' => "$lang->ts_threadowner_desc",
        'optionscode' => 'yesno',
        'value' => 1, // Default
        'disporder' => 2
    ),
    'ts_solved_text' => array(
        'title' => "$lang->ts_solvedtext_title",
        'description' => "$lang->ts_solvedtext_desc",
        'optionscode' => 'text',
        'value' => 'Solved', // Default
        'disporder' => 3
    ),
    'ts_notsolved_text' => array(
        'title' => "$lang->ts_notsolvedtext_title",
        'description' => "$lang->ts_notsolvedtext_desc",
        'optionscode' => 'text',
        'value' => 'Not solved', // Default
        'disporder' => 4
    ),
    'ts_forum_select' => array(
        'title' => "$lang->ts_forumselect_title",
        'description' => "$lang->ts_forumselect_desc",
        'optionscode' => 'forumselect',
        'value' => -1, // Default
        'disporder' => 5
    ),
  );  
  
  // Einstellungen in Datenbank speichern
  foreach($setting_array as $name => $setting)
  {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
  
      $db->insert_query('settings', $setting);
  }

  // Rebuild Settings! :-)
  rebuild_settings();



}

function threadsolved_uninstall() {
    global $db;
    $db->query("ALTER TABLE `".TABLE_PREFIX."threads` DROP `threadsolved`;");
    $db->delete_query('settings', "name IN ('ts_group_select', 'ts_threadowner', 'ts_solved_text', 'ts_notsolved_text', 'ts_forum_select')");
    $db->delete_query('settinggroups', "name = 'ts_tns'");
    
    // Rebuild Settings! :-)
    rebuild_settings();
}

function threadsolved_activate() {
    global $db, $mybb, $lang;

    // Sprachdatei laden
    $lang->load("tsthreadsolved");

    require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("forumdisplay_thread", '#{\$gotounread}#', "{\$gotounread} {\$threadsolved} ");
    find_replace_templatesets("search_results_threads_thread", '#{\$gotounread}#', "{\$gotounread} {\$threadsolved} ");
    find_replace_templatesets("search_results_posts_post", '#{\$lang->post_thread}#', "{\$lang->post_thread} {\$threadsolved}");
    find_replace_templatesets("showthread", '#<strong>{\$thread#', "{\$threadsolved} <strong>{\$thread");
    find_replace_templatesets("showthread", '#{\$newreply}#', "{\$threadsolved_button}{\$newreply}");
}

function threadsolved_deactivate() {
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("forumdisplay_thread", '# {\$threadsolved} #', "", 0);
    find_replace_templatesets("search_results_threads_thread", '# {\$threadsolved} #', "", 0);
    find_replace_templatesets("search_results_posts_post", '# {\$threadsolved}#', "", 0);
    find_replace_templatesets("showthread", '#{\$threadsolved} #', "", 0);
    find_replace_templatesets("showthread", '#{\$threadsolved_button}#', "", 0);
}

function threadsolved_is_installed() {
    global $db;
    if($db->field_exists('threadsolved', "threads")) {
        return true;
    } else {
        return false;
    }
}

function threadsolved() {

    global $threadsolved, $thread, $post, $templates, $mybb, $threadsolved_button, $db, $theme;

    if($mybb->user['uid'] != 0 && (in_array($thread['fid'], explode(',',$mybb->settings['ts_forum_select'])) || $mybb->settings['ts_forum_select'] == "-1") && ($mybb->user['uid'] == $thread['uid'] && $mybb->settings['ts_threadowner'] == 1|| in_array($mybb->user['usergroup'], explode(',',$mybb->settings['ts_group_select'])))) {
        if($mybb->input['marksolved'] == "1") {
            $db->query("UPDATE ".TABLE_PREFIX."threads SET threadsolved = '1' WHERE tid = '".$thread['tid']."';");
            $thread['threadsolved'] = "1";
        }
        if($mybb->input['marksolved'] == "0") {
            $db->query("UPDATE ".TABLE_PREFIX."threads SET threadsolved = '0' WHERE tid = '".$thread['tid']."';");
            $thread['threadsolved'] = "0";
        }
    }

    $threadsolved = $threadsolved_button = "";

    if($thread['threadsolved'] == "1") {
        $threadsolved = "<img src=\"images/solved.png\" border=\"0\" alt=\"\" style=\"vertical-align: middle;\" />";
    }

    if(basename($_SERVER['PHP_SELF']) == "showthread.php") {
        if($thread['threadsolved'] != "1" && (in_array($thread['fid'], explode(',',$mybb->settings['ts_forum_select'])) || $mybb->settings['ts_forum_select'] == "-1") && ($mybb->user['uid'] != 0 && ($mybb->user['uid'] == $thread['uid'] && $mybb->settings['ts_threadowner'] == 1|| in_array($mybb->user['usergroup'], explode(',',$mybb->settings['ts_group_select']))))) {
        
        $solved = $mybb->settings['ts_solved_text']; 
        $threadsolved_button = "<a href=\"showthread.php?tid=".$thread['tid']."&amp;marksolved=1\" class=\"button thread_solved\"><i style=\"font-size: 14px;\" class=\"fa fa-check fa-fw\"></i> 
	<span>$solved</span></a>";
        }
        if($thread['threadsolved'] == "1" && (in_array($thread['fid'], explode(',',$mybb->settings['ts_forum_select'])) || $mybb->settings['ts_forum_select'] == "-1" ) && ($mybb->user['uid'] != 0 && ($mybb->user['uid'] == $thread['uid'] && $mybb->settings['ts_threadowner'] == 1 || in_array($mybb->user['usergroup'], explode(',',$mybb->settings['ts_group_select']))))) {
       
        $notsolved = $mybb->settings['ts_notsolved_text']; 
        $threadsolved_button = "<a href=\"showthread.php?tid=".$thread['tid']."&amp;marksolved=0\" class=\"button thread_notsolved\"><i style=\"font-size: 14px;\" class=\"fa fa-ban fa-fw\"></i> 
	<span>$notsolved</span></a>";
        }
    }
}
?>