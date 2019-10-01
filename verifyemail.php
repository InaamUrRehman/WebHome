<?php
require 'includes/master.inc.php';
require_once 'includes/vlib/vlibTemplate.php';

$tmpl = new vlibTemplate('tmpl/common_messages.htm');
$tmpl->setvar('siteurl', $Config->siteurl);
$tmpl->setvar('page_title', $Config->title . " : ".$tmpl->get_lstring('VERIFY EMAIL'));

$db = Database::getDatabase();

if(isset($_GET['uid']) && isset($_GET['code'])){
$tmpl->setvar('title', $tmpl->get_lstring('EMAIL VERIFICATION'));
$page_content="";
  $id = $_GET['uid'];
  $code = $_GET['code'];
  $res1 = $db->numRows($db->query("select * from email_verify_code where memberid = '$id' and code = '$code'"));

  if($res1==1) {
    $db->query("update users set mail_verified=1 where id='$id'");
    $db->query("delete from email_verify_code where memberid='$id'");
    $res = $db->getRow("SELECT * FROM users u, user_detail d WHERE u.id = d.userid AND u.id = '$id'");

    event_log('normal', 'user', 'User ('.$id.') verified email');

	if(mail_template_enabled('verified')){
		$subject = load_mail_subject('verified');
		$message = load_mail_template("verified");
		mail_to($res['email'], $subject, $message, true);
	}

    //send mail to nominee
    $r = $db->getValue($db->query("select email from users where username = '".$res['sponsor']."'"));
    $to = $r;

if(mail_template_enabled('new_ref')){
	$subject2 = load_mail_subject('new_ref');
    $msg = load_mail_template("new_ref");
    $msg = str_replace("#sponsor#",$res['sponsor'],$msg);
    $msg = str_replace("#username#",$res['username'],$msg);
    $msg = str_replace("#fullname#",$res['firstname']." ".$res['lastname'],$msg);
    $msg = str_replace("#email#",$res['email'],$msg);
    mail_to($to, $subject2 , $msg, true);
   }


    $page_content = "<b>".$tmpl->get_lstring('EMAIL VERIFICATION IS SUCCESSFUL')."!</b><br /><br />
    <a class=\"link\" href=\"$Config->siteurl/login.php\">".$tmpl->get_lstring('LOGIN TO MEMBER AREA')."</a>";
  } else {
    $page_content = $tmpl->get_lstring('INCORRECT VERIFICATION CODE')."!";
  }

}
$tmpl->setVar('message',$page_content);
$tmpl->pparse();
?>
