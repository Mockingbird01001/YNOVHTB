<?php

/**
 * @Author: Mockingbird
 * @Date:   2021-10-20 15:03:28
 * @Last Modified by:   root
 * @Last Modified time: 2021-10-20 15:30:28
 */

class Session{

    static $instance;

    static function getInstance(){
        if(!self::$instance){
            self::$instance = new Session();
        }
        return self::$instance;
    }

    public function __construct(){
        session_start();
    }

    public function setFlash($key, $message){
        $_SESSION['authentification'][$key] = $message;
    }
    
    public function hasFlashes(){
        return isset($_SESSION['authentification']);
    }

    public function getFlashes(){
        $flash = $_SESSION['authentification'];
        unset($_SESSION['authentification']);
        return $flash;
    }

    public function write($key, $value){
        $_SESSION[$key] = $value;
    }

    public function read($key){
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public function delete($key){
        unset($_SESSION[$key]);
    }

}