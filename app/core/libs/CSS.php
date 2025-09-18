<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\XML;
use boctulus\TutorNewCourses\core\libs\Strings;
use Sabberworm\CSS\Parser;

class CSS
{
    /*  
        Dado el HTML de una pagina o el path,

        - Descarga cada archivo .css
        - Generar una linea con css_file() para cada uno

        Con $use_helper en true,

        Salida:

            css_file('practicatest.cl/basic.min.css');
            css_file('practicatest.cl/style.themed.css');
            css_file('practicatest.cl/fontawesome.css');
            css_file('practicatest.cl/brands.css');

        Con $use_helper en false,

        Array
        (
            [0] => D:\www\boctulus\TutorNewCourses\public\assets\practicatest.cl\basic.min.css
            [1] => D:\www\boctulus\TutorNewCourses\public\assets\practicatest.cl\style.themed.css
            [2] => D:\www\boctulus\TutorNewCourses\public\assets\practicatest.cl\fontawesome.css
            [3] => D:\www\boctulus\TutorNewCourses\public\assets\practicatest.cl\brands.css
        )

        Usando $if_callback() se puede filtrar dada una condicion dentro del string

        Ej:

        CSS::downloadAll($url, true, function($url){
            return Strings::contains('/xstore', $url);
        })

        <-- solo descarga si contiene el substring '/xstore'
    */
    static function downloadAll(string $html, bool $use_helper = true, $if_callback = null, $exp_cache = 1800)
    {
        if (Strings::startsWith('https://', $html) || Strings::startsWith('http://', $html)){
            $html = consume_api($html, 'GET', null, null, null, false, false, $exp_cache);
        } else {
            if (strlen($html) <= 255 && Strings::containsAny(['\\', '/'], $html)){
                if (file_exists($html)){
                    $html = Files::getContent($html);
                }            
            }
        }

        $urls = CSS::extractStyleUrls($html, true);

        foreach ($urls as $ix => $url){
            if ($if_callback !== null && is_callable($if_callback) && !$if_callback($url)){
                unset($urls[$ix]);
            }
        }

        // dd($urls, 'URLS');

        $filenames = [];
        foreach ($urls as $url){
            $domain = Url::getDomain($url);
            $path   = ASSETS_PATH . $domain;

            Files::mkDirOrFail($path);        
            $bytes = Files::download($url, $path);   

            if (empty($bytes)){
                throw new \Exception("Download '$url' was not possible");
            }

            $filename    = Files::getFilenameFromURL($url);
            $filenames[] = $path . DIRECTORY_SEPARATOR . $filename;
        }

        if (!$use_helper){
            return $filenames;
        }

        $out = '';
        foreach ($filenames as $ix => $filename){
            $filenames[$ix] = str_replace('\\', '/', Strings::diff($filename, ASSETS_PATH));
            $out .= PHP_EOL . "css_file('$filenames[$ix]');";
        }

        return $out;       
    }

    /*
        Extrae referencias a archivos .css del header de una pagina usando funciones de DOM

        Usar pero tener en cuenta que extractStyleUrls() puede funcionar mejor

        Ej:

        Array
        (
            [1] => https://practicatest.cl/dist/css/basic.min.css
            [2] => https://practicatest.cl/dist/css/style.themed.css
            [3] => https://practicatest.cl/static/fonts/css/fontawesome.css
            [4] => https://practicatest.cl/static/fonts/css/brands.css
            [5] => https://practicatest.cl/static/fonts/css/solid.css
            [6] => https://practicatest.cl/static/fonts/css/regular.css
            [7] => https://practicatest.cl/static/fonts/css/light.css
        )

        Si $use_helper es true, devuelve un string con el uso de css_file()

        Ej:

            css_file('https://practicatest.cl/dist/css/basic.min.css');
            css_file('https://practicatest.cl/dist/css/style.themed.css');
            css_file('https://practicatest.cl/static/fonts/css/fontawesome.css');
            css_file('https://practicatest.cl/static/fonts/css/brands.css');
            css_file('https://practicatest.cl/static/fonts/css/solid.css');
            css_file('https://practicatest.cl/static/fonts/css/regular.css');
            css_file('https://practicatest.cl/static/fonts/css/light.css');

    */
    static function extractLinkUrls(string $html, bool $include_query_params = true, bool $include_fonts = false, bool $use_helper = false) {
        $arr = XML::extractLinksByRelType($html, "stylesheet", (!$include_fonts ? "css" : null), $include_query_params);

        if ($use_helper === false){
            return $arr;
        }

        $lines = [];
        foreach ($arr as $file){
            $lines[] = "css_file('$file');";           
        }

        return implode(PHP_EOL, $lines);
    }

    /*
        Usando expresiones regulares que recupera todas las urls de 

            <link rel='stylesheet> 
        
        de un string.
    */
    static function extractStyleUrls(string $html, $exp_cache = null) {
        if (Strings::startsWith('https://', $html) || Strings::startsWith('http://', $html)){
            $html = consume_api($html, 'GET', null, null, null, false, false, $exp_cache);
        } else {
            if (strlen($html) <= 255 && Strings::containsAny(['\\', '/'], $html)){
                if (file_exists($html)){
                    $html = Files::getContent($html);
                }            
            }
        }

        $pattern = "/<link\s+rel=['\"]stylesheet['\"].*?href=['\"](.*?)['\"].*?>/i";
        preg_match_all($pattern, $html, $matches);
        
        $styleUrls = array();
        foreach ($matches[1] as $match) {
            $styleUrls[] = $match;
        }
        
        return $styleUrls;
    }

