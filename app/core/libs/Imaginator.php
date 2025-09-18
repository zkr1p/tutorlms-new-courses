<?php

namespace boctulus\TutorNewCourses\core\libs;

/*
    Utilities for GD lib

    By Pablo Bozzolo
*/
class Imaginator
{
    protected $w;
    protected $h;
    protected $im;
    protected $image_format;
    protected $foreground_color_name;
    protected $background_color_name;
    protected $colors = [];
    protected $shapes = [];
    protected $filter;

    protected static $rendering = true;

    static function disable(){
        static::$rendering = false;
    }

    function __construct($w, $h){
        $this->w = $w;
        $this->h = $h;

        $this->im = imagecreatetruecolor($w, $h);
    }

    function getImage(){
        return $this->im;
    }

    /*
        @param ?int $transparency de 0 (opaco) a 127 (totalmente transparente)
    */
    function createColor($name, $r, $g, $b, $transparency = null){
        if ($transparency){
            // Habilita la transparencia
            imagesavealpha($this->im, true);

            $this->colors[$name] = imagecolorallocatealpha($this->im, $r, $g, $b, $transparency);

            return;
        }

        $this->colors[$name] = imagecolorallocate($this->im, $r, $g, $b);
    }

    protected function __color($color){
        if (is_string($color)){
            $color = $this->colors[$color];
        }

        return $color;
    }

    function setForegroundColor(string $color_name){
        $this->foreground_color_name = $color_name;
    }

    /*
        Devuelve el foreground color por defecto
        o el primer color que encuentra distinto del de background
    */
    function getForegroundColorName(){
        if ($this->foreground_color_name != null){
            return $this->foreground_color_name;
        }

        foreach (array_keys($this->colors) as $color_name){
            if ($color_name != $this->background_color_name){
                $this->foreground_color_name = $color_name;                
                return $color_name;
            }
        }           
    }

    function getBackgroundColorName(){
        return $this->background_color_name;
    }

    function getForegroundColor(){
        return $this->colors[$this->getForegroundColorName()];
    }

    function getBackgroundColor(){
        return $this->colors[$this->getBackgroundColorName()];
    }

    function setBackgroundColor($color){
        $this->background_color_name = $color;
        imagefill($this->im, 0, 0, $this->__color($color));
    }

    function invertColors(){
        // Aplicar el filtro para invertir colores
        $this->filter = IMG_FILTER_NEGATE;
    }

    function render($image_format = 'png'){
        if (!in_array($image_format, ['png', 'gif', 'jpeg'])){
            throw new \InvalidArgumentException("Invalid image file format");
        }

        if ($this->filter !== null){
            imagefilter($this->im, $this->filter);
        }

        $image_format = $this->image_format ?? $image_format;

        if (static::$rendering === false){
            return;
        }

        $fn = "image{$image_format}";

        // Enviar imagen al navegador
        header('Content-Type: image/' . $image_format);
        $fn($this->im);

        // Liberar memoria
        imagedestroy($this->im); 
    }

    /**
     * Carga una fuente GDF de texto para su uso en funciones de dibujo de GD.
     *
     * @param string $path Ruta del archivo de fuente.
     *
     * @return resource|false Devuelve el recurso de fuente si se carga correctamente, o false si hay un error.
     *
     * @throws \Exception Si el archivo de fuente no se encuentra en la ruta especificada.
     * @throws \Exception Si el formato de archivo de fuente no es compatible 
     */
    function loadFont($path){
        if (!file_exists($path)){
            throw new \Exception("Font not found in '$path'");
        }

        $ext = Files::getExtension($path);

        switch ($ext){
            case 'gdf':
                $fn = 'imageloadfont';
                break;
            // ...
            default:
                throw new \Exception("Unsupported font file format: $ext");
        }

        return $fn($path);
    }

    /**
     * Imprime texto en la imagen usando fuentes integradas o fuentes cargadas desde archivos TTF.
     *
     * @param int    $x       La coordenada x de la posición del texto.
     * @param int    $y       La coordenada y de la posición del texto.
     * @param mixed  $text    El texto que se va a imprimir.
     * @param mixed  $color_name El nombre del color del texto (opcional).
     * @param mixed  $font    Puede ser un número del 1 al 5 para fuentes integradas en codificación latin2 (donde números más altos corresponden a fuentes más grandes) o una instancia GdFont devuelta por imageloadfont(). También se puede proporcionar la ruta de un archivo TTF (opcional).
     * @param int    $size    El tamaño de la fuente en puntos (solo se aplica si se proporciona una fuente TTF, opcional).
     * @param int    $angle   El ángulo de inclinación del texto (solo se aplica si se proporciona una fuente TTF, opcional).
     * @param array  $extra   Parámetros adicionales para la función imagefttext() (solo se aplica si se proporciona una fuente TTF, opcional).
     *
     * @return void
     * 
     * Ej:
     *
     *  $im->text(50,50, "Pablo ama a Felipito", null, 5);
     *  $im->text(450,500, "Pablo ama a Felipito", null, ASSETS_PATH . 'fonts/Swiss 721 Light BT.ttf', 20, 90);
     */
    function text(int $x, int $y, $text, $color_name = null, $font = null, $size = 13, $angle = 0, $extra = []){
        if ($font === null){
            $font = 5;
        } elseif (!is_numeric($font) && !($font instanceof \GdFont)){
            Files::existsOrFail($font);
        }

        if ($color_name == null){
            $color_name = $this->getForegroundColorName();
        }

        if ((is_integer($font) && $font <6) || $font instanceof \GdFont){
            imagestring($this->im, $font, $x, $y, $text, $this->colors[$color_name]);
        } else {
            imagefttext($this->im, $size, $angle, $x, $y, $this->colors[$color_name], $font, $text, $extra);
        }        
    }

