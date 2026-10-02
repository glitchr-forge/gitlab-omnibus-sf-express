<?php

namespace Omnibus\SfExpress\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;
use Omnibus\SfExpress\Api;

/** EXP_RECE_CREATE_ORDER: the waybill (service: the express type id, 1 标快 by default, 2 特快, 5 顺丰次晨...), then its label from COM_RECE_CLOUD_PRINT_WAYBILLS. */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $orderId = $s->reference ?? 'omnibus-'.bin2hex(random_bytes(6));
        $data = $this->api->call('EXP_RECE_CREATE_ORDER', array_filter([
            'language' => 'zh-CN',
            'orderId' => $orderId,
            'cargoDetails' => array_map(static fn ($p) => array_filter(['name' => $s->option('description', '商品'), 'count' => 1, 'unit' => '件', 'weight' => round(max(0.1, $p->weight / 1000), 2), 'amount' => $p->value ? $p->value / 100 : null, 'currency' => $p->value ? $p->currency : null]), $s->parcels),
            'contactInfoList' => [self::contact($s->sender, 1), self::contact($s->recipient, 2)],
            'expressTypeId' => (int) ($s->service ?? 1),
            'payMethod' => 1,
            'monthlyCard' => $this->api->monthlyCard,
            'parcelQty' => \count($s->parcels),
            'totalWeight' => round(max(0.1, $s->weight() / 1000), 2),
            'isReturnRoutelabel' => 1,
            'remark' => mb_substr((string) $s->option('instructions', ''), 0, 100),
        ], static fn ($v) => null !== $v && '' !== $v));
        $waybills = $data['waybillNoInfoList'] ?? [];
        $number = (string) ($waybills[0]['waybillNo'] ?? '');
        if ('' === $number) {
            throw new CarrierException('sf_express', 'SF Express issued no waybill.');
        }
        $content = null;
        $url = null;
        try {
            $print = $this->api->call('COM_RECE_CLOUD_PRINT_WAYBILLS', ['templateCode' => (string) $s->option('template', 'fm_76130_standard_'.$this->api->partnerId), 'documents' => [['masterWaybillNo' => $number]], 'version' => '2.0', 'fileType' => 'pdf', 'sync' => true]);
            $file = $print['obj']['files'][0] ?? $print['files'][0] ?? [];
            $url = $file['url'] ?? null;
            if (isset($file['content']) && \is_string($file['content'])) {
                $content = base64_decode($file['content']);
            }
        } catch (CarrierException) {
        }
        $request->setResult(new Label('sf_express', $number, $content, Label::PDF, $url, 'https://www.sf-express.com/chn/sc/waybill/waybill-detail/'.rawurlencode($number)));
    }

    private static function contact(Address $a, int $type): array
    {
        return array_filter(['contactType' => $type, 'company' => $a->company, 'contact' => $a->name, 'tel' => $a->phone, 'mobile' => $a->phone, 'country' => strtoupper($a->country), 'province' => $a->line(2) ?: null, 'city' => $a->city, 'address' => trim($a->line(0).' '.$a->line(1)), 'postCode' => $a->postcode, 'email' => $a->email], static fn ($v) => null !== $v && '' !== $v);
    }
}
