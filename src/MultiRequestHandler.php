<?php

/**
 * @see    https://github.com/barbushin/multirequest
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 */

namespace com\extremeidea\bidorbuy\storeintegrator\php\multirequest;

/**
 * Class MultiRequestHandler.
 *
 * @package com\extremeidea\bidorbuy\storeintegrator\multirequest
 */
class MultiRequestHandler
{

    /**
     * @var MultiRequestDefaults
     */
    protected $requestsDefaults;

    /**
     * @var MultiRequestCallbacks
     */
    protected $callbacks;

    /**
     * @var MultiRequestQueue
     */
    protected $queue;

    protected $connectionsLimit = 60;
    protected $totalTytesTransfered;
    protected $isActive;
    protected $isStarted;
    protected $isStopped;
    protected $activeRequests = array();
    protected $requestingDelay = 0.01;

    /**
     * MultiRequestHandler constructor.
     *
     * @return self
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('CURL extension require to be installed and enabled in PHP');
        }
        $this->queue = new MultiRequestQueue();
        $this->requestsDefaults = new MultiRequestDefaults();
        $this->callbacks = new MultiRequestCallbacks();
    }

    /**
     * Get Queue instance
     *
     * @return MultiRequestQueue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Set Request delay
     *
     * @param integer $milliseconds milliseconds
     *
     * @return void
     */
    public function setRequestingDelay($milliseconds)
    {
        $this->requestingDelay = $milliseconds / 1000;
    }

    /**
     * Set callback when request complete.
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
     * Notify When Request is Complete.
     *
     * @param MultiRequestRequest $request request
     *
     * @return void
     */
    protected function notifyRequestComplete(MultiRequestRequest $request)
    {
        $request->notifyIsComplete($this);
        $this->callbacks->onRequestComplete($request, $this);
    }

    /**
     * Get request default settings.
     *
     * @return MultiRequestDefaults
     */
    public function requestsDefaults()
    {
        return $this->requestsDefaults;
    }

    /**
     * Get active status.
     *
     * @return mixed
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * Get queue start flag.
     *
     * @return mixed
     */
    public function isStarted()
    {
        return $this->isStarted;
    }

    /**
     * Set Connection count
     *
     * @param $connectionsCount
     *
     * @return void
     */
    public function setConnectionsLimit($connectionsCount)
    {
        $this->connectionsLimit = $connectionsCount;
    }

    /**
     * Get requests in queue
     *
     * @return int
     */
    public function getRequestsInQueueCount()
    {
        return $this->queue->count();
    }

    /**
     * Get active requests count.
     *
     * @return int
     */
    public function getActiveRequestsCount()
    {
        return count($this->activeRequests);
    }

    /**
     * Stop execute requests
     *
     * @return void
     */
    public function stop()
    {
        $this->isStopped = true;
    }

    /**
     * Start to execute requests
     *
     * @return void
     */
    public function activate()
    {
        $this->isStopped = false;
        $this->start();
    }

    /**
     * Add request to queue.
     *
     * @param MultiRequestRequest $request
     *
     * @return void
     */
    public function pushRequestToQueue(MultiRequestRequest $request)
    {
        $this->queue->push($request);
    }

    /**
     * Send to multi request
     *
     * @param resource          $mcurlHandle curl multi handler
     * @param MultiRequestRequest $request request
     *
     * @return void
     */
    protected function sendRequestToMultiCurl($mcurlHandle, MultiRequestRequest $request)
    {
        $this->requestsDefaults->applyToRequest($request);
        curl_multi_add_handle($mcurlHandle, $request->getCurlHandle(true));
    }

    /**
     * Execute all requests.
     *
     * @return void
     *
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function start()
    {
        if ($this->isActive || $this->isStopped) {
            return;
        }
        $this->isActive = true;
        $this->isStarted = true;

        try {
            $this->mcurlHandle = $mcurlHandle = curl_multi_init();

            do {
                // send requests from queue to CURL
                if (count($this->activeRequests) < $this->connectionsLimit) {
                    for ($i = $this->connectionsLimit - count($this->activeRequests); $i > 0; $i--) {
                        $request = $this->queue->pop();
                        if (!$request) {
                            break;
                        }

                        $this->sendRequestToMultiCurl($mcurlHandle, $request);
                        $this->activeRequests[$request->getId()] = $request;
                    }
                }

                while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($mcurlHandle, $activeThreads)) {
                    ;
                }

                // check complete requests
                curl_multi_select($mcurlHandle, $this->requestingDelay);
                while ($completeCurlInfo = curl_multi_info_read($mcurlHandle)) {
                    $completeRequestId = MultiRequestRequest::getRequestIdByCurlHandle($completeCurlInfo['handle']);
                    $completeRequest = $this->activeRequests[$completeRequestId];
                    unset($this->activeRequests[$completeRequestId]);
                    curl_multi_remove_handle($mcurlHandle, $completeRequest->getCurlHandle());
                    $completeRequest->handleCurlResult();

                    // check if response code is 301 or 302 and follow location
                    $ignoreNotification = false;
                    $completeRequestCode = $completeRequest->getCode();

                    if ($completeRequestCode == 301 || $completeRequestCode == 302) {
                        //$completeRequestOptions = $completeRequest->getCurlOptions();
                        //if(!empty($completeRequestOptions[CURLOPT_FOLLOWLOCATION])) {
                        $completeRequest->_permanentlyMoved =
                            empty($completeRequest->_permanentlyMoved) ? 1 : $completeRequest->_permanentlyMoved + 1;
                        $responseHeaders = $completeRequest->getResponseHeaders(true);
                        if ($completeRequest->_permanentlyMoved < 5 && !empty($responseHeaders['Location'])) {
                            // figure out whether we're dealign with an absolute or relative redirect
                            // (thanks to kmontag https://github.com/kmontag for this bugfix)
                            $redirectedUrl = (parse_url($responseHeaders['Location'], PHP_URL_SCHEME) === null
                                    ? $completeRequest->getBaseUrl() : '') . $responseHeaders['Location'];
                            $completeRequest->setUrl($redirectedUrl);
                            $completeRequest->reInitCurlHandle();
                            $this->pushRequestToQueue($completeRequest);
                            $ignoreNotification = true;
                        }
                        //}
                    }
                    if (!$ignoreNotification) {
                        $this->notifyRequestComplete($completeRequest);
                    }
                }
            } while (!$this->isStopped && ($this->activeRequests || $this->queue->count()));
        } catch (\Exception $exception) {
        }

        $this->isActive = false;

        if ($mcurlHandle && is_resource($mcurlHandle)) {
            curl_multi_close($mcurlHandle);
        }

        if (!empty($exception)) {
            throw $exception;
        }

        $this->callbacks->onComplete($this);
    }
}
