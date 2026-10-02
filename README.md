# omnibus/sf-express

SF Express (顺丰速运) for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): orders
with their waybills and labels (EXP_RECE_CREATE_ORDER, cloud print), tracking
(EXP_RECE_SEARCH_ROUTES) and cancellation (EXP_RECE_UPDATE_ORDER) - the open platform's signed
API. Prices come from configuration (`rates`): SF quotes by contract.

```yaml
omnibus:
    gateways:
        sf_express:
            factory: sf_express
            options:
                partner_id: '%env(SF_PARTNER_ID)%'      # 顾客编码
                checkword: '%env(SF_CHECKWORD)%'        # 校验码
                monthly_card: '%env(SF_MONTHLY_CARD)%'  # 月结卡号
                sandbox: true
                rates: [...]
```

The service is the express type id (1 标快, 2 特快, 5 顺丰次晨, 6 顺丰即日...). Addresses: the
third street line carries the province. Shipment options: `description`, `instructions`,
`template` (the cloud print template code). No pickup points.

Credentials: an account on [SF's open platform](https://open.sf-express.com) gives the customer
code and checkword (sandbox first) and your monthly account number.

Built from SF's published open platform documentation and tested on recorded answers;
**unverified** against the sandbox until an account's keys are at hand.

License: LGPL-3.0-or-later.
