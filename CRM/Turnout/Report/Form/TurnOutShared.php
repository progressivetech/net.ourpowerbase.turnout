<?php

// Define a shorter constant for easier code reading.
define('SEP', CRM_Core_DAO::VALUE_SEPARATOR);

class CRM_Turnout_Report_Form_TurnOutShared extends CRM_Report_Form {
  protected $participant_table = 'civicrm_value_participant_info';
  protected $constituent_table = 'civicrm_value_constituent_info';
  protected $invitation_date = 'invitation_date';
  protected $invitation_response = 'invitation_response';
  protected $second_call_date = 'second_call_date';
  protected $second_call_response = 'second_call_response';
  protected $reminder_date = 'reminder_date';
  protected $reminder_response = 'reminder_response';
  protected $constituent_type = 'constituent_type';
  protected $staff_responsible = 'staff_responsible';

  // Variables below store unique values for the given report based on the
  // criteria selected by the user.
  protected $event_ids = array();
  protected $oranizers = array();
  protected $constituent_types = array();

  // We're not using typical Report fields - so tell the parent class
  // so we get the $_params variable set properly - which is required
  // for saving report instances (CRM_Report_Form::beginPostProcess -
  // CRM-8532).
  public $_noFields = TRUE;

  // Temp table for keeping results.
  protected $data_table = NULL;

  function __construct() {
    // Initialize our table name and column names for the participant table.
    // Some installs will have different values, this is for backward 
    // compatibility.
    $sql = "SELECT id, table_name FROM civicrm_custom_group WHERE name =
      'Participant_Info' OR table_name like 'civicrm_value_participant_info%'";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $dao->fetch();
    if (isset($dao->id)) {
      $this->participant_table = $dao->table_name;
      // Now get our field names
      $sql = "SELECT column_name, name FROM civicrm_custom_field WHERE
        custom_group_id = %0";
      $params = array(0 => array($dao->id, 'Integer'));
      $column_dao = CRM_Core_DAO::executeQuery($sql, $params);
      $fields = array('invitation_date', 'invitation_response',
        'second_call_date', 'second_call_response', 'reminder_date',
        'reminder_response');
      while($column_dao->fetch()) {
        reset($fields);
        while(list(,$field) = each($fields)) {
          if(strtolower($column_dao->name) == $field) {
            $this->$field = $column_dao->column_name;
          }
        }
      }
    }
    // Initialize our table name and column names for the constituent table.
    // Some installs will have different values.
    $sql = "SELECT id, table_name FROM civicrm_custom_group WHERE name LIKE 
      'Constituent_Info_Individuals%' OR table_name like 'civicrm_value_constituent_info%'";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $dao->fetch();
    if (isset($dao->id)) {
      $this->constituent_table = $dao->table_name;
      // Now get our field names
      $sql = "SELECT column_name, name FROM civicrm_custom_field WHERE
        custom_group_id = %0";
      $params = array(0 => array($dao->id, 'Integer'));
      $column_dao = CRM_Core_DAO::executeQuery($sql, $params);
      $fields = array('constituent_type', 'staff_responsible');
      while($column_dao->fetch()) {
        reset($fields);
        while(list(,$field) = each($fields)) {
          if(strtolower($column_dao->name) == $field) {
            $this->$field = $column_dao->column_name;
          }
        }
      }
    }
    parent::__construct();
  }

  function select() {
    $select = $this->_columnHeaders = array();
    $this->_select = "SELECT 1";
  }

  function from() {
    $this->_from = "";
  }

  function where() {
    $this->_where = '';
  }

  function populateDataTable() {
    $this->data_table = 'civicrm_tmp_' . substr(sha1(rand()), 0, 10);
    $participant_info_table = $this->participant_table;
    $constituent_info_table = $this->constituent_table;
    $event_ids = implode(',', $this->event_ids);
    $sql = "SELECT DISTINCT c.id AS contact_id, status_id, " .
      $this->invitation_date . ' AS invitation_date,' . 
      $this->invitation_response . ' AS invitation_response,' . 
      $this->second_call_date . ' AS second_call_date,' . 
      $this->second_call_response . ' AS second_call_response,' . 
      $this->reminder_date . ' AS reminder_date,' . 
      $this->reminder_response . ' AS reminder_response,' . 
      $this->staff_responsible . ' AS organizer, ' . 
      $this->constituent_type . ' AS constituent_type ' .
      "FROM civicrm_event e JOIN civicrm_participant p ON e.id = p.event_id ".
      "JOIN `$participant_info_table` pi ON p.id = pi.entity_id ".
      "JOIN civicrm_contact c ON c.id = p.contact_id ".
      "JOIN `$constituent_info_table` ci ON c.id = ci.entity_id ".
      "WHERE e.id IN ($event_ids)";
     

    $sql = "CREATE TEMPORARY TABLE `" . $this->data_table . "` AS " . $sql;
    CRM_Core_DAO::executeQuery($sql);

  }
  
