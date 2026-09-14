<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests\Fixtures\Resources\Users\UserId\Posts;

use TinyGears\Web\AbstractResource;
use TinyGears\Web\Request;
use TinyGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $userId = $this->params['user_id'] ?? '0';
        return Response::json([
            'user_id' => $userId,
            'posts' => [['id' => 101], ['id' => 102]],
        ]);
    }
}
