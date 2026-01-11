<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook("admin_config_settings_change", "celebritylist_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "celebritylist_settings_peek");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'celebritylist_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'celebritylist_admin_update_plugin');
$plugins->add_hook('global_intermediate', 'celebritylist_global');
$plugins->add_hook('misc_start', 'celebritylist_misc');
$plugins->add_hook("modcp_nav", "celebritylist_modcp_nav");
$plugins->add_hook("modcp_start", "celebritylist_modcp");
$plugins->add_hook('fetch_wol_activity_end', 'celebritylist_online_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'celebritylist_online_location');
if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
    $plugins->add_hook('global_start', 'celebritylist_register_myalerts_formatter_back_compat'); // Backwards-compatible alert formatter registration hook-ins.
    $plugins->add_hook('xmlhttp', 'celebritylist_register_myalerts_formatter_back_compat', -2/* Prioritised one higher (more negative) than the MyAlerts hook into xmlhttp */);
    $plugins->add_hook('myalerts_register_client_alert_formatters', 'celebritylist_register_myalerts_formatter'); // Backwards-compatible alert formatter registration hook-ins.
}
 
// Die Informationen, die im Pluginmanager angezeigt werden
function celebritylist_info() {
	return array(
		"name"		=> "Berümheiten [Liste]",
		"description"	=> "Eine interaktive Liste für Berühmheiten unter den Charakteren und fiktive Gruppen.",
		"website"	=> "https://github.com/little-evil-genius/Prominenz",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function celebritylist_install() {

    global $db;

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message("Das ACP Modul <a href=\"https://github.com/little-evil-genius/rpgstuff_modul\" target=\"_blank\">\"RPG Stuff\"</a> muss vorhanden sein!", 'error');
		admin_redirect('index.php?module=config-plugins');
	}
    
    // DATENBANKTABELLE & FELDER
    celebritylist_database();

	// EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
	$setting_group = array(
		'name'          => 'celebritylist',
		'title'         => 'Berühmheiten [Liste]',
        'description'   => 'Einstellungen für die Liste der Berühmheiten',
		'disporder'     => $maxdisporder+1,
		'isdefault'     => 0
	);
	$db->insert_query("settinggroups", $setting_group);  
		
    celebritylist_settings();
	rebuild_settings();

	// TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "celebritylist",
        "title" => $db->escape_string("Berühmheiten [Liste]"),
    );
    $db->insert_query("templategroups", $templategroup);
    celebritylist_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $stylesheet = celebritylist_stylesheet();
    $db->insert_query('themestylesheets', $stylesheet);
    cache_stylesheet(1, "celebritylist.css", $stylesheet['stylesheet']);
    update_theme_stylesheet_list("1");
}

// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function celebritylist_is_installed() {

    global $db;

    if ($db->table_exists("celebritylist")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function celebritylist_uninstall() {
    
	global $db;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("celebritylist"))
    {
        $db->drop_table("celebritylist");
    }

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'celebritylist'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'celebritylist%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'celebritylist%'");
    $db->delete_query('settinggroups', "name = 'celebritylist'");
    rebuild_settings();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'celebritylist.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function celebritylist_activate() {

    require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('modcp_nav_users', '#'.preg_quote('{$nav_ipsearch}').'#', '{$nav_ipsearch} {$nav_celebritylist}');
	find_replace_templatesets('header', '#'.preg_quote('{$modnotice}').'#', '{$modnotice} {$celebritylist_newentry}');

    if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('celebritylist_refuse'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);
		$alertTypeManager->add($alertType);

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('celebritylist_accepted'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);
		$alertTypeManager->add($alertType);
    }
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function celebritylist_deactivate() {

    // VARIABLEN ENTFERNEN
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("modcp_nav_users", "#".preg_quote('{$nav_celebritylist}')."#i", '', 0);
    find_replace_templatesets("header", "#".preg_quote('{$celebritylist_newentry}')."#i", '', 0);

    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('celebritylist_refuse');
        $alertTypeManager->deleteByCode('celebritylist_accepted');
	}
}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function celebritylist_settings_change(){
    
    global $db, $mybb, $celebritylist_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='celebritylist'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $celebritylist_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function celebritylist_settings_peek(&$peekers){

    global $celebritylist_settings_peeker;

    if ($celebritylist_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_celebritylist_list_menu"), $("#row_setting_celebritylist_list_tpl"),/^0/,false)';
    }
}

// ADMIN BEREICH - RPG STUFF //
// Stylesheet zum Master Style hinzufügen
function celebritylist_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "celebritylist") {

        $css = celebritylist_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "celebritylist.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Berühmheiten [Liste]")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'celebritylist.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=celebritylist\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function celebritylist_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "celebritylist") {

        // Einstellungen überprüfen => Type = update
        celebritylist_settings('update');
        rebuild_settings();

        // Templates 
        celebritylist_templates('update');

        // Stylesheet
        $update_data = celebritylist_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'celebritylist.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('celebritylist.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        celebritylist_database();

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Berühmheiten [Liste]")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = celebritylist_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=celebritylist\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// TEAMHINWEIS
function celebritylist_global() {

    global $db, $lang, $mybb, $templates, $newentry_notice, $celebritylist_newentry;

    if ($mybb->usergroup['canmodcp'] != 1) {
        $celebritylist_newentry = "";
        return;
    }
    
    $lang->load("celebritylist");

    $count_celebrity = $db->num_rows($db->query("SELECT cid FROM ".TABLE_PREFIX."celebritylist WHERE accepted = 0")); 

    if ($count_celebrity > 0) {
        if ($count_celebrity == "1") {   
            $newentry_notice = $lang->sprintf($lang->celebritylist_banner, $lang->celebritylist_banner_anon_single, $lang->celebritylist_banner_count_single, $lang->celebritylist_banner_person_single);
        } elseif ($count_celebrity > "1") {
            $newentry_notice = $lang->sprintf($lang->celebritylist_banner, $lang->celebritylist_banner_anon_plural, $count_celebrity, $lang->celebritylist_banner_person_plural);
        }
		
        eval("\$celebritylist_newentry = \"".$templates->get("celebritylist_banner")."\";");
    } else {
        $celebritylist_newentry = "";
    }
}

