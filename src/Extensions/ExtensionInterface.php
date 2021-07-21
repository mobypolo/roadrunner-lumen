<?php

declare(strict_types=1);

namespace mobypolo\RoadRunnerLumen\Extensions;

use mobypolo\RoadRunnerLumen\Config;
use mobypolo\RoadrunnerLumen\WorkerError;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Lumen\Application;

interface ExtensionInterface
{
    /**
     * Executed right after receiving request from the RR server, allows full interception of requests.
     *
     * If this method returns true, it MUST either throw an exception or correctly handle request by using $client
     *
     * @param Application $application
     * @param PSR7Worker $client
     * @param ServerRequestInterface $request
     * @return bool True if this extension handled the request
     */
    public function handleRequest(Application $application, PSR7Worker $client, ServerRequestInterface $request): bool;

    /**
     * Executed before request is transformed into HttpFoundation-style request
     *
     * @param Application $application
     * @param ServerRequestInterface $request
     */
    public function beforeRequest(Application $application, ServerRequestInterface $request): void;

    /**
     * Executed after response is sent to RR server.
     *
     * It doesn't get called when either unhandled exception was thrown or request was handled by handleRequest extension
     *
     * @param Application $application
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return bool Should worker terminate after this request?
     */
    public function afterRequest(
        Application $application,
        ServerRequestInterface $request,
        ResponseInterface $response
    ): bool;

    /**
     * Executed before passing request to Lumen
     *
     * @param Application $application
     * @param Request $request
     */
    public function beforeHandle(Application $application, Request $request): void;

    /**
     * Executed after receiving response from Lumen
     *
     * @param Application $application
     * @param Request $request
     * @param Response $response
     */
    public function afterHandle(Application $application, Request $request, Response $response): void;

    /**
     * Executed when worker's init method is called
     *
     * @param Application $application
     * @param Config $config
     * @return mixed
     */
    public function init(Application $application, Config $config): void;

    /**
     * Executed when unhandled exception was thrown.
     *
     * Returning PSR-7 response or throwable object allows to override default behavior of passing thrown error to RR
     *
     * @param Application $application
     * @param ServerRequestInterface $request
     * @param WorkerError $e
     * @return WorkerError
     */
    public function error(Application $application, ServerRequestInterface $request, WorkerError $e): WorkerError;

    /**
     * Executed before request loop is started
     *
     * @param Container $application
     * @return mixed
     */
    public function beforeLoop(Application $application): void;

    /**
     * Executed after request loop has ended
     *
     * @param Application $application
     * @return mixed
     */
    public function afterLoop(Application $application): void;
}
