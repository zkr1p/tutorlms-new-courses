<?php

use boctulus\TutorNewCourses\core\Constants;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Plugins;
use boctulus\TutorNewCourses\core\libs\Strings;

function get_text_domain(){
    return Plugins::getTextDomain();
}

function get_trans_languages(bool $include_country = true){
    static $langs;

    if ($langs !== null){
        return $include_country ? $langs : array_map(fn($lang) => substr($lang, 0, 2), $langs);
    }

    // Asumiendo las traducciones las coloco dentro del plugin   
    $mos   = glob(Constants::ROOT_PATH . 'languages' . DIRECTORY_SEPARATOR  . '*.mo');

    $langs = [];
    foreach ($mos as $mo){
        $f = basename($mo);

        if ($match = Strings::match($f, '/([a-z]{2}_[A-Z]{2}).mo/')){
            $langs[]      = $match;     
        }
    }    

    return $langs;
}

/*
    dd(get_lang('wp1')); // es    <-- idioma seleccionado como "Espanol"
    dd(get_lang('wp2')); // es_ES
    dd(get_lang('wpu')); // es_ES <-- usuario ha seleccionado Espanol (parece siempre traer el idioma del sitio)
    dd(get_lang('php')); // es_AR <-- en PHP
*/
function get_lang($where = 'wp1', bool $only_lang = false)
{
    switch ($where){
        // Devuelve el idioma y no el pais
        case 'wp1':
            $lan = get_bloginfo("language");
        break;
        // Devuelve el idioma + pais
        case 'wp2':
            $lan = get_locale();
        break;
        case 'wpu':
            $lan = get_user_locale();
        break;
        case 'php':
            $lan = locale_get_default();
        break;
        // Polylang plugin
        case 'ppl':
            if (function_exists('pll_current_language')){
                $fn = 'pll_current_language';
                $lan = $fn();
            }            
        break;            
    }

    if ($only_lang){
        $lan = substr($lan, 0, 2);
    }

    return $lan;
}

/*
    En teoria al menos deberia funcionar en Windows
*/
function set_locale(string $lang)
{    
    $lang = str_replace('_', '-', $lang);    
    locale_set_default($lang);
}

/*    
    @param string $lang como 'en_US' o simplente 'en'
*/
function set_lang(string $lang)
{
    static $langs = [];
    static $pure_langs = [];

    $selected = null;

    if ($lang === NULL || $lang === '*'){
        return;
    }
    
    $throw  = false;

    /*
        Aca se busca en archivos en /languages con nombres como "mystore-es_AR.mo"

        La busqueda NO es por carpetas (como en SimpleRest)
    */

    $mos   = glob(ROOT_PATH . 'languages' . DIRECTORY_SEPARATOR  . '*.mo');

    $langs = [];
    foreach ($mos as $mo){
        $f = basename($mo);

        if ($match = Strings::match($f, '/([a-z]{2}_[A-Z]{2}).mo/')){
            $langs[]      = $match;   
            $pure_langs[] = substr($match, 0, 2);         
        }
    }    

    // dd($langs, 'langs');
    // dd($pure_langs, 'pure langs');

    /*
        If there is no translation for an specific country then to use any for that language
    */
    if (!Strings::contains('_', $lang)){
        $only_lang = substr($lang, 0, 2);
        $lang_ix   = array_search($only_lang, $pure_langs);
        $selected  = $langs[$lang_ix];     
    } else {
        // full format
        $lang_ix   = array_search($lang, $langs);

        if ($lang_ix !== false){
            $selected  = $langs[$lang_ix];    
        }
    }

    /*
        If every thing fails, last chance
    */
    if ($selected === null){
        $only_lang = substr($lang, 0, 2);
        $lang_ix   = array_search($only_lang, $pure_langs);
        $selected  = $langs[$lang_ix];     
    }

    if ($selected === null){
        if ($throw){
            throw new \InvalidArgumentException("Invalid lang $lang");
        }

        return;        
    }

    // dd($selected, 'LANG');

    switch_to_locale($selected);
}

/*
    Copia una traduccion disponible a un idioma sin traduccion
*/
function make_trans_av()
{
    static $ok;

    if ($ok !== null){
        return $ok;
    }

    /*	
        [0] => en_US
        [1] => es_AR
        [2] => es_ES
    */
    $trans_langs_co = get_trans_languages();
    $trans_langs    = get_trans_languages(false);

    // dd($trans_langs_co);
    // dd($trans_langs);

    $path = Constants::ROOT_PATH . 'languages' . DIRECTORY_SEPARATOR;

    // it_IT
    $current_lan = get_lang('wp2');

    $ok = false;
    if (!in_array($current_lan, $trans_langs_co)){
        $domain = get_text_domain();

        if ($ix = array_search(substr($current_lan, 0, 2), $trans_langs)){
            $lang_co_av = $trans_langs_co[$ix];

            $ok = copy($path . "$domain-$lang_co_av.mo", $path . "$domain-$current_lan.mo");

            // Logger::log($path . "$domain-$lang_co_av.mo" . ' -> '. $path . "$domain-$current_lan.mo");
        } else {
            if (file_exists($path . "$domain-en_US.mo")){
                $ok = copy($path . "$domain-en_US.mo", $path . "$domain-$current_lan.mo");
                
                // Logger::log($path . "$domain-en_US.mo" . ' -> '. $path . "$domain-$current_lan.mo");
            }
        }
    } else {
        $ok = true;
    }

    return $ok;
}


/*  
    Podria volver insensible al case las traducciones

    El uso de filtros no es tan buena idea como directamente
    poder pasae el texto con el case que se quiera
*/
function trans($str, $filter = null, $text_domain = null){
    make_trans_av();

    $text_domain = $text_domain ?? get_text_domain();

    $str = __($str, get_text_domain());

    if ($filter !== null){
        $str = Strings::case($filter, $str);
    }

    if (get_lang('wp2') == 'en'){
        $str =  Strings::beforeIfContains($str, ':/');
    }
    
    return $str;
}

