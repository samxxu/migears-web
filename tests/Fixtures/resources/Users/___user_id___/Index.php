<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\Users\UserId;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;
use MiGears\Web\ResourceNotFoundException;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $id = $this->params['user_id'] ?? '0';
        return Response::json(['id' => $id, 'name' => 'User ' . $id]);
    }

    public function PUT(Request $request): Response
    {
        $id = $this->params['user_id'] ?? '0';
        return Response::json(['id' => $id, 'updated' => true]);
    }

    public function DELETE(Request $request): Response
    {
        $id = $this->params['user_id'] ?? '0';
        return Response::json(['id' => $id, 'deleted' => true]);
    }

    public function handleSub(Request $request, array $remaining): Response
    {
        if ($remaining[0] === 'posts') {
            $resource = new Posts\Index();
            $resource->setParams(['user_id' => $this->params['user_id'] ?? '']);
            array_shift($remaining);
            if (empty($remaining)) {
                $response = $resource->before($request);
                if ($response !== null) return $response;
                $response = $resource->GET($request);
                return $resource->after($request, $response);
            }
            return $resource->handleSub($request, $remaining);
        }
        throw new ResourceNotFoundException();
    }
}