// MISC SEITE
function celebritylist_misc() {

    global $db, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $page, $lists_menu, $multipage, $adderrors, $editerrors, $celebritylist_add, $code, $celebritylist_bit, $user_bit, $celebrity_count, $codebuttons, $filter_option;

    // return if the action key isn't part of the input
    $allowed_actions = [
        'celebritylist',
        'do_celebritylist_add',
        'celebritylist_edit',
        'do_celebritylist_edit',
        'celebritylist_delete'
    ];
    if (!in_array($mybb->get_input('action', MyBB::INPUT_STRING), $allowed_actions)) return;

    $lang->load('celebritylist');

    $list_nav = $mybb->settings['celebritylist_list_nav'];
    $displaytypes = $mybb->settings['celebritylist_displaytypes'];
    $typeslist = $mybb->settings['celebritylist_types'];
    $allTypes = explode(",", str_replace(", ", ",", $typeslist));
    if($mybb->settings['celebritylist_multitypes'] == 1) {
        $typecode = "checkbox";
    } else {
        $typecode = "radio";
    }

    // Speichern
    if ($mybb->request_method == "post" && $mybb->input['action'] == "do_celebritylist_add") {

        if ($mybb->get_input('person_type') == 'single') {
            if (empty($mybb->get_input('character'))) {
                $errors[] = $lang->celebritylist_error_character;
            }
        } else {
            if (empty($mybb->get_input('group'))) {
                $errors[] = $lang->celebritylist_error_group;
            }
        }
        if (empty($mybb->get_input('profession'))) {
            $errors[] = $lang->celebritylist_error_profession;      
        }
        if (empty($mybb->get_input('description'))) {
            $errors[] = $lang->celebritylist_error_description;      
        }
        if ($typecode == "checkbox") {
            $type_input = $mybb->get_input('type', MyBB::INPUT_ARRAY);
            if (empty($type_input)) {
                $errors[] = $lang->celebritylist_error_type_multi;
            }
        } else {
            $type_input = $mybb->get_input('type');
            if (empty($type_input)) {
                $errors[] = $lang->celebritylist_error_type;
            }
        }

        if (!empty($errors)) {
            $adderrors = inline_error($errors);
            $mybb->input['action'] = "celebritylist";
        } else {

            if (!empty($mybb->get_input('character'))) {
                $groupname = "";
                $username = $mybb->get_input('character');
                $uid = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username = '".$db->escape_string($username)."'"), "uid");
            } else {
                $groupname = $mybb->get_input('group');
                $username = "";
                $uid = $mybb->user['uid'];
            }

            if ($typecode == "checkbox") {
                $value = $mybb->get_input('type', MyBB::INPUT_ARRAY);
                $type = implode(",", array_map('trim', $value));
            } else {
                $type = $mybb->get_input('type');
            }

            if ($mybb->usergroup['canmodcp'] == '1'){
                $accepted = 1;
            } else {
                $accepted = 0;
            }

            $new_celebrity = array(
                "uid" => (int)$uid,
                "username" =>  $db->escape_string($username),
                "groupname" =>  $db->escape_string($groupname),
                "type" =>  $db->escape_string($type),
                "profession" =>  $db->escape_string($mybb->get_input('profession')),
                "description" =>  $db->escape_string($mybb->get_input('description')),
                "accepted" =>  (int)$accepted                        
            );

            $db->insert_query("celebritylist", $new_celebrity);  
            
            if ($accepted == 1) {
                redirect("misc.php?action=celebritylist", $lang->celebritylist_redirect_add_team);
            } else {
                redirect("misc.php?action=celebritylist", $lang->celebritylist_redirect_add_user);
            }
        }
    }

    // Seite
    if($mybb->get_input('action') == "celebritylist"){

        $lists_menu = celebritylist_lists_menu();

        // NAVIGATION
		if(!empty($list_nav)){
            add_breadcrumb($lang->celebritylist_lists, $list_nav);
            add_breadcrumb($lang->celebritylist_main, "misc.php?action=celebritylist");
		} else{
            add_breadcrumb($lang->celebritylist_main, "misc.php?action=celebritylist");
		}
            
        if(!isset($adderrors)){
            $adderrors = "";
        }

        // Formular
        if($mybb->user['uid'] != 0) {

            if (!empty($adderrors)) {
                $character = $mybb->get_input('character');
                $group = $mybb->get_input('group');
                $profession = $mybb->get_input('profession');
                $description = $mybb->get_input('description');
                $person_type = $mybb->get_input('person_type');

                if ($typecode == "checkbox") {
                    $value = $mybb->get_input('type', MyBB::INPUT_ARRAY);
                } else {
                    $value = $mybb->get_input('type');
                }
            } else {
                $character = ""; 
                $group = "";
                $profession = "";
                $description = "";
                $value = ""; 
                $person_type = "single";
            }
            $code = celebritylist_generate_input_field($typecode, $value);

            $codebuttons = build_mycode_inserter("description");
            if(function_exists('markitup_run_build')) {
				markitup_run_build('description');
			};

            if ($person_type == "group") {
                $person_type_single_checked = "";
                $person_type_group_checked  = "checked=\"checked\"";
            } else {
                $person_type_single_checked = "checked=\"checked\"";
                $person_type_group_checked  = "";
            }

            eval("\$celebritylist_add = \"".$templates->get("celebritylist_add")."\";");
        } else {
            $celebritylist_add = "";
        }
         
        // QUERY KRAM - Filter
        $filterData = celebritylist_filter();
        $typePerson_filter_sql = $filterData['typePerson_filter_sql'];
        $type_filter_sql = $filterData['type_filter_sql'];

        // MULTIPAGE        
        $multipageData = celebritylist_multipage();
        $multipage_sql = $multipageData['multipage_sql'];
        $multipage = $multipageData['multipage'];

        // ohne Tabs
        if ($displaytypes != 0){

            // FILTER    
            if ($displaytypes == 1) {

                $filterOptions = ["filter_typePerson", "filter_type"];  
                $filter_bit = "";  
                foreach ($filterOptions as $filterOption) {

                    $filteroptions = "";
                    // nach Branche
                    if ($filterOption == "filter_type") {
                        $filter_headline = $lang->celebritylist_filter_type_headline;
                        $first_select = $lang->celebritylist_filter_type_firstselect;

                        foreach ($allTypes as $type) {
                            if ($mybb->get_input('filter_type') == $type) {
                                $check_select = "selected";
                            } else {
                                $check_select = "";    
                            }

                            $filteroptions .= "<option value=\"".$type."\" ".$check_select.">".$type."</option>";
                        }
                    }
                    // nach Einzelperson/Gruppen
                    else if ($filterOption == "filter_typePerson") {
                        $filter_headline = $lang->celebritylist_filter_typePerson_headline;    
                        $first_select = $lang->celebritylist_filter_typePerson_firstselect;

                        $allPerson = [$lang->celebritylist_filter_typePerson_single => 'single', $lang->celebritylist_filter_typePerson_group => 'group'];
                        foreach ($allPerson as $label => $value) {
                            if ($mybb->get_input('filter_typePerson') == $value) {
                                $check_select = "selected";                    
                            } else {
                                $check_select = "";    
                            }

                            $filteroptions .= "<option value=\"".$value."\" ".$check_select.">".$label."</option>";
                        }    
                    }
    
                    $filter_select = "<select name=\"".$filterOption."\"><option value=\"%\">".$first_select."</option>".$filteroptions."</select>";

                    eval("\$filter_bit .= \"".$templates->get("celebritylist_filter_bit")."\";");    
                }

                eval("\$filter_option = \"".$templates->get("celebritylist_filter")."\";");
            } else {
                $filter_option = "";
            }

            $celebrity_query = $db->query("SELECT * FROM ".TABLE_PREFIX."celebritylist
            WHERE accepted = 1
            ".$typePerson_filter_sql.
            $type_filter_sql."
            ORDER BY COALESCE(NULLIF(username, ''), groupname) ASC   
            ".$multipage_sql
            );

            $user_bit = "";
            while($cel = $db->fetch_array($celebrity_query)) {
                $user_bit .= celebritylist_user_bit($cel, 'misc');
            }

            if (empty($user_bit)) {
                $user_bit = $lang->celebritylist_nodata;    
            }
            
            eval("\$celebritylist_bit = \"".$templates->get("celebritylist_bit")."\";");
        } else {

            $celebritylist_tab_menu = "";
            $celebritylist_tab_content = "";
            foreach ($allTypes as $key => $type) {

                if ($key === array_key_first($allTypes)) {
                    $defaultTab = "id=\"defaultCelebritylist\"";
                } else {
                    $defaultTab = "";
                }

                // Tab Menü
                eval("\$celebritylist_tab_menu .= \"".$templates->get("celebritylist_bit_tabs_menu")."\";");

                $celebrity_query = $db->query("SELECT * FROM ".TABLE_PREFIX."celebritylist
                WHERE accepted = 1 
                AND type = '".$type."' 
                ORDER BY COALESCE(NULLIF(username, ''), groupname) ASC"
                );

                $user_bit = "";
                while($cel = $db->fetch_array($celebrity_query)) {
                    $user_bit .= celebritylist_user_bit($cel);
                }

                if (empty($user_bit)) {
                    $user_bit = $lang->celebritylist_nodata_tab;
                }

                // Tab Content
                eval("\$celebritylist_tab_content .= \"".$templates->get("celebritylist_bit_tabs_content")."\";");
            }

            eval("\$celebritylist_bit = \"".$templates->get("celebritylist_bit_tabs")."\";");
        }

        $count_celebrity = $db->num_rows($db->query("SELECT cid FROM ".TABLE_PREFIX."celebritylist WHERE accepted = 1 ".$typePerson_filter_sql.$type_filter_sql)); 
        if ($count_celebrity == 1) {   
            $celebrity_count = $lang->sprintf($lang->celebritylist_count, $count_celebrity, $lang->celebritylist_count_single);
        } elseif ($count_celebrity > "1" || $count_celebrity == 0) {
            $celebrity_count = $lang->sprintf($lang->celebritylist_count, $count_celebrity, $lang->celebritylist_count_plural);
        }

        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("celebritylist")."\";");
        output_page($page);
        die();
    }

    // Bearbeiten - Speichern
    if($mybb->request_method == "post" && $mybb->input['action'] == "do_celebritylist_edit"){

        if ($mybb->get_input('person_type') == 'single') {
            if (empty($mybb->get_input('character'))) {
                $errors[] = $lang->celebritylist_error_character;
            }
        } else {
            if (empty($mybb->get_input('group'))) {
                $errors[] = $lang->celebritylist_error_group;
            }
        }
        if (empty($mybb->get_input('profession'))) {
            $errors[] = $lang->celebritylist_error_profession;      
        }
        if (empty($mybb->get_input('description'))) {
            $errors[] = $lang->celebritylist_error_description;      
        }
        if ($typecode == "checkbox") {
            $type_input = $mybb->get_input('type', MyBB::INPUT_ARRAY);
            if (empty($type_input)) {
                $errors[] = $lang->celebritylist_error_type_multi;
            }
        } else {
            $type_input = $mybb->get_input('type');
            if (empty($type_input)) {
                $errors[] = $lang->celebritylist_error_type;
            }
        }

        if (!empty($errors)) {
            $editerrors = inline_error($errors);
            $mybb->input['action'] = "celebritylist_edit";
        } else {
            
            $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

            if (!empty($mybb->get_input('group'))) {
                $groupname = $mybb->get_input('group');
            }

            if ($typecode == "checkbox") {
                $value = $mybb->get_input('type', MyBB::INPUT_ARRAY);
                $type = implode(",", array_map('trim', $value));
            } else {
                $type = $mybb->get_input('type');
            }

            $edit_celebrity = array(
                "groupname" =>  $db->escape_string($groupname),
                "type" =>  $db->escape_string($type),
                "profession" =>  $db->escape_string($mybb->get_input('profession')),
                "description" =>  $db->escape_string($mybb->get_input('description'))
            );

            $db->update_query("celebritylist", $edit_celebrity, "cid = ".(int)$cid.""); 
            redirect("misc.php?action=celebritylist", $lang->celebritylist_redirect_edit);
        }
    }

    // Bearbeiten
    if($mybb->get_input('action') == "celebritylist_edit"){

        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
        $draft = $db->fetch_array($db->simple_select('celebritylist', '*', 'cid = '.$cid));

        $lists_menu = celebritylist_lists_menu();

        // NAVIGATION
		if(!empty($list_nav)){
            add_breadcrumb($lang->celebritylist_lists, $list_nav);
            add_breadcrumb($lang->celebritylist_main, "misc.php?action=celebritylist");
		} else{
            add_breadcrumb($lang->celebritylist_main, "misc.php?action=celebritylist");
		}

        if(!isset($editerrors)){
            $editerrors = "";
            $group = $draft['groupname'];
            $profession = $draft['profession'];
            $description = $draft['description'];  

            if ($typecode == "checkbox") {
                $value = explode(",", $draft['type']);
            } else {
                $value = $draft['type'];
            }

        } else {
            $group = $mybb->get_input('group');
            $profession = $mybb->get_input('profession');
            $description = $mybb->get_input('description'); 

            if ($typecode == "checkbox") {
                $value = $mybb->get_input('type', MyBB::INPUT_ARRAY);
            } else {
                $value = $mybb->get_input('type');
            }
        }     

        $code = celebritylist_generate_input_field($typecode, $value);

        $codebuttons = build_mycode_inserter("description");
        if(function_exists('markitup_run_build')) {
            markitup_run_build('description');	
        };

        // Einzelperson
        if (!empty($draft['username'])) {
            $charactername = $draft['username'];
            $groupname = "";
            
            $lang->celebritylist_edit = $lang->sprintf($lang->celebritylist_edit, $draft['username']);
            add_breadcrumb($lang->sprintf($lang->celebritylist_edit, $draft['username']), "misc.php?action=celebritylist_edit");
        } 
        // Gruppe
        else {
            $charactername = "";
            $groupname = '<input type="text" class="textbox" size="40" name="group" value="'.htmlspecialchars($group).'">';

            $lang->celebritylist_edit = $lang->sprintf($lang->celebritylist_edit, $draft['groupname']);
            add_breadcrumb($lang->sprintf($lang->celebritylist_edit, $draft['groupname']), "misc.php?action=celebritylist_edit");
        }

        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("celebritylist_edit")."\";");
        output_page($page);
        die();
    }

    // Löschen
    if($mybb->get_input('action') == "celebritylist_delete"){
        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
        $db->delete_query("celebritylist", "cid = ".$cid);
        redirect("misc.php?action=celebritylist", $lang->celebritylist_redirect_delete);
    }
}

