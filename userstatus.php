<?php

/* Run daily */
$trialmode = 0;
set_time_limit(0);
require 'includes/master.inc.php';

if (!$trialmode && !cron_limit_in_threshold('userstatus.php')) {
  $fp = fopen('.cron_debug.log', 'a');
  fwrite($fp, "-----------------------------------" . date('Y-m-d H:i:s') . "-----------------------------------------\n");
  fwrite($fp, "Cron already executed - userstatus.php \n");
  fclose($fp);
  die("<span style='color:red;font-weight:bold;'>Bad Request!!</span>");
}

require 'includes/plan.inc.php';
require 'includes/vlib/vlibTemplate.php';
$tmpl = new vlibTemplate('tmpl/common_messages.htm');
$Config = Config::getConfig();
$db = Database::getDatabase();

$contents = "";
$g_credit_renewal_comm = true;
$today = date('Y-m-d');
$now = date('H:i:s');
$contents .="Now: $today $now<br />Trial mode: $trialmode<br />";

$last_week_start = date('Y-m-d H:i:s',strtotime("-7 days"));
$last_month_start = date('Y-m-d H:i:s',strtotime("-1 month"));
$last_quarter_start = date('Y-m-d H:i:s',strtotime("-3 months"));
$last_half_year_start = date('Y-m-d H:i:s',strtotime("-6 months"));

$all_plans = get_plans();
$plan_infos = array();
foreach ($all_plans as $plan) {
  $plan_infos[$plan['id']] = get_plan_info_basic($plan['id']);
}
//print_r($plan_infos);

