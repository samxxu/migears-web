<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests\Fixtures\Resources;

use TinyGears\Web\AbstractResource;
use TinyGears\Web\Request;
use TinyGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        return Response::json(['message' => 'Hello from root']);
    }

    public function POST(Request $request): Response
    {
        return Response::json(['received' => $request->body], 201);
    }
}
