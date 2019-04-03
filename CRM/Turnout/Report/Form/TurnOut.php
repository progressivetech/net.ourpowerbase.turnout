<?php

class CRM_Turnout_Report_Form_TurnOut extends CRM_Turnout_Report_Form_TurnOutShared {
  function __construct() {
    parent::__construct();
    $this->_columns = array(
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'filters' => array(
          'event_ids' => array(
            'title' => ts('Event'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->getEvents(),
          ),
        ),
      ),
     );
  }

  function getEvents() {
    $sql = "SELECT DISTINCT ce.id, title, start_date FROM civicrm_event ce JOIN civicrm_participant cp 
      ON cp.event_id = ce.id JOIN `" . $this->participant_table . "` pi ON cp.id = pi.entity_id 
      ORDER BY title";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $ret = array();
    while($dao->fetch()) {
      $start = substr($dao->start_date, 0, 10);
      $id = $dao->id;
      $ret[$id] = $dao->title . " ($start)" ;
    }
    return $ret;

  }
  function preProcess() {
    $this->assign('reportTitle', ts('Event Turnout Report'));
    parent::preProcess();
  }

  function setSummary($template) {
    $universe_count = $this->getUniverseCount();
    $days = $this->getDays();
    $days_count = count($days);
    $calls_count = $this->getCallsCount();
    $calls_per_day = $days_count == 0 ? 0 : number_format($calls_count / $days_count, 2);
    $contacted_count = $this->getCallsCount(FALSE, NULL, TRUE);
    $contacted_per_day = $days_count == 0 ? 0 : number_format($contacted_count / $days_count, 2);
    $calculated_yes = $this->getCalculatedTotal('Y'); 
    $percent_yes = $universe_count == 0 ? 0 : number_format($calculated_yes / $universe_count, 2) * 100 . '%'; 
    $calculated_no = $this->getCalculatedTotal('N'); 
    $percent_no = $universe_count == 0 ? 0 : number_format($calculated_no / $universe_count, 2) * 100 . '%'; 
    $calculated_maybe = $this->getCalculatedTotal('Maybe'); 
    $percent_maybe = $universe_count == 0 ? 0 : number_format($calculated_maybe / $universe_count, 2) * 100 . '%'; 
    $reminders_yes = $this->getRemindersTotal('Y'); 
    $percent_reminders_yes = $calculated_yes == 0 ? 0 : number_format($reminders_yes / $calculated_yes, 2) * 100 . '%'; 
    $reminders_no = $this->getRemindersTotal('N'); 
    $percent_reminders_no = $calculated_no == 0 ? 0 : number_format($reminders_no / $calculated_no, 2) * 100 . '%'; 
    $reminders_maybe = $this->getRemindersTotal('Maybe'); 
    $percent_reminders_maybe = $calculated_maybe == 0 ? 0 : number_format($reminders_maybe / $calculated_maybe, 2) * 100 . '%'; 
    $attended_total = $this->getAttended();
    $attended_percent = $reminders_yes == 0 ? 0 : number_format($attended_total / $reminders_yes, 2) * 100 . '%';

    $template->assign('universe_count', $universe_count);
    $template->assign('days_count', $days_count);
    $template->assign('calls_count', $calls_count);
    $template->assign('contacted_count', $contacted_count);
    $template->assign('contacted_per_day', $contacted_per_day);
    $template->assign('calls_per_day', $calls_per_day);
    $template->assign('attended_total', $attended_total);
    $template->assign('attended_percent', $attended_percent);

    $summaryResponses = array(
      0 => array('Yes', $calculated_yes, $percent_yes, $reminders_yes, $percent_reminders_yes), 
      1 => array('Maybe', $calculated_maybe, $percent_maybe, $reminders_maybe, $percent_reminders_maybe), 
      2 => array('No', $calculated_no, $percent_no, $reminders_no, $percent_reminders_no), 
    );

    $template->assign('to_results', TRUE);
    $template->assign('summaryResponses', $summaryResponses);
  }

