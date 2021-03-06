<?php

function checkcode($code,$a,$b=null,$c=null)
{
    if ($code[0] != $a) return false;
    if (!is_null($b) && $code[1] != $b) return false;
    if (!is_null($c) && $code[2] != $c) return false;
    return true;
}

/**
 * Defined bounce parsing rules for standard DSN (Delivery Status Notification)
 *
 * @param string $dsn_msg           human-readable explanation
 * @param string $dsn_report        delivery-status report
 * @param boolean $debug_mode        show debug info. or not
 * @return array    $result an array include the following fields: 'email', 'bounce_type','remove','rule_no','rule_cat'
 *                      if we could NOT detect the type of bounce, return rule_no = '0000'
 * @author  Kevin : Wed Sep 06 15:22:22 PDT 2006
 */
function bmhDSNRules($dsn_msg,$dsn_report,$debug_mode=false){
    // initial the result array
    $result=array(
        'email'=>''
        ,'bounce_type'=>false
        ,'remove'=>0
        ,'rule_cat'=>'unrecognized'
        ,'rule_no'=>'0000'
        ,'dsn_msg' => wrapmsg($dsn_msg)
        ,'dsn_report' => wrapmsg($dsn_report)
    );
    $action=false;
    $status_code=false;
    $diag_code=false;


    // ======= parse $dsn_report ======
    // get the recipient email
    if (preg_match ("/Original-Recipient: rfc822;(.*)/i",$dsn_report,$match)){
        // TRICKY : or using the regex written by myself : /Original-Recipient: rfc822;(?:.*<| *)(\S+@\S+\w)/i
        $email_arr=imap_rfc822_parse_adrlist($match[1],'default.domain.name');
        if (isset($email_arr[0]->host) && $email_arr[0]->host != '.SYNTAX-ERROR.' && $email_arr[0]->host != 'default.domain.name' ){
            $result['dsn_original_rcpt']=$email_arr[0]->mailbox.'@'.$email_arr[0]->host;
        }
    }

    if (preg_match ("/Final-Recipient: rfc822;(.*)/i",$dsn_report,$match)){
        $email_arr=imap_rfc822_parse_adrlist($match[1],'default.domain.name');
        if (isset($email_arr[0]->host) && $email_arr[0]->host != '.SYNTAX-ERROR.' && $email_arr[0]->host != 'default.domain.name' ){
            $result['dsn_final_rcpt']=$email_arr[0]->mailbox.'@'.$email_arr[0]->host;
        }
    }

    if (!empty($result['dsn_original_rcpt'])) {
        $result['email']=$result['dsn_original_rcpt'];
    } else if (!empty($result['dsn_final_rcpt'])) {
        $result['email']=$result['dsn_final_rcpt'];
    }

    if (!empty($result['email'])) {
        if ($result['email'] == $result['dsn_original_rcpt']) {
            $result['dsn_original_rcpt']='-';
        }
        if ($result['email'] == $result['dsn_final_rcpt']) {
            $result['dsn_final_rcpt']='-';
        }
    }

    if (preg_match ("/Action: (.+)/i",$dsn_report,$match)){
        $action=strtolower(trim($match[1]));
    }

    if (preg_match ("/Status: ([0-9\.]+)/i",$dsn_report,$match)){
        $status_code=$match[1];
    }

    // TRICKY : could be multi-line , if the new line is beginning with SPACE or HTAB
    if (preg_match ("/Diagnostic-Code:((?:[^\n]|\n[\t ])+)(?:\n[^\t ]|$)/is",$dsn_report,$match)){
        $diag_code=$match[1];
    }
    // ======= rules ======
    if (empty($result['email'])){
        // email address is empty
        // rule : full
        // sample:   DSN Message only
        //
        //User quota exceeded: SMTP <ypkhew@kelah.time.net.my>
        //
        if (preg_match ("/quota exceed.*<(\S+@\S+\w)>/is",$dsn_msg,$match)){
            $result['rule_cat']='full';
            $result['rule_no']='0161';
            $result['email']=$match[1];
        }
    }
    else{
        // action could be one of them as RFC:1894
        // "failed" / "delayed" / "delivered" / "relayed" / "expanded"
        switch ($action){
            case 'failed':
                $result['rule_cat']='dsn_failed';
                $result['rule_no']='9999';

                $code = explode('.',$status_code);
                if (checkcode($code,4)) {
                    // 4.X.X Persistent Transient Failure
                    // A persistent transient failure is one in which the message as sent
                    // is valid, but some temporary event prevents the successful sending
                    // of the message. Sending in the future may be successful.
                    $result['rule_cat']='delayed';
                    $result['rule_no']='0245';

                } elseif (checkcode($code, 5, 1)) {
					// 5.1.0 Other address status
					// 5.1.1 Bad destination mailbox address
					// 5.1.2 Bad destination system address
					// 5.1.3 Bad destination mailbox address syntax
					// 5.1.4 Destination mailbox address ambiguous
					// 5.1.5 Destination mailbox address valid
					// 5.1.6 Mailbox has moved
					// 5.1.7 Bad sender's mailbox address syntax
					// 5.1.8 Bad sender's system address
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0244';
                } elseif (checkcode($code, 5, 4, 4)) {
                    //Unable to route
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0241';
                } else if (checkcode($code, 5, 2)) {
                    // 5.2.0 Other or undefined mailbox status
					// 5.2.1 Mailbox disabled, not accepting messages
					// 5.2.2 Mailbox full
					// 5.2.3 Message length exceeds administrative limit.
					// 5.2.4 Mailing list expansion problem
                    switch($code[0]) {
                        case 0:
                        case 1:
                            $result['rule_cat']='inactive';
                            $result['rule_no']='0247';

                            break;
                        case 2:
                            $result['rule_cat']='full';
                            $result['rule_no']='0242';
                            break;
                        case 3:
                            $result['rule_cat']='oversize';
                            $result['rule_no']='0246';
                            break;
                        case 4:
                            break;
                        default:
                            break;
                    }
                } else if (checkcode($code, 5, 7)) {
					// 5.7.0 Other or undefined security status
					// 5.7.1 Delivery not authorized, message refused
					// 5.7.2 Mailing list expansion prohibited
					// 5.7.3 Security conversion required but not possible
					// 5.7.4 Security features not supported
					// 5.7.5 Cryptographic failure
					// 5.7.6 Cryptographic algorithm not supported
					// 5.7.7 Message integrity failure
                    switch ($code[2]) {
                        case 1:
                            $result['rule_cat']='antispam';
                            $result['rule_no']='0243';
                            break;
                        default:
                            break;
                    }
                }
                // rule : full
                // sample:
                //Diagnostic-Code: X-Postfix; me.freeserve.com platform: said: 552 5.2.2 Over
                //    quota (in reply to RCPT TO command)
                //
                elseif (preg_match ("/over.*quota/is",$diag_code)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0105';
                }
                // rule : full
                // sample:
                //Diagnostic-Code: SMTP; 552 Requested mailbox exceeds quota.
                //
                elseif (preg_match ("/exceed.*quota/is",$diag_code)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0129';
                }
                // rule : full
                // sample:
                // Diagnostic-Code: smtp; 552 RCPT TO:<x@x.x> Mailbox disk quota exceeded
                //
                elseif (preg_match ("/quota.*exceed/is",$diag_code)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0250';
                }
                // rule : full
                // sample:
                //Diagnostic-Code: smtp;552 5.2.2 This message is larger than the current system limit or the recipient's mailbox is full. Create a shorter message body or remove attachments and try sending it again.
                //
                // sample 2:
                //Diagnostic-Code: X-Postfix; host mta5.us4.outblaze.com.int[192.168.9.168] said:
                //    552 recipient storage full, try again later (in reply to RCPT TO command)
                //
                // sample 3:
                //Diagnostic-Code: X-HERMES; host 127.0.0.1[127.0.0.1] said: 551 bounce as<the
                //    destination mailbox <xxxxxxxxxxxn@pub.zhongshan.gd.cn> is full> queue as
                //    100.1.ZmxEL.720k.1140313037.xxxxxxxn@pub.zhongshan.gd.cn (in reply to end of
                //    DATA command)
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*full/is",$diag_code)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0145';
                }
                // rule : full
                // sample:
                //Diagnostic-Code: SMTP; 452 Insufficient system storage
                //
                elseif (preg_match ("/Insufficient system storage/is",$diag_code)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0134';
                }
                // rule : full
                // sample:
                //Diagnostic-Code: X-Postfix; cannot append message to destination file^M
                //    /var/mail/dale.me89g: error writing message: File too large^M
                //
                // sample 2:
                //Diagnostic-Code: X-Postfix; cannot access mailbox /var/spool/mail/b8843022 for^M
                //    user b8843022. error writing message: File too large
                //
                elseif (preg_match ("/File too large/is",$diag_code)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0192';
                }
                // rule : oversize
                // sample:
                //Diagnostic-Code: smtp;552 5.2.2 This message is larger than the current system limit or the recipient's mailbox is full. Create a shorter message body or remove attachments and try sending it again.
                //
                elseif (preg_match ("/larger than.*limit/is",$diag_code)){
                    $result['rule_cat']='oversize';
                    $result['rule_no']='0146';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: X-Notes; User ypxxxxxxxxxxxxxzwd (yp_xxxxxxszwd@zzzzzzzz.com.cn) not listed in public Name & Address Book
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user)(.*)not(.*)list/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0103';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: smtp; 450 user path no exist
                //
                elseif (preg_match ("/user path no exist/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0106';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 Relaying denied.
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 554 <888@sinowin.net>: Relay access denied
                //
                // sample 3:
                //Diagnostic-Code: SMTP; 550 relaying to <et110352@seednet.net.tw> prohibited by administrator
                //
                elseif (preg_match ("/Relay.*(?:denied|prohibited)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0108';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 554 qq Sorry, no valid recipients (#5.1.3)
                //
                elseif (preg_match ("/no.*valid.*(?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0185';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 «Dªk¦a§} - invalid address (#5.5.0)
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 550 Invalid recipient: <xxxx@so-net.com.hk>
                //
                // sample 3:
                //Diagnostic-Code: SMTP; 550 <xxxxxxxxxx@163.net>: Invalid User
                //
                elseif (preg_match ("/Invalid.*(?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0111';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 554 delivery error: dd Sorry your message to xxxxxxxxx@yahoo.com.tw cannot be delivered. This account has been disabled or discontinued [#102]. - mta173.mail.tpe.yahoo.com
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*(?:disabled|discontinued)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0114';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 554 delivery error: dd This user doesn't have a yahoo.com.tw account (www.xxxxxxxxxx@yahoo.com.tw) [0] - mta134.mail.tpe.yahoo.com
                //
                elseif (preg_match ("/user doesn't have.*account/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0127';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 5.1.1 unknown or illegal alias: siming@verizon.net
                //
                elseif (preg_match ("/(?:unknown|illegal).*(?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0128';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 450 mailbox unavailable.
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 550 5.7.1 Requested action not taken: mailbox not available
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*(?:un|not\s+)available/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0122';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 553 sorry, no mailbox here by that name (#5.7.1)
                //
                elseif (preg_match ("/no (?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0123';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 User (xxxxx@so-net.net.tw) unknown.
                //
                // sample:
                //Diagnostic-Code: SMTP; 553 5.3.0 <xxxxxxxxx@sbcglobal.net>... Addressee unknown, relay=[216.131.96.000]
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*unknown/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0125';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 user disabled
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 452 4.2.1 mailbox temporarily disabled: xxxxxxxxxj@emirates.net.ae
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*disabled/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0133';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 <jiapau@tm.net.my>: Recipient address rejected: No such user (jiapau@tm.net.my)
                //
                elseif (preg_match ("/No such (?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0143';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 MAILBOX NOT FOUND
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 550 Mailbox ( w3-0556@yoho.com.tw ) not found or inactivated
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*NOT FOUND/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0136';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: X-Postfix; host m2w-in1.ctmail.com[66.28.189.000] said: 551
                //    <lazykingyoshiki@icqmail.com> is a deactivated mailbox (in reply to RCPT TO
                //    command)
                //
                elseif (preg_match ("/deactivated (?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0138';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 <fighterlin@cox.net> recipient rejected
                // ...
                //<<< 550 <fighterlin@cox.net> recipient rejected
                //550 5.1.1 fighterlin@cox.net... User unknown
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*reject/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0148';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: smtp; 5.x.0 - Message bounced by administrator  (delivery attempts: 0)
                //
                elseif (preg_match ("/bounce.*administrator/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0151';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 <maxqin> is now disabled with MTA service.
                //
                elseif (preg_match ("/<.*>.*disabled/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0152';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 551 not our customer
                //
                elseif (preg_match ("/not our customer/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0154';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: smtp; 5.1.0 - Unknown address error 540-'Error: Wrong recipients' (delivery attempts: 0)
                //
                elseif (preg_match ("/Wrong (?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0159';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: smtp; 5.1.0 - Unknown address error 540-'Error: Wrong recipients' (delivery attempts: 0)
                // sample 2:
                //Diagnostic-Code: SMTP; 501 #5.1.1 bad address carollai@asw.com.hk
                //
                elseif (preg_match ("/(?:unknown|bad).*(?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0160';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 Command RCPT User <interman@canada.com> not OK
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*not OK/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0186';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 5.7.1 Access-Denied-XM.SSR-001
                //
                elseif (preg_match ("/Access.*Denied/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0189';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 5.1.1 <m4ze5@unb.ca>... email address lookup in domain map failed^M
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*lookup.*fail/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0195';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 User not a member of domain: <ctanner@centek.com>^M
                //
                elseif (preg_match ("/(?:recipient|address|email|mailbox|user).*not.*member of domain/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0198';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550-"The recipient cannot be verified.  Please check all recipients of this^M
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*cannot be verified/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0202';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 Unable to relay for cheeptoo@maill.com
                //
                elseif (preg_match ("/Unable to relay/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0203';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 xxxxxxxx@263.net:user not exist
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 550 sorry, that recipient doesn't exist (#5.7.1)
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*(?:n't|not) exist/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0205';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550-I'm sorry but peterip@ipoline.com does not have an account here. I will not
                //
                elseif (preg_match ("/not have an account/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0207';
                }
                // rule : unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 This account is not allowed...chenty@bigfoot.com
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*is not allowed/is",$diag_code)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0220';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 550 <9239@email.com>: inactive user
                //
                elseif (preg_match ("/inactive.*(?:alias|account|recipient|address|email|mailbox|user)/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0135';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 550 cxxxxx@juno.com Account Inactive
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*Inactive/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0155';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 550 <larrychang4@hotpop.com>: Recipient address rejected: Account closed due to inactivity. No forwarding information is available.
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user) closed due to inactivity/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0170';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 550 <skpoon@usa.net>... User account not activated
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user) not activated/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0177';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 550 User suspended
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 550 account expired
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*(?:suspend|expire)/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0183';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 553 5.3.0 <whynam@ust.hk>... Recipient address no longer exists
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*no longer exist/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0184';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 553 VS10-RT Possible forgery or deactivated due to abuse (#5.1.1) 216.131.96.211^M
                //
                elseif (preg_match ("/(?:forgery|abuse)/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0196';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 553 mailbox hkyl120@conversant.com is restricted
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*restrict/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0209';
                }
                // rule : inactive
                // sample:
                //Diagnostic-Code: SMTP; 550 <kfcc@netease.com>: User status is locked.
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*locked/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0228';
                }
                // rule: inactive
                // sample:
                //  smtp; 550 We would love to have gotten this email to
                // x@aim.com. But, your recipient never logged onto their free AIM
                // Mail account. Please contact them and let them know that they're missing
                // out on all the super features offered by AIM Mail. And by the way, they're
                // also missing out on your email. Thanks.
                elseif (preg_match ("/recipient never logged/is",$diag_code)){
                    $result['rule_cat']='inactive';
                    $result['rule_no']='0252';
                }
                // rule : user_reject
                // sample:
                //Diagnostic-Code: SMTP; 553 User refused to receive this mail.
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user) refused/is",$diag_code)){
                    $result['rule_cat']='user_reject';
                    $result['rule_no']='0156';
                }
                // rule : user_reject
                // sample:
                //Diagnostic-Code: SMTP; 501 root@ds176.reliablehosting.com Sender email is not in my domain
                //
                elseif (preg_match ("/sender.*not/is",$diag_code)){
                    $result['rule_cat']='user_reject';
                    $result['rule_no']='0206';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 554 Message refused
                //
                elseif (preg_match ("/Message refused/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0175';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 550 5.0.0 <sp029426@mail.apol.com.tw>... No permit
                //
                elseif (preg_match ("/No permit/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0190';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 553 sorry, that domain isn't in my list of allowed rcpthosts (#5.5.3 - chkuser)
                //
                elseif (preg_match ("/domain isn't in.*allowed rcpthost/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0191';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 553 AUTH FAILED - root@ds176.reliablehosting.com^M
                //
                elseif (preg_match ("/AUTH FAILED/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0197';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 550 relay not permitted^M
                //
                // sample 2:
                //Diagnostic-Code: SMTP; 530 5.7.1 Relaying not allowed: oscar@syhlease.com.tw
                //
                elseif (preg_match ("/relay.*not.*(?:permit|allow)/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0201';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 550 not local host hotmile.com, not a gateway
                //
                elseif (preg_match ("/not local host/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0204';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 500 Unauthorized relay msg rejected
                //
                elseif (preg_match ("/Unauthorized relay/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0215';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 554 Transaction failed
                //
                elseif (preg_match ("/Transaction.*fail/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0221';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: smtp;554 5.5.2 Invalid data in message
                //
                elseif (preg_match ("/Invalid data/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0223';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 550 Local user only or Authentication mechanism
                //
                elseif (preg_match ("/Local user only/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0224';
                }
                // rule : command_reject
                // sample:
                //Diagnostic-Code: SMTP; 550-ds176.reliablehosting.com [216.131.96.211] is currently not permitted to
                //    relay through this server. Perhaps you have not logged into the pop/imap
                //    server in the last 30 minutes or do not have SMTP Authentication turned on
                //    in your email client.
                //
                elseif (preg_match ("/not.*permit.*to/is",$diag_code)){
                    $result['rule_cat']='command_reject';
                    $result['rule_no']='0225';
                }
                // rule : content_reject
                // sample:
                //Diagnostic-Code: SMTP; 550 Content reject. FAAAANsG60M9BmDT.1
                //
                elseif (preg_match ("/Content reject/is",$diag_code)){
                    $result['rule_cat']='content_reject';
                    $result['rule_no']='0165';
                }
                // rule : content_reject
                // sample:
                //Diagnostic-Code: SMTP; 552 MessageWall: MIME/REJECT: Invalid structure
                //
                elseif (preg_match ("/MIME\/REJECT/is",$diag_code)){
                    $result['rule_cat']='content_reject';
                    $result['rule_no']='0212';
                }
                // rule : content_reject
                // sample:
                //Diagnostic-Code: smtp; 554 5.6.0 Message with invalid header rejected, id=13462-01 - MIME error: error: UnexpectedBound: part didn't end with expected boundary [in multipart message]; EOSToken: EOF; EOSType: EOF
                //
                elseif (preg_match ("/MIME error/is",$diag_code)){
                    $result['rule_cat']='content_reject';
                    $result['rule_no']='0217';
                }
                // rule : content_reject
                // sample:
                //Diagnostic-Code: SMTP; 553 Mail data refused by AISP, rule [169648].
                //
                elseif (preg_match ("/Mail data refused.*AISP/is",$diag_code)){
                    $result['rule_cat']='content_reject';
                    $result['rule_no']='0218';
                }
                // rule : dns_unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 Host unknown
                //
                elseif (preg_match ("/Host unknown/is",$diag_code)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0130';
                }
                // rule : dns_unknown
                // sample:
                //Diagnostic-Code: SMTP; 553 Specified domain is not allowed.
                //
                //
                elseif (preg_match ("/Specified domain.*not.*allow/is",$diag_code)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0180';
                }
                // rule : dns_unknown
                // sample:
                //Diagnostic-Code: X-Postfix; delivery temporarily suspended: connect to
                //    210.201.78.112[210.201.78.112]: No route to host
                //
                elseif (preg_match ("/No route to host/is",$diag_code)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0188';
                }
                // rule: dns_unknown
                // sample:
                //Diagnostic-Code: smtp; 553 sorry, that domain isn't allowed to be relayed thru
                //this MTA (#5.7.1)
                 elseif (preg_match ("/domain.*(?:allowed|forbidden)/is",$diag_code)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0248';
                }
                // rule : dns_unknown
                // sample:
                //Diagnostic-Code: SMTP; 550 unrouteable address
                //
                elseif (preg_match ("/unrouteable address/is",$diag_code)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0208';
                }
                // rule : defer
                // sample:
                //Diagnostic-Code: SMTP; 451 System(u) busy, try again later.
                //
                elseif (preg_match ("/System.*busy/is",$diag_code)){
                    $result['rule_cat']='defer';
                    $result['rule_no']='0112';
                }
                // rule : defer
                // sample:
                //Diagnostic-Code: SMTP; 451 mta172.mail.tpe.yahoo.com Resources temporarily unavailable. Please try again later.  [#4.16.4:70].
                //
                elseif (preg_match ("/Resources temporarily unavailable/is",$diag_code)){
                    $result['rule_cat']='defer';
                    $result['rule_no']='0116';
                }
                // rule : antispam, deny ip
                // sample:
                //Diagnostic-Code: SMTP; 554 sender is rejected: 0,mx20,wKjR5bDrnoM2yNtEZVAkBg==.32467S2
                //
                elseif (preg_match ("/sender is rejected/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0101';
                }
                // rule : antispam, deny ip
                // sample:
                //Diagnostic-Code: SMTP; 554 <unknown[216.131.96.000]>: Client host rejected: Access denied
                //
                elseif (preg_match ("/Client host rejected/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0102';
                }
                // rule : antispam, mismatch ip
                // sample:
                //Diagnostic-Code: SMTP; 554 Connection refused(mx). MAIL FROM [bounce@zzzzzzz.com] mismatches client IP [216.131.96.000].
                //
                elseif (preg_match ("/MAIL FROM(.*)mismatches client IP/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0104';
                }
                // rule : antispam, deny ip
                // sample:
                //Diagnostic-Code: SMTP; 554 Please visit http://antispam.sina.com.cn/denyip.php?IP=216.131.96.000 (#5.7.1)
                //
                elseif (preg_match ("/denyip/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0144';
                }
                // rule : antispam, deny ip
                // sample:
                //Diagnostic-Code: SMTP; 554 Service unavailable; Client host [216.131.96.211] blocked using dynablock.excite.com; Your message could not be delivered due to complaints we received regarding the IP address you're using or your ISP. See http://blackholes.excite.com/ Error: WS-02^M
                //
                elseif (preg_match ("/client host.*blocked/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0201';
                }
                // rule : antispam, reject
                // sample:
                //Diagnostic-Code: SMTP; 550 Requested action not taken: mail IsCNAPF76kMDARUY.56621S2 is rejected,mx3,BM
                //
                elseif (preg_match ("/mail.*reject/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0147';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 552 sorry, the spam message is detected (#5.6.0)
                //
                elseif (preg_match ("/spam.*detect/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0162';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 554 5.7.1 Rejected as Spam see: http://alum.mit.edu/help/spam/rejected.html
                //
                elseif (preg_match ("/reject.*spam/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0216';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 553 5.7.1 <jcshieh@emc.com.tw>... SpamTrap=reject mode, dsn=5.7.1, Message blocked by BOX Solutions (www.box-sol.com) SpamTrap Technology, please contact the EMC.COM.TW site manager for help: (ctlusr8012).^M
                //
                elseif (preg_match ("/SpamTrap/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0200';
                }
                // rule : antispam, mailfrom mismatch
                // sample:
                //Diagnostic-Code: SMTP; 550 Verify mailfrom failed,blocked
                //
                elseif (preg_match ("/Verify mailfrom failed/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0210';
                }
                // rule : antispam, mailfrom mismatch
                // sample:
                //Diagnostic-Code: SMTP; 550 Error: MAIL FROM is mismatched with message header from address!
                //
                elseif (preg_match ("/MAIL.*FROM.*mismatch/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0226';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 554 5.7.1 Message scored too high on spam scale.  For help, please quote incident ID 22492290.
                //
                elseif (preg_match ("/spam scale/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0211';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 554 5.7.1 reject: Client host bypassing service provider's mail relay: ds176.reliablehosting.com
                //
                elseif (preg_match ("/Client host bypass/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0229';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 550 sorry, it seems as a junk mail
                //
                elseif (preg_match ("/junk mail/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0230';
                }
                // rule : antispam
                // sample:
                //Diagnostic-Code: SMTP; 553-Message filtered. Please see the FAQs section on spam
                //
                elseif (preg_match ("/message filtered/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0227';
                }
                // rule : antispam, subject filter
                // sample:
                //Diagnostic-Code: SMTP; 554 5.7.1 The message from (<root@ds176.reliablehosting.com>) with the subject of ( *(ca2639) 7|-{%2E* : {2"(%EJ;y} (SBI$#$@<K*:7s1!=l~) matches a profile the Internet community may consider spam. Please revise your message before resending.
                //
                elseif (preg_match ("/subject.*consider.*spam/is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0222';
                }
                // rule : antispam
                // sample:
                // smtp; 550 sorry, mail to that recipient is not accepted (#5.7.1)
                elseif (preg_match ("/mail to.*recipient is not accepted.*5\.7\./is",$diag_code)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0251';
                }
                // rule : internal_error
                // sample:
                //Diagnostic-Code: SMTP; 451 Temporary local problem - please try later
                //
                elseif (preg_match ("/Temporary local problem/is",$diag_code)){
                    $result['rule_cat']='internal_error';
                    $result['rule_no']='0142';
                }
                // rule : internal_error
                // sample:
                //Diagnostic-Code: SMTP; 553 5.3.5 system config error
                //
                elseif (preg_match ("/system config error/is",$diag_code)){
                    $result['rule_cat']='internal_error';
                    $result['rule_no']='0153';
                }
                // rule : delayed
                // sample:
                //Diagnostic-Code: X-Postfix; delivery temporarily suspended: conversation with^M
                //    10.1.3.83[10.1.3.83] timed out while sending end of data -- message may be^M
                //    sent more than once
                //
                elseif (preg_match ("/delivery.*suspend/is",$diag_code)){
                    $result['rule_cat']='delayed';
                    $result['rule_no']='0213';
                }


                // =========== rules based on the dsn_msg ===============
                // rule : unknown
                // sample:
                //   ----- The following addresses had permanent fatal errors -----
                //<xxxxxxxxxxxxxx@china.com>
                //
                //   ----- Transcript of session follows -----
                //... while talking to mta1.china.com.:
                //>>> DATA
                //<<< 503 All recipients are invalid
                //554 5.0.0 Service unavailable
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user)(.*)invalid/i",$dsn_msg)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0107';
                }
                // rule : unknown
                // sample:
                //   ----- Transcript of session follows -----
                //xxxxxxxxxxxxx@milya.com... Deferred: No such file or directory
                //
                elseif (preg_match ("/Deferred.*No such.*(?:file|directory)/i",$dsn_msg)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0141';
                }
                // rule : unknown
                // sample:
                //Failed to deliver to '<xxxx@she.com>'^M
                //LOCAL module(account xxxx) reports:^M
                // mail receiving disabled^M
                //
                elseif (preg_match ("/mail receiving disabled/i",$dsn_msg)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0194';
                }
                // rule : unknown
                // sample:
                // - These recipients of your message have been processed by the mail server:^M
                //mnarket@swissinfo.org; Failed; 5.1.1 (bad destination mailbox address)
                //
                elseif (preg_match ("/bad.*(?:alias|account|recipient|address|email|mailbox|user)/i",$dsn_msg)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='227';
                }
                // rule : full
                // sample:
                //This Message was undeliverable due to the following reason:
                //
                //The user(s) account is temporarily over quota.
                //
                //<axxxxxxxxxxxxxx@netvigator.com>
                //
                // sample 2:
                //  Recipient address: bf1975@macau.ctm.net
                //  Reason: Over quota
                //
                elseif (preg_match ("/over.*quota/i",$dsn_msg)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0131';
                }
                // rule : full
                // sample:
                //Sorry the recipient quota limit is exceeded.
                //This message is returned as an error.
                //
                elseif (preg_match ("/quota.*exceeded/i",$dsn_msg)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0150';
                }
                // rule : full
                // sample:
                //The user to whom this message was addressed has exceeded the allowed mailbox
                //quota. Please resend the message at a later time.
                //
                elseif (preg_match ("/exceed.*\n?.*quota/i",$dsn_msg)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0187';
                }
                // rule : full
                // sample:
                //Failed to deliver to '<xxxxxxx@she.com>'
                //LOCAL module(account xxxxxx) reports:
                // account is full (quota exceeded)
                //
                // sample 2:
                //Error in fabiomod_sql_glob_init: no data source specified - database access disabled
                //[Fri Feb 17 23:29:38 PST 2006] full error for caltsmy:
                //        that member's mailbox is full
                //550 5.0.0 <caltsmy@gay.com>... Can't create output
                //
                elseif (preg_match ("/(?:alias|account|recipient|address|email|mailbox|user).*full/i",$dsn_msg)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0132';
                }
                // rule : full
                // sample:
                // gaosong "(0), ErrMsg=Mailbox space not enough (space limit is 10240KB)
                //
                elseif (preg_match ("/space.*not.*enough/i",$dsn_msg)){
                    $result['rule_cat']='full';
                    $result['rule_no']='0219';
                }
                // rule : defer
                // sample:
                //   ----- Transcript of session follows -----
                //xxxx@kimo.com.tw... Deferred: Connection refused by nomail.tpe.yahoo.com.
                //Message could not be delivered for 5 days
                //Message will be deleted from queue
                //
                // sample 2:
                //451 4.4.1 reply: read error from www.sinamail.com.
                //xxxxxxxxx@sinamail.com... Deferred: Connection reset by www.sinamail.com.
                //
                elseif (preg_match ("/Deferred.*Connection (?:refused|reset)/i",$dsn_msg)){
                    $result['rule_cat']='defer';
                    $result['rule_no']='0115';
                }
                // rule : dns_unknown
                // sample:
                //   ----- The following addresses had permanent fatal errors -----
                //Tan XXXX SSSS <xxxxx@hotmail..com>
                //
                //   ----- Transcript of session follows -----
                //553 5.1.2 XXXX SSSS <xxxxx@hotmail..com>... Invalid host name
                //
                elseif (preg_match ("/Invalid host name/i",$dsn_msg)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0109';
                }
                // rule : dns_unknown
                // sample:
                //   ----- Transcript of session follows -----
                //tc.huan@msa.com.tw... Deferred: mail.msa.com.tw.: No route to host
                //
                elseif (preg_match ("/Deferred.*No route to host/i",$dsn_msg)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0109';
                }
                // rule : dns_unknown
                // sample:
                //   ----- Transcript of session follows -----
                //550 5.1.2 b0912559007@yahop.com... Host unknown (Name server: .: no data known)
                //
                elseif (preg_match ("/Host unknown/i",$dsn_msg)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0140';
                }
                // rule : dns_unknown
                // sample:
                //   ----- Transcript of session follows -----
                //451 HOTMAIL.com.tw: Name server timeout
                //Message could not be delivered for 5 days
                //Message will be deleted from queue
                //
                elseif (preg_match ("/Name server timeout/i",$dsn_msg)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0118';
                }
                // rule : dns_unknown
                // sample:
                //   ----- Transcript of session follows -----
                //tung@hkfight.com... Deferred: Connection timed out with hkfight.com.
                //Message could not be delivered for 5 days
                //Message will be deleted from queue
                //
                elseif (preg_match ("/Deferred.*Connection.*tim(?:e|ed).*out/i",$dsn_msg)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0119';
                }
                // rule : dns_unknown
                // sample:
                //   ----- Transcript of session follows -----
                //xxxxxxxxxx@netviator.com... Deferred: Name server: netviator.com.: host name lookup failure
                elseif (preg_match ("/Deferred.*host name lookup failure/i",$dsn_msg)){
                    $result['rule_cat']='dns_unknown';
                    $result['rule_no']='0121';
                }
                // rule : dns_loop
                // sample:
                //   ----- Transcript of session follows -----^M
                //554 5.0.0 MX list for znet.ws. points back to mail01.worldsite.ws^M
                //554 5.3.5 Local configuration error^M
                //
                elseif (preg_match ("/MX list.*point.*back/i",$dsn_msg)){
                    $result['rule_cat']='dns_loop';
                    $result['rule_no']='0199';
                }
                // rule : internal_error
                // sample:
                //   ----- Transcript of session follows -----
                //451 4.0.0 I/O error
                //
                elseif (preg_match ("/I\/O error/i",$dsn_msg)){
                    $result['rule_cat']='internal_error';
                    $result['rule_no']='0120';
                }
                // rule : internal_error
                // sample:
                //Failed to deliver to 'stv_wu@yahoo.com'^M
                //SMTP module(domain yahoo.com) reports:^M
                // connection with mx1.mail.yahoo.com is broken^M
                //
                elseif (preg_match ("/connection.*broken/i",$dsn_msg)){
                    $result['rule_cat']='internal_error';
                    $result['rule_no']='0231';
                }
                // rule : other
                // sample:
                //Delivery to the following recipients failed.
                //
                //       xxxxxxxxxxxx@pci.co.id
                //
                elseif (preg_match ("/Delivery to the following recipients failed.*\n.*\n.*".$result['email']."/i",$dsn_msg)){
                    $result['rule_cat']='other';
                    $result['rule_no']='0176';
                }
                //TRICKY : followings are wind-up rule : must be the last one
                //              many other rules msg end up with "550 5.1.1 ... User unknown"
                //              many other rules msg end up with "554 5.0.0 Service unavailable"
                // rule : unknown
                // sample:
                //   ----- The following addresses had permanent fatal errors -----^M
                //<xxxx@delphin.com.hk>^M
                //    (reason: User unknown)^M
                //
                // sample 2:
                //550 5.1.1 xxxxxxxxxxxx@fargoexpress.com.hk... User unknown^M
                //
                elseif (preg_match ("/User unknown/i",$dsn_msg)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0193';
                }
                // rule : unknown
                // sample:
                //554 5.0.0 Service unavailable
                //
                //
                elseif (preg_match ("/Service unavailable/i",$dsn_msg)){
                    $result['rule_cat']='unknown';
                    $result['rule_no']='0214';
                }
                // rule: antispam
                // sample:
                // Diagnostic SMTP : 550 SC-004 Mail rejected by Windows Live Hotmail for policy reasons.
                // A block has been placed against your IP address because we have received complaints concerning mail coming from that IP address.
                // If you are not an email/network admin please contact your E-mail/Internet Service Provider for help.
                // Email/network admins, we recommend enrolling in our Junk E-Mail Reporting Program (JMRP), a free program intended to help senders remove unwanted recipients from their e-mail list: http://postmaster.live.com
                elseif (preg_match ("/Mail rejected.*policy reasons/i",$dsn_msg)){
                    $result['rule_cat']='antispam';
                    $result['rule_no']='0249';
                }

                break;
            case 'delayed':
                    $result['rule_cat']='delayed';
                    $result['rule_no']='0110';
                break;
            case 'delivered':
            case 'relayed':
            case 'expanded':
                // unhandled cases
                break;
            default :
                break;

        }
    }


    global $rule_categories;
    if ($result['rule_no']=='0000'){
        if ($debug_mode){
            echo "email:{$result['email']}\n";
            echo "Action:$action\n";
            echo "Status:$status_code\n";
            echo "Diagnostic-Code:$diag_code\n";
            echo "DSN Message:\n$dsn_msg\n";
//          echo "DSN Report:\n$dsn_report\n";
            echo "\n";
        }
    }
    else{
        if ($result['bounce_type'] ===false){
            $result['bounce_type']=$rule_categories[$result['rule_cat']]['bounce_type'];
            $result['remove']=$rule_categories[$result['rule_cat']]['remove'];
        }
    }

    $result['action'] = $action;
    $result['status_code'] = $status_code;
    $result['diag_code'] = $diag_code;

    return $result;

}
?>