    function rectangleTo($x1, $y1, $x2, $y2, $color_name = null, bool $filled = false){
        if ($color_name == null){
            $color_name = $this->getForegroundColorName();
        }

        $fn = $filled ? 'imagefilledrectangle' : 'imagerectangle';

        $fn($this->im, $x1, $y1, $x2, $y2, $this->colors[$color_name]);
        return $this;
    }

    function rectangle($x1, $y1, $width, $height, $color_name = null, bool $filled = false){
        if ($color_name == null){
            $color_name = $this->getForegroundColorName();
        }

        $x2 = $x1 + $width;
        $y2 = $y1 + $height;

        $this->rectangleTo($x1, $y1, $x2, $y2, $color_name, $filled);
        return $this;
    }

    function lineTo(int $x1, int $y1, int $x2, int $y2, $color_name = null, bool $dashed = false){
        if ($color_name == null){
            $color_name = $this->getForegroundColorName();
        }

        /*
            En vez de 2 pixeles de un color y otros 2 de otro, 
            podria ser cualquier otro patron

            Ademas $dashed podria aplicarse a cualquier figura

            https://www.php.net/manual/en/function.imagesetstyle.php
        */
        if ($dashed){
            $fg = $this->getForegroundColor();
            $bg = $this->getBackgroundColor();

            $style = array($fg, $fg, $bg, $bg);
            imagesetstyle($this->im, $style);
        }

        imageline($this->im, $x1, $y1, $x2, $y2, $dashed ? IMG_COLOR_STYLED : $this->colors[$color_name]);
        return $this;
    }

    function line(int $x1, int $y1, int $delta_x, int $delta_y, $color_name = null, bool $dashed = false){
        if ($color_name == null){
            $color_name = $this->getForegroundColorName();
        }

        $x2 = $x1 + $delta_x;
        $y2 = $y1 + $delta_y;

        $this->lineTo($x1, $y1, $x2, $y2, $color_name, $dashed);
        return $this;
    }

    /*
        Ej:
    
        $cantidadActivos = 66; 
        $cantidadInactivos = 11; 

        $im->arcPie(200, 200, 300, 300, 0, (360 * $cantidadActivos / ($cantidadActivos + $cantidadInactivos)), 'rojo');
        $im->arcPie(200, 200, 300, 300, (360 * $cantidadActivos / ($cantidadActivos + $cantidadInactivos)), 360, 'azul');
    */
    function arcPie(int $x, int $y, int $w, int $h, int $start_angle, int $end_angle, string $color_name){
        if ($color_name == null){
            $color_name = $this->getForegroundColorName();
        }

        imagefilledarc($this->im, $x, $y, $w, $h, $start_angle, $end_angle, $this->colors[$color_name], IMG_ARC_PIE);
    }

    // Mas formas nativas como arc(), etc
    // ...

    /*
        Seteo forma personalizada
    */
    function setShape(string $name, callable $callback){
        $this->shapes[$name] = $callback;
    }

    /*PDOC
        Dibuja forma personalizada
    */
    function shape($name, ...$args){
        $this->shapes[$name](...$args);
    }

    /*
        Recupera forma, quizas color
    */
    function __call($name, $args){
        $this->shapes[$name](...$args);
    }

    function copyFrom(Imaginator $src_image, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_width, int $src_height){
        imagecopy($this->im, $src_image->getImage(), $dst_x, $dst_y, $src_x, $src_y, $src_width, $src_height);
    }

    function copyTo($dst_image, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_width, int $src_height){
        imagecopy($dst_image->getImage(), $this->im, $dst_x, $dst_y, $src_x, $src_y, $src_width, $src_height);
    }

    function mergeFrom(Imaginator $src_image, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_width, int $src_height, int $pct){
        imagecopymerge($this->im, $src_image->getImage(), $dst_x, $dst_y, $src_x, $src_y, $src_width, $src_height, $pct);
    }

    function mergeTo($dst_image, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_width, int $src_height, int $pct){
        imagecopymerge($dst_image->getImage(), $this->im, $dst_x, $dst_y, $src_x, $src_y, $src_width, $src_height, $pct);
    }

}