// MODCP - NAVIGATION
function celebritylist_modcp_nav() {

    global $db, $mybb, $templates, $theme, $header, $headerinclude, $footer, $lang, $modcp_nav, $nav_celebritylist;

	// SPRACHDATEI
	$lang->load('celebritylist');

	eval("\$nav_celebritylist = \"".$templates->get ("celebritylist_modcp_nav")."\";");
}

// MODCP  - SEITE
function celebritylist_modcp() {
   
    global $mybb, $templates, $lang, $theme, $header, $headerinclude, $footer, $db, $page, $modcp_nav, $parser_options, $modcp_bit;

    // return if the action key isn't part of the input
    $allowed_actions = [
        'celebritylist',
        'celebritylist_refuse',
        'celebritylist_accepted'
    ];
    if (!in_array($mybb->get_input('action', MyBB::INPUT_STRING), $allowed_actions)) return;

	// SPRACHDATEI
	$lang->load('celebritylist');

	// PARSER - HTML und CO erlauben
	require_once MYBB_ROOT."inc/class_parser.php";;
	$parser = new postParser;
	$parser_options = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"filter_badwords" => 0,
		"nl2br" => 1,
		"allow_videocode" => 0
	);

    // Seite
    if($mybb->get_input('action') == 'celebritylist') {

        // Add a breadcrumb
        add_breadcrumb($lang->nav_modcp, "modcp.php");
        add_breadcrumb($lang->celebritylist_modcp, "modcp.php?action=celebritylist");

		$modcp_query = $db->query("SELECT * FROM ".TABLE_PREFIX."celebritylist
		WHERE accepted = 0
        ORDER BY username ASC
        ");

        $modcp_bit = "";
        while($modcp = $db->fetch_array($modcp_query)) {
            $modcp_bit .= celebritylist_user_bit($modcp, 'modcp');
        }

        if (empty($modcp_bit)) {
            $modcp_bit = $lang->celebritylist_modcp_nodata;
        }
 
        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("celebritylist_modcp")."\";");
        output_page($page);
        die();
    }

    // Ablehnen
    if ($mybb->input['action'] == "celebritylist_refuse") {

        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
        $userid = $db->fetch_field($db->simple_select("celebritylist", "uid", "cid= '".$cid."'"), "uid");
        $username = $db->fetch_field($db->simple_select("celebritylist", "username", "cid= '".$cid."'"), "username");
        $groupname = $db->fetch_field($db->simple_select("celebritylist", "groupname", "cid= '".$cid."'"), "groupname");
        if (!empty($username)) {
            $celebrity = $username;
        } else {
            $celebrity = $groupname;
        }

        // MyALERTS STUFF
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('celebritylist_refuse');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$userid, $alertType, (int)$mybb->user['uid']);
                $alert->setExtraDetails([
                    'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'celebrity' => $celebrity
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);   
            }    
        }

        $db->delete_query("celebritylist", "cid = ".$cid);
        redirect("modcp.php?action=celebritylist", $lang->celebritylist_redirect_refuse);
    }

    // Anehmen
    if ($mybb->input['action'] == "celebritylist_accepted") {

        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
        $userid = $db->fetch_field($db->simple_select("celebritylist", "uid", "cid= '".$cid."'"), "uid");
        $username = $db->fetch_field($db->simple_select("celebritylist", "username", "cid= '".$cid."'"), "username");
        $groupname = $db->fetch_field($db->simple_select("celebritylist", "groupname", "cid= '".$cid."'"), "groupname");
        if (!empty($username)) {
            $celebrity = $username;
        } else {
            $celebrity = $groupname;
        }

        // MyALERTS STUFF
        if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('celebritylist_accepted');
            if ($alertType != NULL && $alertType->getEnabled()) {
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$userid, $alertType, (int)$mybb->user['uid']);
                $alert->setExtraDetails([
                    'username' => $mybb->user['username'],
                    'from' => $mybb->user['uid'],
                    'celebrity' => $celebrity
                ]);
                MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);   
            }    
        }

        $db->query("UPDATE ".TABLE_PREFIX."celebritylist SET accepted = 1 WHERE cid = '".$cid."'");
        redirect("modcp.php?action=celebritylist", $lang->celebritylist_redirect_accepted);
    }
}

