<?php
# *** LICENSE ***
# This file is a addon for BlogoText.
# http://lehollandaisvolant.net/blogotext/
#
# 2016 thuban
#
# You can redistribute it under the terms of the MIT / X11 Licence.
#
# *** LICENSE ***

# TODO : configuration du tag si on souhaite autre chose que _linklist

$GLOBALS['addons'][] = array(
    'tag' => 'linklist',
    'name' => 'Link list',
    'desc' => array(
        'en' => 'Display a list of articles with "_linklist" tag.',
        'fr' => 'Affiche une liste de liens vers les articles avec l\'étiquette "_linklist".',
    ),
    'url' => 'http://yeuxdelibad.net/Blog',
    'version' => '1.0.0',
);

// returns HTML <table> calender
function addon_linklist()
{
    $tag = '_linklist';

    try {
        $result = $GLOBALS['db_handle']->query("SELECT bt_title, bt_id, bt_tags FROM articles WHERE bt_statut=1")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die('Error rand addon_linklist(): '.$e->getMessage());
    }

    $html = "<ul>\n";
    // clean array
    foreach ($result as $i => $art) {
        if (strpos($art['bt_tags'], $tag) !== false) {
            // generates link
            $dec_id = decode_id($art['bt_id']);
            $link = $GLOBALS['racine'].'?d='.implode('/', $dec_id).'-'.titre_url($art['bt_title']);
            $html .= "\t".'<li><a href="'.$link.'">'.$art['bt_title'].'</a>'."\n";
        }
    }
    $html .= "</ul>\n";

    return $html;
}
