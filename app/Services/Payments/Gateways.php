<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Gateways
{
    public const DEFINITIONS = [
        'zarinpal' => ['name' => 'زرین‌پال', 'fields' => ['merchant_id' => 'کد پذیرنده'], 'rules' => ['merchant_id' => 'uuid']],
        'saman' => ['name' => 'بانک سامان · سپ', 'fields' => ['terminal_id' => 'شماره ترمینال'], 'rules' => ['terminal_id' => 'digits_between:1,10']],
        'mellat' => ['name' => 'بانک ملت · به‌پرداخت', 'fields' => ['terminal_id' => 'شماره ترمینال', 'username' => 'نام کاربری پذیرنده', 'password' => 'رمز پذیرنده'], 'rules' => ['terminal_id' => 'digits_between:1,19']],
        'melli' => ['name' => 'بانک ملی · سداد', 'fields' => ['terminal_id' => 'شماره ترمینال', 'merchant_id' => 'شماره پذیرنده', 'key' => 'کلید ترمینال Base64'], 'rules' => ['terminal_id' => 'digits_between:1,19', 'merchant_id' => 'digits_between:1,19']],
        'shahr' => ['name' => 'بانک شهر · آسان‌پرداخت', 'fields' => ['merchant_config_id' => 'شناسه تنظیمات پذیرنده', 'username' => 'شناسه کاربری سرویس', 'password' => 'رمز سرویس'], 'rules' => ['merchant_config_id' => 'digits_between:1,10']],
    ];

    private const ZARINPAL = 'https://payment.zarinpal.com';
    private const SAMAN = 'https://sep.shaparak.ir';
    private const MELLAT = 'https://bpm.shaparak.ir/pgwchannel/';
    private const MELLI = 'https://sadad.shaparak.ir/VPG';
    private const SHAHR_API = 'https://ipgrest.asanpardakht.ir/v1/';

    public function available(): Collection
    {
        return PaymentGateway::where('enabled', true)->pluck('provider')
            ->filter(fn ($provider) => isset(self::DEFINITIONS[$provider]))
            ->mapWithKeys(fn ($provider) => [$provider => self::DEFINITIONS[$provider]['name']]);
    }

    public function issue(Payment $payment, string $callback): string
    {
        $credentials = $payment->credentials;
        $id = (string) $payment->id;
        $amount = $payment->amount_rial;

        $token = match ($payment->provider) {
            'zarinpal' => $this->zarinpalToken($credentials['merchant_id'], $amount, $callback, $id),
            'saman' => $this->samanToken($credentials['terminal_id'], $amount, $callback, $id),
            'mellat' => $this->mellatToken($credentials, $amount, $callback, $id),
            'melli' => $this->melliToken($credentials, $amount, $callback, $id),
            'shahr' => $this->shahrToken($credentials, $amount, $callback, $id),
        };

        return $this->requiredText($token);
    }

    public function handoff(Payment $payment): array
    {
        return match ($payment->provider) {
            'zarinpal' => ['url' => self::ZARINPAL.'/pg/StartPay/'.rawurlencode($payment->token), 'fields' => []],
            'saman' => ['url' => self::SAMAN.'/OnlinePG/OnlinePG', 'fields' => ['Token' => $payment->token]],
            'mellat' => ['url' => self::MELLAT.'startpay.mellat', 'fields' => ['RefId' => $payment->token]],
            'melli' => ['url' => self::MELLI.'/Purchase?Token='.rawurlencode($payment->token), 'fields' => []],
            'shahr' => ['url' => 'https://asan.shaparak.ir', 'fields' => ['RefID' => $payment->token]],
        };
    }

    public function returned(Payment $payment, array $data): ?array
    {
        $fields = match ($payment->provider) {
            'zarinpal' => ['Authority', 'Status'],
            'saman' => ['ResNum', 'TerminalId', 'State', 'Status', 'RefNum'],
            'mellat' => ['RefId', 'ResCode', 'SaleOrderId', 'SaleReferenceId'],
            'melli' => ['Token', 'token', 'ResCode', 'OrderId'],
            'shahr' => [],
        };
        $input = [];
        foreach ($fields as $field) {
            $input[$field] = $this->text($data[$field] ?? null);
        }
        $id = (string) $payment->id;
        // Callbacks only identify candidates. All paid states come from the PSP server.
        $matches = match ($payment->provider) {
            'zarinpal' => $input['Status'] === 'OK' && $input['Authority'] === $payment->token,
            'saman' => $input['ResNum'] === $id && $input['TerminalId'] === (string) $payment->credentials['terminal_id']
                && $input['State'] === 'OK' && $input['Status'] === '2' && $input['RefNum'] !== '',
            'mellat' => $input['SaleOrderId'] === $id && $input['RefId'] === $payment->token
                && $input['ResCode'] === '0' && ctype_digit($input['SaleReferenceId']),
            'melli' => ($input['Token'] ?: $input['token']) === $payment->token && $input['ResCode'] === '0'
                && ($input['OrderId'] === '' || $input['OrderId'] === $id),
            // AP resolves the purchase from the invoice on its server.
            'shahr' => true,
        };

        return $matches ? $input : null;
    }

    public function verify(Payment $payment, array $returned): array
    {
        return match ($payment->provider) {
            'zarinpal' => $this->verifyZarinpal($payment),
            'saman' => $this->verifySaman($payment, $returned),
            'mellat' => $this->verifyMellat($payment, $returned),
            'melli' => $this->verifyMelli($payment),
            'shahr' => $this->verifyShahr($payment),
        };
    }

    private function zarinpalToken(string $merchant, int $amount, string $callback, string $id): mixed
    {
        $data = $this->post(self::ZARINPAL.'/pg/v4/payment/request.json', [
            'merchant_id' => $merchant, 'amount' => $amount, 'currency' => 'IRR',
            'description' => 'سفارش برین‌زر '.$id, 'callback_url' => $callback,
        ]);
        $this->assertCode(data_get($data, 'data.code'), [100]);

        return data_get($data, 'data.authority');
    }

    private function verifyZarinpal(Payment $payment): array
    {
        $data = $this->post(self::ZARINPAL.'/pg/v4/payment/verify.json', [
            'merchant_id' => $payment->credentials['merchant_id'], 'amount' => $payment->amount_rial,
            'authority' => $payment->token,
        ]);
        $this->assertCode(data_get($data, 'data.code'), [100, 101]);

        return [
            'reference' => $this->requiredText(data_get($data, 'data.ref_id')),
            'fee_rial' => data_get($data, 'data.fee_type') === 'Merchant' ? max(0, (int) data_get($data, 'data.fee')) : 0,
        ];
    }

    private function samanToken(string $terminal, int $amount, string $callback, string $id): mixed
    {
        $data = $this->post(self::SAMAN.'/OnlinePG/OnlinePG', [
            'action' => 'token', 'TerminalId' => $terminal, 'Amount' => $amount,
            'ResNum' => $id, 'RedirectUrl' => $callback,
        ]);
        $this->assertCode($data['status'] ?? null, [1]);

        return $data['token'] ?? null;
    }

    private function verifySaman(Payment $payment, array $returned): array
    {
        $terminal = $payment->credentials['terminal_id'];
        $data = $this->post(self::SAMAN.'/verifyTxnRandomSessionkey/ipg/VerifyTransaction', [
            'RefNum' => $returned['RefNum'], 'TerminalNumber' => (int) $terminal,
        ]);
        $this->assertCode($data['ResultCode'] ?? null, [0, 2]);
        $detail = $data['TransactionDetail'] ?? [];
        if (($data['ResultCode'] == 0 && ($data['Success'] ?? false) !== true)
            || $this->text($detail['RefNum'] ?? null) !== $returned['RefNum']
            || (int) ($detail['TerminalNumber'] ?? 0) !== (int) $terminal
            || (isset($detail['ResNum']) && $this->text($detail['ResNum']) !== (string) $payment->id)) {
            throw new GatewayFailure('unmatched_transaction');
        }
        $this->assertAmount($detail['OrginalAmount'] ?? null, $payment->amount_rial);

        return ['reference' => $returned['RefNum']];
    }

    private function mellatToken(array $credentials, int $amount, string $callback, string $id): mixed
    {
        $tehran = now('Asia/Tehran');
        $reply = $this->mellatSoap('bpPayRequest', $credentials, [
            'orderId' => $id, 'amount' => $amount, 'localDate' => $tehran->format('Ymd'), 'localTime' => $tehran->format('His'),
            'additionalData' => '', 'callBackUrl' => $callback, 'payerId' => 0,
        ]);
        [$code, $token] = array_pad(explode(',', $reply, 2), 2, null);
        $this->assertCode($code, [0]);

        return $token;
    }

    private function verifyMellat(Payment $payment, array $returned): array
    {
        $id = (string) $payment->id;
        $arguments = ['orderId' => $id, 'saleOrderId' => $id, 'saleReferenceId' => $returned['SaleReferenceId']];
        $this->assertCode($this->mellatSoap('bpVerifyRequest', $payment->credentials, $arguments), [0, 43, 45]);
        $this->assertCode($this->mellatSoap('bpSettleRequest', $payment->credentials, $arguments), [0, 45]);

        return ['reference' => $returned['SaleReferenceId']];
    }

    private function mellatSoap(string $operation, array $credentials, array $parameters): string
    {
        $fields = ['terminalId' => $credentials['terminal_id'], 'userName' => $credentials['username'], 'userPassword' => $credentials['password']] + $parameters;
        $body = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:p="http://interfaces.core.sw.bps.com/"><s:Body><p:'.$operation.'>';
        foreach ($fields as $name => $value) {
            $body .= '<'.$name.'>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</'.$name.'>';
        }
        $body .= '</p:'.$operation.'></s:Body></s:Envelope>';
        try {
            $response = $this->request()->withHeaders(['SOAPAction' => '""'])->withBody($body, 'text/xml; charset=utf-8')
                ->post(self::MELLAT.'services/pgw');
        } catch (ConnectionException) {
            throw new GatewayFailure('connection');
        }
        $xml = @simplexml_load_string($this->response($response)->body(), options: LIBXML_NONET);
        $result = $xml !== false ? ($xml->xpath('//*[local-name()="Body"]/*[local-name()="'.$operation.'Response"]/*[local-name()="return"]')[0] ?? null) : null;

        return $this->requiredText($result === null ? null : trim((string) $result));
    }

    private function melliToken(array $credentials, int $amount, string $callback, string $id): mixed
    {
        $terminal = $credentials['terminal_id'];
        $data = $this->post(self::MELLI.'/api/v0/Request/PaymentRequest', [
            'MerchantId' => $credentials['merchant_id'], 'TerminalId' => $terminal,
            'Amount' => $amount, 'OrderId' => $id, 'LocalDateTime' => now('Asia/Tehran')->format('Y-m-d H:i:s'),
            'ReturnUrl' => $callback, 'SignData' => $this->melliSignature("$terminal;$id;$amount", $credentials['key']),
        ]);
        $data = array_change_key_case($data);
        $this->assertCode($data['rescode'] ?? null, [0]);

        return $data['token'] ?? null;
    }

    private function verifyMelli(Payment $payment): array
    {
        $data = $this->post(self::MELLI.'/api/v0/Advice/Verify', [
            'Token' => $payment->token, 'SignData' => $this->melliSignature($payment->token, $payment->credentials['key']),
        ]);
        $data = array_change_key_case($data);
        $this->assertCode($data['rescode'] ?? null, [0]);
        if (isset($data['orderid']) && $this->text($data['orderid']) !== (string) $payment->id) {
            throw new GatewayFailure('unmatched_transaction');
        }
        $this->assertAmount($data['amount'] ?? null, $payment->amount_rial);

        return ['reference' => $this->requiredText($data['retrivalrefno'] ?? null)];
    }

    private function melliSignature(string $data, string $encodedKey): string
    {
        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 24) {
            throw new GatewayFailure('terminal_key');
        }
        $signed = openssl_encrypt($data, 'des-ede3', $key, OPENSSL_RAW_DATA);
        if ($signed === false) {
            throw new GatewayFailure('terminal_key');
        }

        return base64_encode($signed);
    }

    private function shahrToken(array $credentials, int $amount, string $callback, string $id): mixed
    {
        $time = $this->shahr('get', 'Time', $credentials)->json();
        if (!is_string($time) || trim($time) === '' || strlen($time) > 80) {
            throw new GatewayFailure('invalid_gateway_time');
        }

        return $this->shahr('post', 'Token', $credentials, [
            'serviceTypeId' => 1, 'merchantConfigurationId' => (int) $credentials['merchant_config_id'],
            'localInvoiceId' => $id, 'amountInRials' => $amount, 'localDate' => $time,
            'callbackURL' => $callback, 'paymentId' => '0', 'additionalData' => '',
        ])->json();
    }

    private function verifyShahr(Payment $payment): array
    {
        $credentials = $payment->credentials;
        $merchant = (int) $credentials['merchant_config_id'];
        $data = $this->shahr('get', 'TranResult', $credentials, [
            'merchantConfigurationId' => $merchant, 'localInvoiceId' => (string) $payment->id,
        ])->json();
        if ($this->text($data['refID'] ?? null) !== $payment->token) {
            throw new GatewayFailure('unmatched_transaction');
        }
        $amount = $data['amountInRials'] ?? $data['amount'] ?? null;
        if ($amount !== null) {
            $this->assertAmount($amount, $payment->amount_rial);
        }
        $reference = $this->requiredText($data['payGateTranID'] ?? null);
        if (!ctype_digit($reference)) {
            throw new GatewayFailure('unmatched_transaction');
        }
        $fields = ['merchantConfigurationId' => $merchant, 'payGateTranId' => (int) $reference];
        $this->shahr('post', 'Verify', $credentials, $fields);
        $this->shahr('post', 'Settlement', $credentials, $fields);

        return ['reference' => $reference];
    }

    private function shahr(string $method, string $path, array $credentials, array $parameters = []): Response
    {
        try {
            return $this->response($this->request()->withHeaders(['usr' => $credentials['username'], 'pwd' => $credentials['password']])
                ->{$method}(self::SHAHR_API.$path, $parameters));
        } catch (ConnectionException) {
            throw new GatewayFailure('connection');
        }
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(5)->timeout(15)->withoutRedirecting();
    }

    private function post(string $url, array $data): array
    {
        try {
            $result = $this->response($this->request()->post($url, $data))->json();
        } catch (ConnectionException) {
            throw new GatewayFailure('connection');
        }
        if (!is_array($result)) {
            throw new GatewayFailure('invalid_response');
        }

        return $result;
    }

    private function response(Response $response): Response
    {
        if (!$response->successful()) {
            throw new GatewayFailure('http_'.$response->status());
        }

        return $response;
    }

    private function assertCode(mixed $code, array $allowed): void
    {
        $number = filter_var($code, FILTER_VALIDATE_INT);
        if (!in_array($number, $allowed, true)) {
            throw new GatewayFailure($number === false ? 'invalid_response' : 'psp_'.$number);
        }
    }

    private function assertAmount(mixed $amount, int $expected): void
    {
        if (filter_var($amount, FILTER_VALIDATE_INT) !== $expected) {
            throw new GatewayFailure('unmatched_amount');
        }
    }

    private function text(mixed $value): string
    {
        return (is_string($value) || is_int($value)) && preg_match('/\A[!-~]{1,160}\z/', (string) $value)
            ? (string) $value : '';
    }

    private function requiredText(mixed $value): string
    {
        $text = $this->text($value);
        if ($text === '') {
            throw new GatewayFailure('invalid_response');
        }

        return $text;
    }
}
