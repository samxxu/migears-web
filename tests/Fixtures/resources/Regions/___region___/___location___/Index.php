<?php

declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\Regions\Region\Location;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        return Response::json([
            'region' => $this->param('region'),
            'location' => $this->param('location'),
        ]);
    }
}