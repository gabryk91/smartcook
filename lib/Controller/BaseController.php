<?php

declare(strict_types=1);

namespace OCA\SmartCook\Controller;

use OCA\SmartCook\AppInfo\Application;
use OCA\SmartCook\Exception\SmartCookException;
use OCA\SmartCook\Exception\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller {
    public function __construct(IRequest $request, private LoggerInterface $logger) {
        parent::__construct(Application::APP_ID, $request);
    }

    protected function respond(callable $callback, int $successStatus = Http::STATUS_OK): JSONResponse {
        try {
            $value = $callback();
            return new JSONResponse($value ?? ['ok' => true], $successStatus);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage(), 'errors' => $e->getErrors()], $e->getHttpStatus());
        } catch (SmartCookException $e) {
            return new JSONResponse(['error' => $e->getMessage()], $e->getHttpStatus());
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('SmartCook request failed', ['app' => Application::APP_ID, 'exception' => $e]);
            return new JSONResponse(['error' => 'An unexpected SmartCook error occurred'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /** @return array<string, mixed> */
    protected function payload(string $key): array {
        $value = $this->request->getParam($key, null);
        if (is_array($value)) {
            return $value;
        }
        $params = $this->request->getParams();
        unset($params['_route'], $params['requesttoken']);
        return is_array($params) ? $params : [];
    }
}
