<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\users\___user_id___;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class posts extends AbstractResource
{
    public function __construct(private string $user_id) {}

    public function GET(Request $request): Response
    {
        return Response::json([
            'user_id' => $this->user_id,
            'posts' => [['id' => 101], ['id' => 102]],
        ]);
    }
}
