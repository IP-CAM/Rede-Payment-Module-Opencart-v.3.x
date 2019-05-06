<?php

class ControllerExtensionPaymentRede extends Controller
{
    const VERSION = '0.1';

    public function index()
    {
        $this->load->model('extension/payment/rede');
        $this->load->model('checkout/order');

        $year = (int)date('Y');

        $data['action'] = $this->url->link('extension/payment/rede/validate', '', true);
        $data['years'] = range($year, $year + 10);
        $data['current_month'] = (int)date('m');
        $data['current_year'] = $year;

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $total = $order_info['total'];
        $installments_max = $this->config->get('payment_rede_max_installments');
        $installments_min = $this->config->get('payment_rede_installments_min_value');
        $installments = [];

        $installment = new stdClass();
        $installment->value = 1;
        $installment->name = sprintf('R$ %.02f à vista', $total);

        $installments[] = $installment;

        for ($i = 2, $t = $installments_max; $i <= $t; $i++) {
            if ($total / $i >= $installments_min) {
                $installment = new stdClass();
                $installment->value = $i;
                $installment->name = sprintf('%d vezes de R$ %.02f', $i, ceil($total / $i * 100) / 100);

                $installments[] = $installment;

                continue;
            }

            break;
        }

        $data['installments'] = $installments;

        return $this->load->view('extension/payment/rede', $data);
    }

    public function validate()
    {
        try {
            $this->validateFields();

            $this->pay();
        } catch (Exception $e) {
            $error = new stdClass();
            $error->error = $e->getMessage();

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($error));
        }
    }

    public function validateFields()
    {
        if (!isset($_POST['card_number'])) {
            throw new Exception('Cartão de crédito inválido');
        }

        if (!isset($_POST['holder_name']) || empty($_POST['holder_name'])) {
            throw new Exception('Titular do cartão inválido');
        }

        if (!isset($_POST['card_expiration_year'])) {
            throw new Exception('Ano de expiração do cartão inválido.');
        }

        if (!isset($_POST['card_expiration_month'])) {
            throw new Exception('Mês de expiração do cartão inválido.');
        }

        if (!isset($_POST['card_cvv'])) {
            throw new Exception('Código de segurança inválido');
        }

        if (!$this->validateCcNum($_POST['card_number'])) {
            throw new Exception('Cartão de crédito inválido');
        }

        if (!is_numeric($_POST['card_cvv']) || (strlen($_POST['card_cvv']) < 3 || strlen($_POST['card_cvv']) > 4)) {
            throw new Exception('Código de segurança inválido');
        }

        if (preg_replace('/[^a-z\s]/i', '', $_POST['holder_name']) != $_POST['holder_name']) {
            throw new Exception('Titular do cartão inválido');
        }

        $year = date('Y');
        $month = date('m');

        if ((int)$_POST['card_expiration_year'] < $year) {
            throw new Exception('Ano de expiração do cartão inválido.');
        }

        if ((int)$_POST['card_expiration_year'] == $year) {
            if ((int)$_POST['card_expiration_month'] < $month) {
                throw new Exception('Mês de expiração do cartão inválido.');
            }
        }
    }

    private function validateCcNum($ccNumber)
    {
        $ccNumber = preg_replace('/[^\d]/', '', $ccNumber);
        $cardNumber = strrev($ccNumber);
        $numSum = 0;

        for ($i = 0; $i < strlen($cardNumber); $i++) {
            $currentNum = substr($cardNumber, $i, 1);

            if ($i % 2 == 1) {
                $currentNum *= 2;
            }

            if ($currentNum > 9) {
                $firstNum = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum = $firstNum + $secondNum;
            }

            $numSum += $currentNum;
        }

        return ($numSum % 10 == 0);
    }

    public function addOrderHistory($route, $data)
    {
        if (!isset($data[0]) || !isset($data[1])) {
            return;
        }

        $order_id = $data[0];
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/rede');

        try {
            switch ($data[1]) {
                case 7:
                case 11:
                case 16:
                    $this->model_extension_payment_rede->cancelTransaction($order_id, $data[1]);

                    break;
                case 5:
                case 15:
                    $this->model_extension_payment_rede->captureTransaction($order_id, $data[1]);

                    break;
            }
        } catch (Exception $e) {
            $error = new stdClass();
            $error->error = $e->getMessage();

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($error));
        }
    }

    public function pay()
    {
        unset($this->session->data['rede']);
        unset($this->session->data['new_order_id']);

        if ($this->session->data['payment_method']['code'] == 'rede') {
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/rede');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $total = $order_info['total'];

            try {
                $status = null;
                $capture = $this->config->get('payment_rede_method') === 'authorize_capture';

                $transaction = $this->model_extension_payment_rede->createTransaction($_POST, $total);
                $return_code = $transaction->getReturnCode();
                $status = 8;

                if ($return_code == '00') {
                    $status = $capture ? 2 : 1;
                }

                if ($return_code == '106') {
                    $status = 10;
                }

                $comment = sprintf('Rede[%s]: %s', $transaction->getReturnCode(), $transaction->getReturnMessage());

                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $status, $comment);

                if ($transaction->getReturnCode() !== '00') {
                    $error = 'Não foi possível processar seu pagamento.';
                    $error .= ' Por favor, confira os dados ou tente novamente com um outro cartão.';

                    throw new Exception($error);
                }

                if (isset($this->session->data['order_id'])) {
                    $this->cart->clear();
                    unset($this->session->data['shipping_method']);
                    unset($this->session->data['shipping_methods']);
                    unset($this->session->data['payment_method']);
                    unset($this->session->data['payment_methods']);
                    unset($this->session->data['comment']);
                    unset($this->session->data['coupon']);
                }

                $success = new stdClass();
                $success->redirect = $this->url->link('checkout/success', '', 'SSL');

                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($success));
            } catch (Exception $e) {
                $error = new stdClass();
                $error->error = 'Não foi possível concluir seu pedido. Por favor, ente novamente em alguns instantes.';
                $error->redirect = $this->url->link('checkout/checkout', '', 'SSL');

                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], 10, sprintf('Rede[%s]: %s', $e->getCode(), $e->getMessage()));

                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($error));
            }
        }
    }
}
