<?php
# *** LICENSE ***
# This file is part of BlogoText.
# http://lehollandaisvolant.net/blogotext/
#
# 2006      Frederic Nassar.
# 2010-2015 Timo Van Neerden.
#
# BlogoText is free software.
# You can redistribute it under the terms of the MIT / X11 Licence.
#
# *** LICENSE ***

$begin = microtime(true);
define('BT_ROOT', '../');



require_once '../inc/inc.php';

operate_session();

$GLOBALS['db_handle'] = open_base();


// RECUP MAJ
$article_id='';
$article_title='';



// TRAITEMENT
$erreurs_form = array();
if (isset($_POST['_verif_envoi'])) {
    if (isset($_POST['com_supprimer']) or isset($_POST['com_activer'])) {
        $comm_action = (isset($_POST['com_supprimer']) ? $_POST['com_supprimer'] : $_POST['com_activer']);
        $erreurs_form = valider_form_commentaire_ajax($comm_action);
        if (empty($erreurs_form)) {
            traiter_form_commentaire($comm_action, 'admin');
        } else {
            echo implode("\n", $erreurs_form);
            die();
        }
    } else {
        $comment = init_post_comment($_POST['comment_article_id'], 'admin');
        $erreurs_form = valider_form_commentaire($comment, 'admin');
        if (empty($erreurs_form)) {
            traiter_form_commentaire($comment, 'admin');
        }
    }
}


$tableau = array();
// if article ID is given in query string

if (isset($_GET['post_id']) and preg_match('#\d{14}#', $_GET['post_id'])) {
    $param_makeup['menu_theme'] = 'for_article';
    $article_id = $_GET['post_id'];

    $query = "SELECT c.*, a.bt_title FROM commentaires AS c, articles AS a WHERE c.bt_article_id=? AND c.bt_article_id=a.bt_id ORDER BY c.bt_id";
    $commentaires = liste_elements($query, array($article_id), 'commentaires');

    if (!empty($commentaires)) {
        $article_title = $commentaires[0]['bt_title'];
    } else {
        $article_title = get_entry($GLOBALS['db_handle'], 'articles', 'bt_title', $article_id, 'return');
    }

    $param_makeup['show_links'] = '0';
} // else, no ID
else {
    $param_makeup['menu_theme'] = 'for_comms';
    if (!empty($_GET['filtre'])) {
        // for "authors" the requests is "auteur.$search" : here we split the type of search and what we search.
        $type = substr($_GET['filtre'], 0, -strlen(strstr($_GET['filtre'], '.')));
        $search = htmlspecialchars(ltrim(strstr($_GET['filtre'], '.'), '.'));
        // filter for date
        if (preg_match('#^\d{6}(\d{1,8})?$#', ($_GET['filtre']))) {
            $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id = c.bt_article_id WHERE c.bt_id LIKE ? ORDER BY c.bt_id DESC";
            $commentaires = liste_elements($query, array($_GET['filtre'].'%'), 'commentaires');
        } // filter for statut
        elseif ($_GET['filtre'] == 'draft') {
            $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id=c.bt_article_id WHERE c.bt_statut=0 ORDER BY c.bt_id DESC";
            $commentaires = liste_elements($query, array(), 'commentaires');
        } elseif ($_GET['filtre'] == 'pub') {
            $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id=c.bt_article_id WHERE c.bt_statut=1 ORDER BY c.bt_id DESC";
            $commentaires = liste_elements($query, array(), 'commentaires');
        } // filter for author
        elseif ($type == 'auteur' and $search != '') {
            $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id=c.bt_article_id WHERE c.bt_author=? ORDER BY c.bt_id DESC";
            $commentaires = liste_elements($query, array($search), 'commentaires');
        } // no filter
        else {
            $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id=c.bt_article_id ORDER BY c.bt_id DESC LIMIT ".$GLOBALS['max_comm_admin'];
            $commentaires = liste_elements($query, array(), 'commentaires');
        }
    } elseif (!empty($_GET['q'])) {
        $arr = parse_search($_GET['q']);
        $sql_where = implode(array_fill(0, count($arr), 'c.bt_content LIKE ? '), 'AND ');
        $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id=c.bt_article_id WHERE ".$sql_where."ORDER BY c.bt_id DESC";
        $commentaires = liste_elements($query, $arr, 'commentaires');
    } else { // no filter, so list'em all
        $query = "SELECT c.*, a.bt_title FROM commentaires c LEFT JOIN articles a ON a.bt_id=c.bt_article_id ORDER BY c.bt_id DESC LIMIT ".$GLOBALS['max_comm_admin'];
        $commentaires = liste_elements($query, array(), 'commentaires');
    }
    $nb_total_comms = liste_elements_count("SELECT count(*) AS nbr FROM commentaires", array());
    $param_makeup['show_links'] = '1';
}

