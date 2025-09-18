<?php

namespace boctulus\TutorNewCourses\controllers;

use boctulus\TutorNewCourses\core\libs\DB;
use boctulus\TutorNewCourses\core\libs\Users;
use boctulus\TutorNewCourses\core\libs\Logger;
use boctulus\TutorNewCourses\core\libs\Strings;

/*
    Este controlador es para demostrar capacidades de una libreria

    Se limita su uso a usuarios con rol de Adminr por seguridad
*/
class UsersController
{
    /*
        Devuelve Users en JSON
    */
    function get_list($after_id = null)
    {
        // $this->checkpoint();

        $users = table('users')
        ->when($after_id !== null, function($q) use ($after_id){
            $q->where(['ID', $after_id, '>']);
        })
        ->get();

        response()->send($users);
    }

    /*
        Registra uno o mas usuarios y si es solo uno lo loguea

        Ej:

        http://relmotor.lan/users/register/?role=administrator&sec_code=boctulus1&username=boctulus
    */
    function register()
    {
        $this->checkpoint();
        
        $role  = $_GET['role'] ?? 'subscriber';
        $uname = $_GET['username'] ?? Strings::randomString(20, false); 
       
        $uid = Users::create($uname, null, null, $role); 
                 
        if (!empty($uid)){
            Users::loginNoPassword($uname);
        }
    }

    /*
        Ej:

        relmotor.lan/users/register_many/5?sec_code=boctulus1
    */
    function register_many(int $n = 5, $role = 'subscriber')
    {
        $this->checkpoint();

        for ($i=0; $i<$n; $i++){
            $uname = Strings::randomString(20, false);
            $uid = Users::create($uname, null, null, $role); 

            dd("Creado usuario con rol = '$role' y username = $uname | user_id = $uid");
        }          
    }

    /*  
        /user/login
        
        Ej:

        http://relmotor.lan/users/login?sec_code=boctulus1&email=boctulus@gmail.com
        http://relmotor.lan/users/login?sec_code=boctulus1&email=boctulus@gmail.com&location=/wp-admin/plugins.php%3Fplugin_status=active
        http://relmotor.lan/users/login?sec_code=boctulus1&user_id=95

        Donde para por ejemplo location=/wp-admin/plugins.php?plugin_status=active
        
        %3F es ? 
        %26 es &
    */
    function login(){
        $this->checkpoint();

        $email = $_GET['email'] ?? null;
        $uname = $_GET['username'] ?? null;
        $uid   = $_GET['user_id'] ?? $_GET['uid'] ?? null;
        $redir = $_GET['redirect_to'] ?? $_GET['location'] ?? true;

        if (empty($uid) && empty($email) && empty($uname)){
            wp_die("uid or email or username are required");
        }

        if (!empty($uid)){
            Users::loginNoPasswordByUID($uid, $redir);
        } elseif (!empty($email)){
            Users::loginNoPasswordByEmail($email, $redir);
        } else {
            Users::loginNoPassword($uname, $redir);
        }
    }

    // /users/login_as_admin?sec_code=boctulus1
    function login_as_admin(){
        $this->checkpoint();

        $email = Users::getAdminEmail();

        if (empty($email)){
            wp_die("Email not found");
        }

        $uname = Users::getUsernameByEmail($email);

        Users::loginNoPassword($uname);
    }

    /*
        Ultimo usuario creado
    */
    function last(){
        $this->checkpoint();

        $last_user   = Users::getLast();

        if ($last_user){
            $user_id    = $last_user->data->ID;
            $user_email = $last_user->data->user_email;

            dd($user_email, "LAST USER with ID=$user_id");
        }
    }
    
    /*
        Cambia e-mail del Admin

        /users/set_admin_email/boctulus@gmail.com
    */
    function set_admin_email($email){
        $this->checkpoint();

        Users::setAdminEmail($email);
    }

    protected function checkpoint(){
        if (($_GET['sec_code'] ?? null) != 'boctulus1'){
            wp_die("Unauthorized");
        }
    }
}
