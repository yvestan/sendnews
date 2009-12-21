<?php

/**
 * configuration exemple
 */

// type de base de donnee (voir la doc de PHP/PDO)
define('DB_TYPE', 'mysql');

// hote base de donnees
define('DB_HOST', 'localhost');

// utilisateur base de donnees
define('DB_USER', 'username');

// mot de passe base de donnees
define('DB_PASS', 'password');

// nom de la base de donnees
define('DB_NAME', 'database');

// table des abonnes
define('USERS', 'subscribers');

// champ de l'adresse email
define('EMAIL_USERS', 'email');

// champ de la cle primaire de la table users
define('ID_USERS','idsubscriber');

//==> requetes de selection des destinataires

//AND s.email NOT REGEXP \'@yahoo\\.\'
$queries = array(
    // requete de sélection des destinataires
    'select' => 'SELECT s.idsubscriber, s.email 
                 FROM subscribers s
                 WHERE s.sent=0
                 AND s.status=1
                 ORDER BY s.idsubscriber ASC',

    //requete de mise à jour après envoi
    'update' => 'UPDATE subscribers
                 SET sent=1
                 WHERE idsubscriber=?',

    //requete de re-initialisation du champ envoye/sent
    'renew' => 'UPDATE subscribers
                SET sent=0
                WHERE sent=1',
);


// type d'expedition (attention aux majuscules !)
/*
 * SendMail = via la commande sendmail (optimal)
 * NativeMail = via la fonction mail() de php
 * Other = vous devez preciser la configuration dans un fichier conf/sending.php NON TESTE !!!
 * 
 */
define('TYPE_SENDING', 'SendMail');

// expediteur de la newsletter (email et nom)
define('SENDER', 'abonnement@exemple.tld');
define('SENDER_NAME', 'Nom Expediteur');

//  nom d'hote ou ip du serveur de mail
define('SERVER_MAIL', 'mail.exemple.tld');

// adresse email du test par defaut
$defaut_send = 'test@exemple.tld';

// sujet du message (peut-être passé en paramètre du script)
$subject = 'Lettre hebdomadaire';

// pour les clients qui ne lise pas le html, texte alternatif (le script ajoute le nom du fichier html à la fin de cette variable)
$msg_text = 'Si vous ne pouvez pas lire correctement ce message : http://www.exemple.tld/newsletter/';

// repertoire de stockage des messages au format HTML (avec slash a la fin)
define('DIR_HTML', '/path/to/newsletters/dir/');

// nombre d'envoi avant la pause
$per_send = 35;

// duree de la pause en seconde
$pause_time = 35;

///// gestion des bounces

// nom d'utilisateur de la boite qui recois les retours
define('SENDER_RETURN', 'noreply@exemple.tld');

// nom d'utilisateur de la boite qui recois les retours
define('BOUNCE_USER', 'noreply@exemple.tld');

// mot de passe de cette boite
define('BOUNCE_PASS', 'password');

// serveur pop/imap de cette boite
define('BOUNCE_SERVER', 'imap.exemple.tld');

// nombre de bounce a traiter en meme temps dans la boite de retour
define('BOUNCE_NB', 6000);

// fonction pour logguer tous les bounces
function logAction($params)
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

    $sql = 'INSERT INTO bounces SET '.$expr.' ON DUPLICATE KEY UPDATE dateinsert=NOW(),'.$expr;
    //print $sql;
    $logquery = $db->prepare($sql);

    foreach(array_keys($params) as $v) {
        $logquery->bindParam(':'.$v, $params[$v]);
    }
    $logquery->execute();
}

// fonction à utiliser pour les bounces reconnus avec succès
/*
function bounceAction($params)
{
    print "\n!!! MATCHED\n";
    print_r($params);
    print "--------------------\n";
    return true;

}
*/

// fonction à utiliser pour les bounces non reconnus
/*
function unmatchedAction($params)
{
    print "\n??? UNMATCHED\n";
    print_r($params);
    print "--------------------\n";
    return false;
}
*/

///// fonction de personalisation des messages

/**
 *
 * Principe : 
 *      - la clé correspond à la valeur entre double accolade dans le fichier HTML. exemple : {{lastname}}
 *      - la valeur correspond à la valeur de remplacement : 'field' => 'NOM_DU_CHAMP'
 *
 * Attention : vous devez ajouter les champs dans la requête définie dans le array $queries
 *
 * */
define('TEMPLATE_MSG', true);

?>
