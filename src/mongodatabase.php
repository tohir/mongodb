<?php

namespace Tohir;

use League\Monga;

class MongoDatabase
{
    public function __construct()
    {
        $this->mongo = Monga::connection('mongodb://'.AppConfig::get('mongodb', 'server').':'.AppConfig::get('mongodb', 'port').'/'.AppConfig::get('mongodb', 'database'));
        $this->mongo =  $this->mongo->database(AppConfig::get('mongodb', 'database'));
    }
    
    public function loadModel($modelName)
    {
        try {
            $className = 'MongoModel_'.$modelName;
            
            $object = new $className($this->mongo);
            return $object;
            
        } catch (\Exception $e) {
            die('Unable to load model - '.$modelName);
        }
    }
    
}
