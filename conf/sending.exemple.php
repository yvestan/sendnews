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
 * Pour la configuration, référez vous à la documentation de SwiftMailer :
 * http://swiftmailer.org/docs/sending.html
 *
 */

// l'objet doit s'appeller $transport

// envoi avec un smtp authentifié sur le port 587
/*$transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 587)
    ->setUsername('username')
    ->setPassword('password');

//  envoi avec le serveur local
//$transport = Swift_SmtpTransport::newInstance("127.0.0.1", 25);
?>
