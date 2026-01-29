<?php
declare(strict_types=1);

namespace sgoranov\IdentityLinkShared\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class RequestResponseLogger
{
    private const MAX_RESPONSE_LENGTH = 2000;

    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
    }

    #[AsEventListener(event: RequestEvent::class)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $headers = $request->headers->all();

        // Mask sensitive values
        $sensitiveKeys = ['authorization', 'cookie', 'set-cookie'];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $headers[$key] = ['*** MASKED ***'];
            }
        }

        $request->attributes->set('request_log', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'headers' => $headers,
            'body' => $request->getContent(),
        ]);
    }

    #[AsEventListener(event: ResponseEvent::class)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $logData = $request->attributes->get('request_log', []);
        $logData['status'] = $response->getStatusCode();
        $logData = array_merge($logData, $this->appendResponseData($response));

        $this->logger->info('REST API Log', $logData);
    }

    #[AsEventListener(event: ExceptionEvent::class)]
    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $logData = $request->attributes->get('request_log', []);
        $exception = $event->getThrowable();

        if ($response instanceof Response) {
            $logData['status'] = $response->getStatusCode();
            $logData = array_merge($logData, $this->appendResponseData($response));
        } else {
            if ($exception instanceof NotFoundHttpException) {
                $logData['status'] = 404;
            } elseif ($exception instanceof AccessDeniedException) {
                $logData['status'] = 403;
            } elseif ($exception instanceof UnauthorizedHttpException) {
                $logData['status'] = 401;
            } else {
                $logData['status'] = 500;
            }
        }

        $logData['error'] = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];

        $this->logger->error('REST API Log', $logData);
    }

    private function appendResponseData(Response $response): array
    {
        $content = $response->getContent();
        $content = is_string($content) ? $content : '';
        $length = strlen($content);

        $logData = [
            'response_length' => $length,
        ];

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        $contentType = trim(explode(';', $contentType, 2)[0]);

        if ($this->isResponseBodyLoggable($contentType, $length)) {
            $logData['response_body'] = $content;
        }

        return $logData;
    }

    private function isResponseBodyLoggable(string $contentType, int $length): bool
    {
        if ($contentType === '' || $length > self::MAX_RESPONSE_LENGTH) {
            return false;
        }

        if (str_starts_with($contentType, 'application/json') || str_ends_with($contentType, '+json')) {
            return true;
        }

        return false;
    }
}
