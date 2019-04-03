<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Turnout_Report_Form_TurnOutSummary',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'TurnOutSummary',
      'description' => 'TurnOutSummary (net.ourpowerbase.turnout)',
      'class_name' => 'CRM_Turnout_Report_Form_TurnOutSummary',
      'report_url' => 'net.ourpowerbase.turnout/turnoutsummary',
      'component' => 'CiviCampaign',
    ),
  ),
);
