<?php

/**
 * @see    https://github.com/barbushin/multirequest
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */

namespace com\extremeidea\bidorbuy\storeintegrator\php\multirequest;

/**
 * Class MultiRequestQueue.
 *
 * @package com\extremeidea\bidorbuy\storeintegrator\multirequest
 */
class MultiRequestQueue
{

    protected $requests = array();

    /**
     * Add request to queue.
     *
     * @param MultiRequestRequest $request request
     *
     * @return void
     */
    public function push(MultiRequestRequest $request)
    {
        $this->requests[] = $request;
    }

    /**
     * Get first element from request array.
     *
     * @return mixed
     */
    public function pop()
    {
        return array_shift($this->requests);
    }

    /**
     * Get requests count.
     *
     * @return int
     */
    public function count()
    {
        return count($this->requests);
    }

    /**
     * Delete all requests.
     *
     * @return void
     */
    public function clear()
    {
        $this->requests = array();
    }
}
