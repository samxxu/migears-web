<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\docs;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class __catch_remaining__ extends AbstractResource
{
    public function __construct(private array $remaining) {}

    public function GET(Request $request): Response
    {
        return Response::json([
            'path' => $request->path,
            'remaining' => $this->remaining,
        ]);
    }

    protected function after(Request $request, Response $response): Response
    {
        return new Response(
            body: $response->body,
            status: $response->status,
            headers: array_merge($response->headers, ['X-Hooked' => 'yes']),
        );
    }
}
