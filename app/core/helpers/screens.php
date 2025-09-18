<?php

use boctulus\TutorNewCourses\core\libs\Strings;
use boctulus\TutorNewCourses\core\libs\Url;

/*
    Deberia mergearse con la lib Page
*/

// antes "is_user_page()"
function is_dashboard_user_page(){
    $slugs   = trim(rtrim(Url::getSlugs(null, true), '/'));
    return ($slugs == '/my-account/edit-account' || $slugs == '/dashboard/editar-cuenta');
}

function is_dashboard_prod_page()
{
    $slugs = trim(rtrim(Url::getSlugs(null, true), '/'));

    if (Strings::contains('/post.php', $slugs)){
        return true;
    }

    if (Strings::contains('/edit.php', $slugs)){
        $q = Url::queryString();

        if (isset($q['post_type']) && $q['post_type'] == 'product'){
            return true;
        }
    }

    return false;
}

/*
    Si se le pasa el step, por ejemplo "done" comprueba este en ese step
*/
function is_dashboard_prod_importer_page($step = null)
{
    if (!is_dashboard_prod_page()){
        return false;
    }

    $q = Url::queryString();

    if (isset($q['page']) && $q['page'] == 'product_importer'){
        if ($step !== null){
            return (isset($q['step']) && $q['step'] == $step);
        }

        return true;
    }

    return false;
}