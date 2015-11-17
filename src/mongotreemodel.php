<?php

namespace tohir;

/**
 * Simple MongoDB Tree Model Class
 *
 */
abstract class MongoTreeModel extends \tohir\MongoModel
{
    // Columns for the Modified Pre-Order Traversal
    protected $primaryColumn = 'item';
    protected $parentTreeColumn = 'parent';
    protected $itemOrderColumn = '';
    
    protected $lftTreeColumn = 'lft';
    protected $rghtTreeColumn = 'rght';
    protected $levelTreeColumn = 'level';
    
    protected $startParentValue = '0';
    
    public function rebuild()
    {
        $this->rebuild_tree(0, 0, 0);
    }
    
    /**
     * Hook to run before adding a new record
     * @param array $data List of Fields and Values
     */
    protected function hook_before_add($data)
    {
        // Add these fields as is - they will be updated afterwards
        $data[$this->lftTreeColumn] = 0;
        $data[$this->rghtTreeColumn] = 0;
        $data[$this->levelTreeColumn] = 0;
        
        if (isset($data[$this->parentTreeColumn]) && empty($data[$this->parentTreeColumn])) {
            $data[$this->parentTreeColumn] = $this->startParentValue;
        }
        
        return $data;
    }
    
    protected function hook_before_update($data)
    {
        if (isset($data[$this->parentTreeColumn]) && empty($data[$this->parentTreeColumn])) {
            $data[$this->parentTreeColumn] = $this->startParentValue;
        }
        
        return $data;
    }
    
    /**
     * Hook to run after adding a new record
     * @param array $data List of Fields and Values
     * @param int|false $result If record was added, last increment id, else FALSE
     */
    protected function hook_after_add($result, $data)
    {
        $this->rebuild_tree(0, 0, 0);
        
        return $result;
    }
    
    /**
     * Hook to run after update a new record
     * @param array $data List of Fields and Values
     * @param int|false $result If record was added, last increment id, else FALSE
     */
    protected function hook_after_update($result, $data)
    {
        $this->rebuild_tree(0, 0, 0);
        
        return $result;
    }
    
    public function remove($column, $value, $single=TRUE)
    {
        $result = parent::remove($column, $value, $single);
        
        $this->rebuild_tree(0, 0, 0);
        
        return $result;
    }
    
    
    /**
     * Method to rebuild the tree left/right/level values using the modified preorder traversal approach
     *
     * This assumes the table has the following columns to support the tree structure:
     * id, parent_id, lft, rght, level
     * 
     * @example $this->rebuild_tree(0, 0, 0);
     * @param mixed $parent Parent Id to Start With
     * @param int $left Starting Left Value
     * @param int $level Starting Level Value
     */
    private function rebuild_tree($documentNode, $left, $level)
    {
        // the right value of this node is the left value + 1
        $right = $left+1;
        
        // Check if document has been passed, or starting out
        if ($documentNode == 0) {
            $parentId = $this->startParentValue;
        } else {
            $parentId = $documentNode[$this->primaryColumn];
        }
        
        // Get All Children 
        $result = $this->collection->find(function ($query) use ($parentId) {
            $query->where($this->parentTreeColumn, $parentId);
            
            if (!empty($this->itemOrderColumn)) {
                $query->orderBy($this->itemOrderColumn);
            }            
        });
        
        // Loop through Children
        if (!empty($result)) {
        
            foreach ($result as $row)
            {
                // recursive execution of this function for each
                // child of this node
                // $right is the current right value, which is
                // incremented by the rebuild_tree function
                $right = $this->rebuild_tree($row, $right, $level+1);
            }
        }
        
        // we've got the left value, and now that we've processed
        // the children of this node we also know the right value
        if ($documentNode != 0) {
            $result = $this->updateDocument($documentNode, array($this->lftTreeColumn=>$left, $this->rghtTreeColumn=>$right, $this->levelTreeColumn=>$level), FALSE);
        }
        
        // return the right value of this node + 1
        return $right+1;
    }
    