function afficher_commentaire($comment, $with_link)
{
    afficher_form_commentaire($comment['bt_article_id'], 'admin', '', $comment);
    echo '<div class="commentbloc'.(!$comment['bt_statut'] ? ' privatebloc' : '').'" id="'.article_anchor($comment['bt_id']).'">'."\n";
    echo '<div class="comm-side-icon">'."\n";
        echo "\t".'<div class="comm-title">'."\n";
        echo "\t\t".'<img class="author-icon" src="cache/get.php?w=gravatar&amp;q='.md5((!empty($comment['bt_email']) ? $comment['bt_email'] : $comment['bt_author'] )).'&amp;s=48&amp;d=monsterid"/>'."\n";
        echo "\t\t".'<span class="date">'.date_formate($comment['bt_id']).'<span>'.heure_formate($comment['bt_id']).'</span></span>'."\n" ;

        echo "\t\t".'<span class="reply" onclick="reply(\'[b]@['.str_replace('\'', '\\\'', $comment['bt_author']).'|#'.article_anchor($comment['bt_id']).'] :[/b] \'); ">Reply</span> ';
        echo (!empty($comment['bt_webpage'])) ? "\t\t".'<span class="webpage"><a href="'.$comment['bt_webpage'].'" title="'.$comment['bt_webpage'].'">'.$comment['bt_webpage'].'</a></span>'."\n" : '';
        echo (!empty($comment['bt_email'])) ? "\t\t".'<span class="email"><a href="mailto:'.$comment['bt_email'].'" title="'.$comment['bt_email'].'">'.$comment['bt_email'].'</a></span>'."\n" : '';
        echo "\t".'</div>'."\n";
    echo '</div>'."\n";
    
    echo '<div class="comm-main-frame">'."\n";

    echo "\t".'<div class="comm-header">'."\n";

    echo "\t\t".'<div class="comm-title">'."\n";
    echo "\t\t\t".'<span class="author"><a href="?filtre=auteur.'.$comment['bt_author'].'" title="'.$GLOBALS['lang']['label_all_comm_by_author'].'">'.$comment['bt_author'].'</a> :</span>'."\n";
    echo "\t\t".'</div>'."\n";

    echo ($with_link == 1 and !empty($comment['bt_title'])) ? "\t\t".'<span class="link-article"> '.$GLOBALS['lang']['sur'].' <a href="'.basename($_SERVER['SCRIPT_NAME']).'?post_id='.$comment['bt_article_id'].'">'.$comment['bt_title'].'</a></span>'."\n" : '';

    echo "\t\t".'<div class="comm-options">'."\n";
    echo "\t\t\t".'<ul>'."\n";
    echo "\t\t\t\t".'<li class="cl-edit" onclick="unfold(this);">'.$GLOBALS['lang']['editer'].'</li>'."\n";
    echo "\t\t\t\t".'<li class="cl-activ" onclick="activate_comm(this);" data-comm-id="'.$comment['ID'].'" data-comm-btid="'.$comment['bt_id'].'" data-comm-art-id="'.$comment['bt_article_id'].'">'.$GLOBALS['lang'][(!$comment['bt_statut'] ? '' : 'des').'activer'].'</li>'."\n";
    echo "\t\t\t\t".'<li class="cl-suppr" onclick="suppr_comm(this);" data-comm-id="'.$comment['ID'].'" data-comm-art-id="'.$comment['bt_article_id'].'">'.$GLOBALS['lang']['supprimer'].'</li>'."\n";
    echo "\t\t\t".'</ul>'."\n";
    echo "\t\t".'</div>'."\n";

    echo "\t".'</div>'."\n";

    echo "\t".'<div class="comm-content">'."\n";
    echo $comment['bt_content'];
    echo "\t".'</div>'."\n";
    echo $GLOBALS['form_commentaire'];

    echo "\t".'</div>'."\n\n";
    echo '</div>'."\n\n";
}

