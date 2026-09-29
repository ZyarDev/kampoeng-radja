<?php

namespace App\Http\Middleware;

use App\Support\CmsAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageCms
{
    public function __construct(private readonly CmsAccess $access)
    {
    }

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->access->for($request->user())['canManage'], Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
