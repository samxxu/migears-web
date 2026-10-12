<?php
declare(strict_types=1);

namespace MiGears\Web;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * MiRest — a minimalist REST framework.
 *
 * Core feature: file-as-routing. No need to define a route table;
 * a resource file's path is the endpoint it answers.
 *
 * It is also the container: register only what needs configuration
 * (PDO, Redis, Logger, each DAO, each Manager). Everything else is just `new`.
 * The container speaks PSR-11 — `Psr\Container\ContainerInterface` — so a
 * Manager or a resource reaches it without any bespoke interface, and this
 * package and migears/manager stay independent of each other.
 *
 * Usage:
 *   $rest = new MiRest(__DIR__ . '/resources', 'App\\Resources');
 *   $rest->set(LoggerInterface::class, fn() => new NullLogger()); // required; NullLogger for silence
 *   $rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));
 *   $rest->set(OrderManager::class, static fn() => new OrderManager($rest));
 *   $response = $rest->handle(Request::fromGlobals());
 *   $response->send();
 */
class MiRest implements ContainerInterface
{
    public const VERSION = '2.3.0';

    /** The constructor parameter a `__catch_remaining__.php` receives the leftover path as. */
    private const REMAINING_ARGUMENT = 'remaining';

    private ?ResourceLocator $locator = null;