    // Parsear fonts: https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face/src
    // static function fontExtractor($html, $exp_cache = null) {
    //     // ...
    // }

     /*
        Devuelve todas las reglas de CSS donde sobre determinadas clases
        buscando dentro un path

        Ej:

            $path        = 'D:\www\woo2\wp-content\themes\kadence\assets\css\slider.min.css'; //  <--- archivo
            $css_classes = ['tns-slider', 'tns-item'];

            $css_rules   = CSSUtils::getCSSRules($path, $css_classes)

        o...

        Ej:

            $path        = 'D:\www\woo2\wp-content\themes\kadence\assets\css';  // <---- directory
            $css_classes = ['tns-slider', 'tns-item'];

            $css_rules   = CSSUtils::getCSSRules($path, $css_classes);

        Resultado

            Array
            (
                [0] => .tns-slider {transition: all 0s;}
                [1] => .tns-slider>.tns-item {box-sizing: border-box;}
                [2] => .tns-horizontal.tns-subpixel>.tns-item {display: inline-block;vertical-align: top;white-space: normal;}
                [3] => .tns-horizontal.tns-no-subpixel>.tns-item {float: left;}
                [4] => .tns-horizontal.tns-carousel.tns-no-subpixel>.tns-item {margin-right: -100%;}
                [5] => .tns-gallery>.tns-item {position: absolute;left: -100%;transition: opacity 0s,-webkit-transform 0s;transition: transform 0s,opacity 0s;transition: transform 0s,opacity 0s,-webkit-transform 0s;}
            )
    */
    static function getCSSRules(string $path, array $css_classes) {        
        if (!Strings::endsWith('.css', $path)){
            if (!is_dir($path)){
                throw new \InvalidArgumentException("Path should be a .css file or directory containing .css file(s)");
            }

            $files = Files::recursiveGlob($path . DIRECTORY_SEPARATOR . '*.css');
        } else {
            $files = [ $path ];
        }

        $rules = [];
        foreach ($files as $path){
            Stdout::pprint("Processing $path ...");

            $css    = static::beautifier($path);
            Stdout::pprint("Beautification done");

            $_rules = Strings::lines($css, true);
                        
            foreach ($_rules as $ix => $rule){
                if (!Strings::containsAny($css_classes, $rule)){
                    unset($_rules[$ix]);
                }
            }

            $rules = array_merge($rules, array_values($_rules));
        }

        return $rules;
    }

    /*
        Crear dentro de package asi puedo incluir dependencias dentro
        y no a nivel de proyecto completo

        "sabberworm/php-css-parser": "^8.4"
        
        @param  string $css    CSS en si o la ruta al archivo .css

        Ej:

        $path = 'D:\www\woo2\wp-content\themes\kadence\assets\css\slider.min.css';

        dd(
            CSSUtils::beautifier($path)
        );
    */
    static function beautifier(string $css) {
        if (Url::isValid($css) || (Strings::endsWith('.css', $css) && Files::exists($css))){
            $css = file_get_contents($css);
        }

        $parser          = new Parser($css);

        $css            = $parser->parse();
        $desminifiedCSS = $css->render();

        return $desminifiedCSS;
    }

    static function removeCSSClasses(string $html, $classesToRemove = null) : string {
        if (!empty($classesToRemove)) {
            foreach ($classesToRemove as $class) {
                $html = str_replace(" $class ", ' ', $html);
                $html = str_replace(" $class\"", ' "', $html);
            }

            return $html;
        }

        return preg_replace_callback('/<[^<>]*\sclass=[\'"`][^\'"`]*[\'"`][^<>]*>/i', function($match) {
            return preg_replace('/\sclass=[\'"`][^\'"`]*[\'"`]/i', '', $match[0]);
        }, $html);        
    }

    static function removeCSS(string $page, bool $remove_style_sections = true, bool $remove_css_inline = true) : string
     {
		// Eliminar CSS entre etiquetas <style></style>
		if ($remove_style_sections) {
			$page = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $page);
		}
		
		// Eliminar CSS inline dentro del atributo style=""
		if ($remove_css_inline) {
			$page = preg_replace('/style="[^"]*"/i', '', $page);
		}

        /*
            Elimino class="" vacios
        */

        $page = str_replace('class=""', '', $page);     
		
