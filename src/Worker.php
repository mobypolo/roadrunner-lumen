<?php

declare(strict_types=1);

namespace mobypolo\RoadRunnerLumen;

use mobypolo\RoadRunnerLumen\Extensions\ExtensionInterface;
use mobypolo\RoadRunnerLumen\Extensions\ExtensionStack;
use Illuminate\Http\Request;
use Laravel\Lumen\Application;
use Spiral\Goridge\RPC;
use Spiral\RoadRunner\PSR7Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

class Worker
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ExtensionInterface
     */
    protected $extensionStack;

    /**
     * Worker constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Initialize worker
     *
     * Loads Lumen application and initializes extensions
     */
    public function init(): void
    {
        $this->app = $this->createApplication();

        $this->extensionStack = $this->createExtensionStack();
        $this->extensionStack->init($this->app, $this->config);
    }


    /**
     * Serves HTTP requests
     */
    public function serve(): void
    {

        $worker = \Spiral\RoadRunner\Worker::create();
        $psrFactory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $worker = new \Spiral\RoadRunner\Http\PSR7Worker($worker, $psrFactory, $psrFactory, $psrFactory);

        $requestBridge = new HttpFoundationFactory;
        $responseBridge = $this->createResponseBridge();

        $this->extensionStack->beforeLoop($this->app);

        while ($req = $worker->waitRequest()) {
            try {
                // allows full interception of requests by extensions
                $handled = $this->extensionStack->handleRequest($this->app, $worker, $req);
                if ($handled) {
                    continue;
                }

                $this->extensionStack->beforeRequest($this->app, $req);
                $request = Request::createFromBase($requestBridge->createRequest($req));

                $this->extensionStack->beforeHandle($this->app, $request);
                $response = $this->app->handle($request);
                $this->extensionStack->afterHandle($this->app, $request, $response);

                $content = null;
                if($response->getContent()){
                    $content = $response->getContent();
                } else {
                    $content = $response->getFile()->getContent();
                }

                $rsp = new \Nyholm\Psr7\Response(
                    $response->getStatusCode(),
                    $response->headers->all(),
                    $content,
                    $response->getProtocolVersion());
                $worker->respond($rsp);

                $psrResponse = $responseBridge->createResponse($response);

                if ($this->extensionStack->afterRequest($this->app, $req, $psrResponse)) {
                    $worker->stop();
                }
            } catch (\Throwable $e) {
                $worker->getWorker()->error((string)$e);
            }
        }
        $this->extensionStack->afterLoop($this->app);
    }

    /**
     * @return Application
     */
    protected function createApplication(): Application
    {
        return require $this->config->getBootstrapFilePath();
    }

    /**
     * @return ExtensionInterface
     */
    protected function createExtensionStack(): ExtensionInterface
    {
        $extensions = $this->app->tagged(ExtensionInterface::class);
        $extensions = is_array($extensions) ? $extensions : iterator_to_array($extensions);

        return new ExtensionStack($extensions);
    }

    /**
     * @return RPC|null
     */
    protected function createRpc(): ?RPC
    {
        if ($this->config->getRpcRelay() != null) {
            return new RPC($this->config->getRpcRelay());
        }

        return null;
    }

    /**
     * @param \Spiral\RoadRunner\Worker $worker
     * @return PSR7Client
     */
    protected function createPsr7Client(\Spiral\RoadRunner\Worker $worker): PSR7Client
    {
        $factory = new Psr17Factory();

        return new PSR7Client($worker, $factory, $factory, $factory);
    }

    /**
     * @return HttpMessageFactoryInterface
     */
    protected function createResponseBridge(): PsrHttpFactory
    {
        $factory = new Psr17Factory();

        return new PsrHttpFactory($factory, $factory, $factory, $factory);
    }
}
