<?php declare(strict_types=1);

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Strings;

/*
    @author Pablo Bozzolo < boctulus@gmail.com >

    De momento no clasifica las palabras y por tanto no maneja plurales que afectarian
    a distinto tipo de palabras (tipicamente sustantivos) segun el idioma
*/

class LangDetector
{
    protected static $common_words = [
        'en' => [
            'in', 'on', 'at', 'to', 'for', 'by', 'with', 'from', 'about', 'against', 'between',
            'through', 'during', 'before', 'after', 'above', 'below', 'over', 'under', 'down', 
            'up', 'with', 'of', 'and', 'or', 'best', 'only', 'is', 'are', 'will', 'able', 'have', 'had', 
            'get', 'that', 'most', 'was', 'were', 'each', 'more', 'less', 'make', 'made', 
            'does', 'like', 'your', 'been', 'all', 'going', 'into', 'take', 'since', 'buy', 'sell', 
            'but', 'this', 'that', 'free', 'type', 'allow', 'allows', 'solution', 'add', 'adds',
            'which', 'where', 'who', 'how', 'if',
            'big', 'small', 'very', 'give', 'gives'
        ],

        'es' => [
            'bajo', 'con', 'contra', 'desde', 'hacia', 'hasta', 'para', 'mediante', 'una', 'este', 
            'del', 'esta', 'como', 'menos', 'cierto', 'gratis', 'más', 'fácil', 'crear', 'cantidad', 'solo',
            'muy', 'mucho', 'mucha', 'muchos', 'muchas', 'tipo', 'cualquier', 'cualquiera', 'poder', 'hacer',
            'casi', 'poco', 'crecer', 'gran', 'sitio', 'puede', 'permite', 'permitir', 'y', 'o', 'de', 'del',
            'que', 'cual', 'quien', 'donde', 'cuando', 'si', 'su', 'sus'
        ]
    ];

    protected static $common_word_groups = [
        'en' => [
            'is an', 'is a', 'are the', 'middle of', 'kind of', 'type of', 'for free', 'for any', 'is the', 
            'are the', 'it is', "it's"
        ],

        'es' => [
            'es un', 'es una', 'son las', 'es la', 'por lo tanto', 'por medio', 'en la', 'en el',
            'de la', 'de los', 'de las', 'con una', 'con unas', 'que le', 'que les', 'tipo de', 'ya que'
        ]
    ];

    static function langs(){
        return array_keys(static::$common_words);
    }

    /*
        Retorna un score de 0 a 100 con una "probabilidad" estimada
        de que el texto este escrito en cierto idioma

        Luego se puede escribir una regla de desicion adaptada al contexto

        Ej:

        $en_score = LangDetector::is($str, 'en');
        $es_score = LangDetector::is($str, 'es');

        if ($es_score == 0 && $en_score == 0){
            $lang = '??';
        } else {
            if ($es_score >= $en_score){
                $lang = 'es';
            } elseif ($en_score > $es_score) {
                $lang = 'en';
            } 
        }          
    */
    static function is(string $str, string $lang){
        if (!in_array($lang, static::langs())){
            throw new \InvalidArgumentException("Language is not supported");
        }

        $score = 0;
        foreach(static::$common_word_groups[$lang] as $word){
            if (Strings::contains($word, $str, false)){
                $score += 49;
            }
        }

        $word_count = Strings::wordsCount($str);

        $word_score = 0;
        foreach(static::$common_words[$lang] as $word){
            if (Strings::containsWord($word, $str, false)){
                $word_score += 1;
            }
        }

        $score += round(100 * ($word_score / $word_count));
        
        return min($score, 100);
    } 


}