// ONLINE LOCATION
function celebritylist_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'misc':
			if ($parameters['action'] == "celebritylist") {
				$user_activity['activity'] = "celebritylist";
			}
            break;
	}

	return $user_activity;
}
function celebritylist_online_location($plugin_array) {

	global $lang, $db, $mybb;
    
    // SPRACHDATEI LADEN
    $lang->load("celebritylist");

	if ($plugin_array['user_activity']['activity'] == "celebritylist") {
		$plugin_array['location_name'] = $lang->celebritylist_online_location;
	}

	return $plugin_array;
}

##############
### ALERTS ###
##############

// Backwards-compatible alert formatter registration.
function celebritylist_register_myalerts_formatter_back_compat(){

	global $lang;
	$lang->load('celebritylist');

	if (function_exists('myalerts_info')) {
		$myalerts_info = myalerts_info();
		if (version_compare($myalerts_info['version'], '2.0.4') <= 0) {
			celebritylist_register_myalerts_formatter();
		}
	}
}

// Alert formatter registration.
function celebritylist_register_myalerts_formatter(){

	global $mybb, $lang;

	$lang->load('celebritylist');

    // Annehmen
	if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') &&
	    class_exists('MybbStuff_MyAlerts_AlertFormatterManager') &&
	    !class_exists('celebritylistAcceptedAlertFormatter')
	) {
		class celebritylistAcceptedAlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
		{
			/**
			* Format an alert into it's output string to be used in both the main alerts listing page and the popup.
			*
			* @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
			*
			* @return string The formatted alert string.
			*/
			public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
			{
				$alertContent = $alert->getExtraDetails();
                return $this->lang->sprintf(
                $this->lang->celebritylist_alert_accepted,
                $outputAlert['from_user'],
                $alertContent['celebrity']
                );
			}

			/**
			* Init function called before running formatAlert(). Used to load language files and initialize other required
			* resources.
			*
			* @return void
			*/
			public function init()
			{
				if (!$this->lang->celebritylist_alert_accepted) {
					$this->lang->load('celebritylist');
				}
			}
		
			/**
			* Build a link to an alert's content so that the system can redirect to it.
			*
			* @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
			*
			* @return string The built alert, preferably an absolute link.
			*/
			public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
			{
				$alertContent = $alert->getExtraDetails();
				$postLink = $this->mybb->settings['bburl'] . '/misc.php?action=celebritylist';
				return $postLink;
			}
		}

		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
		if (!$formatterManager) {
		        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}
		if ($formatterManager) {
			$formatterManager->registerFormatter(new celebritylistAcceptedAlertFormatter($mybb, $lang, 'celebritylist_accepted'));
		}
	}

    // Ablehnen
	if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') &&
	    class_exists('MybbStuff_MyAlerts_AlertFormatterManager') &&
	    !class_exists('celebritylistRefuseAlertFormatter')
	) {
		class celebritylistRefuseAlertFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
		{
			/**
			* Format an alert into it's output string to be used in both the main alerts listing page and the popup.
			*
			* @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
			*
			* @return string The formatted alert string.
			*/
			public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
			{
				$alertContent = $alert->getExtraDetails();
                return $this->lang->sprintf(
                $this->lang->celebritylist_alert_refuse,
                $outputAlert['from_user'],
                $alertContent['celebrity']
                );
			}

			/**
			* Init function called before running formatAlert(). Used to load language files and initialize other required
			* resources.
			*
			* @return void
			*/
			public function init()
			{
				if (!$this->lang->celebritylist_alert_refuse) {
					$this->lang->load('celebritylist');
				}
			}
		
			/**
			* Build a link to an alert's content so that the system can redirect to it.
			*
			* @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
			*
			* @return string The built alert, preferably an absolute link.
			*/
			public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
			{
				$alertContent = $alert->getExtraDetails();
				$postLink = $this->mybb->settings['bburl'] . '/misc.php?action=celebritylist';
				return $postLink;
			}
		}

		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
		if (!$formatterManager) {
		        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}
		if ($formatterManager) {
			$formatterManager->registerFormatter(new celebritylistRefuseAlertFormatter($mybb, $lang, 'celebritylist_refuse'));
		}
	}
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// LISTEN MENÜ
function celebritylist_lists_menu() {

    global $db, $mybb, $templates, $lang, $lists_menu;

    $list_menu = $mybb->settings['celebritylist_list_menu'];
    $list_tpl = $mybb->settings['celebritylist_list_tpl'];

    if($list_menu == 2){
        $lists_menu = "";
        return;
    }

    // Jules Plugin
    if ($list_menu == 1) {
        $lang->load("lists");
        $query_lists = $db->simple_select("lists", "*");
        $menu_bit = "";
        while($list = $db->fetch_array($query_lists)) {
            eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
        }
    
        eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
    } else {
        eval("\$lists_menu = \"".$templates->get($list_tpl)."\";");
    }

    return $lists_menu;
}

