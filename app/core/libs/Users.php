<?php

/*
    @author  Pablo Bozzolo boctulus@gmail.com
*/

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Strings;

class Users
{
    static function isGuest(){
        return get_current_user_id() === 0;
    }

    static function isLogged(){
        return get_current_user_id() !== 0;
    }

    static function getUsernameByID($uid) {
        $user = get_user_by('id', $uid);

        // Verificar si se encontró un usuario
        if ($user) {
            return $user->user_login; // Devolver el nombre de usuario
        } else {
            return false; // Usuario no encontrado
        }
    }

    // Login
    static function login(string $username, string $password, bool $remember = true){
        $user_data = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember, // Opcional, si se quiere recordar al usuario
        );
        
        $user = wp_signon($user_data, false);
        
        if (is_wp_error($user)) {
            $error_message = $user->get_error_message();
            Logger::logError($error_message);
        }

        return !is_wp_error($user);
    }

    static function loginNoPasswordByUID($user_id, $redirect = true){
       
        wp_clear_auth_cookie();
        wp_set_current_user ($user_id);
        wp_set_auth_cookie  ($user_id);
    
        if ($redirect){
            $redirect_to = (is_string($redirect) ? $redirect : user_admin_url());
            wp_safe_redirect( $redirect_to );
            exit();
        }
    }

    // login por username sin password
    static function loginNoPassword(string $username, $redirect = true){
        $user = get_user_by('login', $username );
        
        if ($user === false){
            wp_die("User not found for '$username'");
        }

        // Redirect URL //
        if (!is_wp_error( $user ) )
        {
            wp_clear_auth_cookie();
            wp_set_current_user ( $user->ID );
            wp_set_auth_cookie  ( $user->ID );
        
            if ($redirect){
                $redirect_to = (is_string($redirect) ? $redirect : user_admin_url());
                wp_safe_redirect( $redirect_to );
                exit();
            }
        } else {
            $error_message = $user->get_error_message();
            Logger::logError($error_message);
        }

        return !is_wp_error($user);
    }

    // No funciona?
    static function loginNoPasswordByEmail(string $email, $redirect = true){
        $user = get_user_by('email', $email ); // No funciona?
        
        if ($user === false){
            wp_die("User not found for '$email' !!");
        }

        // Redirect URL //
        if (!is_wp_error( $user ) )
        {
            wp_clear_auth_cookie();
            wp_set_current_user ( $user->ID );
            wp_set_auth_cookie  ( $user->ID );
        
            if ($redirect){
                $redirect_to = (is_string($redirect) ? $redirect : user_admin_url());
                wp_safe_redirect( $redirect_to );
                exit();
            }
        } else {
            $error_message = $user->get_error_message();
            Logger::logError($error_message);
        }

        return !is_wp_error($user);
    }

    /*
        Ej:

        $uname = $_GET['username'] ?? Strings::randomString(20);

        $uid = Users::create($uname);   
        
        if (!empty($uid)){
            Users::loginNoPassword($uname);
        }
    */
    static function create($username, $email=null, $password=null, $role = 'administrator')
    {
        $password = $password ?? Strings::randomString(10);

        if (!username_exists($username))
        {
            if (!empty($email)){
                if (!email_exists($email)){
                    $user_id = wp_create_user( $username, $password, $email);
                } else {
                    // or.. throw new \Exception
                    return false;
                }
                
            } else {
                $email   = "{$username}@fakemail.com";
                $user_id = wp_create_user( $username, $password, $email);
            }

            if (isset($user_id) && is_int($user_id))
            {
                $wp_user_object = new \WP_User($user_id);
                $wp_user_object->set_role($role);

                return $user_id;
            }
            else {
                // or.. throw new \Exception
                return false;
            }
        } else {
            // 'This user or email already exists. Nothing was done.'
            // or.. throw new \Exception
            return false;
        }        
    }

    static function restrictAccess($capability = 'administrator', $redirect_to = '/access-denied'){
        if (is_cli()){
            return; // ok
        }

        if (!is_user_logged_in() || !current_user_can($capability)) {
            // El usuario no es administrador / lo-que-sea o no ha iniciado sesión
            // Redirigir a una página de acceso denegado (a crear) o mostrar un mensaje de error
            wp_redirect(home_url($redirect_to));
            exit();
        }
    }
    
    static function getCurrentUserId(){        
        $user_id = get_current_user_id();
    
        if (empty($user_id)){
            return null;
        }
    
        return $user_id;
    }

    static function getUserById($id){
        if (!is_numeric($id)){
            throw new \InvalidArgumentException("UID no tiene el formato esperado");
        }

        return get_user_by('id', $id);
    }

    static function getEmailById($id){
        if (!is_numeric($id)){
            throw new \InvalidArgumentException("UID no tiene el formato esperado");
        }

        $user_data = get_userdata($id);

        if (!$user_data) {
            // El ID no existe en la base de datos o no corresponde a un usuario válido
            throw new \RuntimeException("No se encontró un usuario para el ID proporcionado");
        }

        $email = $user_data->user_email;

        if (empty($email)) {
            // El usuario no tiene un correo electrónico asociado
            throw new \RuntimeException("No se encontró un correo electrónico para el ID proporcionado");
        }

        return $email;
    }

    static function getUserByEmail($email){
        return get_user_by('email', $email);
    }

    static function getUserIdByEmail($email){
        $u = get_user_by('email', $email);

        if (!empty($u)){
            return $u->ID;
        }
    }

    static function getUserIdByUsername($username){
        $u = get_user_by('login', $username);

        if (!empty($u)){
            return $u->ID;
        }
    }

    /**
     * Obtener el nombre de usuario por correo electrónico
     *
     * @param string $email Correo electrónico del usuario
     * @return string|false Nombre de usuario si se encuentra, false si no se encuentra
     */
   static function getUsernameByEmail($email) {
        $user = get_user_by('email', $email);

        if (empty($user)){
            wp_die("User not found for email '$email'");
        }

        // Verificar si se encontró un usuario
        if ($user) {
            return $user->user_login; // Devolver el nombre de usuario
        } else {
            return false; // Usuario no encontrado
        }
    }
    static function getAdminEmail(){
        return get_option('admin_email');
    } 

    static function setAdminEmail(string $email){
        return update_option('admin_email', $email);
    } 

    static function getAdminID(){
        global $wpdb;

        $admin_email = self::getAdminEmail();
        $admin_id    = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_email = %s", $admin_email));

        return $admin_id;
    }

    // set user meta
    static function setMeta($meta_key, $value, $user_id = null){
        return update_user_meta($user_id ?? Users::getCurrentUserId(), $meta_key, $value);
    }

    static function deleteMeta($meta_key, $user_id){
        return delete_user_meta($user_id, $meta_key);
    }


    /*
        Ej:

        Users::getMeta('api_key', 7)
    */
    static function getMeta($meta_key = '', $user_id = null){
        return get_the_author_meta($meta_key, $user_id ?? Users::getCurrentUserId());
    }

    // for All Users
    static function getMetaAll($meta_key = null, $user_id = null){
        $select = ($meta_key === null ? ['user_id', 'meta_key', 'meta_value'] :  ['user_id', 'meta_value']);
        
        $tb = (object) table('usermeta');

        $metas = $tb
        ->select($select)
        ->when($meta_key != null, function($q) use ($meta_key){
            $q->where([
                'meta_key' => $meta_key
            ]);
        })
        ->when($user_id != null, function($q) use ($user_id){
            $q->where([
                'user_id' => $user_id
            ]);
        })
        ->get();

        return $metas;
    }

    /*
        Ej:

        Users::getAPIKey(7)
    */
    static function getAPIKey($user_id = null){
        return static::getMeta('api_key', $user_id);
    }

    /*
        for All Users

        Ej de salida:

        Array
        (
            [0] => Array
                (
                    [user_id] => 8
                    [meta_value] => v8gVyfbF334077
                )

            [1] => Array
                (
                    [user_id] => 7
                    [meta_value] => 3JYiRsVv
                )

        )

    */
    static function getAPIKeyAll(){
        return static::getMetaAll('api_key');
    }

    static function getUserIdByMetaKey($meta_value, $meta_key = null){
        /*
            SELECT `user_id` FROM `woo3`.`wp_usermeta`
            WHERE meta_key = 'user_api_key' AND meta_value = 'woo3-010011010101';
        */

        $tb = (object) table('usermeta');

        $user_id = $tb
        ->select(['user_id'])
        ->where([
            'meta_value' => $meta_value
        ])
        ->when($meta_key != null, function($q) use ($meta_key){
            $q->where([
                'meta_key' => $meta_key
            ]);
        })
        ->value('user_id');

        return $user_id;
    }
    
    static function getUserIdByAPIKey($api_key){
        return Users::getUserIdByMetaKey($api_key, 'api_key');
    }

    static function userExistsByEmail($email){
        return !empty( get_user_by( 'email', $email) );
    }

     /*
        Acepta un rol o array de roles o null para todos
    */
    static function getCapabilities($rol = null){
        global $wp_roles;

        if ($rol == null){
            $rol = static::getRoleSlugs();
        } 

        if (is_array($rol)){
            $capabilities = [];

            // roles
            foreach ($rol as $r){
                if (!static::roleExists($r)){
                    throw new \InvalidArgumentException("Rol '$r' no existe");
                }

                $capabilities[$r] = array_keys($wp_roles->roles[$r]['capabilities'] ); 
            }
        } else {
            if (!static::roleExists($rol)){
                throw new \InvalidArgumentException("Rol '$rol' no existe");
            }

            $capabilities = array_keys($wp_roles->roles[$rol]['capabilities'] );
        }
    
        return $capabilities;
    }

    static function roleExists($role) {
        // busco en minusculas
        $role  = strtolower($role);

        $roles = array_keys(static::getRoleNames());
        
        return in_array($role, $roles);
    }

    static function createRole(string $role_name, $role_title = null, Array $capabilities = []){
        $role_name = strtolower($role_name);

        if ($role_title === null){
            $role_title = ucfirst($role_name);
        }

        if (empty($capabilities)){
            $capabilities =  array(
                'read'         => true, // true allows this capability
                'edit_posts'   => true,
                'delete_posts' => true, 
            );
        }

        $result = add_role(
            $role_name,
            __( $role_title ),
           $capabilities
        );

        return (null !== $result);
    }

    /**
     * hasRole 
     *
     * function to check if a user has a specific role
     * 
     * @param  string  $role    role to check against 
     * @param  int  $user_id    user id
     * @return boolean
     * 
     */
    static function hasRole($role, $user = null){
        if (!is_user_logged_in()){
            return false;
        }

        if (empty($user)){
            $user = wp_get_current_user();
        } else {
            if (is_numeric($user) ){
                $user = get_user_by('id', $user);
            }
        }
            
        if ( empty( $user ) )
            return false;

        return in_array( $role, (array) $user->roles );
    }

    /*
        Parece ignorar cualquier rol distinto del primero
    */
    static function addRole($role, $user = null){
        if (empty($user)){
            $user = wp_get_current_user();
        } else {
            if (is_numeric($user) ){
                $user = get_user_by('id', $user);
            }
        }   
        
        return $user->add_role( $role );
    }

    static function removeRole($role, $user = null){
        if (empty($user)){
            $user = wp_get_current_user();
        } else {
            if (is_numeric($user) ){
                $user = get_user_by('id', $user);
            }
        }   

        return $user->remove_role( $role );
    }

    /*
        Lista todos los roles posibles
    */
    static function getRoleNames() {
        global $wp_roles;
        
        if ( ! isset( $wp_roles ) )
            $wp_roles = new \WP_Roles();
        
        return $wp_roles->get_names();
    }

     /*
        Retorna algo como:

       Array
        (
            [0] => administrator
            [1] => editor
            [2] => author
            [3] => contributor
            [4] => subscriber
            [5] => customer
            [6] => shop_manager
        )

    */
    static function getRoleSlugs() {
        return array_keys(static::getRoleNames());
    }

    static function getUsersByRole($roles, $limit = -1) {
        if (!is_array($roles)){
            $roles = [ $roles ];
        }
    
        // Preparar los argumentos para la consulta.
        $args = array(
            'fields' => 'ID',
            'role__in' => $roles
        );
        
        // Si el límite no es -1, agregarlo a los argumentos.
        if ($limit !== -1) {
            $args['number'] = $limit;
        }
    
        // Crear la consulta con los argumentos.
        $query = new \WP_User_Query($args);
    
        return $query->get_results();
    }    

    /*
       Equivale a 

       Users::getUserRolesByUID(Users::getCurrentUserId());
    */
    static function getCurrentUserRoles() {
        if( is_user_logged_in() ) {
            $user  = wp_get_current_user();
            $roles = (array) $user->roles;
        
            return $roles; 
        } else {   
            return [];
        }   
    }


    static function getUserRolesByUsername($username) {
        $user = get_user_by('login', $username);
        
        if ($user) {
            $roles = (array) $user->roles;
            return $roles;
        } else {
            return [];
        }
    }    

    static function getUserRolesByUID($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if ($user) {
            $roles = (array) $user->roles;
            return $roles;
        } else {
            return [];
        }
    }    

    static function getUserIDList() {
        $query = new \WP_User_Query(
           array(
              'fields' => 'ID',
              'limit' => -1                 
           )
        );

        return $query->get_results();
    }
    
    static function getLastID() {
        $query = new \WP_User_Query(
            array(
                'fields' => 'ID',
                'number' => 1,  
                'orderby' => 'ID',
                'order' => 'DESC',  
            )
        );
    
        $results = $query->get_results();
    
        if (!empty($results)) {
            return $results[0];
        }
    
        return false;
    }

    static function getLast() {
        $query = new \WP_User_Query(
            array(
                'number' => 1,  
                'orderby' => 'ID',
                'order' => 'DESC',  
            )
        );
    
        $results = $query->get_results();
    
        if (!empty($results)) {
            return $results[0];
        }
    
        return false;
    }
    
    /*
        Para WooCommerce
    */

    static function getCustomerList() {
        $query = new \WP_User_Query(
           array(
              'fields' => 'ID',
              'role' => 'customer',
              'limit' => -1                 
           )
        );

        return $query->get_results();
    }

    /*
        Si el $user_id es null, se toma del usuario actual
    */
    static function isPayingCustomer($user_id = null){
        return (bool) Users::getMeta('paying_customer', $user_id);
    }

    static function makePayingCustomer($customer_id = null){
        Users::setMeta( $customer_id, 'paying_customer', 1 );
    }

    static function removeAsPayingCustomer($customer_id = null){
        Users::setMeta( $customer_id, 'paying_customer', 0 );
    }

    static function getLang(){
        $user        = wp_get_current_user();
        $user_locale = get_user_meta($user->ID, 'locale', true);

        return $user_locale;
    }
}