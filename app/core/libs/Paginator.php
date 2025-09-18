<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Files;
use boctulus\TutorNewCourses\core\libs\Arrays;
use boctulus\TutorNewCourses\core\libs\Strings;

class Paginator
{
    protected $orders = [];
    protected $offset = 0;
    protected $limit = null;
    protected $order = null;
    protected $attributes = [];
    protected $query = '';
    protected $binding = [];

    const TOP    = 'TOP';
    const BOTTOM = 'BOTTOM';

    /*
        Calcula el offset dados el size la page y la page actual
    */

    static function calcOffset(int $current_page, int $page_size){
        return ($page_size * $current_page) - $page_size;
    }

    static function human2SQL(int $page, int $page_size)
    {  
        $offset = static::calcOffset($page, $page_size);
        $limit  = $page_size;

        return [$offset, $limit];
    }
    /*
        Calcula todo lo que debe tener el paginador 
        a excepcion de la proxima url

    */
    static function calc(int $current_page, int $page_size, int $row_count){
        $page_count  = (int) ceil($row_count / $page_size);
        
        if ($current_page < $page_count){
            $count = $page_size;
        } elseif ($current_page > $page_count){
            $count = 0;
        } else {
            $count = $row_count - ($page_size * ($current_page-1));
        }
    
        return [
            "total"       => $row_count,  // cant. total de registros
            "count"       => $count,
            "currentPage" => $current_page,
            "totalPages"  => $page_count,
            "pageSize"    => $page_size
        ];
    }

    /** 
     * @param array $attributes of the entity to be paginated
     * @param array $order 
     * @param int $offset
     * @param int $limit
    */
    function __construct($attributes = null, array $order = null, int $offset = 0, int $limit = null) {
        $this->order = $order;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->attributes = $attributes;

        if ($order!=null && $limit!=null)
            $this->compile();
    }

    function compile(): void
    {
        $query = '';
        if (!empty($this->orders)){
            $query .= ' ORDER BY ';
            
            foreach($this->orders as $field => $_order){
                $order = strtoupper($_order);

                if ((preg_match('/^[a-z0-9\-_\.]+$/i',$field) != 1)){
                    throw new \InvalidArgumentException("Field '$field' is not a valid field");
                }

                if ($order == 'ASC' || $order == 'DESC'){
                    $query .= "$field $order, ";
                }else
                    throw new \InvalidArgumentException("Order direction '$_order' is invalid. Order should be ASC or DESC!");   


                if (Strings::contains('.', $field)){
                    list($tb, $_field) = explode('.', $field);
                
                    if(!in_array($_field, $this->attributes)){
                        throw new \InvalidArgumentException("property '$field' not found!"); 
                    }
                }
                
            }
            $query = substr($query,0,strlen($query)-2);
        }

        $ol = [$this->limit !== null, !empty($this->offset)];

        // https://stackoverflow.com/questions/595123/is-there-an-ansi-sql-alternative-to-the-mysql-limit-keyword
        if ($ol[0] || $ol[1]){
            switch (DB::driver()){
                case 'mysql':
                case 'sqlite':
                    switch($ol){
                        case [true, true]:
                            $query .= " LIMIT ?, ?";
                            $this->binding[] = [1 , $this->offset, \PDO::PARAM_INT];
                            $this->binding[] = [2 , $this->limit,  \PDO::PARAM_INT];
                        break;
                        case [true, false]:
                            $query .= " LIMIT ?";
                            $this->binding[] = [1 , $this->limit, \PDO::PARAM_INT];
                        break;
                        case [false, true]:
                            // https://stackoverflow.com/questions/7018595/sql-offset-only
                            $query .= " LIMIT ?, 18446744073709551615";
                            $this->binding[] = [1 , $this->offset, \PDO::PARAM_INT];
                        break;
                    } 
                    break;    
                case 'pgsql': 
                    switch($ol){
                        case [true, true]:
                            $query .= " OFFSET ? LIMIT ?";
                            $this->binding[] = [1 , $this->offset, \PDO::PARAM_INT];
                            $this->binding[] = [2 , $this->limit,  \PDO::PARAM_INT];
                        break;
                        case [true, false]:
                            $query .= " LIMIT ?";
                            $this->binding[] = [1 , $this->limit, \PDO::PARAM_INT];
                        break;
                        case [false, true]:
                            $query .= " OFFSET ?";
                            $this->binding[] = [1 , $this->offset, \PDO::PARAM_INT];
                        break;
                    } 
                    break;            
            }
        }

        $this->query = $query;
    }

    function setAttr(Array $attributes) : Paginator {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Set the value of orders
     *
     * @return  self
     */ 
    function setOrders($orders): Paginator
    {
        $this->orders = $orders;
        return $this;
    }

    /**
     * Set the value of offset
     *
     * @return  self
     */ 
    function setOffset($offset): Paginator
    {
        $this->offset = $offset;
        return $this;
    }

    function getOffset(){
        return $this->offset;
    }

    /**
     * Set the value of limit
     *
     * @return  self
     */ 
    function setLimit($limit): Paginator
    {
        $this->limit = $limit;
        return $this;
    }

    function getLimit() {
        return $this->limit;
    }

     /**
     * Get the value of query
     */ 
    function getQuery(): string
    {
        return $this->query;
    }

    function page(int $current_page, int $page_size){
        $this->limit  = $page_size;
        $this->offset = static::calcOffset($current_page, $page_size);
    }

    /**
     * Get the value of binding
     */ 
    function getBinding(): array
    {
        return $this->binding;
    }

    /*
        Que hace

        Dados unos registros (ya sea como array o json) 
        y un "path" hacia donde se hallan anidados dentro de un array o JSON,
        pagina los resultados

        Cuando usarla
        
        El caso de uso de esta funcion es tomar un JSON con muchos registros
        y paginarlos para ser servidos en una API posiblemente para testing

        Ej:

        $path      = 'D:\www\woo2\wp-content\plugins\mutawp\etc\responses\products.json';
        $row_path  = "data.products"; // ['data']['products']
        $page      = 2;
        $page_size = 3;

        $data      = Files::getContent($path);

        $res       = Paginator::paginate($data, $page, $page_size, $row_path);
    */
    static function paginate($data, $page, $page_size, $rows_path = null)
    {
        if (Strings::isJSON($data)){
            $data = json_decode($data, true);
        }

        if (!empty($rows_path)){
            $rows_path_s = explode('.', $rows_path);
            
            foreach ($rows_path_s as $slug){
                $data = $data[$slug];
            }
        }

        $offset = static::calcOffset($page, $page_size);

        $res = Arrays::chunk($data, $page_size, $offset);

        if (!empty($rows_path)){
            $res = Arrays::makeArray($res, $rows_path);
        }

        return $res;
    }
}