// TYPES FELDER GENERIEN
function celebritylist_generate_input_field($type, $value = '') {

    global $mybb;

    $identification = "type";
    $expoptions = explode(',', str_replace(", ", ",", $mybb->settings['celebritylist_types']));

    $input = '';

    switch ($type) {
        case 'radio':
            foreach ($expoptions as $option) {
                $checked = ($option == $value) ? ' checked' : '';
                $input .= '<input type="radio" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($option) . '"' . $checked . '>';
                $input .= '<span class="smalltext">' . htmlspecialchars($option) . '</span><br />';
            }
            break;
        case 'checkbox':
            $value = is_array($value) ? $value : explode(',', $value);
            foreach ($expoptions as $option) {
                $checked = in_array($option, $value) ? ' checked' : '';
                $input .= '<input type="checkbox" name="'.htmlspecialchars($identification).'[]" value="' . htmlspecialchars($option) . '"' . $checked . '>';
                $input .= '<span class="smalltext">' . htmlspecialchars($option) . '</span><br />';
            }
            break;

        default:
            $input = '<input type="text" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($value) . '">';
            break;
    }

    return $input;
}

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function celebritylist_get_allchars($user_id) {

	global $db;

    if (function_exists('accountswitcher_is_installed')) {
        if (intval($user_id) === 0) {
            return array();
        } else {
            $userids_array = array(
                $user_id => get_user($user_id)['username']
            );
        }
        return $userids_array;
    }

    if (intval($user_id) === 0) {
        return array();
    }

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY username");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY username");
	}
	while ($users = $db->fetch_array($get_all_users)) {
        $uid = $users['uid'];
        $charas[$uid] = $users['username'];
	}
	return $charas;  
}

// MULTIPAGE
function celebritylist_multipage() {

	global $db, $mybb;

    $multipage_setting = $mybb->settings['celebritylist_multipage'];
    $displaytypes = $mybb->settings['celebritylist_displaytypes'];

    if ($displaytypes == 0 && $multipage_setting == 0) {
        return ['multipage_sql' => '', 'multipage' => ''];
    }
      
    $filterData = celebritylist_filter();
    $filter_multipage = $filterData['filter_multipage'];
    $typePerson_multipage = $filterData['typePerson_multipage'];
    $type_multipage = $filterData['type_multipage'];
    $typePerson_filter_sql = $filterData['typePerson_filter_sql'];
    $type_filter_sql = $filterData['type_filter_sql'];

    $allCelebrity = $db->num_rows($db->query("SELECT cid FROM ".TABLE_PREFIX."celebritylist
    WHERE accepted = 1        
    ".$typePerson_filter_sql.           
    $type_filter_sql));

    $perpage = $multipage_setting;
    $input_page = $mybb->get_input('page', MyBB::INPUT_INT);
    if($input_page) {
        $start = ($input_page-1) *$perpage;
    }
    else {
        $start = 0;
        $input_page = 1;       
    }
    
    $end = $start + $perpage;
    $lower = $start+1;
    $upper = $end;
    if($upper > $allCelebrity) {
        $upper = $allCelebrity;                   
    }

    $page_url = htmlspecialchars_uni("misc.php?action=celebritylist".$typePerson_multipage.$type_multipage.$filter_multipage);
    $multipage = multipage($allCelebrity, $perpage, $input_page, $page_url);
    $multipage_sql = "LIMIT ".$start.", ".$perpage; 

    return [
        'multipage_sql' => $multipage_sql,
        'multipage' => $multipage
    ];
}

