<?php

/**
 * Nettoyage de la liste des abonnés entierement base
 * sur un script de sourceforge bmh
 *
 */

// appel la lib bmh
define('LIB_BMH','');
include LIB_BMH.'bmh.php';

/**
 * Fonction action
 *
 * @param string $bounce_type   the bbounce type, 'hard', 'soft', 'blocked', 'temporary', 'autoreply', 'subscribe', 'unsubscribe', 'generic', 'challengeresponse', 'non'
 * @param string $email         the target email address
 * @param string $subject       the subject, ignore now
 * @param string $xheader       the XBounceHeader from the mail
 * @param 1 or 0 $remove        remove suggestion from bbounce, 1 means suggest remove
 * @param string $rule_no        bounce mail detect rule no.
 * @param string $rule_cat       bounce mail detect rule category.
 * @return boolean
 * @author  Kevin : Fri Jun 16 09:47:42 PDT 2006
 */
function bounceActionClean($params) {

	if(!$GLOBALS['silent']) {
		echo "-> Le mail ".$params['email']." est detecte en '".$params['bounce_type']." bounce'";
	}

	// recupere le statment
    if(!$GLOBALS['testsend']) {
        // mettre a jour le champ bounce
        if(!$GLOBALS['deletebounce']) {
            $sta = $GLOBALS['sta'];
            $sta->bindParam(1, $params['bounce_type']);
            $sta->bindParam(2, $params['email']);
            $sta->execute();
        }
    }

    if(!$GLOBALS['silent']) {
        echo "\n";
    }

	return true;
}


function logBounces($params)
{
    $db = $GLOBALS['db'];
    $expr='';
    $values='';
    foreach(array_keys($params) as $v) {
        if ($expr != '') {
            $expr.=',';
        }
        $expr.= '`'.$v.'` = :'.$v;
    }

    $sql = 'INSERT INTO '.LOG_BOUNCES_TABLE.' SET '.$expr.' ON DUPLICATE KEY UPDATE dateinsert=NOW()';
    //print $sql;
    $logquery = $db->prepare($sql);

    foreach(array_keys($params) as $v) {
        $logquery->bindParam(':'.$v, $params[$v]);
    }
    $logquery->execute();
//    $error = $logquery->errorInfo();
//    if ($error[0] != '00000') print_r($arr);
}

// objet BounceMailHandler
$bmh = new BounceMailHandler();

// en mode test
if($testsend) {

    $bmh->testmode = true;

    // si on veux les corps de message
    //$bmh->debug_body_rule=true;
    //$bmh->debug_dsn_rule=true;

    // niveau de debug
    //$bmh->verbose=VERBOSE_DEBUG;
}

$bmh->action_function='bounceActionClean';
$bmh->log_bounces_function='logBounces';
$bmh->openPop3(BOUNCE_SERVER,BOUNCE_USER,BOUNCE_PASS);
$bmh->processMailbox(BOUNCE_NB);
?>
