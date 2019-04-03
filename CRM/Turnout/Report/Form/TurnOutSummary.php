<?php

class CRM_Turnout_Report_Form_TurnOutSummary extends CRM_Turnout_Report_Form_TurnOutShared {
  function __construct() {
    $this->_columns = array(
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'filters' => array(
          'event_start_date' => array(
            'title' => ts('Date Range'),
            'type' => CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
      ),
     );
    parent::__construct();
  }

  function preProcess() {
    $this->assign('reportTitle', ts('Event Turnout Summary Report'));
    parent::preProcess();
  }

  function setEventIds() {
    $fieldName = 'event_start_date';
    list($from, $to) = $this->getFromTo(
      CRM_Utils_Array::value("{$fieldName}_relative", $this->_params),
      CRM_Utils_Array::value("{$fieldName}_from", $this->_params),
      CRM_Utils_Array::value("{$fieldName}_to", $this->_params)
    );
    // Find events in this date range.
    $sql = 'SELECT id FROM civicrm_event WHERE start_date > %0 AND start_date < %1';
    $params = array(
      0 => array($from, 'Timestamp'),
      1 => array($to, 'Timestamp'),
    );
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    while($dao->fetch()) {
      $this->event_ids[] = $dao->id;
    }
  }

  function postProcess() {
    parent::postProcess();
    $this->setEventIds(); 
    $template = CRM_Core_Smarty::singleton();
    if(count($this->event_ids) == 0) {
      $template->assign('to_message', ts("No events were chosen."));
      return;
    }
    $this->populateDataTable();
    if($this->getUniverseCount() == 0) {
      $template->assign('to_message', ts("No turn out data is entered for this date range."));
      return;
    }
    $this->setOrganizers();
    $this->setConstituentTypes();
    $this->setQuarterlySummary($template);
  }

  /**
   * Return count of all contacts assigned to organizer with this ct
   */
  function getTotalUniverseCount($organizer, $constituent_type) {
    $sql = "SELECT COUNT(*) AS count FROM civicrm_contact c JOIN `" .
      $this->constituent_table .  "` ci ON c.id = ci.entity_id " .
      "WHERE `" . $this->staff_responsible . "` = %0 AND `" . $this->constituent_type .
      "` LIKE %1 AND is_deleted = 0";

    $params = array(
      0 => array($organizer, 'String'), 
      1 => array('%' . SEP . $constituent_type . SEP . '%' , 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->fetch();
    return $dao->count;
  }

  /**
   * Get number of people touched - called with a response.
   */
  function getTouchedCount($organizer, $constituent_type) {
    $sql = "SELECT COUNT(DISTINCT contact_id) AS count FROM `$this->data_table` WHERE ".
      "organizer = %0 AND constituent_type LIKE %1 ";
    $responses = $this->getContactedResponses();
    $responses_fragment = array();
    while(list(,$response) = each($responses)) {
      $responses_fragment[] = '"' . CRM_Core_DAO::escapeString($response) . '"';
    }
    $responses_in = implode(',', $responses_fragment);
    $sql .= "AND (
      invitation_response IN ($responses_in) OR 
      second_call_response IN ($responses_in) OR
      reminder_response IN ($responses_in) 
    )";
   $params = array(
      0 => array($organizer, 'String'), 
      1 => array('%' . SEP . $constituent_type . SEP . '%' , 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->fetch();
    return $dao->count;
  }
  
  function getAttendedCount($organizer, $constituent_type) {
    // Status_id 2 is attended
    $sql = "SELECT COUNT(DISTINCT contact_id) AS count FROM `$this->data_table` WHERE ".
      "organizer = %0 AND constituent_type LIKE %1 AND status_id = 2";
    $params = array(
      0 => array($organizer, 'String'), 
      1 => array('%' . SEP . $constituent_type . SEP . '%' , 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->fetch();
    return $dao->count;
  }

  static function getWantedConstituentTypes() {
    return array('B', 'A1', 'A2', 'New', 'Lead 1', 'Leader');
  }

  function setQuarterlySummary(&$template) {
    $data = array();
    $wanted_constituent_types = $this->getWantedConstituentTypes();
    while(list($organizer, $organizer_friendly) = each($this->organizers)) {
      if(empty($organizer)) continue;
      $data[$organizer_friendly] = array();
      reset($this->constituent_types);
      while(list($constituent_type, $constituent_type_label) = each($this->constituent_types)) {
        if(empty($constituent_type)) continue;
        if(!in_array($constituent_type, $wanted_constituent_types)) {
          continue;
        }
        $universe = $this->getTotalUniverseCount($organizer, $constituent_type);
        $touched = $this->getTouchedCount($organizer, $constituent_type);
        $reminder_yes = $this->getRemindersTotal('Y', $organizer, NULL, $constituent_type);
        $call_yes = $this->getCalculatedTotal('Y', $organizer, NULL, $constituent_type);
        $attended = $this->getAttendedCount($organizer, $constituent_type);

        if($universe == 0) continue;
        $touched_percent = number_format($touched / $universe * 100, 0);
        $reminder_yes_percent = number_format($reminder_yes / $universe * 100, 0);
        $call_yes_percent = number_format($call_yes / $universe * 100, 0);
        $attended_percent = number_format($attended / $universe * 100, 0);

        $data[$organizer_friendly][$constituent_type_label] = array();
        $data[$organizer_friendly][$constituent_type_label]['universe'] = $universe;
        $data[$organizer_friendly][$constituent_type_label]['touched'] = "$touched (${touched_percent}%)";
        $data[$organizer_friendly][$constituent_type_label]['call_yes'] = "$call_yes (${call_yes_percent}%)";
        $data[$organizer_friendly][$constituent_type_label]['reminder_yes'] = "$reminder_yes (${reminder_yes_percent}%)";
        $data[$organizer_friendly][$constituent_type_label]['attended'] = "$attended (${attended_percent}%)";
      }
      if(count($data[$organizer_friendly]) == 0) {
        unset($data[$organizer_friendly]);
      }
    }
    $template->assign('to_results', TRUE);
    $template->assign('data', $data);
  }

}
