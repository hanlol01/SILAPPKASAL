<?php

namespace App\Http\Middleware;

use App\Services\SecurityAccessDeniedLogger;
use App\Support\SensitiveAuditOperation;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditSensitiveAuthorizationDenials
{
    public const HANDLED_ATTRIBUTE = 'audit_denial_terminal_recorded';

    public function __construct(private readonly SecurityAccessDeniedLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $operationCode = SensitiveAuditOperation::fromRouteName($request->route()?->getName());

        try {
            return $next($request);
        } catch (AuthorizationException $exception) {
            $this->recordUnlessHandled($request, $operationCode);

            throw $exception;
        } catch (HttpResponseException $exception) {
            if ($exception->getResponse()->getStatusCode() === 403) {
                $this->recordUnlessHandled($request, $operationCode);
            }

            throw $exception;
        }
    }

    private function recordUnlessHandled(Request $request, ?string $operationCode): void
    {
        if (! $request->attributes->get(self::HANDLED_ATTRIBUTE)) {
            $this->logger->record($request, operationCode: $operationCode);
        }
    }
}