// FILTER - QUERY & MULTIPAGE
function celebritylist_filter() {

    global $mybb;

    $typePerson_filter = "%";
    $type_filter = "%";
    $filter_multipage = "";
    if($mybb->get_input('celebritylist_filter')) {
        $typePerson_filter = $mybb->get_input('filter_typePerson');
        $type_filter = $mybb->get_input('filter_type');
        $filter_multipage = "&celebritylist_filter=filterCelebritylist";        
    }
        
    if ($typePerson_filter != "%" AND !empty($typePerson_filter)) {

        if ($typePerson_filter == 'single') {
            $typePerson_filter_sql = "AND username != '' AND groupname = ''";
        } elseif ($typePerson_filter == 'group') {
            $typePerson_filter_sql = "AND username = '' AND groupname != ''";            
        }

        $typePerson_multipage = "&filter_typePerson=".$typePerson_filter;
    } else {
        $typePerson_filter_sql = "";
        $typePerson_multipage = "";
    }

    if ($type_filter != "%" AND !empty($type_filter)) {
        $type_filter_sql = "AND type = '".$type_filter."'";
        $type_multipage = "&filter_type=".$type_filter;
    } else {
        $type_filter_sql = "";
        $type_multipage = "";
    }

    return [
        'filter_multipage' => $filter_multipage,
        'typePerson_multipage' => $typePerson_multipage,
        'type_multipage' => $type_multipage,
        'typePerson_filter_sql' => $typePerson_filter_sql,
        'type_filter_sql' => $type_filter_sql,
    ];
}

// USER BIT AUSGABE
function celebritylist_user_bit($cel, $mode = '') {

    global $db, $mybb, $lang, $templates, $options;

    $result = "";

    $lang->load('celebritylist');

    // PARSER - HTML und CO erlauben
	require_once MYBB_ROOT."inc/class_parser.php";;
	$parser = new postParser;
	$parser_options = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"filter_badwords" => 0,
		"nl2br" => 1,
		"allow_videocode" => 0
	);

    $userids_array = celebritylist_get_allchars($mybb->user['uid']);
   
    // Leer laufen lassen  
    $cid = "";
    $uid = "";
    $name = "";
    $type = "";
    $profession = "";
    $description = "";
    $options = "";
            
    // Mit Infos füllen  
    $cid = $cel['cid'];
    $uid = $cel['uid'];
    $type = $cel['type'];
    $profession = $cel['profession'];                
    $description = $parser->parse_message($cel['description'], $parser_options);
    
    if (!empty($cel['username'])) {
        if (!empty(get_user($uid)['username'])){
            $name = build_profile_link(get_user($uid)['username'], $uid);
        } else {
            $name = $cel['username'];
        }
    } else {
        $name = $cel['groupname'];
    }

    if ($mode == "modcp") {
        eval("\$result .= \"".$templates->get("celebritylist_modcp_bit")."\";"); 
    } else {
         
        if ($mybb->usergroup['canmodcp'] == '1' || array_key_exists($uid, $userids_array)) {
            eval("\$options = \"".$templates->get("celebritylist_options")."\";");
        } else {
            $options = "";          
        }

        eval("\$result .= \"".$templates->get("celebritylist_user")."\";"); 
    }

    return $result;
}

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// DATENBANKTABELLEN
function celebritylist_database() {

    global $db;
    
    if (!$db->table_exists("celebritylist")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."celebritylist(
            `cid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `uid` int(10) unsigned NOT NULL,
            `username` VARCHAR(500) NOT NULL,
            `groupname` VARCHAR(500) NOT NULL,
            `type` VARCHAR(500) NOT NULL,
            `profession` VARCHAR(1000) NOT NULL,
            `description` VARCHAR(5000) NOT NULL,
            `accepted` int(1) unsigned NOT NULL,
            PRIMARY KEY(`cid`),
            KEY `cid` (`cid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }
}

