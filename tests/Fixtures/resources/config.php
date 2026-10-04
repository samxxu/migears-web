<?php
declare(strict_types=1);

namespace MiGears\Web\Tests\Fixtures\Resources;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;
use Psr\Container\ContainerInterface;

class config extends AbstractResource
{
    public function __construct(private ContainerInterface $container) {}

    public function GET(Request $request): Response
    {
        // an unregistered id is an assembly mistake: get() throws rather than
        // handing back null, so the failure happens here and not three calls later
        $config = $this->container->get('config');

        return Response::json([
            'app_name' => $config['app_name'] ?? null,
        ]);
    }
}
