<?php

namespace Omnibus\SfExpress;

use Omnibus\Exception\CarrierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SF Express's open platform (sfapi.sf-express.com): one endpoint, the
 * service name and a JSON payload posted as form fields, signed with
 * msgDigest = base64(md5(urlencode(msgData + timestamp + checkword))).
 */
final class Api
{
    public const LIVE = 'https://bspgw.sf-express.com/std/service';
    public const TEST = 'https://sfapi-sbox.sf-express.com/std/service';

    public function __construct(
        private readonly HttpClientInterface $http,
        public readonly string $partnerId,
        private readonly string $checkword,
        public readonly ?string $monthlyCard = null,
        public readonly bool $sandbox = false,
        private readonly int $timeout = 20,
    ) {
    }

    /** @return array<string, mixed> the apiResultData, decoded */
    public function call(string $service, array $data): array
    {
        $msgData = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $timestamp = (string) (int) (microtime(true) * 1000);
        try {
            $response = $this->http->request('POST', $this->sandbox ? self::TEST : self::LIVE, [
                'body' => [
                    'partnerID' => $this->partnerId,
                    'requestID' => bin2hex(random_bytes(16)),
                    'serviceCode' => $service,
                    'timestamp' => $timestamp,
                    'msgDigest' => self::digest($msgData, $timestamp, $this->checkword),
                    'msgData' => $msgData,
                ],
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $envelope = json_decode($response->getContent(false), true);
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw new CarrierException('sf-express', 'SF Express request failed: '.$e->getMessage(), null, $e);
        }
        if ($status >= 400 || !\is_array($envelope)) {
            throw new CarrierException('sf-express', sprintf('SF Express answered HTTP %d.', $status));
        }
        if ('A1000' !== ($envelope['apiResultCode'] ?? null)) {
            throw new CarrierException('sf-express', (string) ($envelope['apiErrorMsg'] ?? 'SF Express refused the call.'), isset($envelope['apiResultCode']) ? (string) $envelope['apiResultCode'] : null);
        }
        $result = json_decode((string) ($envelope['apiResultData'] ?? '{}'), true);
        if (!\is_array($result)) {
            throw new CarrierException('sf-express', 'SF Express answered with a result that is not JSON.');
        }
        if (isset($result['success']) && !$result['success']) {
            throw new CarrierException('sf-express', (string) ($result['errorMsg'] ?? 'SF Express refused the request.'), isset($result['errorCode']) ? (string) $result['errorCode'] : null);
        }

        return $result['msgData'] ?? $result;
    }

    public static function digest(string $msgData, string $timestamp, string $checkword): string
    {
        return base64_encode(md5(urlencode($msgData.$timestamp.$checkword), true));
    }
}
