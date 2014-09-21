<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

/**
 * Get gateway plugin status
 *
 * @global array $core_config
 * @param string $name
 *        	gateway name
 * @return boolean
 */
function gateway_get_status($name) {
	global $core_config;
	if (core_gateway_get() == $name) {
		$ret = TRUE;
	} else {
		$ret = FALSE;
	}
	return $ret;
}

/**
 * Get all gateway data
 *
 * @return array
 */
function gateway_getall() {
	global $core_config, $plugin_config;
	
	$ret = array();
	foreach ($core_config['gatewaylist'] as $gw ) {
		$ret[] = $plugin_config[$gw];
	}
	
	return $ret;
}

/**
 * Get all smsc data
 *
 * @return array
 */
function gateway_getall_smsc() {
	$ret = array();
	
	$db_table = _DB_PREF_ . "_tblGateway";
	$ret = dba_search($db_table, '*', $conditions);
	
	return $ret;
}

/**
 * Get smsc data by ID
 *
 * @param integer $id        	
 * @return array
 */
function gateway_get_smscbyid($id) {
	$ret = array();
	
	$db_table = _DB_PREF_ . "_tblGateway";
	$conditions = array(
		'id' => $id 
	);
	$ret = dba_search($db_table, '*', $conditions);
	
	return $ret[0];
}

/**
 * Get smsc data by name
 *
 * @param string $name        	
 * @return array
 */
function gateway_get_smscbyname($name) {
	$ret = array();
	
	$db_table = _DB_PREF_ . "_tblGateway";
	$conditions = array(
		'name' => $name 
	);
	$ret = dba_search($db_table, '*', $conditions);
	
	return $ret[0];
}

/**
 * Get valid name for supplied gateway
 *
 * @param string $name        	
 * @return mixed
 */
function gateway_valid_name($name) {
	global $core_config;
	
	if (trim($name)) {
		foreach ($core_config['gatewaylist'] as $gw ) {
			if ($name && $gw && $name == $gw) {
				if ((strtolower($name) == 'blocked') || (strtolower($name) == 'dev')) {
					$name = '';
				}
				return $name;
			}
		}
	}
	
	return FALSE;
}

/**
 * Activate selected gateway plugin
 *
 * @global array $core_config
 * @param string $name
 *        	gateway name
 * @return boolean
 */
function gateway_set_active($name) {
	global $core_config;
	$ret = FALSE;
	$fn1 = $core_config['apps_path']['plug'] . '/gateway/' . $name . '/config.php';
	$fn2 = $core_config['apps_path']['plug'] . '/gateway/' . $name . '/config.php';
	if (file_exists($fn1) && file_exists($fn2) && (core_gateway_get() != $name)) {
		$items = array(
			'gateway_module' => $name 
		);
		if (registry_update(1, 'core', 'main_config', $items)) {
			$core_config['main']['gateway_module'] = $name;
			$ret = TRUE;
		}
	}
	return $ret;
}

/**
 * List gateway plugins and load its configuration
 *
 * @global array $core_config
 * @return string gateway plugins configuration
 */
function gateway_list() {
	global $core_config;
	$upload_path = $core_config['apps_path']['plug'] . '/gateway/';
	$dir = opendir($upload_path);
	$z = 0;
	while ($fn = readdir($dir)) {
		$template = preg_match('/^_/', $fn, $match);
		if (is_dir($upload_path . $fn) && $f != "." && $f != ".." && $template != true && $fn != 'common') {
			$subdir_tab[$z]['name'] .= $fn;
			$subdir_tab[$z]['version'] .= trim(file_get_contents($core_config['apps_path']['plug'] . '/gateway/' . $f . '/docs/VERSION'));
			$subdir_tab[$z]['date'] .= date($core_config['datetime']['format'], filemtime($upload_path . $f));
			if (gateway_get_status($fn)) {
				$subdir_tab[$z][status] .= '<span class=status_enabled></span>';
			} else {
				$subdir_tab[$z][status] .= '<span class=status_disabled></span>';
			}
			$z++;
		}
	}
	return $subdir_tab;
}

/**
 * Display gateways on UI
 *
 * @global array $core_config
 * @return string
 */
