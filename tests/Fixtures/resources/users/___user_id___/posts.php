<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\users\___user_id___;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class posts extends AbstractResource
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
