<?php

namespace Omnibus\SfExpress\Tests;

use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Model\TrackingStatus;
use Omnibus\SfExpress\Api;
use Omnibus\SfExpress\SfExpressGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SfExpressGatewayTest extends TestCase
{
    private array $calls = [];

    private static function shipment(): Shipment
    {
        return new Shipment(new Address('张三', ['福田区深南大道1000号', '', '广东省'], '518000', '深圳市', 'CN', 'Glitch Art', phone: '13800000000'), new Address('李四', ['朝阳区建国路88号', '', '北京市'], '100022', '北京市', 'CN', phone: '13900000000'), [new Parcel(1500, 30, 20, 10, 20000, 'CNY')], reference: 'ORDER-1042');
    }

    private function gateway(): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('https://sfapi-sbox.sf-express.com/std/service', $url);
            parse_str((string) $options['body'], $form);
            $this->calls[] = $form + ['msg' => json_decode($form['msgData'] ?? '{}', true)];
            $ok = static fn (array $msgData) => new MockResponse(json_encode(['apiResultCode' => 'A1000', 'apiResultData' => json_encode(['success' => true, 'errorCode' => 'S0000', 'msgData' => $msgData], \JSON_UNESCAPED_UNICODE)]));

            return match ($form['serviceCode'] ?? '') {
                'EXP_RECE_CREATE_ORDER' => $ok(['orderId' => 'ORDER-1042', 'waybillNoInfoList' => [['waybillType' => 1, 'waybillNo' => 'SF1234567890123']], 'filterResult' => 2]),
                'COM_RECE_CLOUD_PRINT_WAYBILLS' => $ok(['obj' => ['files' => [['url' => 'https://sfapi-sbox.sf-express.com/print/SF1234567890123.pdf', 'content' => base64_encode('%PDF-1.4 sf')]]]]),
                'EXP_RECE_SEARCH_ROUTES' => $ok(['routeResps' => [['mailNo' => 'SF1234567890123', 'routes' => [['acceptTime' => '2026-10-02 11:30:00', 'acceptAddress' => '北京市', 'remark' => '已签收，感谢使用顺丰', 'opCode' => '80'], ['acceptTime' => '2026-10-01 17:00:00', 'acceptAddress' => '深圳市', 'remark' => '顺丰速运 已收取快件', 'opCode' => '50']]]]]),
                'EXP_RECE_UPDATE_ORDER' => $ok(['orderId' => 'SF1234567890123', 'resStatus' => '2']),
                default => new MockResponse(json_encode(['apiResultCode' => 'A1001', 'apiErrorMsg' => 'Unknown service'])),
            };
        });

        return (new SfExpressGatewayFactory($http))->create(['partner_id' => 'GLITCH01', 'checkword' => 'cw', 'monthly_card' => '7551234567', 'sandbox' => true]);
    }

    public function testTheDigestSignsTheMessage(): void
    {
        self::assertSame(base64_encode(md5(urlencode('{"a":1}1700000000000cw'), true)), Api::digest('{"a":1}', '1700000000000', 'cw'));
    }

    public function testAnOrderIsCreatedWithItsWaybillAndLabel(): void
    {
        $label = $this->gateway()->ship(self::shipment());
        self::assertSame('SF1234567890123', $label->trackingNumber);
        self::assertSame('%PDF-1.4 sf', $label->content);
        $form = $this->calls[0];
        self::assertSame('GLITCH01', $form['partnerID']);
        self::assertSame(Api::digest($form['msgData'], $form['timestamp'], 'cw'), $form['msgDigest']);
        self::assertSame('7551234567', $form['msg']['monthlyCard']);
        self::assertSame(1, $form['msg']['expressTypeId']);
        self::assertSame('广东省', $form['msg']['contactInfoList'][0]['province']);
        self::assertSame(2, $form['msg']['contactInfoList'][1]['contactType']);
    }

    public function testTrackingAndCancel(): void
    {
        $gateway = $this->gateway();
        $tracking = $gateway->track('SF1234567890123', 'zh');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame(TrackingStatus::IN_TRANSIT, $tracking->events[0]->status);
        self::assertSame('北京市', $tracking->latest()->location);
        self::assertTrue($gateway->cancel('SF1234567890123'));
    }

    public function testARefusedCallIsRaisedWithItsCode(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['apiResultCode' => 'A1002', 'apiErrorMsg' => '签名错误'])));
        try {
            (new SfExpressGatewayFactory($http))->create(['partner_id' => 'x', 'checkword' => 'y'])->track('SF1');
            self::fail('raised');
        } catch (CarrierException $e) {
            self::assertSame('A1002', $e->carrierCode);
        }
    }
}
