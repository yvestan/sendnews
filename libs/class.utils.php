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

   /** Remplacement de variable TODO
    *
    * Mini système de template pour créer des fichiers de configuration à partir d'un squelette
    * en remplaçant {pattern} par la valeur
    *
    * @access   public
    * @return    bool
    * @param    string  $orig         Fichier "template"
    * @param    string  $dest        Fichier de destination
    * @param    array   $keywords Mots à rechercher dans le template
    * @param    array   $values     Valeur de remplacement
    */
    public static function setConfigFile($orig,$dest,$keywords,$values)
    {

        // contenu du fichier dans une variable
        $content = file_get_contents($orig);

        // ajout pattern
        foreach($keywords as $key=>$value) {
            $patterns[] = "/{".$value."}/imSU";
        }

        // remplacement
        $output_content = preg_replace($patterns, $values, $content);

        // variable dans un fichier ou exception
        if(!file_put_contents($dest,$output_content)) {
            throw new Exception('Erreur dans la creation du fichier');
        }

        return true;
       
    }

    // }}}

}
?>
