<?php

require 'includes/master.inc.php';
require_once 'includes/vlib/vlibTemplate.php';
$Auth->requireUser('withdraw.php');
enabled_check();
$tmpl = new vlibTemplate('tmpl/withdraw.htm');
$tmpl->setvar('siteurl', $Config->siteurl);
$tmpl->setvar('page_title', $Config->title . " : " . $tmpl->get_lstring('WITHDRAW'));
$db = Database::getDatabase();
$bal = get_account_balance();
$min = $Config->minpayout;
$usermsg = "";
if (isset($_POST["withdraw"])) {
    $transpwd = $_POST['password_ewal'];
    $userid = $_SESSION["m_user"]["id"];
    $username = get_member_username($userid);

    function check_transpwd($username, $transpwd) {
        global $Config;
        $db = Database::getDatabase();
        $dbpwd = $db->getValue("select transaction_pwd from users where username='" . $username . "'");
        $enter_pwd = md5($transpwd . $Config->authSalt);
        if ($dbpwd == $enter_pwd) {
            return true;
        } else {
            return false;
        }
    }

    if (isset($transpwd)) {
        if ($Error->blank($transpwd, $tmpl->get_lstring('TRANSACTION PASSWORD'))) {
            $err = 1;
            $tmpl->setvar('msg_err', $Error->show());
        } elseif (!check_transpwd($_SESSION['m_user']['username'], $transpwd)) {
            $err = 1;
            $tmpl->setvar('msg_err', $tmpl->get_lstring('TRANSACTION PASSWORD INCORRECT'));
        }
    } else {
        $err = 1;
    }
    if (!$err) {
        $ftc = get_config('fund_transfer_charge');
        $notes = "E-Wallet Fund Transfer charge";
        make_entry($ftc, '', '', 'e_wallet_fund_transfer_charge', '', $userid, '', $notes);
        $w_amt = $_POST["amount"];
        if (payout_eligible($_SESSION["m_user"]["id"])) {
            if ($w_amt > $bal) {
                $usermsg = $tmpl->get_lstring('WITHDRAW AMOUNT IS MORE THAN BALANCE') . "!";
            } elseif ($w_amt < $min) {
                $usermsg = $tmpl->get_lstring('MIN WITHDRAWAL IS') . " " . format_currency($min) . "";
            } else {
                $date = date('Y-m-d H:i:s');
                $payout_req_cnt = $db->numRows("select * from payout_request where userid='" . $_SESSION["m_user"]["id"] . "' and paid='0'");
                if ($payout_req_cnt >= 1) {
                    $usermsg = $tmpl->get_lstring('WITHDRAWAL REQUEST ALREADY EXISTS') . "!";
                } else {
                    $db->query("insert into payout_request(userid,sent_time,amount,paid) values ('" . $_SESSION["m_user"]["id"] . "','$date',$w_amt,'0')");

                    event_log('normal', 'user', 'User (' . $_SESSION["m_user"]['username'] . ') created withdrawal request');

                    if (mail_template_enabled('withdraw')) {
                        $message = load_mail_template("withdraw");
                        $subject = load_mail_subject('withdraw');
                        $message = str_replace("#username#", $_SESSION['m_user']['username'], $message);
                        $message = str_replace("#firstname#", $_SESSION['m_user']['firstname'], $message);
                        $message = str_replace("#lastname#", $_SESSION['m_user']['lastname'], $message);
                        $message = str_replace("#amount#", format_currency($w_amt), $message);
                        $message = str_replace("#date#", $date, $message);
                        mail_user($_SESSION['m_user']['username'], $subject, $message, true);
                    }
                    if (sms_template_enabled('withdraw')) {
                        $message = load_sms_template("withdraw");
                        $message = str_replace("#username#", $_SESSION['m_user']['username'], $message);
                        $message = str_replace("#firstname#", $_SESSION['m_user']['firstname'], $message);
                        $message = str_replace("#lastname#", $_SESSION['m_user']['lastname'], $message);
                        $message = str_replace("#fullname#", $_SESSION['m_user']['firstname'] . " " . $_SESSION['m_user']['lastname'], $message);
                        $message = str_replace("#memberid#", $_SESSION['m_user']['id'], $message);
                        $message = str_replace("#amount#", format_currency($w_amt), $message);
                        sms_user($_SESSION['m_user']['username'], $message);
                    }
                    $usermsg_success = $tmpl->get_lstring('WITHDRAWAL REQUEST IS SENT') . "!";
                }
            }
        } else {
            $usermsg = $tmpl->get_lstring('ACCOUNT BALANCE IS LOWER THAN THE MINIMUM BALANCE FOR PAYOUT') . "!";
        }
    }
}
$request = 0;
$res = $db->query("select * from payout_request where userid='" . $_SESSION["m_user"]["id"] . "' and paid='0'");
if ($row = $db->getRow($res)) {
    $request = $row['amount'];
}
$paidout = 0;
$res = $db->query("select sum(amount) as tot from payout where userid='" . $_SESSION["m_user"]["id"] . "' group by userid");
if ($row = $db->getRow($res)) {
    $paidout = $row['tot'];
}
$content .="<div class='col-xs-12 col-sm-12 col-md-12 col-lg-12'> <table class='table table-bordered table-striped table-hover'>
<tr><th>#CURRENT BALANCE#</th><th>" . $tmpl->get_lstring('WAITING WITHDRAWAL') . "</th><th>" . $tmpl->get_lstring('TOTAL PAID OUT') . "</th></tr>
<tr><td>" . format_currency($bal) . "</td><td>" . format_currency($request) . "</td><td>" . format_currency($paidout) . "</td></tr>
</table></div>
";

