<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccess;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
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

    private const FINANCIAL_QUERY_FIELDS = [
        'total_gross',
        'total_before_additions',
        'total_tax',
        'total_fee',
        'total_refunded',
        'total_sales_gross',
    ];

    /**
     * @param  class-string<BaseResource>  $resource
     * @param  class-string<IsSortable|IsFilterable>  $domainObject
     */
    protected function filterableResourceResponse(
        string $resource,
        Collection|DomainObjectInterface|LengthAwarePaginator $data,
        string $domainObject,
        int $statusCode = ResponseCodes::HTTP_OK,
    ): JsonResponse {
        $metaFields = [];
        $withheldFields = $this->isWithheldFromCurrentUser(Permission::VIEW_FINANCIALS) ? self::FINANCIAL_QUERY_FIELDS : [];

        if (is_a($domainObject, IsFilterable::class, true)) {
            $metaFields['allowed_filter_fields'] = array_values(array_diff($domainObject::getAllowedFilterFields(), $withheldFields));
        }

        if (is_a($domainObject, IsSortable::class, true)) {
            $metaFields['allowed_sorts'] = array_diff_key($domainObject::getAllowedSorts()->toArray(), array_flip($withheldFields));
            $metaFields['default_sort'] = $domainObject::getDefaultSort();
            $metaFields['default_sort_direction'] = $domainObject::getDefaultSortDirection();
        }

        return $this->resourceResponse($resource, $data, $statusCode, $metaFields);
    }

    /**
     * @param  class-string<BaseResource>  $resource
     */
    protected function resourceResponse(
        string $resource,
        Collection|DomainObjectInterface|LengthAwarePaginator|BaseDTO|Paginator|BaseDataObject $data,
        int $statusCode = ResponseCodes::HTTP_OK,
        array $meta = [],
        array $headers = [],
        array $errors = [],
    ): JsonResponse {
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
        int $statusCode = ResponseCodes::HTTP_BAD_REQUEST,
        array $errors = [],
    ): JsonResponse {
        return $this->jsonResponse([
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    protected function jsonResponse(
        mixed $data,
        int $statusCode = ResponseCodes::HTTP_OK,
        bool $wrapInData = false,
    ): JsonResponse {
        if ($wrapInData) {
            $data = [
                'data' => $data,
            ];
        }

        return new JsonResponse($data, $statusCode);
    }

    protected function xmlResponse(
        string $xmlContent,
        int $statusCode = ResponseCodes::HTTP_OK,
        array $headers = [],
    ): LaravelResponse {
        $defaultHeaders = [
            'Content-Type' => 'application/xml',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        return Response::make($xmlContent, $statusCode, $allHeaders);
    }

    protected function isActionAuthorized(int $entityId, string $entityType): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->isActionAuthorized(
            $entityId,
            $entityType,
            $this->getAuthenticatedUser(),
            $this->getAuthenticatedAccountId(),
        );
    }

    protected function isOrganizerReadAuthorized(int $organizerId): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->isOrganizerReadAuthorized(
            $organizerId,
            $this->getAuthenticatedUser(),
            $this->getAuthenticatedAccountId(),
        );
    }

    protected function isAccountReadAuthorized(int $accountId): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->isAccountReadAuthorized(
            $accountId,
            $this->getAuthenticatedUser(),
            $this->getAuthenticatedAccountId(),
        );
    }

    protected function getAuthenticatedAccess(): AccountUserAccess
    {
        $access = app(AccountUserAccessService::class)->forCurrentUser();

        if ($access === null) {
            throw new UnauthorizedException;
        }

        return $access;
    }

    protected function isWithheldFromCurrentUser(Permission $permission): bool
    {
        return app(AccountUserAccessService::class)->isWithheldFromCurrentUser($permission);
    }

    protected function requirePermission(Permission $permission): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->validatePermission($permission, $this->getAuthenticatedUser());
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

        throw new UnauthorizedException;
    }

    protected function getAuthenticatedUser(): UserDomainObject|DomainObjectInterface
    {
        if (Auth::check()) {
            /** @var AuthUserService $service */
            $service = app(AuthUserService::class);

            return $service->getUser();
        }

        throw new UnauthorizedException;
    }

    protected function isUserAuthenticated(): bool
    {
        return Auth::check();
    }

    protected function getAuthTokenId(): ?string
    {
        return app(AuthUserService::class)->getSessionTokenId();
    }

    protected function isImpersonating(): bool
    {
        return app(AuthUserService::class)->isImpersonating();
    }

    protected function impersonationForbiddenResponse(string $message): JsonResponse
    {
        return $this->jsonResponse([
            'message' => $message,
            'error_code' => 'IMPERSONATION_NOT_ALLOWED',
        ], ResponseCodes::HTTP_FORBIDDEN);
    }

    protected function requireSuperAdmin(): void
    {
        /** @var IsAuthorizedService $authService */
        $authService = app()->make(IsAuthorizedService::class);

        $authService->validateSuperAdmin($this->getAuthenticatedUser());
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
        $params = $request->query->all();

        if ($this->isWithheldFromCurrentUser(Permission::VIEW_FINANCIALS)) {
            $params['filter_fields'] = array_diff_key((array) ($params['filter_fields'] ?? []), array_flip(self::FINANCIAL_QUERY_FIELDS));

            if (in_array($params['sort_by'] ?? null, self::FINANCIAL_QUERY_FIELDS, true)) {
                unset($params['sort_by'], $params['sort_direction']);
            }
        }

        return QueryParamsDTO::fromArray($params);
    }

    public function isIncludeRequested(Request $request, string $include): bool
    {
        return in_array($include, explode(',', $request->query('include', '')), true);
    }
}
