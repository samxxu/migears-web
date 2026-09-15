<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

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
