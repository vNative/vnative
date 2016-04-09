<?php

/**
 * @author Hemant Mann
 */
use Framework\Registry as Registry;
class Website extends Shared\Model{
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @validate required
     * @index
     */
    protected $_user_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     * 
     * @validate required, min(3), max(32)
     * @label category
     */
    protected $_category = "";

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     * 
     * @validate required, min(3), max(100)
     * @label Property Name
     */
    protected $_name;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 64
     * @index
     *
     * @validate required, min(5)
     * @label Google Analytics ID
     */
    protected $_gaid;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label url
     */
    protected $_url;

    public function campaign() {
        $collection = Registry::get("MongoDB")->;
        
        $records = $collection->find(array("website_id" => (int) $this->id));
        if (isset($records)) {
            echo "<pre>", print_r($records), "</pre>";
        }
    }
}