// EINSTELLUNGEN
function celebritylist_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'celebritylist_types' => array(
			'title' => 'Branchen',
            'description' => 'Gebe hier die Branchen an, in welche, die Berühmheiten einsortiert werden können. Trenne die einzelnen Branchen durch Kommas.',
            'optionscode' => 'textarea',
            'value' => 'Sport, Musik, Serien & Filme, Theater, Social Media, Wirtschaft', // Default
            'disporder' => 1
		),
        'celebritylist_multitypes' => array(
			'title' => 'mehrere Branchen',
            'description' => 'Können die Berühmheiten auch in mehrere Branchen einsortiert werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 2
		),
        'celebritylist_displaytypes' => array(
			'title' => 'Sortierung',
            'description' => 'Sollen die Berühmheiten in Tabs dargestellt werden, nur ein einfacher Filter oder gar keine spezielle Sortierung?',
            'optionscode' => 'select\n0=Tabs\n1=einfacher Dropselect Filter\n2=keine extra Sortierung',
            'value' => '1', // Default
            'disporder' => 3
		),
        'celebritylist_multipage' => array(
			'title' => 'Einträge pro Seite',
            'description' => 'Wie viele Berühmheiten sollen pro Seite angezeigt werden (0 = Keine Beschränkung)?',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 4
		),
        'celebritylist_delete' => array(
			'title' => 'Einträge löschen',
            'description' => 'Soll bei Löschung von einem Account automatisch der entsprechende Eintrag (bezieht sich nur auf Einzelpersonen) über diesen Charakter gelöscht werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 5
		),
		'celebritylist_list_nav' => array(
			'title' => "Listen PHP",
			'description' => "Wie heißt die Hauptseite der Listen-Seite? Dies dient zur Ergänzung der Navigation. Falls nicht gewünscht einfach leer lassen.",
			'optionscode' => 'text',
			'value' => 'lists.php', // Default
			'disporder' => 6
		),
		'celebritylist_list_menu' => array(
			'title' => 'Listen Menü',
			'description' => 'Soll über die Variable {$lists_menu} das Menü der Listen aufgerufen werden?<br>Wenn ja, muss noch angegeben werden, ob eine eigene PHP-Datei oder das Automatische Listen-Plugin von sparks fly genutzt?',
			'optionscode' => 'select\n0=eigene Listen/PHP-Datei\n1=Automatische Listen-Plugin\n2=keine Menü-Anzeige',
			'value' => '0', // Default
			'disporder' => 7
		),
        'celebritylist_list_tpl' => array(
            'title' => 'Listen Menü Template',
            'description' => 'Damit das Listen Menü richtig angezeigt werden kann, muss hier einmal der Name von dem Tpl von dem Listen-Menü angegeben werden.',
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 8
        ),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'celebritylist' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function celebritylist_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'celebritylist',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->celebritylist_main}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		{$adderrors}
		<div class="tborder celebritylist">
			<div class="thead"><strong>{$lang->celebritylist_main}</strong></div>
			<div class="trow1">
				<div class="celebritylist_info">{$lang->celebritylist_desc}</div>
				{$celebritylist_add}
				<div class="thead"><strong>{$celebrity_count}</strong></div>
				{$celebritylist_bit}
			</div>
		</div>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
     
    $templates[] = array(
        'title'		=> 'celebritylist_add',
        'template'	=> $db->escape_string('<form action="misc.php" method="post" id="celebritylist_add">
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td class="tcat" colspan="2">
				<strong>{$lang->celebritylist_form}</strong>
			</td>
		</tr>

		<tr>
			<td class="trow1" width="30%">
				<strong>{$lang->celebritylist_form_character}</strong>
				<div class="smalltext">
					{$lang->celebritylist_form_character_desc}<br>
					<label>
						<input type="radio" name="person_type" value="single" id="radio_single" {$person_type_single_checked}> Einzelperson
					</label>
					&nbsp;
					<label>
						<input type="radio" name="person_type" value="group" id="radio_group" {$person_type_group_checked}> Gruppe
					</label>
				</div>
			</td>
			<td class="trow1">
				<span class="smalltext">
					<input type="text" class="textbox" name="character" id="character" size="80" maxlength="1155" value="{$character}" />
					<input type="text" class="textbox" name="group" id="group" size="40" value="{$group}" style="display:none;" />
				</span>
			</td>
		</tr>

		<tr>
			<td class="trow1" width="30%"><strong>{$lang->celebritylist_form_profession}</strong>
				<div class="smalltext">{$lang->celebritylist_form_profession_desc}</div>
			</td>
			<td class="trow1">
				<span class="smalltext">
					<input type="text" class="textbox" name="profession" id="profession" value="{$profession}" placeholder="{$lang->celebritylist_form_profession_placeholder}" size="40" />
				</span>		
			</td>
		</tr>

		<tr>
			<td class="trow1" width="30%"><strong>{$lang->celebritylist_form_type}</strong>
				<div class="smalltext">{$lang->celebritylist_form_type_desc}</div>
			</td>
			<td class="trow1">
				<span class="smalltext">{$code}</span>		
			</td>
		</tr>
		<tr>
			<td class="trow1" width="30%"><strong>{$lang->celebritylist_form_description}</strong>
				<div class="smalltext">{$lang->celebritylist_form_description_desc}</div>
			</td>
			<td class="trow1">
				<span class="smalltext">
					<textarea id="description" name="description" rows="6" cols="42">{$description}</textarea>{$codebuttons}
				</span>		
			</td>
		</tr>

		<tr>
			<td class="trow1" colspan="2" align="center">
				<input type="hidden" name="action" value="do_celebritylist_add" />
				<input type="submit" class="button" name="celebritylistsubmit" value="{$lang->celebritylist_form_button}" />
			</td>
		</tr>
        </table>
        </form>

        <link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807">
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>
        <script type="text/javascript">
        document.addEventListener(\'DOMContentLoaded\', function() {

		const radioSingle = document.getElementById(\'radio_single\');
		const radioGroup  = document.getElementById(\'radio_group\');
		const inputCharacter = document.getElementById(\'character\');
		const inputGroup     = document.getElementById(\'group\');

		function toggleInputs() {
			if ($("#character").data(\'select2\')) {
				$("#character").select2(\'destroy\');
			}

			if (radioSingle.checked) {
				inputCharacter.style.display = \'inline-block\';
				inputGroup.style.display = \'none\';
				initSelect2(\'#character\');
			} else {
				inputCharacter.style.display = \'none\';
				inputGroup.style.display = \'inline-block\';
			}
		}

		function initSelect2(selector) {
			if (typeof use_xmlhttprequest !== "undefined" && use_xmlhttprequest == "1") {
				MyBB.select2();

				$(selector).select2({
					placeholder: "{$lang->search_user}",
					minimumInputLength: 2,
					multiple: false,
					allowClear: true,
					ajax: {
						url: "xmlhttp.php?action=get_users",
						dataType: \'json\',
						data: function (term, page) {
							return { query: term };
						},
						results: function (data, page) {
							return { results: data };
						}
					},
					initSelection: function(element, callback) {
						var value = $(element).val();
						if (value !== "") {
							callback({ id: value, text: value });
						}
					},
					createSearchChoice: function(term, data) {
						if ($(data).filter(function() {
							return this.text.localeCompare(term) === 0;
						}).length === 0) {
							return { id: term, text: term };
						}
					}
				});
			}
		}

		radioSingle.addEventListener(\'change\', toggleInputs);
		radioGroup.addEventListener(\'change\', toggleInputs);
		toggleInputs();

        });
        </script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_banner',
        'template'	=> $db->escape_string('<div class="red_alert"><a href="modcp.php?action=celebritylist">{$newentry_notice}</a></div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_bit',
        'template'	=> $db->escape_string('<div class="celebritylist_bit trow1">
        {$filter_option}
        {$user_bit}
        {$multipage}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_bit_tabs',
        'template'	=> $db->escape_string('<div class="celebritylist_bit trow1">
        <div class="celebritylist_tab">
		{$celebritylist_tab_menu}
        </div>
        {$celebritylist_tab_content}
        </div>

        <script>
        function openCelebritylistType(evt, typeName) {
		var i, celebritylist_tabcontent, celebritylist_tablinks;

		celebritylist_tabcontent = document.getElementsByClassName("celebritylist_tabcontent");
		for (i = 0; i < celebritylist_tabcontent.length; i++) {
			celebritylist_tabcontent[i].style.display = "none";
		}

		celebritylist_tablinks = document.getElementsByClassName("celebritylist_tablinks");
		for (i = 0; i < celebritylist_tablinks.length; i++) {
			celebritylist_tablinks[i].className = celebritylist_tablinks[i].className.replace(" celebritylist_active", "");
		}

		document.getElementById(typeName).style.display = "block";
		evt.currentTarget.className += " celebritylist_active";
        }

        document.getElementById("defaultCelebritylist")?.click();
        </script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_bit_tabs_content',
        'template'	=> $db->escape_string('<div id="{$type}" class="celebritylist_tabcontent">
        <h3>{$type}</h3>
        {$user_bit}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_bit_tabs_menu',
        'template'	=> $db->escape_string('<button class="celebritylist_tablinks" onclick="openCelebritylistType(event, \'{$type}\')" {$defaultTab}>{$type}</button>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_edit',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->celebritylist_edit}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		{$editerrors}
		<div class="tborder celebritylist">
			<div class="thead"><strong>{$lang->celebritylist_edit}</strong></div>
			<div class="trow1">
				<form action="misc.php" method="post" id="celebritylist_edit">
					<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}">
						<tr>
							<td class="trow1" width="30%">
								<strong>{$lang->celebritylist_form_character}</strong>
								<div class="smalltext">
									{$lang->celebritylist_form_character_desc}
								</div>
							</td>
							<td class="trow1">
								{$charactername}{$groupname}
							</td>
						</tr>

						<tr>
							<td class="trow1" width="30%"><strong>{$lang->celebritylist_form_profession}</strong>
								<div class="smalltext">{$lang->celebritylist_form_profession_desc}</div>
							</td>
							<td class="trow1">
								<span class="smalltext">
									<input type="text" class="textbox" name="profession" id="profession" value="{$profession}" placeholder="{$lang->celebritylist_form_profession_placeholder}" size="40" />
								</span>		
							</td>
						</tr>

						<tr>
							<td class="trow1" width="30%"><strong>{$lang->celebritylist_form_type}</strong>
								<div class="smalltext">{$lang->celebritylist_form_type_desc}</div>
							</td>
							<td class="trow1">
								<span class="smalltext">{$code}</span>		
							</td>
						</tr>
						<tr>
							<td class="trow1" width="30%"><strong>{$lang->celebritylist_form_description}</strong>
								<div class="smalltext">{$lang->celebritylist_form_description_desc}</div>
							</td>
							<td class="trow1">
								<span class="smalltext">
									<textarea id="description" name="description" rows="6" cols="42">{$description}</textarea>{$codebuttons}
								</span>		
							</td>
						</tr>

						<tr>
							<td class="trow1" colspan="2" align="center">
								<input type="hidden" name="cid" value="{$cid}" />
								<input type="hidden" name="groupname" value="{$draft[\'groupname\']}" />
								<input type="hidden" name="action" value="do_celebritylist_edit" />
								<input type="submit" class="button" name="celebritylistsubmit" value="{$lang->celebritylist_form_button}" />
							</td>
						</tr>
					</table>
				</form>
			</div>
		</div>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_filter',
        'template'	=> $db->escape_string('<form id="celebritylist_filter" method="get" action="misc.php">
        <input type="hidden" name="action" value="celebritylist" />
        <div class="celebritylist-filter">
		<div class="celebritylist-filteroptions">
			{$filter_bit}
		</div>
		<center>
			<input type="submit" name="celebritylist_filter" value="{$lang->celebritylist_filter_button}" id="celebritylist_filter" class="button">
		</center>
        </div>
        </form>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_filter_bit',
        'template'	=> $db->escape_string('<div class="celebritylist_filter_bit">
        <div class="celebritylist_filter_bit-headline">{$filter_headline}</div>
        <div class="celebritylist_filter_bit-dropbox">
		{$filter_select}
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_modcp',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->celebritylist_modcp}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$modcp_nav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead"><strong>{$lang->celebritylist_modcp}</strong></td>
						</tr>
						<tr>
							<td class="trow1">
								{$modcp_bit}
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_modcp_bit',
        'template'	=> $db->escape_string('<div class="celebritylist_user tborder">
        <div class="celebritylist_charaktername thead">{$name}</div>
        <div class="celebritylist_profession tcat">{$profession} <span class="float_right">[{$type}]</span></div>
        <div class="celebritylist_desc trow1">{$description}</div>
        <div class="celebritylist_ModCP-options trow1">
        <center>
        <a href="modcp.php?action=celebritylist_refuse&cid={$cid}" onClick="return confirm(\'{$lang->celebritylist_modcp_refuse_notice}\')" class="button">{$lang->celebritylist_modcp_refuse}</a> 
			<a href="modcp.php?action=celebritylist_accepted&cid={$cid}" class="button">{$lang->celebritylist_modcp_accepted}</a>
		</center>
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_modcp_nav',
        'template'	=> $db->escape_string('<tr><td class="trow1 smalltext"><a href="modcp.php?action=celebritylist" class="modcp_nav_item modcp_nav_modqueue">{$lang->celebritylist_modcp_nav}</td></tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_options',
        'template'	=> $db->escape_string('<span class="celebritylist_options float_right">
        <a href="misc.php?action=celebritylist_delete&cid={$cid}" onClick="return confirm(\'{$lang->celebritylist_options_delete_notice}\')">{$lang->celebritylist_options_delete}</a> 
        <a href="misc.php?action=celebritylist_edit&cid={$cid}">{$lang->celebritylist_options_edit}</a>
        </span>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'celebritylist_user',
        'template'	=> $db->escape_string('<div class="celebritylist_user tborder">
        <div class="celebritylist_charaktername thead">{$name} {$options}</div>
        <div class="celebritylist_profession tcat">{$profession}</div>
        <div class="celebritylist_desc trow1">{$description}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function celebritylist_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'celebritylist.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.celebritylist_info {
        padding: 20px 40px;
        text-align: justify;
        line-height: 180%;
        }

        #celebritylist_add {
        width: 80%;
        margin: auto;
        margin-bottom: 20px;
        }

        .celebritylist_user {
        margin-bottom: 20px;
        box-sizing: border-box;
        width: 100%;
        }

        .celebritylist_bit {
        padding: 20px;
        }

        .celebritylist_desc {
        padding: 5px;
        text-align: justify;
        }

        .celebritylist_tab {
        overflow: hidden;
        border: 1px solid #ccc;
        background-color: #f1f1f1;
        }

        .celebritylist_tab button {
        background-color: inherit;
        float: left;
        border: none;
        outline: none;
        cursor: pointer;
        padding: 14px 16px;
        transition: 0.3s;
        }

        .celebritylist_tab button:hover {
        background-color: #ddd;
        }

        .celebritylist_tab button.celebritylist_active {
        background-color: #ccc;
        }

        .celebritylist_tabcontent {
        display: none;
        padding: 6px 12px;
        border: 1px solid #ccc;
        border-top: none;
        }

        .celebritylist-filter {
        background: #f5f5f5;
        margin-bottom: 20px;
        }

        .celebritylist-filter-headline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        font-size: 12px;
        }

        .celebritylist-filteroptions {
        display: flex;
        justify-content: space-around;
        width: 90%;
        margin: 10px auto;
        gap: 5px;
        }

        .celebritylist_filter_bit {
        width: 100%;
        text-align: center;
        }

        .celebritylist_filter_bit-headline {
        padding: 6px;
        background: #ddd;
        color: #666;
        }

        .celebritylist_filter_bit-dropbox {
        margin: 5px;
        }',
		'cachefile' => 'celebritylist.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function celebritylist_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function celebritylist_is_updated(){

    global $db;

    if ($db->table_exists("celebritylist")) {
        return true;
    }
    return false;
}
