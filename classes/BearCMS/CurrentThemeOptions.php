<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS;

/**
 * Data structure with array access containing all theme options
 */
class CurrentThemeOptions implements \ArrayAccess, \Iterator
{

    /**
     * Options data
     * 
     * @var array 
     */
    private $data = array();

    /**
     * The constructor
     * 
     * @param array $data
     * @throws \InvalidArgumentException
     */
    public function __construct($data)
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('');
        }
        $this->data = $data;
    }

    /**
     * Cannot modify theme options
     * 
     * @param strint $offset
     * @param mixed $value
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception('Cannot modify theme options');
    }

    /**
     * Checks whether a option is set
     * 
     * @param string $offset
     * @return boolean TRUE if the option is set, FALSE otherwise
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Cannot modify theme options
     * 
     * @param strint $offset
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception('Cannot modify theme options');
    }

    /**
     * Returns the value of the option specified
     * 
     * @param string $offset
     * @return mixed The value of the option specified or null
     */
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Iterator helper method
     */
    function rewind()
    {
        reset($this->data);
    }

    /**
     * Iterator helper method
     */
    function current()
    {
        return current($this->data);
    }

    /**
     * Iterator helper method
     */
    function key()
    {
        return key($this->data);
    }

    /**
     * Iterator helper method
     */
    function next()
    {
        next($this->data);
    }

    /**
     * Iterator helper method
     */
    function valid()
    {
        return isset($this->data[key($this->data)]);
    }

}