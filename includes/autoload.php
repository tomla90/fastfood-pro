<?php
spl_autoload_register(function($class){
    if (strpos($class, 'FFP_') !== 0) return;
    $file = FFP_DIR . 'includes/' . 'class-' . strtolower(str_replace('_','-',$class)) . '.php';
    if (file_exists($file)) require_once $file;
});
