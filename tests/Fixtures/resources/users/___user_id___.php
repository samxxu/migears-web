<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\users;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class ___user_id___ extends AbstractResource
{
    public function __construct(private string $user_id) {}

    public function GET(Request $request): Response
    {
        return Response::json(['id' => $this->user_id, 'name' => 'User ' . $this->user_id]);
    }

    public function PUT(Request $request): Response
    {
        return Response::json(['id' => $this->user_id, 'updated' => true]);
    }

    public function DELETE(Request $request): Response
    {
        return Response::json(['id' => $this->user_id, 'deleted' => true]);
    }
}
