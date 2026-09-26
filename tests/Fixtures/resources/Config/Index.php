<?php

declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources\Config;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        // an unregistered id is an assembly mistake: resolve() throws rather than
        // handing back null, so the failure happens here and not three calls later
        $config = $this->resolve('config');

        return Response::json([
            'app_name' => $config['app_name'] ?? null,
        ]);
    }
}
