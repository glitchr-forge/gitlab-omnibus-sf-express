<?php

namespace Omnibus\SfExpress\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;
use Omnibus\SfExpress\Api;

/** EXP_RECE_SEARCH_ROUTES: the waybill's routes, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call('EXP_RECE_SEARCH_ROUTES', ['language' => str_starts_with($request->locale, 'zh') ? '0' : '1', 'trackingType' => '1', 'trackingNumber' => [$request->trackingNumber], 'methodType' => '1']);
        $events = [];
        foreach ($data['routeResps'][0]['routes'] ?? [] as $route) {
            $events[] = new TrackingEvent(new \DateTimeImmutable((string) ($route['acceptTime'] ?? 'now'), new \DateTimeZone('Asia/Shanghai')), self::status($route['opCode'] ?? null, $route['remark'] ?? null), (string) ($route['remark'] ?? ''), $route['acceptAddress'] ?? null, $route['opCode'] ?? null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $request->setResult(new TrackingModel('sf_express', $request->trackingNumber, $events ? $events[array_key_last($events)]->status : TrackingStatus::UNKNOWN, $events));
    }

    private static function status(?string $code, ?string $remark): TrackingStatus
    {
        $r = (string) $remark;

        return match (true) {
            '80' === $code || str_contains($r, '已签收') || str_contains($r, 'signed') || str_contains($r, 'delivered') => TrackingStatus::DELIVERED,
            '44' === $code || str_contains($r, '派件') || str_contains($r, 'out for delivery') => TrackingStatus::OUT_FOR_DELIVERY,
            \in_array($code, ['70', '99'], true) || str_contains($r, '退回') || str_contains($r, 'return') => TrackingStatus::RETURNED,
            '33' === $code || str_contains($r, '异常') || str_contains($r, 'exception') || str_contains($r, 'unsuccessful') => TrackingStatus::EXCEPTION,
            \in_array($code, ['50', '54'], true) || str_contains($r, '已收取') || str_contains($r, 'picked up') || str_contains($r, 'collected') => TrackingStatus::IN_TRANSIT,
            \in_array($code, ['30', '31', '36', '604'], true) || str_contains($r, '已发出') || str_contains($r, '到达') || str_contains($r, 'arrived') || str_contains($r, 'departed') => TrackingStatus::IN_TRANSIT,
            '' === (string) $code && '' === $r => TrackingStatus::UNKNOWN,
            default => TrackingStatus::IN_TRANSIT,
        };
    }
}
