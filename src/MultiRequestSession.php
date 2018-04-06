<?php

/**
 * @see    https://github.com/barbushin/multirequest
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */

namespace com\extremeidea\bidorbuy\storeintegrator\php\multirequest;

/**
 * Class MultiRequestSession.
 *
 * @package com\extremeidea\bidorbuy\storeintegrator\multirequest
 */
class MultiRequestSession
{

    /**
     * @var MultiRequestDefaults
     */
    protected $requestsDefaults;

    /**
     * @var MultiRequestCallbacks
     */
    protected $callbacks;

    protected $mrHandler;
    protected $cookiesFilepath;
    protected $lastRequest;
    protected $enableAutoStart;
    protected $enableAutoReferer;
    protected $requestsDelay;

    /**
     * MultiRequestSession constructor.
     *
     * @param MultiRequestHandler $mrHandler          handler instance
     * @param string              $cookiesBasedir     dir for save cookie
     * @param bool                $enableAutoReferrer enable referrer
     * @param int                 $requestsDelay      request delay
     */
    public function __construct(
        MultiRequestHandler $mrHandler,
        $cookiesBasedir,
        $enableAutoReferrer = 0,
        $requestsDelay = 0
    ) {
        $this->callbacks = new MultiRequestCallbacks();
        $this->mrHandler = $mrHandler;
        $this->enableAutoReferer = $enableAutoReferrer;
        $this->requestsDelay = $requestsDelay;
        $this->requestsDefaults = new MultiRequestDefaults();
        $this->cookiesFilepath = tempnam($cookiesBasedir, '_');
    }

    /**
     * Get Handler instance.
     *
     * @return MultiRequestHandler
     */
    public function getMrHandler()
    {
        return $this->mrHandler;
    }

    /**
     * Build request.
     *
     * @param string $url request url
     *
     * @return MultiRequestRequest
     */
    public function buildRequest($url)
    {
        $request = new MultiRequestRequest($url);
        $request->_session = $this;
        return $request;
    }

    /**
     * @return MultiRequestDefaults
     */
    public function requestsDefaults()
    {
        return $this->requestsDefaults;
    }

    /**
     * Add callback when request complete.
     *
     * @param callable $callback callback
     *
     * @return $this
     */
    public function onRequestComplete($callback)
    {
        $this->callbacks->add(__FUNCTION__, $callback);
        return $this;
    }

    /**
     * Request complete notify.
     *
     * @param MultiRequestRequest $request request
     * @param MultiRequestHandler $mrHandler handler
     *
     * @return void
     */
    public function notifyRequestIsComplete(MultiRequestRequest $request, MultiRequestHandler $mrHandler)
    {
        $this->lastRequest = $request;
        $this->callbacks->onRequestComplete($request, $this, $mrHandler);
    }

    /**
     * Start queue
     *
     * @return void
     */
    public function start()
    {
        $this->enableAutoStart = true;
        $this->mrHandler->start();
    }

    /**
     * Stop queue
     *
     * @return void
     */
    public function stop()
    {
        $this->enableAutoStart = false;
    }

    /**
     * Set cookie and prepare request.
     *
     * @param MultiRequestRequest $request request
     *
     * @return void
     */
    public function request(MultiRequestRequest $request)
    {
        if ($this->requestsDelay) {
            sleep($this->requestsDelay);
        }
        $request->onComplete(array(
            $this,
            'notifyRequestIsComplete'
        ));

        $this->requestsDefaults->applyToRequest($request);
        $request->setCookiesStorage($this->cookiesFilepath);
        if ($this->enableAutoReferer && $this->lastRequest) {
            $request->setCurlOption(CURLOPT_REFERER, $this->lastRequest->getUrl());
        }

        $this->mrHandler->pushRequestToQueue($request);
        if ($this->enableAutoStart) {
            $this->mrHandler->start();
        }
    }

    /**
     * Delete cookie
     *
     * @return void
     */
    public function clearCookie()
    {
        if (file_exists($this->cookiesFilepath)) {
            unlink($this->cookiesFilepath);
        }
    }

    /**
     * Destruct
     *
     * @return void
     */
    public function __destruct()
    {
        $this->clearCookie();
    }
}
