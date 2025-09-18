<?php

/*
    @author Pablo Bozzolo < boctulus@gmail.com >
*/

namespace boctulus\TutorNewCourses\core\libs;

class Installables
{
    const NONE   = null;
    const PLUGIN = 1;
    const THEME  = 2;

    static function getType(string $path){
        if (Templates::isTheme($path)){
            return static::THEME;
        }

        if (Plugins::isPlugin($path)){
            return static::PLUGIN;
        }

        return static::NONE;
    }

}