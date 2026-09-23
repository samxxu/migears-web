<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\Catchall;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class CatchAllResource extends AbstractResource
{
    public function GET(Request $request): Response
    {
        return Response::json([
            'caught' => true,
            'path' => $request->path,
            'params' => $this->params,
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
