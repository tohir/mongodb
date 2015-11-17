<?php

namespace Tohir;

use League\Monga;

class MongoDatabase
{
    public function __construct($database, $connectionString='', $server='localhost', $port='27017')
    {
        if (empty($connectionString)) {
            $connectionString = 'mongodb://'.$server.':'.$port.'/'.$database;
        }
        
        $this->mongo = Monga::connection($connectionString);
        $this->mongo =  $this->mongo->database($database);
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
