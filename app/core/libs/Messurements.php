<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
    Unit conversion library

    @author Pablo Bozzolo <boctulus@gmail.com>
*/

class Messurements
{
    static function toInches(float $feets, $inches = 0) {
        $feet_into_inches = (float) $feets * 12;
        $inches           = (float) $inches;
       
        return $feet_into_inches + $inches;
    }

    static function toFeet(float $inches){
        return $inches / 12;
    }

    static function toFeetAndInches(float $inches) {
        $div = $inches / 12;
        $feets  = floor($div);
        $inches = floor(($div - $feets) * 12);

        return ($inches == 0) ? "$feets'" : "$feets'$inches''";
    }
}