  /**
   * Helper function to track data that uses lookup values.
   *
   * This function will populate either $this->organizers with an array
   * of all organizers in the data set or $this->constituent_types with 
   * an array of all constituent types.
   *
   * param @type - String - either constituent_type or staff_responsible.
   */
  function setClassVariable($type) {
    $sql = "SELECT id, option_group_id, filter FROM civicrm_custom_field
      WHERE (name = %0 OR (name IS NULL AND column_name like %1)) AND
       is_active = 1 ";
    $params = array(0 => array($type, 'String'), 1 => array("${type}%", 'String'));
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->fetch();
    $map = array();
    $lookup_field = FALSE;
    if (preg_match('/^action=lookup/', $dao->filter)) {
      $lookup_field = TRUE;
    }

    $class_variable = NULL;
    if($type == 'staff_responsible') {
      $class_variable = 'organizers';
      $field_name = 'organizer';
    }
    elseif($type == 'constituent_type') {
      $class_variable = 'constituent_types';
      $field_name = 'constituent_type';
    }
    if(!empty($dao->option_group_id)) {
      $params = array('field' => 'custom_' . $dao->id);
      try {
        $result = civicrm_api3('CustomField', 'getoptions', $params);
        if(array_key_exists('values', $result)) {
          reset($result['values']);
          while(list($key, $value) = each($result['values'])) {
            $map[$key] = $value;
          }
        }
      }
      catch (CiviCRM_API3_Exception $e) {
        // No op. What should we do here? 
      }
    }
    $sql = "SELECT DISTINCT `$field_name` FROM `" . $this->data_table . "` ORDER BY `$field_name`";
    $dao = CRM_Core_DAO::executeQuery($sql);

    $this->$class_variable = array();
    while($dao->fetch()) {
      $db_values = explode(SEP, $dao->$field_name);
      foreach($db_values as $db_value) {
        if(empty($db_value)) {
          $key = '';
          $value = '[empty]';
        }
        elseif(array_key_exists($db_value, $map)) {
          $value = $map[$db_value];
          $key = $db_value;
        }
        elseif ($lookup_field) {
          // We should have a contact_id and need to replace with display_name.
          $params = array('return' => 'display_name', 'id' => $db_value);
          $value = civicrm_api3('Contact', 'getvalue', $params);
          $key = $db_value;
        }
        else {
          $key = $value = $db_value;
        }
        $this->{$class_variable}[$key] = $value;
      }
    }
  }

  function setOrganizers() {
    $this->setClassVariable('staff_responsible');
  }

  function setConstituentTypes() {
    $this->setClassVariable('constituent_type');
  }

  // Note: $organizer might be null or empty
  function getUniverseCount($organizer = FALSE) {
    $sql = "SELECT COUNT(DISTINCT contact_id) AS count FROM `" . $this->data_table . "`";
    $params = array();
    if($organizer !== FALSE) {
      if(empty($organizer)) {
        $sql .= "WHERE organizer IS NULL OR organizer = ''";
      }
      else{
        $sql .= " WHERE organizer = %0";
        $params[0] = array($organizer, 'String');
      }
    }
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->fetch();
    return $dao->count;
  }

  function getDays($organizer = FALSE) {
    $fields = array('invitation_date', 'second_call_date', 'reminder_date');
    $dates = array();
    while(list(,$field) = each($fields)) {
      $sql = "SELECT DISTINCT `$field` AS date FROM `" . $this->data_table . "` WHERE `$field` IS NOT NULL ORDER BY date";
      $params = array();
      if($organizer !== FALSE) {
        if(empty($organizer)) {
          $sql .= " AND (organizer IS NULL OR organizer = '')";
        }
        else {
          $sql .= " AND organizer = %0";
          $params[0] = array($organizer, 'String');
        }
      }
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while($dao->fetch()) {
        if(!in_array($dao->date, $dates)) {
          $dates[] = $dao->date;
        }
      }
    }
    return $dates;
  }