// DEBUT PAGE
$msgg = $GLOBALS['lang']['titre_commentaires']. ((!empty($article_title)) ?' | '.$article_title : '');
afficher_html_head($msgg);


echo '<div id="header">'."\n";
    echo '<div id="top">'."\n";
    afficher_msg();
    echo moteur_recherche();
    afficher_topnav($GLOBALS['lang']['titre_commentaires']);
    echo '</div>'."\n";
echo '</div>'."\n";

echo '<div id="axe">'."\n";
// SUBNAV
echo '<div id="subnav">'."\n";
    // Affichage formulaire filtrage commentaires
if (isset($_GET['filtre'])) {
    afficher_form_filtre('commentaires', htmlspecialchars($_GET['filtre']));
} else {
    afficher_form_filtre('commentaires', '');
}
    echo '<div class="nombre-elem">'."\n";
if ($param_makeup['menu_theme'] == 'for_article') {
    $dec_id = decode_id($article_id);
    $article_link = $GLOBALS['racine'].'?d='.$dec_id['annee'].'/'.$dec_id['mois'].'/'.$dec_id['jour'].'/'.$dec_id['heure'].'/'.$dec_id['minutes'].'/'.$dec_id['secondes'].'-'.titre_url($article_title);
    echo '<ul>'."\n";
    echo "\t".'<li><a href="ecrire.php?post_id='.$article_id.'">'.$GLOBALS['lang']['ecrire'].$article_title.'</a></li>'."\n";
    echo "\t".'<li><a href="'.$article_link.'">'.$GLOBALS['lang']['lien_article'].'</a></li>'."\n";
    echo '</ul>'."\n";
    echo '– &nbsp; '.ucfirst(nombre_objets(count($commentaires), 'commentaire'));
} elseif ($param_makeup['menu_theme'] == 'for_comms') {
    echo ucfirst(nombre_objets(count($commentaires), 'commentaire')).' '.$GLOBALS['lang']['sur'].' '.$nb_total_comms;
}
    echo '</div>'."\n";
echo '</div>'."\n";

echo '<div id="page">'."\n";


// COMMENTAIRES
if (count($commentaires) > 0) {
    echo '<div id="liste-commentaires">'."\n";
    $token = new_token();
    foreach ($commentaires as $content) {
        $content['comm-token'] = $token;
        afficher_commentaire($content, $param_makeup['show_links']);
    }
    echo '</div>'."\n";
} else {
    echo info($GLOBALS['lang']['note_no_commentaire']);
}


if ($param_makeup['menu_theme'] == 'for_article') {
    echo '<div id="post-nv-commentaire">'."\n";
    afficher_form_commentaire($article_id, 'admin', $erreurs_form, '');
    echo '<h2 class="poster-comment">'.$GLOBALS['lang']['comment_ajout'].'</h2>'."\n";
    echo $GLOBALS['form_commentaire'];
    echo '</div>'."\n";
}

echo "\n".'<script src="style/javascript.js" type="text/javascript"></script>'."\n";
echo '<script type="text/javascript">'."\n";
echo php_lang_to_js(0);
echo 'var csrf_token = \''.new_token().'\'';
echo '</script>'."\n";

footer($begin);
