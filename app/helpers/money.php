<?php //declare(strict_types = 1);

function my_round($val)
{
    $config = config();

    //if ($config['decimals'] != null){
        if ($config['decimals'] === 0){
            $val = (int) $val;
        } else {
            $val =  number_format($val, $config['decimals'], '.', '');
        }
    //}    

    return $val;
};

function gain($per_gain, $price){
    $config = config();

    return $config['gain_formula']($per_gain, $price);
}