		return $page;
	}

    /*
        Devuelve un array con todas las clases de CSS utilizadas en el documento

        Puede servir para:

        - Luego solo seleccionar archivos CSS que las contengan o las reglas en especifico
        - Determinar si se utiliza un framework como Bootstrap (col-??-??, etc)

        Usar en combinacion HTML::getIDs()
    */
    static function getCSSClasses(string $html) {
        $dom = XML::getDocument($html);

        $xpath = new \DOMXPath($dom);
        $classNodes = $xpath->query('//*[@class]');

        $cssClasses = array();
        foreach ($classNodes as $node) {
            $classes = explode(' ', $node->getAttribute('class'));
            $cssClasses = array_merge($cssClasses, $classes);
        }

        $cssClasses = array_unique($cssClasses);
        return $cssClasses;
    }

    
    static function removeBTClases($str){
        $classes = [
            0 => 'accordion-body',
            1 => 'accordion-button',
            2 => 'accordion-collapse',
            3 => 'accordion-flush',
            4 => 'accordion-header',
            5 => 'accordion-item',
            6 => 'collapsed',
            7 => 'alert-danger',
            8 => 'alert-dark',
            9 => 'alert-dismissible',
            10 => 'alert-heading',
            11 => 'alert-info',
            12 => 'alert-light',
            13 => 'alert-link',
            14 => 'alert-primary',
            15 => 'alert-secondary',
            16 => 'alert-success',
            17 => 'alert-warning',
            18 => 'fade',
            19 => 'badge',
            20 => 'badge-danger',
            21 => 'badge-dark',
            22 => 'badge-info',
            23 => 'badge-light',
            24 => 'badge-pill',
            25 => 'badge-primary',
            26 => 'badge-secondary',
            27 => 'badge-success',
            28 => 'badge-warning',
            29 => 'border',
            30 => 'border-*-0',
            31 => 'border-1',
            32 => 'border-danger',
            33 => 'border-dark',
            34 => 'border-info',
            35 => 'border-light',
            36 => 'border-primary',
            37 => 'border-secondary',
            38 => 'border-success',
            39 => 'border-warning',
            40 => 'border-white',
            41 => 'rounded',
            42 => 'rounded-*',
            43 => 'rounded-0',
            44 => 'rounded-1',
            45 => 'rounded-2',
            46 => 'rounded-3',
            47 => 'rounded-bottom',
            48 => 'rounded-circle',
            49 => 'rounded-end',
            50 => 'rounded-pill',
            51 => 'rounded-start',
            52 => 'rounded-top',
            53 => 'breadcrumb',
            54 => 'breadcrumb-item',
            55 => 'btn-group',
            56 => 'btn-group (nested)',
            57 => 'btn-group-lg',
            58 => 'btn-group-sm',
            59 => 'btn-group-vertical',
            60 => 'btn-toolbar',
            61 => 'active button',
            62 => 'btn-block',
            63 => 'btn-lg',
            64 => 'btn-sm',
            65 => 'checkbox as button',
            66 => 'disabled button',
            67 => 'radio as button',
            68 => 'btn',
            69 => 'btn-close',
            70 => 'btn-close-white',
            71 => 'btn-danger',
            72 => 'btn-dark',
            73 => 'btn-info',
            74 => 'btn-light',
            75 => 'btn-link',
            76 => 'btn-outline-danger',
            77 => 'btn-outline-dark',
            78 => 'btn-outline-info',
            79 => 'btn-outline-light',
            80 => 'btn-outline-primary',
            81 => 'btn-outline-secondary',
            82 => 'btn-outline-success',
            83 => 'btn-outline-warning',
            84 => 'btn-primary',
            85 => 'btn-secondary',
            86 => 'btn-success',
            87 => 'btn-warning',
            88 => 'card',
            89 => 'card bg-... text-...',
            90 => 'card-body',
            91 => 'card-columns',
            92 => 'card-deck',
            93 => 'card-footer',
            94 => 'card-group',
            95 => 'card-header',
            96 => 'card-header-pills',
            97 => 'card-header-tabs',
            98 => 'card-img-bottom',
            99 => 'card-img-overlay',
            100 => 'card-img-top',
            101 => 'card-link',
            102 => 'card-subtitle',
            103 => 'card-text',
            104 => 'card-title',
            105 => 'h*.card-header',
            106 => 'list-group',
            107 => 'middle image',
            108 => 'stretched-link',
            109 => 'carousel slide',
            110 => 'carousel-caption',
            111 => 'carousel-control-next',
            112 => 'carousel-control-next-icon',
            113 => 'carousel-control-prev',
            114 => 'carousel-control-prev-icon',
            115 => 'carousel-dark',
            116 => 'carousel-fade',
            117 => 'carousel-indicators',
            118 => 'carousel-inner',
            119 => 'carousel-item',
            120 => 'accordion',
            121 => 'collapse',
            122 => 'bg-body',
            123 => 'bg-danger',
            124 => 'bg-dark',
            125 => 'bg-gradient',
            126 => 'bg-info',
            127 => 'bg-light',
            128 => 'bg-primary',
            129 => 'bg-secondary',
            130 => 'bg-success',
            131 => 'bg-transparent',
            132 => 'bg-warning',
            133 => 'bg-white',
            134 => 'link-danger',
            135 => 'link-dark',
            136 => 'link-info',
            137 => 'link-light',
            138 => 'link-primary',
            139 => 'link-secondary',
            140 => 'link-success',
            141 => 'link-warning',
            142 => 'text-danger',
            143 => 'text-dark',
            144 => 'text-info',
            145 => 'text-light',
            146 => 'text-primary',
            147 => 'text-secondary',
            148 => 'text-success',
            149 => 'text-warning',
            150 => 'text-white',
            151 => 'custom-checkbox',
            152 => 'custom-file',
            153 => 'custom-radio',
            154 => 'custom-range',
            155 => 'custom-select',
            156 => 'custom-switch',
            157 => 'd-*-block',
            158 => 'd-*-flex',
            159 => 'd-*-inline',
            160 => 'd-*-inline-block',
            161 => 'd-*-inline-flex',
            162 => 'd-*-none',
            163 => 'd-*-table',
            164 => 'd-*-table-cell',
            165 => 'd-grid',
            166 => 'd-lg-grid',
            167 => 'd-lg-table-row',
            168 => 'd-md-grid',
            169 => 'd-md-table-row',
            170 => 'd-print-...',
            171 => 'd-print-flex',
            172 => 'd-print-grid',
            173 => 'd-print-inline-flex',
            174 => 'd-print-table',
            175 => 'd-print-table-cell',
            176 => 'd-print-table-row',
            177 => 'd-sm-grid',
            178 => 'd-sm-table-row',
            179 => 'd-table-row',
            180 => 'd-xl-grid',
            181 => 'd-xl-table-row',
            182 => 'd-xxl-block',
            183 => 'd-xxl-flex',
            184 => 'd-xxl-grid',
            185 => 'd-xxl-inline',
            186 => 'd-xxl-inline-block',
            187 => 'd-xxl-inline-flex',
            188 => 'd-xxl-none',
            189 => 'd-xxl-table',
            190 => 'd-xxl-table-cell',
            191 => 'd-xxl-table-row',
            192 => 'dropdown',
            193 => 'dropdown (split)',
            194 => 'dropdown-divider',
            195 => 'dropdown-header',
            196 => 'dropdown-item',
            197 => 'dropdown-item disabled',
            198 => 'dropdown-item-text',
            199 => 'dropdown-menu',
            200 => 'dropdown-menu-dark',
            201 => 'dropdown-menu-end',
            202 => 'dropdown-menu-lg-end',
            203 => 'dropdown-menu-lg-start',
            204 => 'dropdown-menu-md-end',
            205 => 'dropdown-menu-md-start',
            206 => 'dropdown-menu-right',
            207 => 'dropdown-menu-sm-end',
            208 => 'dropdown-menu-sm-start',
            209 => 'dropdown-menu-start',
            210 => 'dropdown-menu-xl-end',
            211 => 'dropdown-menu-xl-start',
            212 => 'dropdown-toggle',
            213 => 'dropdown-toggle-split',
            214 => 'dropleft',
            215 => 'dropright',
            216 => 'dropup',
            217 => 'dropup (split)',
            218 => 'figure',
            219 => 'figure-caption',
            220 => 'figure-img',
            221 => 'align-content-*-around',
            222 => 'align-content-*-center',
            223 => 'align-content-*-end',
            224 => 'align-content-*-start',
            225 => 'align-content-*-stretch',
            226 => 'align-items-*-baseline',
            227 => 'align-items-*-center',
            228 => 'align-items-*-end',
            229 => 'align-items-*-start',
            230 => 'align-items-*-stretch',
            231 => 'align-self-*-baseline',
            232 => 'align-self-*-center',
            233 => 'align-self-*-end',
            234 => 'align-self-*-start',
            235 => 'align-self-*-stretch',
            236 => 'flex-*-column',
            237 => 'flex-*-column-reverse',
            238 => 'flex-*-grow-0',
            239 => 'flex-*-grow-1',
            240 => 'flex-*-nowrap',
            241 => 'flex-*-row',
            242 => 'flex-*-row-reverse',
            243 => 'flex-*-shrink-0',
            244 => 'flex-*-shrink-1',
            245 => 'flex-*-wrap',
            246 => 'flex-*-wrap-reverse',
            247 => 'flex-fill',
            248 => 'flex-lg-fill',
            249 => 'flex-md-fill',
            250 => 'flex-sm-fill',
            251 => 'flex-xl-fill',
            252 => 'flex-xxl-column',
            253 => 'flex-xxl-column-reverse',
            254 => 'flex-xxl-fill',
            255 => 'flex-xxl-grow-0',
            256 => 'flex-xxl-grow-1',
            257 => 'flex-xxl-nowrap',
            258 => 'flex-xxl-row',
            259 => 'flex-xxl-row-reverse',
            260 => 'flex-xxl-shrink-0',
            261 => 'flex-xxl-shrink-1',
            262 => 'flex-xxl-wrap',
            263 => 'flex-xxl-wrap-reverse',
            264 => 'justify-content-*-around',
            265 => 'justify-content-*-between',
            266 => 'justify-content-*-center',
            267 => 'justify-content-*-end',
            268 => 'justify-content-*-start',
            269 => 'justify-content-around',
            270 => 'justify-content-between',
            271 => 'justify-content-center',
            272 => 'justify-content-end',
            273 => 'justify-content-evenly',
            274 => 'justify-content-lg-around',
            275 => 'justify-content-lg-between',
            276 => 'justify-content-lg-center',
            277 => 'justify-content-lg-end',
            278 => 'justify-content-lg-evenly',
            279 => 'justify-content-lg-start',
            280 => 'justify-content-md-around',
            281 => 'justify-content-md-between',
            282 => 'justify-content-md-center',
            283 => 'justify-content-md-end',
            284 => 'justify-content-md-evenly',
            285 => 'justify-content-md-start',
            286 => 'justify-content-sm-around',
            287 => 'justify-content-sm-between',
            288 => 'justify-content-sm-center',
            289 => 'justify-content-sm-end',
            290 => 'justify-content-sm-evenly',
            291 => 'justify-content-sm-start',
            292 => 'justify-content-start',
            293 => 'justify-content-xl-around',
            294 => 'justify-content-xl-between',
            295 => 'justify-content-xl-center',
            296 => 'justify-content-xl-end',
            297 => 'justify-content-xl-evenly',
            298 => 'justify-content-xl-start',
            299 => 'justify-content-xxl-around',
            300 => 'justify-content-xxl-between',
            301 => 'justify-content-xxl-center',
            302 => 'justify-content-xxl-end',
            303 => 'justify-content-xxl-evenly',
            304 => 'justify-content-xxl-start',
            305 => 'order-*-#',
            306 => 'order-0',
            307 => 'order-1',
            308 => 'order-first',
            309 => 'order-last',
            310 => 'order-lg-0',
            311 => 'order-lg-first',
            312 => 'order-lg-last',
            313 => 'order-md-0',
            314 => 'order-md-first',
            315 => 'order-md-last',
            316 => 'order-sm-0',
            317 => 'order-sm-first',
            318 => 'order-sm-last',
            319 => 'order-xl-0',
            320 => 'order-xl-first',
            321 => 'order-xl-last',
            322 => 'order-xxl-0',
            323 => 'order-xxl-first',
            324 => 'order-xxl-last',
            325 => 'checkbox',
            326 => 'dropdown',
            327 => 'input-group',
            328 => 'input-group-append',
            329 => 'input-group-lg',
            330 => 'input-group-prepend',
            331 => 'input-group-sm',
            332 => 'radio',
            333 => 'segmented buttons',
            334 => 'form (full example)',
            335 => 'col-form-label',
            336 => 'col-form-label-lg',
            337 => 'col-form-label-sm',
            338 => 'disabled items',
            339 => 'form using the grid',
            340 => 'form-check',
            341 => 'form-check-inline',
            342 => 'form-check-input',
            343 => 'form-check-label',
            344 => 'form-control',
            345 => 'form-control-color',
            346 => 'form-control-file',
            347 => 'form-control-lg',
            348 => 'form-control-plaintext',
            349 => 'form-control-range',
            350 => 'form-control-sm',
            351 => 'form-floating',
            352 => 'form-group',
            353 => 'form-inline',
            354 => 'form-label',
            355 => 'form-select',
            356 => 'form-select-lg',
            357 => 'form-select-sm',
            358 => 'form-switch',
            359 => 'form-text',
            360 => 'input-group-text',
            361 => 'is-invalid',
            362 => 'is-valid',
            363 => 'readonly',
            364 => 'valid-feedback',
            365 => 'valid-tooltip',
            366 => 'col',
            367 => 'col-*',
            368 => 'col-# (&lt;576px)',
            369 => 'col-1',
            370 => 'col-10',
            371 => 'col-11',
            372 => 'col-12',
            373 => 'col-2',
            374 => 'col-3',
            375 => 'col-4',
            376 => 'col-5',
            377 => 'col-6',
            378 => 'col-7',
            379 => 'col-8',
            380 => 'col-9',
            381 => 'col-auto',
            382 => 'col-lg-# (≥992px)',
            383 => 'col-lg-1',
            384 => 'col-md-# (≥768px)',
            385 => 'col-md-1',
            386 => 'col-sm-# (≥576px)',
            387 => 'col-sm-1',
            388 => 'col-xl-# (≥1200px)',
            389 => 'col-xl-1',
            390 => 'col-xxl-1',
            391 => 'container',
            392 => 'container-fluid',
            393 => 'container-sm',
            394 => 'contanier-lg',
            395 => 'contanier-md',
            396 => 'contanier-xl',
            397 => 'contanier-xxl',
            398 => 'g-0',
            399 => 'g-lg-0',
            400 => 'g-md-0',
            401 => 'g-sm-0',
            402 => 'g-xl-0',
            403 => 'g-xxl-0',
            404 => 'gap-0',
            405 => 'gap-lg-0',
            406 => 'gap-md-0',
            407 => 'gap-sm-0',
            408 => 'gap-xl-0',
            409 => 'gap-xxl-0',
            410 => 'gx-0',
            411 => 'gx-0',
            412 => 'gx-lg-0',
            413 => 'gx-lg-0',
            414 => 'gx-md-0',
            415 => 'gx-md-0',
            416 => 'gx-sm-0',
            417 => 'gx-sm-0',
            418 => 'gx-xl-0',
            419 => 'gx-xl-0',
            420 => 'gx-xxl-0',
            421 => 'gy-xxl-0',
            422 => 'nested columns',
            423 => 'no-gutters',
            424 => 'offset-*-#',
            425 => 'offset-0',
            426 => 'offset-lg-0',
            427 => 'offset-md-0',
            428 => 'offset-xxl-0',
            429 => 'order-#',
            430 => 'row',
            431 => 'row-cols-1',
            432 => 'row-cols-auto',
            433 => 'row-cols-lg-1',
            434 => 'row-cols-lg-auto',
            435 => 'row-cols-md-1',
            436 => 'row-cols-md-auto',
            437 => 'row-cols-sm-1',
            438 => 'row-cols-sm-auto',
            439 => 'row-cols-xl-1',
            440 => 'row-cols-xl-auto',
            441 => 'row-cols-xxl-1',
            442 => 'row-cols-xxl-auto',
            443 => 'img-fluid',
            444 => 'img-thumbnail',
            445 => 'jumbotron',
            446 => 'jumbotron-fluid',
            447 => 'list-group',
            448 => 'list-group with badges',
            449 => 'list-group with d-flex',
            450 => 'list-group-item active',
            451 => 'list-group-item disabled',
            452 => 'list-group-item-action',
            453 => 'list-group-item-danger',
            454 => 'list-group-item-dark',
            455 => 'list-group-item-info',
            456 => 'list-group-item-light',
            457 => 'list-group-item-primary',
            458 => 'list-group-item-secondary',
            459 => 'list-group-item-success',
            460 => 'list-group-item-warning',
            461 => 'list-group-flush',
            462 => 'list-group-horizontal',
            463 => 'list-group-horizontal-lg',
            464 => 'list-group-horizontal-md',
            465 => 'list-group-horizontal-sm',
            466 => 'list-group-horizontal-xl',
            467 => 'list-group-horizontal-xxl',
            468 => 'list-group-item',
            469 => 'd-flex align-self-center',
            470 => 'd-flex align-self-end',
            471 => 'd-flex align-self-start',
            472 => 'media',
            473 => 'nested media',
            474 => 'right aligned media',
            475 => 'close',
            476 => 'embed-responsive',
            477 => 'initialism',
            478 => 'invisible',
            479 => 'overflow-auto',
            480 => 'overflow-hidden',
            481 => 'overflow-scroll',
            482 => 'overflow-visible',
            483 => 'pe-auto',
            484 => 'pe-none',
            485 => 'shadow',
            486 => 'shadow-lg',
            487 => 'shadow-none',
            488 => 'shadow-sm',
            489 => 'sr-only',
            490 => 'sr-only-focusable',
            491 => 'visible',
            492 => 'visually-hidden',
            493 => 'visually-hidden-focusable',
            494 => 'modal',
            495 => 'modal fade',
            496 => 'modal-dialog-centered',
            497 => 'modal-lg',
            498 => 'modal-sm',
            499 => 'modal-xl',
            500 => 'modal-body',
            501 => 'modal-contant',
            502 => 'modal-dialog',
            503 => 'modal-dialog-scrollable',
            504 => 'modal-footer',
            505 => 'modal-fullscreen',
            506 => 'modal-fullscreen-lg-down',
            507 => 'modal-fullscreen-md-down',
            508 => 'modal-fullscreen-sm-down',
            509 => 'modal-fullscreen-xl-down',
            510 => 'modal-fullscreen-xxl-down',
            511 => 'modal-header',
            512 => 'modal-static',
            513 => 'modal-title',
            514 => 'collapse navbar-collapse',
            515 => 'nav-item',
            516 => 'nav-link',
            517 => 'navbar',
            518 => 'navbar fixed-bottom',
            519 => 'navbar fixed-top',
            520 => 'navbar sticky-top',
            521 => 'navbar with form',
            522 => 'navbar-brand',
            523 => 'navbar-collapse',
            524 => 'navbar-dark',
            525 => 'navbar-dark bg-dark',
            526 => 'navbar-expand-*',
            527 => 'navbar-expand-lg',
            528 => 'navbar-expand-md',
            529 => 'navbar-expand-sm',
            530 => 'navbar-expand-xl',
            531 => 'navbar-expand-xxl',
            532 => 'navbar-light',
            533 => 'navbar-nav',
            534 => 'navbar-text',
            535 => 'navbar-toggler',
            536 => 'navbar-toggler-icon',
            537 => 'nav flex-column',
            538 => 'nav justify-content-*',
            539 => 'nav with flex utils',
            540 => 'nav-fill',
            541 => 'nav-justified',
            542 => 'nav-pills',
            543 => 'nav-pills with dropdown',
            544 => 'nav-tabs',
            545 => 'nav-tabs with dropdown',
            546 => 'nav.nav',
            547 => 'tab-content',
            548 => 'tab-pane',
            549 => 'ul.nav',
            550 => 'page-item active',
            551 => 'page-item disabled',
            552 => 'pagination',
            553 => 'pagination-lg',
            554 => 'pagination-sm',
            555 => 'dismissible popover',
            556 => 'popovers',
            557 => 'align-*',
            558 => 'bottom-0',
            559 => 'bottom-100',
            560 => 'bottom-50',
            561 => 'clearfix',
            562 => 'end-0',
            563 => 'end-100',
            564 => 'end-50',
            565 => 'fixed-bottom',
            566 => 'fixed-top',
            567 => 'float-*-left',
            568 => 'float-*-none',
            569 => 'float-*-right',
            570 => 'float-end',
            571 => 'float-lg-end',
            572 => 'float-lg-none',
            573 => 'float-lg-start',
            574 => 'float-md-end',
            575 => 'float-md-none',
            576 => 'float-md-start',
            577 => 'float-none',
            578 => 'float-sm-end',
            579 => 'float-sm-none',
            580 => 'float-sm-start',
            581 => 'float-start',
            582 => 'float-xl-end',
            583 => 'float-xl-none',
            584 => 'float-xl-start',
            585 => 'float-xxl-end',
            586 => 'float-xxl-none',
            587 => 'float-xxl-start',
            588 => 'position-absolute',
            589 => 'position-relative',
            590 => 'position-static',
            591 => 'start-0',
            592 => 'start-100',
            593 => 'start-50',
            594 => 'sticky-lg-top',
            595 => 'sticky-md-top',
            596 => 'sticky-sm-top',
            597 => 'sticky-top',
            598 => 'sticky-xl-top',
            599 => 'top-0',
            600 => 'top-100',
            601 => 'top-50',
            602 => 'translate-middle',
            603 => 'multiple progress-bar',
            604 => 'progress',
            605 => 'progress-bar',
            606 => 'progress-bar bg-*',
            607 => 'progress-bar with height',
            608 => 'progress-bar with label',
            609 => 'progress-bar-animated',
            610 => 'progress-bar-striped',
            611 => 'progress-bar-striped bg-*',
            612 => 'data-spy',
            613 => 'h-100',
            614 => 'h-25',
            615 => 'h-50',
            616 => 'h-75',
            617 => 'h-auto',
            618 => 'mh-100',
            619 => 'min-vw-100',
            620 => 'mw-100',
            621 => 'w-100',
            622 => 'w-100',
            623 => 'w-25',
            624 => 'w-50',
            625 => 'w-75',
            626 => 'w-auto',
            627 => 'm-1 / m-*-#',
            628 => 'm-auto',
            629 => 'm-lg-0',
            630 => 'm-lg-auto',
            631 => 'm-md-0',
            632 => 'm-md-auto',
            633 => 'm-n1 / m-*-n#',
            634 => 'm-sm-0',
            635 => 'm-sm-auto',
            636 => 'm-xl-0',
            637 => 'm-xl-auto',
            638 => 'm-xxl-0',
            639 => 'm-xxl-auto',
            640 => 'mb-1 / mb-*-#',
            641 => 'mb-auto',
            642 => 'mb-lg-0',
            643 => 'mb-lg-auto',
            644 => 'mb-md-0',
            645 => 'mb-md-auto',
            646 => 'mb-sm-0',
            647 => 'mb-sm-auto',
            648 => 'mb-xl-0',
            649 => 'mb-xl-auto',
            650 => 'mb-xxl-0',
            651 => 'mb-xxl-auto',
            652 => 'me-auto',
            653 => 'me-lg-0',
            654 => 'me-lg-auto',
            655 => 'me-md-0',
            656 => 'me-md-auto',
            657 => 'me-sm-0',
            658 => 'me-sm-auto',
            659 => 'me-xl-0',
            660 => 'me-xl-auto',
            661 => 'me-xxl-0',
            662 => 'me-xxl-auto',
            663 => 'ml-1 / ml-*-#',
            664 => 'mr-1 / mr-*-#',
            665 => 'ms-auto',
            666 => 'ms-lg-0',
            667 => 'ms-lg-auto',
            668 => 'ms-md-0',
            669 => 'ms-md-auto',
            670 => 'ms-sm-0',
            671 => 'ms-sm-auto',
            672 => 'ms-xl-0',
            673 => 'ms-xl-auto',
            674 => 'ms-xxl-0',
            675 => 'ms-xxl-auto',
            676 => 'mt-1 / mt-*-#',
            677 => 'mt-auto',
            678 => 'mt-lg-0',
            679 => 'mt-lg-auto',
            680 => 'mt-md-0',
            681 => 'mt-md-auto',
            682 => 'mt-sm-0',
            683 => 'mt-sm-auto',
            684 => 'mt-xl-0',
            685 => 'mt-xl-auto',
            686 => 'mt-xxl-0',
            687 => 'mt-xxl-auto',
            688 => 'mx-1 / mx-*-#',
            689 => 'mx-auto',
            690 => 'mx-lg-0',
            691 => 'mx-lg-auto',
            692 => 'mx-md-0',
            693 => 'mx-md-auto',
            694 => 'mx-sm-0',
            695 => 'mx-sm-auto',
            696 => 'mx-xl-0',
            697 => 'mx-xl-auto',
            698 => 'mx-xxl-0',
            699 => 'mx-xxl-auto',
            700 => 'my-1 / my-*-#',
            701 => 'my-auto',
            702 => 'my-lg-0',
            703 => 'my-lg-auto',
            704 => 'my-md-0',
            705 => 'my-md-auto',
            706 => 'my-sm-0',
            707 => 'my-sm-auto',
            708 => 'my-xl-0',
            709 => 'my-xl-auto',
            710 => 'my-xxl-0',
            711 => 'my-xxl-auto',
            712 => 'p-1 / p-*-#',
            713 => 'p-lg-0',
            714 => 'p-md-0',
            715 => 'p-sm-0',
            716 => 'p-xl-0',
            717 => 'p-xxl-0',
            718 => 'pb-0',
            719 => 'pb-1 / pb-*-#',
            720 => 'pb-lg-0',
            721 => 'pb-md-0',
            722 => 'pb-sm-0',
            723 => 'pb-xl-0',
            724 => 'pb-xxl-0',
            725 => 'pe-0',
            726 => 'pe-lg-0',
            727 => 'pe-md-0',
            728 => 'pe-sm-0',
            729 => 'pe-xl-0',
            730 => 'pe-xxl-0',
            731 => 'pl-1 / pl-*-#',
            732 => 'pr-1 / pr-*-#',
            733 => 'ps-0',
            734 => 'ps-lg-0',
            735 => 'ps-md-0',
            736 => 'ps-sm-0',
            737 => 'ps-xl-0',
            738 => 'ps-xxl-0',
            739 => 'pt-0',
            740 => 'pt-1 / pt-*-#',
            741 => 'pt-lg-0',
            742 => 'pt-md-0',
            743 => 'pt-sm-0',
            744 => 'pt-xl-0',
            745 => 'pt-xxl-0',
            746 => 'px-0',
            747 => 'px-1 / px-*-#',
            748 => 'px-lg-0',
            749 => 'px-md-0',
            750 => 'px-sm-0',
            751 => 'px-xl-0',
            752 => 'px-xxl-0',
            753 => 'py-0',
            754 => 'py-1 / py-*-#',
            755 => 'py-lg-0',
            756 => 'py-md-0',
            757 => 'py-sm-0',
            758 => 'py-xl-0',
            759 => 'py-xxl-0',
            760 => 'spinner-border',
            761 => 'spinner-border text-*',
            762 => 'spinner-border-sm',
            763 => 'spinner-grow',
            764 => 'spinner-grow text-*',
            765 => 'spinner-grow-sm',
            766 => 'caption-top',
            767 => 'table',
            768 => 'table-*-responsive',
            769 => 'table-active',
            770 => 'table-bordered',
            771 => 'table-borderless',
            772 => 'table-danger',
            773 => 'table-dark',
            774 => 'table-hover',
            775 => 'table-info',
            776 => 'table-light',
            777 => 'table-primary',
            778 => 'table-reflow',
            779 => 'table-responsive-xxl',
            780 => 'table-secondary',
            781 => 'table-sm',
            782 => 'table-striped',
            783 => 'table-success',
            784 => 'table-warning',
            785 => 'thead-dark',
            786 => 'thead-light',
            787 => 'font-italic',
            788 => 'font-weight-bold',
            789 => 'font-weight-bolder',
            790 => 'font-weight-light',
            791 => 'font-weight-lighter',
            792 => 'font-weight-normal',
            793 => 'text-*-center',
            794 => 'text-*-left',
            795 => 'text-*-right',
            796 => 'text-black-50',
            797 => 'text-body',
            798 => 'text-capitalize',
            799 => 'text-decoration-none',
            800 => 'text-hide',
            801 => 'text-justify',
            802 => 'text-lowercase',
            803 => 'text-monospace',
            804 => 'text-muted',
            805 => 'text-nowrap',
            806 => 'text-truncate',
            807 => 'text-uppercase',
            808 => 'text-white-50',
            809 => 'toast-body',
            810 => 'toast-header',
            811 => 'toast',
            812 => 'tooltip',
            813 => 'blockquote',
            814 => 'blockquote-footer',
            815 => 'blockquote-reverse',
            816 => 'display-# (1-4)',
            817 => 'display-1',
            818 => 'display-2',
            819 => 'display-3',
            820 => 'display-4',
            821 => 'display-5',
            822 => 'display-6',
            823 => 'dl-horizontal',
            824 => 'font-monospace',
            825 => 'fs-1',
            826 => 'fs-2',
            827 => 'fs-3',
            828 => 'fs-4',
            829 => 'fs-4',
            830 => 'fs-5',
            831 => 'fs-6',
            832 => 'fs-lg-1',
            833 => 'fs-md-1',
            834 => 'fs-sm-1',
            835 => 'fst-italic',
            836 => 'fst-normal',
            837 => 'fw-bolder',
            838 => 'fw-light',
            839 => 'fw-lighter',
            840 => 'fw-normal',
            841 => 'h1',
            842 => 'h2',
            843 => 'h3',
            844 => 'h4',
            845 => 'h5',
            846 => 'h6',
            847 => 'lead',
            848 => 'lh-1',
            849 => 'lh-base',
            850 => 'lh-lg',
            851 => 'lh-sm',
            852 => 'list-inline',
            853 => 'list-unstyled',
            854 => 'text-break',
            855 => 'text-center',
            856 => 'text-decoration-line-through',
            857 => 'text-decoration-underline',
            858 => 'text-end',
            859 => 'text-lg-center',
            860 => 'text-lg-end',
            861 => 'text-lg-start',
            862 => 'text-md-center',
            863 => 'text-md-end',
            864 => 'text-md-start',
            865 => 'text-reset',
            866 => 'text-sm-center',
            867 => 'text-sm-end',
            868 => 'text-sm-start',
            869 => 'text-start',
            870 => 'text-wrap',
            871 => 'text-xl-center',
            872 => 'text-xl-end',
            873 => 'text-xl-end',
            874 => 'text-xl-end',
            875 => 'text-xl-start',
            876 => 'text-xl-start',
            877 => 'text-xl-start',
            878 => 'text-xxl-center',
            879 => 'text-xxl-end',
            880 => 'text-xxl-start',
        ];

        foreach ($classes as $class){
            $str = str_replace($class, '', $str);
        }    

        return $str;
    }

}

