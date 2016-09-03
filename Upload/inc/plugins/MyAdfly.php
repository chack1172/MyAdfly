<?php
/**
 * MyAdfly
 * 
 * PHP Version 5
 * 
 * @category MyBB_18
 * @package  MyAdfly
 * @author   chack1172 <NBerardozzi@gmail.com>
 * @license  https://creativecommons.org/licenses/by-nc/4.0/ CC BY-NC 4.0
 * @link     http://www.chack1172.altervista.org/Projects/MyBB-18/MyAdfly.html
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_config_plugins_deactivate_commit', 'MyAdfly_destroy');
    $plugins->add_hook('admin_config_settings_begin', 'MyAdfly_lang');
} else {
    $plugins->add_hook('parse_message', 'MyAdfly_parse');
    $plugins->add_hook('showthread_start', 'MyAdfly_thread');
}

/**
 * Return plugin info
 *
 * @return array
 */
function MyAdfly_info()
{
    global $mybb, $lang;
    
	MyAdfly_lang();
	
    $destroy = <<<EOT
<p>
    <a href="index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;destroy=1&amp;plugin=MyAdfly&amp;my_post_key={$mybb->post_code}" style="color: red; font-weight: bold">{$lang->adfly_destroy}</a>
</p>
EOT;

    return [
        'name'          => $lang->adfly_title,
        'description'   => $lang->adfly_desc.$destroy,
        'website'       => $lang->adfly_url,
        'author'        => 'chack1172',
        'authorsite'    => $lang->adfly_chack1172,
        'version'       => '1.0',
        'compatibility' => '18*',
        'codename'      => 'MyAdfly',
    ];
}

/**
 * Install the plugin
 *
 * @return void
 */
function MyAdfly_install()
{
    global $db, $lang;
    
    $collation = $db->build_create_table_collation();
    
    if (!$db->table_exists('adfly')) {
        $db->write_query(
            "CREATE TABLE ".TABLE_PREFIX."adfly (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `tid` int(10) unsigned NOT NULL,
                `from` varchar(255) NOT NULL default '',
                `to` varchar(255) NOT NULL default '',
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM{$collation};"
        );
    }
    
    MyAdfly_deleteSettings();
    
    $group = [
        'name'        => 'myadfly',
        'title'       => $db->escape_string($lang->setting_group_myadfly),
        'description' => $db->escape_string($lang->setting_group_myadfly_desc),
    ];
    $gid = $db->insert_query('settinggroups', $group);
    
    $settings = [
        'adfly_id'     => [
            'title'       => $db->escape_string($lang->setting_adfly_id),
            'description' => $db->escape_string($lang->setting_adfly_id_desc),
            'value'       => '',
            'optionscode' => 'numeric',
        ],
        'adfly_api'    => [
            'title'       => $db->escape_string($lang->setting_adfly_api),
            'description' => $db->escape_string($lang->setting_adfly_api_desc),
            'value'       => '',
            'optionscode' => 'text',
        ],
        'adfly_groups' => [
            'title'       => $db->escape_string($lang->setting_adfly_groups),
            'description' => $db->escape_string($lang->setting_adfly_groups_desc),
            'value'       => '',
            'optionscode' => 'groupselect',
        ]
    ];
    
    foreach ($settings as $key => $setting) {
        $setting['name'] = $key;
        $setting['gid']  = $gid;
        
        $db->insert_query('settings', $setting);
    }
    rebuild_settings();
}

/**
 * Check if plugin is installed
 *
 * @return bool
 */
function MyAdfly_is_installed()
{
    global $db, $mybb;
    
    if (isset($mybb->settings['adfly_id'])
        && isset($mybb->settings['adfly_api'])
        && isset($mybb->settings['adfly_groups'])
        && $db->table_exists('adfly')
    ) {
        return true;
    }
    
    return false;
}

/**
 * Uninstall the plugin
 *
 * @return void
 */
function MyAdfly_uninstall()
{
    global $db;
    
    MyAdfly_deleteSettings();
    rebuild_settings();
    
    if ($db->table_exists('adfly')) {
        $db->drop_table('adfly');
    }
}

