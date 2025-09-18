<?php

namespace boctulus\TutorNewCourses\core\libs;

use Composer\InstalledVersions;

require_once __DIR__ . '/../../../vendor/composer/InstalledVersions.php';

class XML
{
    static function isXML($str, bool $fast_check = false) 
    {
        $str = trim($str);

        if ((substr($str, 0, 1) != '<') || substr($str, -1) !== '>'){
            return false;
        }

        if ($fast_check){
            return true;
        }

        // Habilitar el uso de errores internos de libxml
        libxml_use_internal_errors(true);
        
        // Intentar cargar el string XML
        $xml = simplexml_load_string($str);

        // Verificar si hubo errores al cargar el XML
        $is_xml = ($xml !== false);

        // Limpiar los errores de libxml
        libxml_clear_errors();

        return $is_xml;
    }

    /*
        The intend is to get the "DOM selector" given a text which should be found as substring of a text node

        Verificar si funciona!
    */
    static function getSelector(string $html, string $text) : string {
            $dom = static::getDocument($html);

            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query("//text()[contains(., '$text')]/parent::*");

            $selector = '';
            foreach ($nodes as $node) {
                $selector .= self::getNodeSelector($node) . '/';
            }

            $selector = rtrim($selector, '/');

            return $selector;
        }

        private static function getNodeSelector(\DOMNode $node) : string {
        $selector = '';

        while ($node && $node->nodeType === XML_ELEMENT_NODE) {
            $nodeName = $node->nodeName;
            $nodeIndex = self::getNodeIndex($node);
            $selector = "{$nodeName}[{$nodeIndex}]{$selector}"; // Update the order of concatenation

            $node = $node->parentNode; // Move to the parent node
        }

        $selector = '/' . rtrim($selector, '/');

        return $selector;
    }
 

    private static function getNodeIndex(\DOMNode $node) : int {
        $index = 1;
        $previousNode = $node->previousSibling;

        while ($previousNode) {
            if ($previousNode->nodeName === $node->nodeName) {
                $index++;
            }
            $previousNode = $previousNode->previousSibling;
        }

        return $index;
    }

    /*
        Ej:

        $selector = '//p';
        $result = XML::getTag($html, $selector);
        dd($result, $selector); 
    
        $selector = '//div[contains(@class, "my_class")]';
        $result = XML::getTag($html, $selector);
        dd($result, $selector); 
    */
    static function getTag(string $html, string $selector): array {
        $dom = static::getDocument($html);
        
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query($selector);

        $result = array();
        foreach ($nodes as $node) {
            $result[] = $dom->saveXML($node);
        }

        return $result;
    }

    /*
        Devuelve ocurrencias de <article>

        Ej:

        $html = Files::getContent(ETC_PATH . 'page.html');        
        $html = XML::getArticles($html) ?? $html;

        dd($html);
    */
    static function getArticles(string $html, bool $as_string = true){
        $arts = XML::query($html, '//article');

        if (empty($arts)){
            return;
        }

        if ($as_string){
            $arts = implode("\r\n\r\n", $arts);
        }   

        return $arts;
    }

    // alias
    static function query(string $html, string $selector): array {
        return static::getTag($html, $selector);
    }
    
    static function saveXMLNoHeader(\DOMDocument $dom) : string {
        $str = $dom->saveXML();

        $str = Strings::afterIfContains($str, '<?xml version="1.0" standalone="yes"?>');
        $str = Strings::afterIfContains($str, '<?xml encoding="UTF-8"?>');
        $str = static::stripDOCTYPE($str);

        return ltrim($str);
    }