$res = $db->query("select u.*,d.reason_renew,d.reason_recur,d.reason_minps,d.reason_pairs from users u, user_detail d where u.id!=1 and u.paidfees=1 and u.terminated=0 and u.id=d.userid");
while ($row = $db->getRow($res)) {
  $plan_info = $plan_infos[$row['plan']];
  $contents .= "<br /><br />--------------------------- <b>" . $row['username'] . " (" . $row['id'] . ")</b> ------------------------<br />Plan: ".$row['plan']." Status: ".$row['status']." Expired: ".$row['expired'];

  //-----------------Membership Renewal status update----------------------------
  $contents .= "<br>Membership Renewal Check:";
  if ($plan_info['fee_renewal'] > 0) {
    $cutoff = 0;
    $plan = $plan_info['id'];
    $interval = $plan_info['renewal_term'];
    if ($interval == 'half_yearly')
      $cutoff = time() - (6 * 30 * 24 * 60 * 60);
    elseif ($interval == 'yearly')
      $cutoff = time() - (365 * 24 * 60 * 60);
    $contents .= "<br> Interval: $interval Cutoff: $cutoff";
    if ($cutoff) {
      $contents .= " Last Renewal: ".$row['last_renewal'];
      if($row['last_renewal'] < $cutoff) {
        $contents .= " -<span style='color:red'>UNQUALIFY</span>";
        if (!$trialmode) {
          $db->query("update users set expired=1 where id='" . $row['id'] . "'");
          set_unqualify_reason($row['id'], 3);
          $contents .= "<br> User Unqualified.";
        }
      } else {
        $contents .= " -OK";
      }
    }
  } else {
    $contents .= "<br> No Renewal fee";
  }

  //-------------------Recurring fee status update-----------------------------------
  $contents .= "<br>Recurring Fee Check:";

  if ($plan_info['fee_maintenance'] > 0 && $plan_info['maintenance_term'] == 'monthly') {
    //get the due invoice
    $q = "select * from invoices where userid='" . $row['id'] . "' and due_date < '$today' and status='unpaid' order by due_date asc limit 0,1";
    $contents .= "<br>Check due invoices";
    $due = $db->getRow($q);
    if ($due) {
      $contents .= " <strong>Due Invoice #" . $due['id'] . "</strong><br />";
      if ($row['auto_debit']) {
        $contents .= "<br />Auto debit enabled.";
        $fee = $due['amount'];
        $bal = get_member_balance($row['username']);
        if ($bal >= $fee) {
          $contents .= " Debit Recurring fee";
          if (!$trialmode) {
            debit_member($row['username'], $fee, $tmpl->get_default_lstring("MONTHLY MAINTENANCE FEE") . " " . $tmpl->get_default_lstring("INVOICE") . "#" . $due['id'], date('Y-m-d H:i:s'));
            mark_paid($row['username'], "renew");
            new_auto_order($row['username'], 'account', 'rec');
            if (mail_template_enabled('autodebit')) {
              $message = load_mail_template("autodebit");
              $message = str_replace("#username#", $row['username'], $message);
              $message = str_replace("#fullname#", $row['firstname'] . " " . $row['lastname'], $message);
              $message = str_replace("#lastname#", $row['lastname'], $message);
              $message = str_replace("#siteurl#", $Config->siteurl, $message);
              $message = str_replace("#sitename#", $Config->sitename, $message);
              $subject = load_mail_subject('autodebit');
              mail_user($row['username'], $subject, $message, true);
            }
          }
          $contents .= "<span style='color:green'>User Qualified</span><br>";
        } else {
          $contents .= " Insufficient balance -<span style='color:red;'>UNQUALIFY</span>";
          if (!$trialmode) {
            if (set_unqualify_reason($row['id'], 1)) {
              if (mail_template_enabled('autodebitfailed')) {
                $message = load_mail_template("autodebitfailed");
                $message = str_replace("#username#", $row['username'], $message);
                $message = str_replace("#fullname#", $row['firstname'] . " " . $row['lastname'], $message);
                $message = str_replace("#lastname#", $row['lastname'], $message);
                $message = str_replace("#siteurl#", $Config->siteurl, $message);
                $message = str_replace("#sitename#", $Config->sitename, $message);
                $subject = load_mail_subject('autodebitfailed');
                mail_user($row['username'], $subject, $message, true);
              }
            }
          }
          $contents .= "<br />User Unqualified";
        }
      } else {
        //payment not received
        $contents .= "<br />Auto debit disabled -<span style='color:red;'>UNQUALIFY</span>";
        if (!$trialmode) {
          set_unqualify_reason($row['id'], 1);
          $contents .= "<br />User Unqualified";
        }
      }
    } else {
      $contents .= "<br />No invoice due";
    }
  } else {
    $contents .= "<br />No monthly maintenance fee";
  }



  //-------------Min purchase status update----------------------------------
  $contents .="<br><br />Minimum Purchase Check:";
  if ($plan_info['min_halfyr_purchase'] > 0) {
    $contents .= "<br> Half yearly requirement=".$plan_info['min_halfyr_purchase'];
    if($row['actdate'] < $last_half_year_start) {
      //get order total
      $order_total = $db->getValue("select sum(total) from orders where user_id='" . $row['id'] . "' and (status='paid' or status='complete') and complete_date > '" . $last_half_year_start . "'");
      $contents .= ", Purchased=$order_total";
      if($order_total < $plan_info['min_halfyr_purchase']) {
        $contents .= " -<span style='color:red;'>UNQUALIFY</span>";
        if (!$trialmode) {
          set_unqualify_reason($row['id'], 2);
          $contents .= "<br />User Unqualified";
        }
      }
    } else {
      $contents .= " Activation date > ".$last_half_year_start." , Ignoring";
    }
  }


  if ($plan_info['min_qrtr_purchase'] > 0) {
    $contents .= "<br> Quarterly requirement=".$plan_info['min_qrtr_purchase'];
    if($row['actdate'] < $last_quarter_start) {
      //get order total
      $order_total = $db->getValue("select sum(total) from orders where user_id='" . $row['id'] . "' and (status='paid' or status='complete') and complete_date > '" . $last_quarter_start . "'");
      $contents .= ", Purchased=$order_total";
      if($order_total < $plan_info['min_qrtr_purchase']) {
        $contents .= " -<span style='color:red;'>UNQUALIFY</span>";
        if (!$trialmode) {
          set_unqualify_reason($row['id'], 2);
          $contents .= "<br />User Unqualified";
        }
      }
    } else {
      $contents .= " Activation date > ".$last_quarter_start." , Ignoring";
    }
  }


  if ($plan_info['min_month_purchase'] > 0) {
    $contents .= "<br> Monthly requirement=".$plan_info['min_month_purchase'];
    if($row['actdate'] < $last_month_start) {
      //get order total
      $order_total = $db->getValue("select sum(total) from orders where user_id='" . $row['id'] . "' and (status='paid' or status='complete') and complete_date > '" . $last_month_start . "'");
      $contents .= ", Purchased=$order_total";
      if($order_total < $plan_info['min_month_purchase']) {
        $contents .= " -<span style='color:red;'>UNQUALIFY</span>";
        if (!$trialmode) {
          set_unqualify_reason($row['id'], 2);
          $contents .= "<br />User Unqualified";
        }
      }
    } else {
      $contents .= " Activation date > ".$last_month_start." , Ignoring";
    }
  }


  if ($plan_info['min_week_purchase'] > 0) {
    $contents .= "<br> Weekly requirement=".$plan_info['min_week_purchase'];
    if($row['actdate'] < $last_week_start) {
      //get order total
      $order_total = $db->getValue("select sum(total) from orders where user_id='" . $row['id'] . "' and (status='paid' or status='complete') and complete_date > '" . $last_week_start . "'");
      $contents .= ", Purchased=$order_total";
      if($order_total < $plan_info['min_week_purchase']) {
        $contents .= " -<span style='color:red;'>UNQUALIFY</span>";
        if (!$trialmode) {
          set_unqualify_reason($row['id'], 2);
          $contents .= "<br />User Unqualified";
        }
      }
    } else {
      $contents .= " Activation date > ".$last_week_start." , Ignoring";
    }
  }

  $contents .="<br><br />";
}

$contents .=date('Y-m-d H:i:s')." ----------------------------- Cron Ended  -----------------------------<br />";
echo $contents;


if (!$trialmode) {
  update_cron_execution('userstatus.php');
  event_log('normal', 'system', 'userstatus.php cron executed');
  if (debug_on()) {
    $to = get_admin_email();
    $subject = $Config->sitename . " userstatus.php cron output";
    mail_to($to, $subject, $contents, true);
  }
}
?>