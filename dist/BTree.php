<?php

class BTree{
    
    ////////////PUBLIC////////////////
    
    public function __construct($path = '.'){
        
        $this->path = rtrim($path,'/');
        
    }
    
    public static function create($path, $size){
        //@TODO
        
        self::createPath($path);
        self::createInstance($path,$size);
    }
    
    public static function exists(){
        //@TODO
    }
    
    public static function move(){
        //@TODO
    }
    
    public static function copy(){
        //@TODO
    }
    
    public static function open($path){
        //if(self::isStatic)
        //    return new BTreeInstance(rtrim($path,'/'));
        return new BTreeInstance($path);
    }
    
    
    /////////////PRIVATE/////////////
    
    /** 
     *  recursively create path if not exists;
     */
    public static function createPath($path) {
        if(file_exists($path))
            return true;
        return mkdir($path,0777,true);
        ///if (is_dir($path)) return true;
        ///$prev_path = substr($path, 0, strrpos($path, '/', -2) + 1 );
        ///echo $prev_path.'<br>';
        ///$return = self::createPath($prev_path);
        ///return ($return && is_writable($prev_path)) ? mkdir($path) : false;
    }
    
    private static function createInstance($path,$size){
        BTreeInstance::create($path, $size);
    }
    
    /**
     *  Check if method call is static or not;
     */
    private static function isStatic() {
        $backtrace = debug_backtrace();
        return $backtrace[1]['type'] == '::';
    }
    
    private function getPath($path){
        $path = $this->path.'/'.trim($path,'/');
        return $path;
    }
    
    private $pth;
    
    
}





?>