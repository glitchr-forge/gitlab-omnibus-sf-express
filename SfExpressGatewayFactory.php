<?php

namespace Omnibus\SfExpress;

use Omnibus\Config;
use Omnibus\Exception\InvalidConfigException;
use Omnibus\GatewayFactory;
use Omnibus\SfExpress\Action\CancelAction;
use Omnibus\SfExpress\Action\ShippingAction;
use Omnibus\SfExpress\Action\TrackingAction;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     partner_id: '%env(SF_PARTNER_ID)%'      # the open platform's customer code (顾客编码)
 *     checkword: '%env(SF_CHECKWORD)%'        # its checkword (校验码)
 *     monthly_card: '%env(SF_MONTHLY_CARD)%'  # the monthly account (月结卡号) that pays
 *     sandbox: true
 *     rates: [...]                            # prices from configuration: SF quotes by contract
 *
 * No pickup points. Unverified until an account's keys are at hand.
 */
final class SfExpressGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'sf-express',
            'omnibus.factory_title' => 'SF Express',
            'omnibus.required_options' => ['partner_id', 'checkword'],
            'monthly_card' => null,
            'sandbox' => false,
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? (class_exists(HttpClient::class) ? HttpClient::create() : throw new InvalidConfigException('The "sf-express" gateway needs symfony/http-client.'));

                return new Api($http, (string) $c['partner_id'], (string) $c['checkword'], $c['monthly_card'] ?: null, (bool) $c['sandbox']);
            },
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => new TrackingAction(),
            'omnibus.action.cancel' => new CancelAction(),
        ]);
    }
}