function _gateway_display() {
	global $core_config, $icon_config, $plugin_config;
	
	$subdir_tab = gateway_list();
	for($l = 0; $l < sizeof($subdir_tab); $l++) {
		unset($gateway_info);
		$c_gateway = strtolower($subdir_tab[$l]['name']);
		$xml_file = $core_config['apps_path']['plug'] . '/gateway/' . $c_gateway . '/docs/info.xml';
		if ($fc = file_get_contents($xml_file)) {
			$gateway_info = core_xml_to_array($fc);
			$gateway_info['status'] = $subdir_tab[$l]['status'];
		}
		if ($gateway_info['name']) {
			$c_link_edit = "index.php?app=main&inc=gateway_" . $c_gateway . "&op=manage";
			$c_link_add = '';
			if (!(($c_gateway == 'dev') || ($c_gateway == 'blocked'))) {
				$c_link_add = "index.php?app=main&inc=core_gateway&op=add_smsc&gateway=" . $c_gateway;
			}
			$gw_list[$gateway_info['name']] = array(
				'link_edit' => $c_link_edit,
				'link_add' => $c_link_add,
				'name' => $gateway_info['name'],
				'description' => $gateway_info['description'],
				'release' => $gateway_info['release'],
				'status' => $gateway_info['status'] 
			);
		}
	}
	ksort($gw_list);
	$content = "
		<div class=table-responsive>
		<table class=playsms-table-list id='gateway_view'>
			<thead><tr>
				<th width=40%>" . _('Name') . "</th>
				<th width=50%>" . _('Description') . "</th>
				<th width=10%>" . _('Action') . "</th>
			</tr></thead>
			<tbody>";
	foreach ($gw_list as $gw ) {
		$c_link_edit = '';
		if ($gw['link_edit']) {
			$c_link_edit = "<a href='" . _u($gw['link_edit']) . "'>" . $icon_config['edit'] . "</a>";
		}
		$c_link_add = '';
		if ($gw['link_add']) {
			$c_link_add = "<a href='" . _u($gw['link_add']) . "'>" . $icon_config['add'] . "</span></a>";
		}
		$content .= "
			<tr>
				<td>" . $gw['name'] . "</td>
				<td>" . $gw['description'] . "</td>
				<td>
					" . $c_link_edit . "
					" . $c_link_add . "
				</td>
			</tr>";
	}
	$content .= "</tbody></table></div>";
	
	return $content;
}

/**
 * Display smscs on UI
 *
 * @global array $core_config
 * @return string
 */
function _gateway_display_smsc() {
	global $core_config, $icon_config;
	
	$db_table = _DB_PREF_ . '_tblGateway';
	$extras = array(
		'ORDER BY' => 'gateway' 
	);
	$smsc_list = dba_search($db_table, '*', '', '', $extras);
	
	$content = "
		<div class=table-responsive>
		<table class=playsms-table-list id='gateway_view_smsc'>
			<thead><tr>
				<th width=40%>" . _('Name') . "</th>
				<th width=50%>" . _('Gateway') . "</th>
				<th width=10%>" . _('Action') . "</th>
			</tr></thead>
			<tbody>";
	foreach ($smsc_list as $smsc ) {
		
		$c_link_edit = '';
		$c_link_del = '';
		if (!(($smsc['gateway'] == 'dev') || ($smsc['gateway'] == 'blocked'))) {
			$smsc['link_edit'] = "index.php?app=main&inc=core_gateway&op=edit_smsc&id=" . $smsc['id'];
			$c_link_edit = "<a href='" . _u($smsc['link_edit']) . "'>" . $icon_config['edit'] . "</a>";
			
			$smsc['link_del'] = "index.php?app=main&inc=core_gateway&op=del_smsc&id=" . $smsc['id'];
			$c_link_del = "<a href=\"javascript: ConfirmURL('" . _('Are you sure ?') . "', '" . _u($smsc['link_del']) . "')\">" . $icon_config['delete'] . "</span></a>";
		}
		
		$content .= "
			<tr>
				<td>" . $smsc['name'] . "</td>
				<td>" . $smsc['gateway'] . "</td>
				<td>
					" . $c_link_edit . "
					" . $c_link_del . "
				</td>
			</tr>";
	}
	$content .= "</tbody></table></div>";
	
	return $content;
}