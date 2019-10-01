<?php

include 'includes/master.inc.php';
include 'includes/vlib/vlibTemplate.php';
$tmpl = new vlibTemplate('tmpl/common_messages.htm');
$tmpl->setvar('siteurl', $Config->siteurl);
$tmpl->setvar('page_title', $Config->title . " : " . $tmpl->get_lstring('UNSUBSCRIBE'));
$tmpl->setvar('title', $tmpl->get_lstring('UNSUBSCRIBE'));

$useremailid = $_GET["emailid"];
if (!isset($useremailid)) {
  $page_content = "<b>" . $tmpl->get_lstring('IF YOU ARE MEMBER OF') . " " . $Config->sitename . " : " . "
<a href=\"$Config->siteurl/subscriptions.php\" class=\"link\">" . $tmpl->get_lstring('CLICK HERE') . "</a></b>
<br />
<br />
<br />
<b>" . $tmpl->get_lstring('IF YOU ARE NOT A MEMBER OF') . " " . $Config->sitename . " : </b>
<br />
<form name=unsubscribeform id=unsubscribeform action=\"" . my_name() . "?action=unsubscrib\" method=get class=text>
<br />
" . $tmpl->get_lstring('ENTER EMAIL') . " : " . "
<input type='text' name='emailid' id='emailid' class=\"input1\"/>
<input type='submit' value='" . $tmpl->get_lstring('UNSUBSCRIBE') . "' />
</form>";
} else {
  $result = ereg("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $useremailid);
  if (!$result) {
    $page_content = "<div align=center class=\"error\">" . $tmpl->get_lstring('ENTER A VALID EMAIL ADDRESS') . "</div>
                      <br><br><br><br><br>";
  } else {
    $quer = mysql_query("select * from users where email='$useremailid'") or die(db_error());
    $num = mysql_num_rows($quer);

    if ($num < 1) {
      $unsubscribed = mysql_query("select * from opt_out_email where unsubscribe_email= '$useremailid'");
      $numb = mysql_num_rows($unsubscribed);
      if ($numb) {
        $page_content = "<div align=center class=\"error\">" . $tmpl->get_lstring('ALREADY UNSUBSCRIBED') . "!</div>";
      } else {
        $expr = "^[_a-z0-9-]+(\.[_a-z0-9-]+)*@(\\" . $Config->sitedomain . ")*(\.com)$";
        $result1 = ereg($expr, $useremailid);
        if ($result1) {
          $page_content = "<div align=center class=\"error\">" . $tmpl->get_lstring('CANNOT UNSUBSCRIBE') . "!</div>";
        } else {
          //Sending Mial to the unsubscribed users
          $subject = $tmpl->get_lstring('UNSUBSCRIBED');
          $message = "<div align=center class=text>" . $tmpl->get_lstring('UNSUBSCRIBED FROM') . " $Config->sitename</div>";
          mail_to($useremailid, $subject, $message, true);

          //Inserting the unsubscribed users in the database
          $query = mysql_query("insert into opt_out_email (unsubscribe_email) values ('$useremailid')");
          $page_content = "<b>" . $tmpl->get_lstring('UNSUBSCRIBED FROM') . " " . $Config->sitename . " " . $tmpl->get_lstring('MAIL HAS BEEN SENT TO') . " " . $useremailid . ".<b>";
        }
      }
    } else {
      $page_content = "<div align=center class=text>" . $tmpl->get_lstring('EXISTING MEMBERS') . " <a href=\"$Config->siteurl/subscriptions.php\" class=\"link\">" . $tmpl->get_lstring('LOGIN') . "</a> " . $tmpl->get_lstring('TO UNSUBSCRIBE') . "</div>
                                            <br><br><br><br><br>";
    }
  }
}

$tmpl->setvar('message', $page_content);
$tmpl->pparse();
?>