<?php
require 'includes/master.inc.php';
require_once 'includes/vlib/vlibTemplate.php';

$Auth->requireUser('withdrawreport.php');
enabled_check();

$tmpl = new vlibTemplate('tmpl/reports.htm');
$tmpl->setvar('siteurl', $Config->siteurl);
$tmpl->setvar('page_title', $Config->title . " : ".$tmpl->get_lstring('REPORTS'));
$content="";
$db = Database::getDatabase();
$content = "
<table class='table table-striped table-hover'>
  <tr>
    <th></th>
    <th>" . $tmpl->get_lstring('DATE') . "</th>
    <th>" . $tmpl->get_lstring('NOTES') . "</th>
    <th>" . $tmpl->get_lstring('AMOUNT') . "</th>
    <th" . $tmpl->get_lstring('TDS') . "</th>
    <th>" . $tmpl->get_lstring('CHARGES') . "</th>
    <th>" . $tmpl->get_lstring('NET PAY') . "</th>
  </tr>";
$id = 1;
$res = $db->getRows("select * from payout where userid = '" . $_SESSION['m_user']['id'] . "'");
if (sizeof($res) == 0)
    $content = "<div align='center'><b>" . $tmpl->get_lstring('NORECORDS') . "</b></div>";
else {
    foreach ($res as $row) {
        $content.="<tr>
  <td>$id</td>
  <td>" . get_display_date($row['transdate']) . "</td>
  <td>" . $row['comment'] . "</td>
  <td>" . $row['amount'] . "</td>
  <td>" . $row['tds'] . "</td>
  <td>" . $row['charges'] . "</td>
  <td>" . $row['netpay'] . "</td>
  </tr>";
  $id++;
}
$content .= "</table>";
}
$tmpl->setvar('reports',$content);
$tmpl->pparse();
?>