  /**
   * Return count of calls made.
   *
   * @organizer String Limit to count made by organizer
   * @date Date Limit to count made on given date
   * @contacted Bolean Limit to responses that indicate the organizer
   *  spoke to someone
   */
  function getCallsCount($organizer = FALSE, $date = NULL, $contacted = FALSE) {
    $fields = array(
      'invitation_response' => 'invitation_date',
      'second_call_response' => 'second_call_date',
      'reminder_response' => 'reminder_date'
    );
    $count = 0;
    while(list($response_field,$date_field) = each($fields)) {
      $sql = "SELECT COUNT(`$date_field`) AS count FROM `" . $this->data_table . "` WHERE `$date_field` IS NOT NULL";
      $params = array();
      if($organizer !== FALSE) {
        if(empty($organizer)) {
          $sql .= " AND (organizer IS NULL OR organizer = '')";
        }
        else {
          $sql .= " AND organizer = %0";
          $params[0] = array($organizer, 'String');
        }
      }
      if($date) {
        $sql .= " AND `$date_field` = %1";
        $params[1] = array($date, 'String');
      }
      if($contacted) {
        $responses_fragment = '';
        $responses = $this->getContactedResponses();
        while(list(,$response) = each($responses)) {
          $responses_fragment[] = '"' . CRM_Core_DAO::escapeString($response) . '"';
        }
        $sql .= " AND `$response_field` IN (" . implode(',', $responses_fragment) . ')';
      }
      
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      while($dao->fetch()) {
        $count += $dao->count;
      }
    }
    return $count;
  }

  function getCalculatedTotal($answer, $organizer = FALSE, $date = NULL, $constituent_type = NULL) {
    $params = array(0 => array($answer, 'String'));
    if($organizer) {
      $params[1] = array($organizer, 'String');
    }
    if($date) {
      $params[2] = array($date, 'String');
    }
    if($constituent_type) {
      $params[3] = array('%' . SEP . $constituent_type . SEP . '%' , 'String');
    }

    $sql = "SELECT COUNT(DISTINCT contact_id) AS count FROM `" . $this->data_table . "` WHERE ";
    $sql .= "(";

    $sql .= "((second_call_response = '' OR second_call_response IS NULL) AND invitation_response = %0";
    if($date) {
      $sql .= " AND invitation_date = %2";
    }
    $sql .= ') ';

    $sql .= " OR ";

    $sql .= "(second_call_response  = %0";
    if($date) {
      $sql .= " AND second_call_date = %2";
    }
    $sql .= ') ';
    $sql .= ')';
    if($organizer !== FALSE) {
      if(empty($organizer)) {
        $sql .= " AND (organizer IS NULL OR organizer = '')";
      }
      else {
        $sql .= " AND organizer = %1";
      }
    }
    if($constituent_type) {
      $sql .= " AND constituent_type LIKE %3";
    }
    $dao = CRM_Core_DAO::executeQuery($sql,$params);
    
    $dao->fetch();
    return $dao->count;
  }
  
  function getAttended($organizer = FALSE) {
    $params = array();
    $sql = "SELECT COUNT(DISTINCT contact_id) AS count FROM `" . $this->data_table . "` WHERE ";
    $sql .= "status_id = 2";
    if($organizer !== FALSE) {
      if(empty($organizer)) {
        $sql .= " AND (organizer IS NULL OR organizer = '')";
      }
      else {
        $sql .= " AND organizer = %0";
        $params[0] = array($organizer, 'String');
      }
    }
    $dao = CRM_Core_DAO::executeQuery($sql,$params);
    $dao->fetch();
    return $dao->count;
  }

  function getRemindersTotal($answer, $organizer = FALSE, $date = NULL, $constituent_type = NULL) {
    $sql = "SELECT COUNT(DISTINCT contact_id) AS count FROM `" . $this->data_table . "` WHERE 
      reminder_response = %0";
    $params = array(0 => array($answer, 'String'));
    if($organizer !== FALSE) {
      if(empty($organizer)) {
        $sql .= " AND (organizer IS NULL OR organizer = '')";
      }
      else {
        $sql .= " AND organizer = %1";
        $params[1] = array($organizer, 'String');
      }
    }
    if($constituent_type) {
      $params[2] = array('%' . SEP . $constituent_type . SEP . '%' , 'String');
      $sql .= " AND constituent_type LIKE %2";
    } 
    if($date) {
      $params[3] = array($date, 'String');
      $sql .= " AND reminder_date = %3";
    }
    $dao = CRM_Core_DAO::executeQuery($sql,$params);
    $dao->fetch();
    return $dao->count;
  }

  function getContactedResponses() {
    // Should be a lookup...
    return array('Y', 'N', 'Maybe');
  }
}