  function setOrganizerSummary($template) {
    $this->setOrganizers();
    $resp = array();
    reset($this->organizers);
    while(list($organizer, $organizer_friendly) = each($this->organizers)) {
      $universe_count = $this->getUniverseCount($organizer);
      $days = $this->getDays($organizer);
      $days_count = count($days);
      $calls_count = $this->getCallsCount($organizer);
      $contacted_count = $this->getCallsCount($organizer, NULL, TRUE);
      $calls_per_day = empty($days_count) ? '0' : number_format($calls_count / $days_count, 2);
      $contacted_per_day = empty($days_count) ? '0' : number_format($contacted_count / $days_count, 2);
      $calculated_yes = $this->getCalculatedTotal('Y', $organizer); 
      $percent_yes = empty($universe_count) ? '0%' : number_format($calculated_yes / $universe_count, 2) * 100 . '%'; 
      $calculated_no = $this->getCalculatedTotal('N', $organizer); 
      $percent_no = empty($universe_count) ? '0%' : number_format($calculated_no / $universe_count, 2) * 100 . '%'; 
      $calculated_maybe = $this->getCalculatedTotal('Maybe', $organizer); 
      $percent_maybe = empty($universe_count) ? '0%' : number_format($calculated_maybe / $universe_count, 2) * 100 . '%'; 
      $reminders_yes = $this->getRemindersTotal('Y', $organizer); 
      $percent_reminders_yes = empty($calculated_yes) ? '0%' : number_format($reminders_yes / $calculated_yes, 2) * 100 . '%'; 
      $reminders_no = $this->getRemindersTotal('N', $organizer); 
      $percent_reminders_no = empty($calculated_no) ? '0%' : number_format($reminders_no / $calculated_no, 2) * 100 . '%'; 
      $reminders_maybe = $this->getRemindersTotal('Maybe', $organizer); 
      $percent_reminders_maybe = empty($calculated_maybe) ? '0%' : number_format($reminders_maybe / $calculated_maybe, 2) * 100 . '%'; 
      $attended_total = $this->getAttended($organizer); 
      $percent_attended = empty($reminders_yes) ? '0%' : number_format($attended_total / $reminders_yes, 2) * 100 . '%'; 

      $organizer_label = NULL;
      if(!empty($organizer_friendly)) {
        $organizer_label = $organizer_friendly;
      }
      else{
        if(!empty($organizer)) {
          $organizer_label = $organizer;
        }
        else {
          $organizer_label = '[organizer not set]';
        }
      }
      $resp[] = array(
        $organizer_label, $universe_count, $calls_count, $contacted_count, $days_count,
        $calls_per_day, $contacted_per_day, "${calculated_yes} (${percent_yes})",
        "${calculated_maybe} (${percent_maybe})", "$calculated_no (${percent_no})",
        "${reminders_yes} (${percent_reminders_yes})", "${reminders_maybe} (${percent_reminders_maybe})",
        "${reminders_no} (${percent_reminders_no})", "${attended_total} (${percent_attended})"
      );
    }
    $template->assign('summaryResponsesByOrganizer', $resp);
  }

  function setDailySummary($template) {
    $resp = array();
    $days = $this->getDays();
    while(list(, $day) = each($days)) {
      $resp[$day] = array(
        'name' => substr($day, 0, 10)
      );
      reset($this->organizers);
      $resp[$day]['organizers'] = array();
      while(list($organizer, $organizer_friendly) = each($this->organizers)) {
        $universe = $this->getUniverseCount($organizer);
        $calls = $this->getCallsCount($organizer, $day);
        $contacts = $this->getCallsCount($organizer, $day, TRUE);
        // Don't include rows where the person made no calls.
        if($calls == 0) continue;
        $yes = $this->getCalculatedTotal('Y', $organizer, $day);
        $reminders_yes = $this->getRemindersTotal('Y', $organizer, $day);
        $maybe = $this->getCalculatedTotal('Maybe', $organizer, $day);
        $reminders_maybe = $this->getRemindersTotal('Maybe', $organizer, $day);
        $no = $this->getCalculatedTotal('N', $organizer, $day);
        $reminders_no = $this->getRemindersTotal('No', $organizer, $day);

        $organizer_label = NULL;
        if(!empty($organizer_friendly)) {
          $organizer_label = $organizer_friendly;
        }
        else{
          if(!empty($organizer)) {
            $organizer_label = $organizer;
          }
          else {
            $organizer_label = '[organizer not set]';
          }
        }

        $resp[$day]['organizers'][$organizer] = array($organizer_label, $universe, $calls, $contacts,
          "${yes} (${reminders_yes})", "${maybe} (${reminders_maybe})", "${no} (${reminders_no})");
      }
    }
    $template->assign('summaryResponsesByDay', $resp);
  }

  function postProcess() {
    parent::postProcess();
    if(array_key_exists('event_ids_value', $this->_params)) {
      $this->event_ids = $this->_params['event_ids_value'];
    }
    $template = CRM_Core_Smarty::singleton();
    if(count($this->event_ids) == 0) {
      $template->assign('to_message', ts("No events were chosen."));
      return;
    }
    $this->populateDataTable();
    if($this->getUniverseCount() == 0) {
      $template->assign('to_message', ts("No turn out data is entered."));
      return;
    }

    $this->setSummary($template);
    $this->setOrganizerSummary($template);
    $this->setDailySummary($template);
  }
}
