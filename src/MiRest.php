<?php
declare(strict_types=1);

namespace MiGears\Web;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MiRest — a minimalist REST framework.
 *
 * Core feature: directory-as-route. No need to define a route table;
 * the filesystem is the route table.
 *
 * It is also the container: register only what needs configuration
 * (PDO, Redis, Logger, each DAO, each Manager). Everything else is just `new`.
 * The container speaks PSR-11 — `Psr\Container\ContainerInterface` — so a
 * Manager or a resource reaches it without any bespoke interface, and this
 * package and migears/manager stay independent of each other.
 *
 * Usage:
 *   $rest = new MiRest(__DIR__ . '/resources', 'App\\Resources');
 *   $rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));
 *   $rest->set(OrderManager::class, static fn() => new OrderManager($rest));
 *   $response = $rest->handle(Request::fromGlobals());
 *   $response->send();
 */
class MiRest implements ContainerInterface
{
    public const VERSION = '2.0.1';

    private ResourceLocator $locator;
    private LoggerInterface $logger;

    /** @var array<string, callable(): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var list<callable(Request): ?Response> */
    private array $beforeHandlers = [];

    /** @var list<callable(Request, Response): Response> */
    private array $afterHandlers = [];

    /** @var ?callable(): Response */
    private $notFoundHandler = null;

    /** @var ?callable(\Throwable): Response */
    private $errorHandler = null;

    public function __construct(
        private readonly string $baseDir,
        private readonly string $namespace = '',
        ?LoggerInterface $logger = null,
    ) {
        $this->locator = new ResourceLocator($baseDir);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Register an entry. Only for things that need configuration
     * (PDO, Redis, Logger, a DAO, a Manager). Everything else: just `new`.
     *
     * @param string $id Entry ID (class name or custom string)
     * @param callable(): mixed $factory Factory that creates the instance
     */
    public function set(string $id, callable $factory): self
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
        return $this;
    }

    /**
     * Whether an id is registered (PSR-11).
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /**
     * The object registered under $id — the factory runs once, then the instance
     * is cached (PSR-11).
     *
     * @throws NotFoundException when nothing is registered under $id. An
     *                           unregistered id is an assembly mistake, and
     *                           PSR-11 requires the failure to happen here
     *                           rather than as a silent null further downstream
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new NotFoundException("Nothing is registered under '{$id}'");
        }

        return $this->instances[$id] = ($this->factories[$id])();
    }

    /**
     * Add a global before hook. Returning a Response short-circuits execution.
     */
    public function before(callable $handler): self
    {
        $this->beforeHandlers[] = $handler;
        return $this;
    }

    /**
     * Add a global after hook. May modify the response.
     */
    public function after(callable $handler): self
    {
        $this->afterHandlers[] = $handler;
        return $this;
    }

    /**
     * Custom 404 handler.
     */
    public function notFound(callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Custom exception handler.
     */
    public function error(callable $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /**
     * Handle a request and return a response.
     */
    public function handle(Request $request): Response
    {
        try {
            // Global before hooks
            foreach ($this->beforeHandlers as $handler) {
                if (null !== $response = $handler($request)) {
                    return $this->runAfter($request, $response);
                }
            }

            // Locate resource
            $result = $this->locator->locate($request->path);
            if ($result === null) {
                return $this->runAfter($request, $this->handleNotFound());
            }

            [$shortClassName, $filePath, $params, $remaining, $namespaceSegments] = $result;

            // Build fully qualified class name and load the file
            $className = $this->buildClassName($shortClassName, $namespaceSegments);
            if (!class_exists($className, false)) {
                require_once $filePath;
            }

            // Instantiate resource
            $resource = $this->createResource($className);
            $resource->setParams($params);

            // Remaining path → the located resource (e.g. a catch-all) serves
            // the rest of the path through the same template method, so the
            // before/after hooks run consistently.
            if (!empty($remaining)) {
                $resource->setRemaining($remaining);
                $response = $resource->handle($request, $request->method);
                return $this->runAfter($request, $response);
            }

            // Call the resource's handle method (template method: before → method → after)
            $response = $resource->handle($request, $request->method);

            return $this->runAfter($request, $response);

        } catch (ResourceNotFoundException $e) {
            return $this->runAfter($request, $this->handleNotFound());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return $this->runAfter($request, $this->handleError($e));
        }
    }

    /**
     * Run global after hooks.
     */
    private function runAfter(Request $request, Response $response): Response
    {
        foreach ($this->afterHandlers as $handler) {
            $response = $handler($request, $response);
        }
        return $response;
    }

    /**
     * Handle 404.
     */
    private function handleNotFound(): Response
    {
        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)();
        }
        return Response::json(['error' => 'Not Found'], 404);
    }

    /**
     * Handle exceptions.
     */
    private function handleError(\Throwable $e): Response
    {
        if ($this->errorHandler !== null) {
            return ($this->errorHandler)($e);
        }
        return Response::json(['error' => 'Internal Server Error'], 500);
    }

    /**
     * Build the fully qualified class name.
     *
     * @param list<string> $namespaceSegments
     */
    private function buildClassName(string $shortName, array $namespaceSegments): string
    {
        $parts = [];
        if ($this->namespace !== '') {
            $parts[] = trim($this->namespace, '\\');
        }
        foreach ($namespaceSegments as $seg) {
            $parts[] = $seg;
        }
        $parts[] = $shortName;
        return implode('\\', $parts);
    }

    /**
     * Create a resource instance.
     */
    private function createResource(string $className): AbstractResource
    {
        if (!class_exists($className)) {
            throw new \RuntimeException("Resource class not found: $className");
        }
        $resource = new $className();
        if (!$resource instanceof AbstractResource) {
            throw new \RuntimeException("Resource must extend AbstractResource: $className");
        }
        $resource->setRest($this);
        return $resource;
    }
}
