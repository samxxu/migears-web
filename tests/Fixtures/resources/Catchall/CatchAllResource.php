<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests\Fixtures\Resources\Catchall;

use TinyGears\Web\AbstractResource;
use TinyGears\Web\Request;
use TinyGears\Web\Response;

class CatchAllResource extends AbstractResource
{
    public function GET(Request $request): Response
    {
        return Response::json([
            'caught' => true,
            'path' => $request->path,
            'params' => $this->params,
        ]);
    }

    public function handleSub(Request $request, array $remaining): Response
    {
        // CatchAll handles all remaining paths directly
        return $this->GET($request);
    }
}
