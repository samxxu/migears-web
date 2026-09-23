<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * REST resource base class.
 *
 * Resource classes extend this class and implement the corresponding HTTP methods
 * (GET/POST/PUT/DELETE, etc., uppercase).
 * Methods accept a Request and return a Response.
 *
 * Service registry: use $this->service(PDO::class) to access registered services.
 * Only register things that need configuration (PDO, Redis, Logger, etc.).
 */
abstract class AbstractResource
{
    /**
     * Named parameters from the resource path (parsed from URL wildcards).
     *
     * @var array<string, string>
     */
    protected array $params = [];

    /**
     * Remaining path segments after the located resource (only populated for
     * catch-all resources; empty otherwise).
     *
     * @var list<string>
     */
    protected array $remaining = [];

    private MiRest $rest;

    /**
     * Set named parameters (called by MiRest).
     *
     * @param array<string, string> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Set remaining path segments (called by MiRest for catch-all resources).
     *
     * @param list<string> $remaining
     */
    public function setRemaining(array $remaining): void
    {
        $this->remaining = $remaining;
    }

    /**
     * Set the MiRest instance (called by MiRest during resource creation).
     */
    public function setRest(MiRest $rest): void
    {
        $this->rest = $rest;
    }

    /**
     * Get a registered service by ID (class name or custom string).
     * Returns null if the service is not registered.
     *
     * Only services registered via MiRest::set() are available.
     * For things that don't need configuration, just use `new`.
     */
    protected function service(string $id): mixed
    {
        return $this->rest->service($id);
    }

    /**
     * Get a named route parameter.
     */
    protected function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Validate a value as an integer. Returns the int on success,
     * throws a 404 ResourceNotFoundException on failure.
     */
    protected function assertInt(mixed $value, string $name): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            throw new ResourceNotFoundException("Invalid integer for '$name'");
        }
        return $int;
    }

    /**
     * Handle the request: before → HTTP method → after.
     * This is a template method; subclasses generally do not need to override it.
     */
    public function handle(Request $request, string $method): Response
    {
        $method = strtoupper($method);

        if (null !== $response = $this->before($request)) {
            return $response;
        }

        if (!method_exists($this, $method)) {
            return Response::json(['error' => 'Method Not Allowed'], 405);
        }

        $response = $this->$method($request);

        return $this->after($request, $response);
    }

    /**
     * Before hook.
     * Returning a Response short-circuits execution (it is returned directly as the response,
     * and the method is not called).
     * Returning null continues execution.
     */
    protected function before(Request $request): ?Response
    {
        return null;
    }

    /**
     * After hook.
     * May modify or replace the response object.
     */
    protected function after(Request $request, Response $response): Response
    {
        return $response;
    }

    /**
     * Default GET method: 405 Method Not Allowed.
     */
    public function GET(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /**
     * Default POST method: 405.
     */
    public function POST(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /**
     * Default PUT method: 405.
     */
    public function PUT(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /**
     * Default DELETE method: 405.
     */
    public function DELETE(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /**
     * Default PATCH method: 405.
     */
    public function PATCH(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /**
     * OPTIONS method: returns Allow header by default.
     */
    public function OPTIONS(Request $request): Response
    {
        $methods = $this->getAllowedMethods();
        return new Response(
            status: 204,
            headers: ['Allow' => implode(', ', $methods)],
        );
    }

    /**
     * HEAD method: same as GET by default but without a body.
     */
    public function HEAD(Request $request): Response
    {
        $response = $this->GET($request);
        return new Response(
            body: '',
            status: $response->status,
            headers: $response->headers,
        );
    }

    /**
     * Get the HTTP methods supported by this resource (used for the Allow header).
     *
     * @return list<string>
     */
    protected function getAllowedMethods(): array
    {
        $methods = ['OPTIONS', 'HEAD'];
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $reflection = new \ReflectionMethod($this, $method);
            if ($reflection->getDeclaringClass()->getName() !== self::class) {
                $methods[] = $method;
            }
        }
        return $methods;
    }
}
