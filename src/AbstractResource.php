<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * REST resource base class.
 *
 * Resource classes extend this class and implement the corresponding HTTP methods
 * (GET/POST/PUT/DELETE, etc., uppercase). Methods accept a Request and return a Response.
 *
 * What a resource needs arrives through its constructor, and only what it declares:
 *
 *   public function __construct(
 *       private ContainerInterface $container,   // optional, and it must come first
 *       private string $user_id,                 // a wildcard this path captured
 *       private array $remaining,                // a __catch_remaining__.php's tail
 *   ) {}
 *
 * The captured values are passed by name unconditionally, so each one the path
 * carries has to be declared; the container is opt-in through the type of the first
 * parameter; `remaining` is opt-in by name. Captured segments are raw and still
 * URL-encoded, so a parameter can only be typed `string`. A resource that wants none
 * of them declares no constructor at all — and anything needing no configuration is
 * simply `new`'d.
 */
abstract class AbstractResource
{
    /**
     * The HTTP verbs a request may dispatch to.
     *
     * A request whose verb is not on this list is answered with 405 and never
     * calls a handler method. It is not dispatched to the class, but it does
     * reach it: the resource is already instantiated and its before() runs
     * first, because the verb is checked only after that hook. This guard keeps
     * method_exists()'s case-insensitivity from invoking an internal helper as
     * if it were a handler.
     *
     * @var list<string>
     */
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

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
     * Which verbs can be dispatched, and what the rest get, is stated on
     * HTTP_METHODS rather than repeated here.
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
