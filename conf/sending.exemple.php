<?php
/*
 *
 * Si vous avez défini la constante TYPE_SENDING à "Other" dans le fichier de configuration,
 * dans ce fichier, vous pouvez préciser une configuration particulière de votre envoi
 * en reprécisant l'instanciation de l'object SwiftMailer
 *
 * Il faut renommer ce fichier en "sending.php" pour qu'il soit pris en compte
 *
 * De nombreuses options sont disponibles :
 * - Via STMP (sécurisé, non sécurisé etc...)
 * - En utilisant plusieurs SMTP en même temps
 * - En tournant sur plusieurs systèmes d'envoi
 * ...
 *
 * Pour la configuration, référez vous à la documentation de SwfitMailer :
 * http://www.swiftmailer.org/wikidocs/ (The connections).
 *
 */

// l'objet doit s'appeller $swift
// exemple :
//
//  require_once $swiftdir.'Swift/Connection/SMTP.php';
//  $smtp = new Swift_Connection_SMTP('smtp.exemple.tld', Swift_Connection_SMTP::PORT_SECURE, Swift_Connection_SMTP::ENC_TLS);
//
//  $smtp->setUsername('username');
//  $smtp->setpassword('password');
//
//  $swift =& new Swift($smtp);
?>
