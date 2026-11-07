<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/** Base dos controllers do Workspace: resolve o vínculo do usuário com a empresa. */
abstract class WorkspaceBaseController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected readonly WorkspaceAccess $access,
        protected readonly NotificationService $notifications,
    ) {}

    protected function membership(Request $request, Hotel $hotel): ?HotelUser
    {
        return $this->access->membership($request->user(), $hotel);
    }

    protected function link(Hotel $hotel, string $path): string
    {
        return "/w/{$hotel->slug}/{$path}";
    }
}
