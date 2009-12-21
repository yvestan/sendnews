<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * SendNews_Utils : classe d'utilitaires
 *
 * PHP versions 5
 *
 * LICENSE: Ce programme est un logiciel libre distribue sous licence GNU/GPL
 *
 * @author     Yves Tannier <yves SANSPAM grafactory.net>
 * @copyright  2009 [grafactory.net]
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    0.7.1 
 * @link       http://www.grafactory.net
 */

class Sendnews_Utils
{

    // {{{ setTemplateMsg()

   /** Remplacement de variable
    *
    * Mini système de template pour créer des fichiers de configuration à partir d'un squelette
    * en remplaçant {_(pattern)_} par la valeur
    *
    * @access   public
    * @return   string
    * @param    string  $msg         Message
    * @param    arrayu  $values      Valeur de l'enregistrement
    */
    public function setTemplateMsg($msg,$values)
    {
        $GLOBALS['values'] = $values;
        $pattern = '#{_\(([a-zA-Z0-9_]+)\)_}#mSU';
        return preg_replace_callback($pattern,create_function('$matches', 'global $values; if(!empty($values[$matches[1]])) return $values[$matches[1]];'), $msg);
    }

    // }}}

    /// {{{ getTimeExec()

   /** Calcul le temps d'execution du script
    *
    * @access   public
    * @return   string
    * @param    string  $start  Début
    * @param    string  $end    Fin
    */
    public function getTimeExec($start,$end)
    {
        $timestamp = abs($end - $start);
        $diff_heure = floor($timestamp / 3600);
        $timestamp = $timestamp - ($diff_heure * 3600);
        $diff_min = $timestamp / 60;
        return $diff_heure.' heure(s) '.round($diff_min,1).' minute(s)';
    }

    // }}}

    /// {{{ isEmailSyntaxValid()

   /** Contrôle la validité de l'adresse email
    *
    * Auteur : bobocop (arobase) bobocop (point) cz
    * Traduction des commentaires par mathieu
    *
    * Le code suivant est la version du 2 mai 2005 qui respecte les RFC 2822 et 1035
    * http://www.faqs.org/rfcs/rfc2822.html
    * http://www.faqs.org/rfcs/rfc1035.html
    *
    * @access   public
    * @return   bool
    * @param    string  $email  Adresse email
    */
    public function isEmailSyntaxValid($email)
    {

        $atom   = '[-a-z0-9!#$%&\'*+\\/=?^_`{|}~]';   // caractères autorisés avant l'arobase
        $domain = '([a-z0-9]([-a-z0-9]*[a-z0-9]+)?)'; // caractères autorisés après l'arobase (nom de domaine)
                                       
        $regex = '/^' . $atom . '+' .   // Une ou plusieurs fois les caractères autorisés avant l'arobase
        '(\.' . $atom . '+)*' .         // Suivis par zéro point ou plus
                                        // séparés par des caractères autorisés avant l'arobase
        '@' .                           // Suivis d'un arobase
        '(' . $domain . '{1,63}\.)+' .  // Suivis par 1 à 63 caractères autorisés pour le nom de domaine
                                        // séparés par des points
        $domain . '{2,63}$/i';          // Suivi de 2 à 63 caractères autorisés pour le nom de domaine
        return preg_match($regex,$email);

    }

    // }}}

}
?>
