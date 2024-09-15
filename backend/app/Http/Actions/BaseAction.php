<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\AuthUserService;
use HiEvents\Services\Infrastructure\Authorization\IsAuthorizedService;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

abstract class BaseAction extends Controller
{
    use ValidatesRequests;

    /**
     * @param class-string<BaseResource> $resource
     * @param Collection|DomainObjectInterface|LengthAwarePaginator $data
     * @param int $statusCode
     * @param class-string<IsSortable|IsFilterable> $domainObject
     * @return JsonResponse
     */
    protected function filterableResourceResponse(
        string                                                $resource,
        Collection|DomainObjectInterface|LengthAwarePaginator $data,
        string                                                $domainObject,
        int                                                   $statusCode = ResponseCodes::HTTP_OK,
    ): JsonResponse
    {
        $metaFields = [];

        if (is_a($domainObject, IsFilterable::class, true)) {
            $metaFields['allowed_filter_fields'] = $domainObject::getAllowedFilterFields();
        }

        if (is_a($domainObject, IsSortable::class, true)) {
            $metaFields['allowed_sorts'] = $domainObject::getAllowedSorts()->toArray();
            $metaFields['default_sort'] = $domainObject::getDefaultSort();
            $metaFields['default_sort_direction'] = $domainObject::getDefaultSortDirection();
        }

        return $this->resourceResponse($resource, $data, $statusCode, $metaFields);
    }

    /**
     * @param class-string<BaseResource> $resource
     * @param Collection|DomainObjectInterface|LengthAwarePaginator|BaseDTO|Paginator $data
     * @param int $statusCode
     * @param array $meta
     * @param array $headers
     * @return JsonResponse
     */
    protected function resourceResponse(
        string                                                                  $resource,
        Collection|DomainObjectInterface|LengthAwarePaginator|BaseDTO|Paginator $data,
        int                                                                     $statusCode = ResponseCodes::HTTP_OK,
        array                                                                   $meta = [],
        array                                                                   $headers = [],
        array                                                                   $errors = [],
    ): JsonResponse
    {
        if ($data instanceof Collection || $data instanceof Paginator) {
            $additional = array_filter([
                'meta' => $meta ?? null,
                'errors' => $errors ?? null,
            ]);
            $response = ($resource::collection($data)->additional($additional))
                ->response()
                ->setStatusCode($statusCode);
        } else {
            $response = (new $resource($data))
                ->response()
                ->setStatusCode($statusCode);
        }

        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }

        return $response;
    }

    protected function noContentResponse(int $status = ResponseCodes::HTTP_NO_CONTENT): LaravelResponse
    {
        return Response::noContent($status);
    }

    protected function deletedResponse(): LaravelResponse
    {
        return Response::noContent();
    }

    protected function notFoundResponse(): LaravelResponse
    {
        return Response::noContent(ResponseCodes::HTTP_NOT_FOUND);
    }

    protected function errorResponse(
        string $message,
        int    $statusCode = ResponseCodes::HTTP_BAD_REQUEST,
        array  $errors = [],
    ): JsonResponse
    {
        return $this->jsonResponse([
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    protected function jsonResponse(mixed $data, $statusCode = ResponseCodes::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $statusCode);
    }

    protected function isActionAuthorized(
        int    $entityId,
        string $entityType,
        Role   $minimumRole = Role::ORGANIZER
    ): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->isActionAuthorized(
            $entityId,
            $entityType,
            $this->getAuthenticatedUser(),
            $this->getAuthenticatedAccountId(),
            $minimumRole
        );
    }

    protected function getAuthenticatedAccountId(): int
    {
        if (Auth::check()) {
            /** @var AuthUserService $service */
            $service = app(AuthUserService::class);
            $accountId = $service->getAuthenticatedAccountId();

            if ($accountId === null) {
                throw new UnauthorizedException(__('No account ID found in token'));
            }

            return $accountId;
        }

        throw new UnauthorizedException();
    }

    protected function getAuthenticatedUser(): UserDomainObject|DomainObjectInterface
    {
        if (Auth::check()) {
            /** @var AuthUserService $service */
            $service = app(AuthUserService::class);
            return $service->getUser();
        }

        throw new UnauthorizedException();
    }

    protected function isUserAuthenticated(): bool
    {
        return Auth::check();
    }

    protected function minimumAllowedRole(Role $minimumRole): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->validateUserRole($minimumRole, $this->getAuthenticatedUser());
    }

    public function getClientIp(Request $request): ?string
    {
        // If the request is coming from a DigitalOcean load balancer, use the connecting IP
        if ($digitalOceanIp = $request->server('HTTP_DO_CONNECTING_IP')) {
            return $digitalOceanIp;
        }

        return $request->getClientIp();
    }

    public function getPaginationQueryParams(Request $request): QueryParamsDTO
    {
        return QueryParamsDTO::fromArray($request->query->all());
    }

    public function isIncludeRequested(Request $request, string $include): bool
    {
        return in_array($include, explode(',', $request->query('include', '')), true);
    }
}