    /*
        @author Eaten by a Grue 
        https://stackoverflow.com/users/1767412/eaten-by-a-grue
    */
    static protected function DOMtoArray($root) {
        $result = array();
    
        if ($root->hasAttributes()) {
            $attrs = $root->attributes;
            foreach ($attrs as $attr) {
                $result['@attributes'][$attr->name] = $attr->value;
            }
        }
    
        if ($root->hasChildNodes()) {
            $children = $root->childNodes;
            if ($children->length == 1) {
                $child = $children->item(0);
                if (in_array($child->nodeType,[XML_TEXT_NODE,XML_CDATA_SECTION_NODE])) {
                    $result['_value'] = $child->nodeValue;
                    return count($result) == 1
                        ? $result['_value']
                        : $result;
                }
    
            }
            $groups = array();
            foreach ($children as $child) {
                if (!isset($result[$child->nodeName])) {
                    $result[$child->nodeName] = static::DOMtoArray($child);
                } else {
                    if (!isset($groups[$child->nodeName])) {
                        $result[$child->nodeName] = array($result[$child->nodeName]);
                        $groups[$child->nodeName] = 1;
                    }
                    $result[$child->nodeName][] = static::DOMtoArray($child);
                }
            }
        }
        return $result;
    }

    /*
        @author Eaten by a Grue 
        https://stackoverflow.com/users/1767412/eaten-by-a-grue
    */
    static function toArray($xml) {
        $previous_value = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        
        $dom->preserveWhiteSpace = false; 
        $dom->loadXml($xml);

        libxml_use_internal_errors($previous_value);
        
        if (libxml_get_errors()) {
            return [];
        }
        
        return static::DOMtoArray($dom);
    }
    
    /*
        Requiere del paquete de Composer spatie/array-to-xml

        composer require spatie/array-to-xml
    */
    static function fromArray(array $arr, string $root_elem = 'root', $header = true){
        if (!InstalledVersions::isInstalled('spatie/array-to-xml')){
            throw new \Exception("Composer package spatie/array-to-xml is requiered");
        }


        $class = "\Spatie\ArrayToXml\ArrayToXml";

        if (!class_exists($class)){
            throw new \Exception("Class not found");
        } 

        $converter = new $class($arr, $root_elem);

        $result    = $converter::convert($arr, $root_elem, $header);

        if (!$header){
            $result = trim(substr($result, 21));
        }

        return $result;
    }
    
    static function getDocument(string $html){
        $doc = new \DOMDocument();

        libxml_use_internal_errors(true);

        if (!Strings::contains('<?xml encoding="UTF-8">', $html)){
            $html = '<?xml encoding="UTF-8">' . $html;
        }
        
        $doc->loadHTML($html);       
        libxml_use_internal_errors(false);

        return $doc;
    }  
    
    static function getXPath(string $html){
        return new \DOMXPath(
            static::getDocument($html)
        );
    }   

    // Recupera textos de nodos
    static function getTextFromNodes($html) {
        $dom   = static::getDocument($html);
        $xpath = new \DOMXPath($dom);
        
        $nodes = $xpath->query('//text()');

        $textNodes = [];
        foreach ($nodes as $node) {
            $text = trim($node->nodeValue);
            if (!empty($text)) {
                $textNodes[] = $text;
            }
        }

        return $textNodes;
    }

    /*
        Puede remover cualquier <tag>

        Remueve tambien sus hijos

        https://stackoverflow.com/a/7131156/980631
    */
    static function stripTag(string $html, $tag) : string {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $script = $dom->getElementsByTagName($tag);

        $remove = [];
        foreach($script as $item){
            $remove[] = $item;
        }

        foreach ($remove as $item){
            $item->parentNode->removeChild($item); 
        }

        $html = static::saveXMLNoHeader($dom);
        
        return $html;
    }

    static function stripTagById(string $page, string $id): string {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($page, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Obtén el elemento con el ID especificado
        $element = $dom->getElementById($id);
        
        if ($element) {
            // Obtén el padre del elemento y elimina el elemento del árbol DOM
            $parent = $element->parentNode;
            $parent->removeChild($element);
        }
        
        // Obtén el XML resultante como string
        $newPage = static::saveXMLNoHeader($dom);
        
        return $newPage;
    }

    static function stripXMLTags(string $str): string {
        $str = Strings::afterIfContains($str, '<?xml version="1.0" standalone="yes"?>');
        $str = Strings::afterIfContains($str, '<?xml encoding="UTF-8"?>');

        return trim($str);
    }
    
    static function stripDOCTYPE(string $page) : string {
        $pattern = '/<!DOCTYPE[^>]+>/i';
        $stripped_page = preg_replace($pattern, '', $page);
        return $stripped_page;
    }
   
}

