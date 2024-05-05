<?php

namespace DbTableInstigator;

class MvcTool
{
    public static function getAllModules(string $appDir): array
    {
        $paths = glob($appDir . '/module/*' , GLOB_ONLYDIR);
        $modules = array_map('basename', $paths);
        return  $modules;
    }
    
    public static function hasModule($module, string $appDir = null) : bool
    {
        if(null === $appDir) {
            $appDir = getcwd();
        }
        
        return in_array($module, static::getAllModules($appDir) );
        
    }
}


