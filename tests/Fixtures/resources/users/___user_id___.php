<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\users;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class ___user_id___ extends AbstractResource
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
}
