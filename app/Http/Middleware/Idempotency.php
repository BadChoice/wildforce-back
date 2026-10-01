<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Idempotency
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
                'code' => 'idempotency_key_required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($key) > 255) {
            return response()->json([
                'message' => 'The Idempotency-Key header may not be greater than 255 characters.',
                'code' => 'idempotency_key_too_long',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $scope = $this->scope($request);
        $requestHash = $this->requestHash($request);
        $cacheKey = $this->cacheKey($scope, $key);
        $record = $this->reserve($cacheKey, $requestHash);

        if ($record instanceof Response) {
            return $record;
        }

        try {
            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'status' => 'completed',
                    'response_status' => $response->getStatusCode(),
                    'response_headers' => $response->headers->all(),
                    'response_body' => $response->getContent(),
                    'request_hash' => $requestHash,
                ], now()->addDay());
            } else {
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    /**
     * @return array{status: string, request_hash: string}|Response
     */
    private function reserve(string $cacheKey, string $requestHash): array|Response
    {
        $pending = ['status' => 'pending', 'request_hash' => $requestHash];

        if (Cache::add($cacheKey, $pending, now()->addDay())) {
            return $pending;
        }

        /** @var array{status?: string, request_hash?: string, response_status?: int, response_headers?: array<string, list<string>>, response_body?: string}|null $record */
        $record = Cache::get($cacheKey);

        if (! is_array($record) || ! isset($record['request_hash'])) {
            return response()->json([
                'message' => 'The idempotency request state could not be read. Please retry.',
                'code' => 'idempotency_state_unavailable',
            ], Response::HTTP_CONFLICT);
        }

        if (! hash_equals($record['request_hash'], $requestHash)) {
            return response()->json([
                'message' => 'The Idempotency-Key has already been used with a different request.',
                'code' => 'idempotency_key_reused',
            ], Response::HTTP_CONFLICT);
        }

        if ($record['status'] === 'completed') {
            return response(
                $record['response_body'] ?? '',
                $record['response_status'] ?? Response::HTTP_OK,
                $record['response_headers'] ?? [],
            );
        }

        return response()->json([
            'message' => 'A request with this Idempotency-Key is already in progress.',
            'code' => 'idempotency_request_in_progress',
        ], Response::HTTP_CONFLICT)->header('Retry-After', '1');
    }

    private function scope(Request $request): string
    {
        $actor = $request->user()?->getAuthIdentifier() ?? 'guest:'.$request->ip();
        $route = $request->route()?->uri() ?? $request->path();

        return hash('sha256', implode('|', [$actor, $request->method(), $route]));
    }

    private function requestHash(Request $request): string
    {
        return hash('sha256', ($request->getQueryString() ?? '')."\n".$request->getContent());
    }

    private function cacheKey(string $scope, string $key): string
    {
        return 'idempotency:'.$scope.':'.hash('sha256', $key);
    }
}
