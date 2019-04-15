{* Use the default layout *}
{include file="CRM/Report/Form.tpl"}
{$to_message}
{if $to_results}
  <h2>Summary</h2>
  <div class="to-summary-numbers">
    <div><span class="reports-header">{ts}People In Universe{/ts}</span>: <span class="to-value">{$universe_count}</spam></div>
    <div><span class="reports-header">{ts}Days{/ts}</span>: <span class="to-value">{$days_count}</spam></div>
    <div><span class="reports-header">{ts}Total Calls{/ts}</span>: <span class="to-value">{$calls_count}</spam></div>
    <div><span class="reports-header">{ts}Calls per day{/ts}</span>: <span class="to-value">{$calls_per_day}</spam></div>
    <div><span class="reports-header">{ts}Total Contacted{/ts}</span>: <span class="to-value">{$contacted_count}</spam></div>
    <div><span class="reports-header">{ts}Contacted per day{/ts}</span>: <span class="to-value">{$contacted_per_day}</spam></div>
    <div><span class="reports-header">{ts}Attended{/ts}</span>: <span class="to-value">{$attended_total}</spam></div>
    <div><span class="reports-header">{ts}Attended percent{/ts}</span>: <span class="to-value">{$attended_percent}</spam></div>
  </div>
    <table class="to-summary-responses">
    <tr>
      <th class="reports-header">Answer</th>
      <th class="reports-header">Calculated Total</th>
      <th class="reports-header">Percent of Universe</th>
      <th class="reports-header">Reminders Total</th>
      <th class="reports-header">Percent</th>
    </tr>
    {foreach from=$summaryResponses item=responses}
      <tr>
        {foreach from=$responses item=response}
        <td class="to-value">{$response}</td>
        {/foreach}
      </tr>
    {/foreach}
  </table>

  <h2>Organizer Summary</h2>
  <table>
    <tr>
      <th class="reports-header">Name</th>
      <th class="reports-header">Universe</th>
      <th class="reports-header">Calls</th>
      <th class="reports-header">Contacted</th>
      <th class="reports-header">Days</th>
      <th class="reports-header">Calls/Day</th>
      <th class="reports-header">Contacted/Day</th>
      <th class="reports-header">Yes (%)</th>
      <th class="reports-header">Maybe (%)</th>
      <th class="reports-header">No (%)</th>
      <th class="reports-header">Yes Rem (%)</th>
      <th class="reports-header">Maybe Rem (%)</th>
      <th class="reports-header">No Rem (%)</th>
      <th class="reports-header">Attended (%)</th>
    </tr>
    {foreach from=$summaryResponsesByOrganizer item=responses}
      <tr>
        {foreach from=$responses item=response}
        <td class="to-value">{$response}</td>
        {/foreach}
      </tr>
    {/foreach}
  </table>

  <h2>Daily Summary</h2>
  {foreach from=$summaryResponsesByDay item=day}
    <h3>{$day.name}</h3>
    <table>
      <tr>
        <th class="reports-header">Name</th>
        <th class="reports-header">Universe</th>
        <th class="reports-header">Calls</th>
        <th class="reports-header">Contacts</th>
        <th class="reports-header">Yes (Reminders)</th>
        <th class="reports-header">Maybe (Reminders)</th>
        <th class="reports-header">No (Reminders)</th>
      </tr>
      {foreach from=$day.organizers item=organizer}
      <tr>
        {foreach from=$organizer item=response}
        <td class="to-value">{$response}</td>
        {/foreach}
      </tr>
      {/foreach}
    </table>
  {/foreach} 
  <div class="turnout-explanation">
    <p>Attended percent is the number of people who attended divided by the number of people who said yes to the reminder call. If someone says yes to the first or second call, but doesn't receive a reminder call, they will not be part of this percentage.</p>
    <p>The Yes, No, and Maybe responses only count the first or second call response (it uses the most recent answer). It does not count the Reminder response. If a person says maybe to the first call, yes to the second call and no to the reminder call, they will count toward the "Yes" total and toward the "No Reminder" total.</p>
    <p>The reminder percent is the percent of people who said Yes (or No or Maybe) to the reminder call as a percent of the people who said Yes (or No or Maybe) to the first or second call.</p>
  </div>

{/if}