/**
 * Delete all files of the plugin
 *
 * @return void
 */
function MyAdfly_destroy()
{
    global $mybb, $message, $lang;
    
    if ($mybb->input['destroy'] == 1) {
		MyAdfly_lang();
        // extra files and dirs to remove
        $extra_files = [
            'inc/languages/english/admin/MyAdfly.lang.php',
            'inc/languages/italiano/admin/MyAdfly.lang.php',
        ];
        
        foreach ($extra_files as $file) {
            if (!file_exists(MYBB_ROOT.$file) || is_dir(MYBB_ROOT.$file)) {
                continue;
            }
            
            unlink(MYBB_ROOT.$file);
        }
        unlink(__FILE__);
        
        $message = $lang->adfly_destroyed;
    }
}

/**
 * Delete setting group and settings
 *
 * @return void
 */
function MyAdfly_deleteSettings()
{
    global $db;
    
    $db->delete_query(
        'settings',
        "name IN ('adfly_id', 'adfly_api', 'adfly_groups')"
    );
    $db->delete_query('settinggroups', "name = 'myadfly'");
}

/**
 * Load adfly links of the current thread
 *
 * @return void
 */
function MyAdfly_thread()
{
    global $db, $tid;
    
    $query = $db->simple_select('adfly', '*', "tid = {$tid}");
    $links = [];
    if ($db->num_rows($query) > 0) {
        while ($link = $db->fetch_array($query)) {
            $links[$link['from']] = $link['to'];
        }
    }
    
    $GLOBALS['links'] = $links;
}

/**
 * Load afly links of the current thread
 *
 * @param string $message The post message
 *
 * @return void
 */
function MyAdfly_parse(&$message)
{
	global $mybb;
    $tid = $GLOBALS['tid'];
	
    if (isset($tid) && is_member($mybb->settings['adfly_groups'])) {
        $message = preg_replace_callback(
            "#\<a(.*?)href=\"(.*?)\"(.*?)\>#is",
            create_function('$matches', 'return MyAdfly_parseUrl($matches);'),
            $message
        );
    }
    
    return $message;
}

/**
 * Change url tags
 *
 * @param string $matches The tag url
 *
 * @return string $tag
 */
function MyAdfly_parseUrl($matches)
{
    $links = $GLOBALS['links'];
    
    if (isset($links[$matches[2]])) {
        $url = $links[$matches[2]];
    } else {
        $url = MyAdfly_getUrl($matches[2]);
    }
    
    $tag = str_replace($matches[2], $url, $matches[0]);
    return $tag;
}

/**
 * Get a new adfly url
 *
 * @param string $url The normal url
 *
 * @return string $link The generated url
 */
function MyAdfly_getUrl($url)
{
    $mybb = $GLOBALS['mybb'];
    $api = 'http://api.adf.ly/api.php?';
    $query = [
        'key' => $mybb->settings['adfly_api'],
        'uid' => $mybb->settings['adfly_id'],
        'advert_type' => 'int',
        'domain' => 'adf.ly',
        'url' => $url,
    ];
    $api .= http_build_query($query);
	
    $link = '';
    if (!empty($query['key']) && !empty($query['uid'])) {
        if ($data = file_get_contents($api)) {
            $link = $data;

            MyAdfly_saveUrl($url, $link);
        }
    }
	
    if (empty($link) && !empty($query['uid'])) {
        $link = 'http://adf.ly/'.$query['uid'].'/'.$url;
    } elseif (empty($link)) {
        $link = $url;
    }
	
    return $link;
}

/**
 * Save generated adfly url in the database
 *
 * @param string $from The normal url
 * @param string $to   The generated adfly url
 *
 * @return void
 */
function MyAdfly_saveUrl($from, $to)
{
    $db = $GLOBALS['db'];
    
    $data = [
        'tid'  => $GLOBALS['tid'],
        'from' => $from,
        'to'   => $to,
    ];
    
    $db->insert_query('adfly', $data);
}

/**
 * Load the lang
 *
 * @return object $lang
 */
function MyAdfly_lang()
{
    global $lang;
    
    $lang->load('MyAdfly');
    
    return $lang;
}
