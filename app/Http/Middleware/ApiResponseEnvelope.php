<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseEnvelope
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $status = $response->getStatusCode();
        $isNoContent = $status === Response::HTTP_NO_CONTENT;
        $isJson = $response instanceof JsonResponse
            || str_contains((string) $response->headers->get('Content-Type'), 'json');

        // API attachment endpoints return file streams and keep their original response.
        if (! $isJson && ! $isNoContent) {
            return $response;
        }

        $payload = $isNoContent ? null : $this->decode($response);
        $envelope = $this->envelope($payload, $status);

        if ($isNoContent) {
            $response->setStatusCode(Response::HTTP_OK);
        }

        $response->headers->remove('Content-Length');
        $response->headers->set('Content-Type', 'application/json');

        if ($response instanceof JsonResponse) {
            $response->setData($envelope);
        } else {
            $response->setContent(json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return $response;
    }

    private function decode(Response $response): mixed
    {
        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        return json_decode($content, true);
    }

    private function envelope(mixed $payload, int $status): array
    {
        $successful = $status >= 200 && $status < 400;
        $payload = is_array($payload) ? $payload : [];
        $message = $payload['message'] ?? ($successful ? 'Request completed successfully.' : 'The request could not be completed.');
        $errors = $payload['errors'] ?? null;

        if (! $successful) {
            return [
                'success' => false,
                'message' => is_string($message) ? $message : 'The request could not be completed.',
                'data' => null,
                'errors' => $errors,
            ];
        }

        if (array_key_exists('success', $payload)
            && array_key_exists('data', $payload)
            && array_key_exists('errors', $payload)) {
            return [
                'success' => true,
                'message' => is_string($message) ? $message : 'Request completed successfully.',
                'data' => $payload['data'],
                'errors' => null,
            ];
        }

        unset($payload['success'], $payload['message'], $payload['errors']);

        if (array_key_exists('data', $payload)) {
            $data = $payload['data'];
            unset($payload['data']);

            if ($payload !== []) {
                if (is_array($data) && array_is_list($data)) {
                    $data = ['items' => $data];
                } elseif (! is_array($data)) {
                    $data = ['resource' => $data];
                }

                $data = array_merge($data, $payload);
            }
        } else {
            $data = $payload === [] ? null : $payload;
        }

        return [
            'success' => true,
            'message' => is_string($message) ? $message : 'Request completed successfully.',
            'data' => $data,
            'errors' => null,
        ];
    }
}
