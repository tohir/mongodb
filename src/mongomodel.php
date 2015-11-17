<?php

namespace tohir;

/**
 * Simple MongoDB Model Class
 *
 */
abstract class MongoModel
{
    protected $db;
    
    /**
     * @var string Name of Table
     */
    protected $collectionName = 'tablename';
    
    /**
     * @var string The column holding the Date Inserted Field - auto populated if set
     */
    protected $dateInsertColumn = '';
    
    /**
     * @var string The column holding the Date Updated Field - auto populated if set
     */
    protected $dateUpdateColumn = '';
    
    /**
     * Constructor
     * @param object $dbObject PDO Wrapper
     */
    public function __construct($dbObject)
    {
        $this->mongo = $dbObject;
        $this->collection = $this->mongo->collection($this->collectionName);
    }
    
    protected function loadModel($modelName)
    {
        try {
            $className = 'MongoModel_'.$modelName;
            
            $object = new $className($this->mongo);
            return $object;
            
        } catch (\Exception $e) {
            die('Unable to load model - '.$modelName);
        }
    }
    
    /**
     * Method to get a single row by the MongoId
     * - Note, an exception will be thrown for an invalid MongoID (not incorrect one)
     * @param mixed $value Value of Key to Search For
     * @return array|FALSE Record Details
     */
    public function getRowById($value)
    {
        return $this->collection->findOne(function ($query) use ($value) {
            $query->whereId($value);
        });
    }
    
    /**
     * Method to get a single row by column, value
     * @param string $column Key to Search For
     * @param mixed $value Value of Key to Search For
     * @return array|FALSE Record Details
     */
    public function getRow($column, $value)
    {
        return $this->collection->findOne(function ($query) use ($column, $value) {
            $query->where($column, $value);
        });
    }
    
    /**
     * Method to get a single row by column, value
     * @param string $column Key to Search For
     * @param mixed $value Value of Key to Search For
     * @return array|FALSE Record Details
     */
    public function getRowLike($column, $value)
    {
        return $this->collection->findOne(function ($query) use ($column, $value) {
            $query->whereLike($column, $value);
        });
    }
    
    
    /**
     * Method to get all records
     * @return array
     */
    public function getAll($orderBy='', $direction='asc', $limit=NULL, $skip=NULL)
    {
        return $this->collection->find(function ($query) use ($orderBy, $direction, $limit, $skip) {
            
            if (!empty($orderBy)) {
                $query->orderBy($orderBy, $direction); 
            }
            
            if (!empty($limit)) {
                $query->limit($limit);
            }
            
            if (!empty($skip)) {
                $query->skip($skip);
            }
            
        })->toArray();
    }
    
    /**
     * Method to add a single row
     * @param int $id Primary Key Value
     * @param array $data Column Values
     * @return int|false Insert ID or FALSE if failed
     */
    public function add($data)
    {
        // Run Hook
        $data = $this->hook_before_add($data);
        
        // Auto set Date Added Value
        if (!empty($this->dateInsertColumn)) {
            $data[$this->dateInsertColumn] = date('Y-m-d H:i:s');
        }
        
        // Auto set Date Updated Value
        if (!empty($this->dateUpdateColumn)) {
            $data[$this->dateUpdateColumn] = date('Y-m-d H:i:s');
        }
        
        // Run the insert
        $result = $this->collection->insert($data);
        
        // Run the Hook
        $hookResult = $this->hook_after_add($result, $data);
        
        return $result;
    }
    
    /**
     * Method to update a Mongodb record (row)
     * Note, the document itself can be updated and this method just used to save it,
     * OR the data to be saved can be passed
     *
     * @param array $document MongoDB Document
     * @param array $data Data to be updated
     * 
     * @return boolean success boolean
     */
    public function updateDocument(&$document, $data=array(), $runHookUpdate=TRUE)
    {
        // Run Hook
        if ($runHookUpdate) {
            $data = $this->hook_before_update($data);
        }
        
        if (!empty($this->dateUpdateColumn)) {
            $data[$this->dateUpdateColumn] = date('Y-m-d H:i:s');
        }
        
        foreach ($data as $key=>$val)
        {
            $document[$key] = $val;
        }
        
        $result = $this->collection->save($document);
        
        
        // Run the Hook
        if ($runHookUpdate) {
            $result = $this->hook_after_update($result, $data);
        }
        
        return $result;
    }
    
    /**
     * Method to remove documents from a collection
     * @param string $column Name of Column/Key
     * @param mixed $value Value of the Column/Key
     * @param boolean $single Whether to delete a single document or multiple
     */
    public function remove($column, $value, $single=TRUE)
    {
        return $this->collection->remove(function ($query) use ($column, $value, $single) {
            
            if ($single) {
                $query->where($column, $value)->single();
            } else {
                $query->where($column, $value)->multiple();
            }
            
        });
    }
    
    /* List of Hooks - These should be overriden */
    
    /**
     * Hook to run before adding a new record
     * @param array $data List of Fields and Values
     */
    protected function hook_before_add($data)
    {
        return $data;
    }
    
    /**
     * Hook to run after updating a new record
     * @param int|false $result If record was added, last increment id, else FALSE
     * @param array $data List of Fields and Values
     */
    protected function hook_after_add($result, $data)
    {
        return $result;
    }
    
    /**
     * Hook to run before updating a new record
     * @param array $data List of Fields and Values
     */
    protected function hook_before_update($data)
    {
        return $data;
    }
    
    /**
     * Hook to run after adding a new record
     * @param boolean $result Update result
     * @param array $data List of Fields and Values
     */
    protected function hook_after_update($result, $data)
    {
        return $result;
    }
    
    public function getRecordCount()
    {
        //@todo
    }
    
    protected function removeValueFromArray(&$array, $val)
    {
        // http://stackoverflow.com/questions/7225070/php-array-delete-by-value-not-key
        
        if(($key = array_search($val, $array)) !== false) {
            unset($array[$key]);
        }
    }
    
}