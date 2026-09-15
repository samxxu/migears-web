<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\Users;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $page = $request->query['page'] ?? 1;
        return Response::json([
            'users' => [['id' => 1], ['id' => 2]],
            'page' => (int) $page,
        ]);
    }

    public function POST(Request $request): Response
    {
        return Response::json(['id' => 42, 'name' => $request->body['name'] ?? ''], 201);
    }
}
