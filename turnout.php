<?php

require_once 'turnout.civix.php';
use CRM_Turnout_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/ 
 */
function turnout_civicrm_config(&$config) {
  _turnout_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function turnout_civicrm_xmlMenu(&$files) {
  _turnout_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function turnout_civicrm_install() {
  _turnout_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function turnout_civicrm_postInstall() {
  _turnout_civix_civicrm_postInstall();

  // Via managed entities, we create a group of custom fields. Some of the fields
  // are radio fields that have options, so we ask managed entities to create
  // those options. 
  //
  // However, managed entities cannot assign each custom field to the
  // appropriate option group so we do that manually here.

  $pairs = array(
    'turnout_reminder_response' => 'turnout_invite_response_values',
    'turnout_invitation_response' => 'turnout_invite_response_values',
    'turnout_second_call_response' => 'turnout_invite_response_values',
  );

  foreach($pairs as $field_name => $option_group_name) {
    turnout_assign_option_group_to_custom_field($field_name, $option_group_name); 
  }

  // Bugfix. It seems that managed entities do not properly set our
  // custom data group to be based on participants by event so updated it here.
  $params = array(
    'name' => 'turnout_fields',
    'return' => 'id'
  );
  $id = civicrm_api3('CustomGroup', 'getvalue', $params);
  $sql = 'UPDATE civicrm_custom_group SET extends_entity_column_id = 2 WHERE id = %0';
  CRM_Core_DAO::executeQuery($sql, array(0 => array($id, 'Integer')));

  // We add some special dynamic code to the managed hook call. So, we
  // have to trigger a fresh reconciliation at the end of installation
  // to ensure everything is properly created.
 
  // Also, the participant custom fields are, for some reason, heavily cached. 
  // So we have to clear that cache to ensure they are created properly.  
  CRM_Event_BAO_Participant::$_importableFields = NULL;
  $force = TRUE;
  CRM_Core_BAO_UFField::getAvailableFieldsFlat($force);

  CRM_Core_ManagedEntities::singleton(TRUE)->reconcile();

}

/**
 * Assign option groups to fields
 *
 * @param string $field_name 
 *   string name of the field
 * @param string $option_group_name
 *   string name of option group
 *
 **/
function turnout_assign_option_group_to_custom_field($field_name, $option_group_name) {
  $params = array('name' => $option_group_name);
  $option_group = civicrm_api3('option_group', 'getsingle', $params);

  // Get the custom field.
  $params = array('name' => $field_name);

  try {
    $field = civicrm_api3('custom_field', 'getsingle', $params); 
    // Update the custom field.
    $field['option_group_id'] = $option_group['id'];
    civicrm_api3('custom_field', 'create', $field);
  }
  catch(CiviCRM_API3_Exception $e) {
    if ($e->getMessage() == 'Expected one CustomField but found 0') {
      // If we can't locate the custom field, it might mean they have disabled
      // it, deleted it or it never existed in the first place. That's ok.
      return;
    }
  }
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function turnout_civicrm_uninstall() {
  _turnout_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function turnout_civicrm_enable() {
  _turnout_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function turnout_civicrm_disable() {
  _turnout_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function turnout_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _turnout_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function turnout_civicrm_managed(&$entities) {
  // We dynamically add our profile because api3 doesn't support adding
  // custom fields using the name, we can only add them using their id
  // and the id will change on every installation.
  $profile  = array(
    'name' => 'turnout_update_event_invite_response',
    'entity' => 'UFGroup',
    'update' => 'never',
    'module' => 'net.ourpowerbase.turnout',
    'params' => array(
      'version' => 3,
      'title' => 'Update Event Invite Response',
      'description' => 'Powerbase profile for updating responses to invitations',
      'is_active' => 1,
      'name' => 'turnout_update_event_invite_response',
    ),
  );
  $fields = array(
    'turnout_invitation_date',
    'turnout_invitation_response',
    'turnout_second_call_date',
    'turnout_second_call_response',
    'turnout_reminder_date',
    'turnout_reminder_response',
  );
 
  $profile_fields = array();
  $weight = 0;
  foreach ($fields as $field_name) {
    // Get the custom id of the field we want.
    $result = civicrm_api3('CustomField', 'get', array('name' => $field_name));
    if ($result['count'] > 0) {
      $id = $result['id'];
      $values = array_pop($result['values']);
      $label = $values['label'];
      $weight += 10;
      $profile_fields[] = array(
        'uf_group_id' => '$value.id',
        'field_name' => 'custom_' . $id,
        'is_active' => 1,
        'label' => $label,
        'field_type' => 'Participant',
        "weight" => $weight,
        "in_selector" => "0",
        'visibility' => 'User and User Admin Only',
      );
    }
  }
  // Depending on timing, the custom fields may not yet be created.
  // If that's the case, don't add this at all - we want to wait
  // until we have all the pieces before we add it because we have
  // update set to never.
  if (count($profile_fields) > 0) {
    $profile['params']['api.uf_field.create'] = $profile_fields;
    $entities[] = $profile;
  }

  _turnout_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function turnout_civicrm_caseTypes(&$caseTypes) {
  _turnout_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function turnout_civicrm_angularModules(&$angularModules) {
  _turnout_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function turnout_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _turnout_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function turnout_civicrm_entityTypes(&$entityTypes) {
  _turnout_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 *
function turnout_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 *
function turnout_civicrm_navigationMenu(&$menu) {
  _turnout_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _turnout_civix_navigationMenu($menu);
} // */
