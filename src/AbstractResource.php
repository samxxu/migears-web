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
 * Container: use $this->resolve(PDO::class) to reach a registered entry.
 * Only register things that need configuration (PDO, Redis, Logger, DAOs, ...).
 */
abstract class AbstractResource
{
    /**
     * The HTTP verbs a request may dispatch to.
     *
     * A request whose verb is not on this list is answered with 405 and never
     * reaches the class: method_exists() is case-insensitive, so without this
     * guard an internal helper could be invoked as if it were a handler.
     *
     * @var list<string>
     */
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

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
     * Resolve a registered entry by ID (class name or custom string).
     *
     * Named resolve() rather than get() on purpose: PHP method names are
     * case-insensitive, so a get() here would collide with the HTTP GET() verb.
     *
     * Only ids registered via MiRest::set() are available; for things that need
     * no configuration, just use `new`.
     *
     * @throws NotFoundException when nothing is registered under $id
     */
    protected function resolve(string $id): mixed
    {
        return $this->rest->get($id);
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
     *
     * Only the verbs in HTTP_METHODS are dispatchable; anything else is answered
     * with 405 without consulting the class.
     */
    public function handle(Request $request, string $method): Response
    {
        $method = strtoupper($method);

        if (null !== $response = $this->before($request)) {
            return $response;
        }

        if (!in_array($method, self::HTTP_METHODS, true) || !method_exists($this, $method)) {
            return $this->methodNotAllowed();
        }

        $response = $this->$method($request);

        return $this->after($request, $response);
    }

    /**
     * Build a 405 Method Not Allowed response with an Allow header.
     *
     * RFC 9110 §15.5.6 requires the Allow header on 405 responses.
     */
    private function methodNotAllowed(): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405)
            ->withHeader('Allow', implode(', ', $this->getAllowedMethods()));
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
        return $this->methodNotAllowed();
    }

    /**
     * Default POST method: 405.
     */
    public function POST(Request $request): Response
    {
        return $this->methodNotAllowed();
    }

    /**
     * Default PUT method: 405.
     */
    public function PUT(Request $request): Response
    {
        return $this->methodNotAllowed();
    }

    /**
     * Default DELETE method: 405.
     */
    public function DELETE(Request $request): Response
    {
        return $this->methodNotAllowed();
    }

    /**
     * Default PATCH method: 405.
     */
    public function PATCH(Request $request): Response
    {
        return $this->methodNotAllowed();
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