    public function getFormSelectOptions($currentId = FALSE)
    {
        $items = $this->getAll($this->lftTreeColumn, 'asc');
        
        $options = array();
        
        if ($currentId == FALSE) {
            $hasDisabled = FALSE;
        } else {
            
            $node = $this->getRow($this->primaryColumn, $currentId);
            
            if ($node == NULL) {
                $hasDisabled = FALSE;
            } else {
                $hasDisabled = TRUE;
            }
            
            
        }
        
        foreach ($items as $item)
        {
            $prepend = str_repeat('- ', $item[$this->levelTreeColumn] - 1);
            $optionArr = array('label'=>$prepend.$item[$this->itemOrderColumn], 'value'=>$item[$this->primaryColumn]);
            
            // Make the current node and all its children disabled
            if ($hasDisabled && $item[$this->lftTreeColumn] >= $node[$this->lftTreeColumn] && $item[$this->rghtTreeColumn] <= $node[$this->rghtTreeColumn]) {
                $optionArr['disabled'] = 'disabled';
            }
            
            $options[] = $optionArr;
        }
        
        return $options;
    }
    
    
    /*****************************/
    
    public function getTree($topParentId = 0)
    {
        $where = '';
        
        if ($topParentId != $this->startParentValue) {
            // @todo
            $topGroup = $this->getRow($this->primaryColumn, $topParentId);
            
            if ($topGroup == FALSE) {
                die('Group does not exists - <a href="/orphans">Check for Orphans</a>');
            }

            $items = $this->collection->find(function ($query) use ($topGroup){
                
                $query->whereGte($this->lftTreeColumn, $topGroup[$this->lftTreeColumn]);
                $query->whereLte($this->rghtTreeColumn, $topGroup[$this->rghtTreeColumn]);
            });
        } else {
            $items = $this->getAll($this->lftTreeColumn, 'asc');
        }
        
        $return = array();
        $alias = array();
        
        foreach ($items as $row)
        {
            $record = array(
                'id'=>$row['_id'],
                'primary'=>$row[$this->primaryColumn],
                'name'=>$row[$this->itemOrderColumn],
                'left'=>$row[$this->lftTreeColumn],
                'right'=>$row[$this->rghtTreeColumn],
                'children'=>array()
            );
            
            if ($row[$this->parentTreeColumn] == $topParentId) {
                $return['t_'.$row['_id']] = $record;
                $alias['t_'.$row[$this->primaryColumn]] =& $return['t_'.$row['_id']];
            } else {
                $alias['t_'.$row[$this->parentTreeColumn]]['children']['t_'.$row['_id']] = $record;
                $alias['t_'.$row[$this->primaryColumn]] =& $alias['t_'.$row[$this->parentTreeColumn]]['children']['t_'.$row['_id']];
            }
        }
        
        return $return;
    }
    
    public function displayTree($topParentId=0, $url='')
    {
        $tree = $this->getTree($topParentId);
        
        if (empty($tree)) {
            return '';
        }
        
        $str = '<ul class="tree">'; // @todo - move this to the options
        
        foreach ($tree as $node)
        {
            $str .= $this->drillTree($node, $url);
        }
        
        $str .= '</ul>';
        
        return $str;
    }
    
    protected function drillTree($node, $url)
    {
        $str = '<li><span>';
        
        if (!empty($url)) {
            $str .= '<a href="'.str_replace('[-ID-]', $node['primary'], $url).'">';
        }
        
        $str .= htmlspecialchars($node['name']);
        
        if (!empty($url)) {
            $str .= '</a>';
        }
        
        $str .= '</span>';
        
        if (count($node['children']) > 0) {
            $str .= '<ul>';
            
            foreach($node['children'] as $child) {
                $str .= $this->drillTree($child, $url);
            }
            
            $str .= '</ul>';
        }
        
        $str .= '</li>';
        
        return $str;
    }
    
    public function getParents($document, $includeCurrent=FALSE)
    {
        return $this->collection->find(function ($query) use ($document, $includeCurrent){
                
                if ($includeCurrent) {
                    $query->whereLte($this->lftTreeColumn, $document[$this->lftTreeColumn]);
                    $query->whereGte($this->rghtTreeColumn, $document[$this->rghtTreeColumn]);
                } else {
                    $query->whereLt($this->lftTreeColumn, $document[$this->lftTreeColumn]);
                    $query->whereGt($this->rghtTreeColumn, $document[$this->rghtTreeColumn]);
                }
                
                $query->orderBy($this->lftTreeColumn);
            });
    }
    
}