<?php

/**
 * @see    https://github.com/barbushin/multirequest
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */

namespace com\extremeidea\bidorbuy\storeintegrator\php\multirequest;

/**
 * Class MultiRequestCallbacks.
 *
 * @package com\extremeidea\bidorbuy\storeintegrator\multirequest
 */
class MultiRequestCallbacks
{

    protected $callbacks;

    /**
     * @param string   $name     name
     * @param callable $callback callback
     *
     * @return void
     *
     * @throws \Exception
     */
    public function add($name, $callback)
    {
        if (!is_callable($callback)) {
            $callbackName = $callback;
            
            if (is_array($callback)) {
                $callbackName =
                    (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '::' . $callback[1];
            }

            throw new \Exception('Callback "' . $callbackName . '" with name "' . $name . '" is not callable');
        }
        $this->callbacks[$name][] = $callback;
    }

    /**
     * Call functions added by add method.
     *
     * @param string $name      function name
     * @param array  $arguments function args
     *
     * @return void
     */
    public function call($name, $arguments)
    {
        if (isset($this->callbacks[$name])) {
            foreach ($this->callbacks[$name] as $callback) {
                call_user_func_array($callback, $arguments);
            }
        }
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
        $this->call($method, $arguments);
    }
}
