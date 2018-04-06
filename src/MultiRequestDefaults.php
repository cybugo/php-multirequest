<?php

/**
 * @see    https://github.com/barbushin/multirequest
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */

namespace com\extremeidea\bidorbuy\storeintegrator\php\multirequest;

/**
 * Class MultiRequestDefaults.
 *
 * @package com\extremeidea\bidorbuy\storeintegrator\multirequest
 */
class MultiRequestDefaults
{

    protected $properties = array();
    protected $methods = array();

    /**
     * Apply to request.
     *
     * @param MultiRequestRequest $request request
     *
     * @return void
     */
    public function applyToRequest(MultiRequestRequest $request)
    {
        foreach ($this->properties as $property => $value) {
            $request->$property = $value;
        }
        foreach ($this->methods as $method => $calls) {
            foreach ($calls as $arguments) {
                call_user_func_array(array(
                    $request,
                    $method
                ), $arguments);
            }
        }
    }

    /**
     * Magic method, set property.
     *
     * @param mixed $property property name
     * @param mixed $value    property value
     *
     * @return void
     */
    public function __set($property, $value)
    {
        $this->properties[$property] = $value;
    }

    /**
     * Magic method.
     *
     * @param string $method    called method
     * @param array  $arguments arguments
     *
     * @return void
     */
    public function __call($method, $arguments = array())
    {
        $this->methods[$method][] = $arguments;
    }
}