//error showup<ul class="messages">
if ($usermsg) {
    $content .='<div class = "col-xs-12 col-sm-12 col-md-12 col-lg-12">
  <ul class="messages">
<li class="error-msg">
<ul>
<li><span><span>' . $usermsg . '</span></span></li>
</ul>
</li>
</ul>
       </div>';
}
//bug id 0018384
if ($usermsg_success) {
    $content .='<div class = "col-xs-12 col-sm-12 col-md-12 col-lg-12">
  <ul class="messages">
<li class="success-msg">
<ul>
<li><span><span>' . $usermsg_success . '</span></span></li>
</ul>
</li>
</ul>
       </div>';
}


$content .='<div class = "col-xs-12 col-sm-12 col-md-6 col-lg-6">
<form method = "post" name = "withdrawform" id = "withdrawform" action = "' . $Config->siteurl . '/withdraw.php" role = "form">
<input type = hidden name = withdraw value = 1>
<div class = "form-group">
<label>#ACCOUNT BALANCE#</label>
<label>' . format_currency($bal) . '</label>
</div>
<div class = "form-group">
<label for = "amount">#ENTER WITHDRAW AMOUNT#</label>
<input type = "text" class = "form-control" name = "amount" id = "amount" placeholder = "#ENTER WITHDRAW AMOUNT#">
</div>
<div class = "form-group">
<label for = "password_ewal">#TRANSACTION PASSWORD#</label>
<input type = "password" class = "form-control" name = "password_ewal" id = "password_ewal" placeholder = "#TRANSACTION PASSWORD#">
</div>
';
$charges_row = $db->getRow("select * from settings ");
if ($charges_row) {
    $charge = $charges_row['fixed_proc_fee'];
    $charge2 = $charges_row['fixed_proc_fee_upto'];
    $perc = round($charges_row['percent_proc_fee']);
}

$content.='<input type = "submit" class = "btn btn-success" value = "#WITHDRAW#"/></form></div>';
$content .='<div class = "col-xs-12 col-sm-12 col-md-12 col-lg-12"><br /><b>#NOTES#:<br />';

$content .='#COMPLETE THE# <a href="account_settings.php"> #PAYMENT PREFERENCES# </a> #BEFORE REQUESTING A PAYOUT#<br />';
$content.= $tmpl->get_lstring('APPLICABLE WITHDRAWAL CHARGES') . ": ";

if ($charge > 0 || $charge2 > 0 || $perc > 0) {
    if ($charge == 0 && $charge2 == 0)
        $content.= $perc . "% of " . $tmpl->get_lstring('WITHDRAWAL AMOUNT');
    elseif ($perc == 0)
        $content.= format_currency($charge, 1);
    else
        $content.= format_currency($charge, 1) . " " . $tmpl->get_lstring('UPTO') . " " . format_currency($charge2, 1) . " " . $tmpl->get_lstring('AND') . " " . $perc . "% " . $tmpl->get_lstring('OF') . " " . $tmpl->get_lstring('WITHDRAWAL AMOUNT') . " " . $tmpl->get_lstring('FOR') . " " . $tmpl->get_lstring('ABOVE') . " " . format_currency($charge2, 1);
} else {
    $content.= '0';
}

$content .='<br />#YOU NEED AT LEAST# ' . format_currency($min, 1) . ' #TO WITHDRAW EARNINGS#</div>';
$tmpl->setvar('withdraw', $content);
$tmpl->pparse();
?>