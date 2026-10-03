<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\regions\___region___;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class ___location___ extends AbstractResource
{
    public function GET(Request $request): Response
    {
        return Response::json([
            'region' => $this->param('region'),
            'location' => $this->param('location'),
        ]);
    }
}
