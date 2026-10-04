<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\regions\___region___;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class ___location___ extends AbstractResource
{
    public function __construct(private string $region, private string $location) {}

    public function GET(Request $request): Response
    {
        return Response::json([
            'region' => $this->region,
            'location' => $this->location,
        ]);
    }
}
