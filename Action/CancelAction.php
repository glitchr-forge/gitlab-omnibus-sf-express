<?php

namespace Omnibus\SfExpress\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Request\Cancel;
use Omnibus\Request\Request;
use Omnibus\SfExpress\Api;

/** EXP_RECE_UPDATE_ORDER with dealType 2: the order cancelled (by its waybill number). */
final class CancelAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $data = $this->api->call('EXP_RECE_UPDATE_ORDER', ['dealType' => 2, 'waybillNoInfoList' => [['waybillNo' => $request->trackingNumber]], 'orderId' => $request->trackingNumber]);
        $request->setResult(\in_array((string) ($data['resStatus'] ?? '2'), ['2'], true));
    }
}
