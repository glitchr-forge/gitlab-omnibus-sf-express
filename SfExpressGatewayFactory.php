<?php

namespace Omnibus\SfExpress;

use Omnibus\Core\GatewayFactory;

class SfExpressGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'sf-express',
            'omnibus.factory_title' => 'SF Express'
        ]);
    }
}