    /** @var array<string, callable(): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, array{container: bool, params: list<string>}> */
    private array $constructors = [];

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
    ) {
        if ($baseDir === '' || !is_dir($baseDir)) {
            throw new \InvalidArgumentException("Resource base directory must be an existing directory: $baseDir");
        }
    }

    /**
     * Create the resource locator. Override to supply a custom subclass
     * (e.g. one that changes how URL segments map to file names via nameFor()).
     */
    protected function createLocator(): ResourceLocator
    {
        return new ResourceLocator($this->baseDir);
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
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
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
        if (array_key_exists($id, $this->instances)) {
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
     *
     * The logger is resolved from the container first, before dispatch starts:
     * a missing or wrong registration must fail loudly here instead of being
     * hidden behind an internal default. That resolution is deliberate and sits
     * outside the guards below, so when nothing (or the wrong type) is
     * registered under LoggerInterface the exception escapes handle() rather
     * than becoming a response. Register LoggerInterface at bootstrap —
     * NullLogger if you want silence.
     *
     * Dispatch runs next, then the global after hooks run once. Both stages are
     * guarded, so once the logger is resolved, handle() returns a Response on
     * every path: a throwing after hook is logged and answered with an error
     * response rather than escaping the method, and it can never run twice.
     */
    public function handle(Request $request): Response
    {
        $logger = $this->get(LoggerInterface::class);
        if (!$logger instanceof LoggerInterface) {
            throw new \RuntimeException(
                "The container entry '" . LoggerInterface::class . "' must be a Psr\\Log\\LoggerInterface instance"
            );
        }

        try {
            $response = $this->dispatch($request);
        } catch (ResourceNotFoundException $e) {
            $response = $this->handleNotFound();
        } catch (\Throwable $e) {
            $logger->error($e->getMessage(), ['exception' => $e]);
            $response = $this->handleError($e);
        }

        try {
            return $this->runAfter($request, $response);
        } catch (\Throwable $e) {
            $logger->error('An after hook threw: ' . $e->getMessage(), ['exception' => $e]);

            return $this->handleError($e);
        }
    }

    /**
     * Run the global before hooks, locate the resource and dispatch to it.
     */
    private function dispatch(Request $request): Response
    {
        // Global before hooks
        foreach ($this->beforeHandlers as $handler) {
            if (null !== $response = $handler($request)) {
                return $response;
            }
        }

        // Locate resource
        $result = ($this->locator ??= $this->createLocator())->locate($request->path);
        if ($result === null) {
            return $this->handleNotFound();
        }

        [$shortClassName, $filePath, $params, $remaining, $namespaceSegments] = $result;

        // Build fully qualified class name and load the file
        $className = $this->buildClassName($shortClassName, $namespaceSegments);
        $this->loadResourceClass($className, $filePath);

        // Instantiate resource, handing it the container, the captured parameters and
        // whatever is left of the path; the resource declares which of them it wants.
        $resource = $this->createResource($className, $params, $remaining);

        // Call the resource's handle method (template method: before → method → after)
        return $resource->handle($request, $request->method);
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
     * Load the resource class declared in the file the locator found.
     *
     * The located file has to be the one that declares the class. Falling back to
     * the autoloader would answer the route with a class from a file the locator
     * never found, and class_exists($name, false) alone only says that *some*
     * file has already declared the class. Loading the wrong one is reported
     * rather than papered over.
     *
     * A resource file name doubles as its class name, so a segment that is a PHP
     * keyword (`/new`, `/list`, `/match`) makes the file a parse error. That
     * ParseError is turned into a readable message instead of a blank 500.
     */
    private function loadResourceClass(string $className, string $filePath): void
    {
        if (!class_exists($className, false)) {
            try {
                require_once $filePath;
            } catch (\ParseError $e) {
                throw new \RuntimeException(
                    "The resource file {$filePath} cannot be parsed. A resource file name is also its class "
                    . "name, so a PHP keyword such as /new, /list, /class or /match is rejected by the parser; "
                    . "rename the resource (the plural form works: /news, /lists, /classes). "
                    . "Parser: {$e->getMessage()}",
                    0,
                    $e
                );
            }

            // The located file has to be the one that declares the class. Falling
            // back to the autoloader here would answer the route with a class from
            // a file the locator never found.
            if (!class_exists($className, false)) {
                throw new \RuntimeException(
                    "The resource file {$filePath} was located for {$className} but does not declare it"
                );
            }

            return;
        }

        $declaredIn = (new \ReflectionClass($className))->getFileName();
        if ($declaredIn === false || realpath($declaredIn) !== realpath($filePath)) {
            throw new \RuntimeException(
                "Ambiguous resource: {$className} is declared in {$declaredIn} "
                . "but {$filePath} was located; two resource files map to the same class name"
            );
        }
    }

    /**
     * Create a resource instance. The contract — what arrives, and when — is stated
     * once, on AbstractResource; this is the call site that honours it.
     *
     * The captured values always go in, by name, so a constructor that does not
     * declare one of them is an assembly mistake: PHP's message names the offending
     * parameter, and what this call site adds is the context, because one call here
     * serves every route. The container goes in positionally, in front of them — it
     * cannot travel by name, since PHP forbids unpacking after named arguments. The
     * leftover path goes in by name, and only when the constructor declares it.
     *
     * @param array<string, string> $params
     * @param list<string> $remaining
     */
    private function createResource(string $className, array $params, array $remaining): AbstractResource
    {
        if (!class_exists($className, false)) {
            throw new \RuntimeException("Resource class not found: $className");
        }

        $constructor = $this->constructor($className);

        $arguments = $params;
        if (in_array(self::REMAINING_ARGUMENT, $constructor['params'], true)) {
            if (array_key_exists(self::REMAINING_ARGUMENT, $params)) {
                throw new \RuntimeException(
                    "Cannot build {$className}: a wildcard captured a parameter called '"
                    . self::REMAINING_ARGUMENT . "', which is the name the leftover path is handed over as. "
                    . 'Rename that wildcard, or drop the parameter from the constructor.'
                );
            }
            $arguments[self::REMAINING_ARGUMENT] = $remaining;
        }

        try {
            $resource = $constructor['container']
                ? new $className($this, ...$arguments)
                : new $className(...$arguments);
        } catch (\Error $e) {
            throw new \RuntimeException(
                "Cannot build {$className}: the route captured ["
                . implode(', ', array_keys($params)) . "] but constructing the resource failed. "
                . $e->getMessage(),
                0,
                $e
            );
        }

        if (!$resource instanceof AbstractResource) {
            throw new \RuntimeException("Resource must extend AbstractResource: $className");
        }
        return $resource;
    }

    /**
     * What the resource's constructor declares, reflected once per class and
     * remembered like the container's own entries.
     *
     * `container` is true when the first parameter is typed `ContainerInterface`, or
     * any type implementing it — the type is the request, and that position is where
     * it goes. `params` are the declared names, so a value is only handed over by name
     * when the constructor actually declares it.
     *
     * @return array{container: bool, params: list<string>}
     */
    private function constructor(string $className): array
    {
        if (isset($this->constructors[$className])) {
            return $this->constructors[$className];
        }

        $constructor = (new \ReflectionClass($className))->getConstructor();
        $parameters = $constructor === null ? [] : $constructor->getParameters();

        $names = [];
        foreach ($parameters as $parameter) {
            $names[] = $parameter->getName();
        }

        $type = $parameters === [] ? null : $parameters[0]->getType();

        return $this->constructors[$className] = [
            'container' => $type instanceof \ReflectionNamedType
                && !$type->isBuiltin()
                && is_a($type->getName(), ContainerInterface::class, true),
            'params' => $names,
        ];
    }
}
