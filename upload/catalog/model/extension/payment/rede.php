<?php
require 'upload/system/storage/vendor/autoload.php';

class ModelExtensionPaymentRede extends Model
{
    public function getMethod($address, $total)
    {
        $method_data = array(
            'code' => 'rede',
            'title' => 'Rede',
            'terms' => '',
            'sort_order' => 1
        );

        return $method_data;
    }

    public function cancelTransaction($order_id, $status)
    {
        $transaction = null;
        $query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'erede` WHERE `id_order`="' . $order_id . '";');
        $cancel = isset($query->row['can_cancel']) ? $query->row['can_cancel'] : false;

        if ($cancel) {
            $amount = isset($query->row['amount']) ? $query->row['amount'] : '';
            $tid = isset($query->row['transaction_id']) ? $query->row['transaction_id'] : '';
            $exception = null;

            $transaction = (new \Rede\eRede($this->store(), $this->logger()))->cancel(
                (new \Rede\Transaction($amount))->setTid($tid)
            );

            $refund_id = $transaction->getRefundId();
            $return_code = $transaction->getReturnCode();
            $return_message = $transaction->getReturnMessage();

            $this->db->query(sprintf(
                'UPDATE `%serede` SET
                    `refund_id`="%s",
                    `can_capture`="0",
                    `can_cancel`="0",
                    `return_code_cancelment`="%s",
                    `return_message_cancelment`="%s",
                    `cancelment_datetime`="%s"
                    WHERE `id_order`="%s";',
                DB_PREFIX,
                $refund_id, $return_code, $return_message, date('Y-m-d H:i:s'), $order_id));
        }

        return $transaction;
    }

    public function captureTransaction($order_id, $status)
    {
        $transaction = null;
        $query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'erede` WHERE `id_order`="' . $order_id . '";');
        $capture = isset($query->row['can_capture']) ? $query->row['can_capture'] : false;

        if ($capture) {
            $amount = isset($query->row['amount']) ? $query->row['amount'] : '';
            $tid = isset($query->row['transaction_id']) ? $query->row['transaction_id'] : '';
            $exception = null;

            $transaction = (new \Rede\eRede($this->store(), $this->logger()))->capture(
                (new \Rede\Transaction($amount))->setTid($tid)
            );

            $nsu = $transaction->getNsu();
            $return_code = $transaction->getReturnCode();
            $return_message = $transaction->getReturnMessage();

            $this->db->query(sprintf(
                'UPDATE `%serede` SET
                    `nsu`="%s",
                    `can_capture`="0",
                    `return_code_capture`="%s",
                    `return_message_capture`="%s",
                    `capture_datetime`="%s"
                    WHERE `id_order`="%s";',
                DB_PREFIX,
                $nsu, $return_code, $return_message, date('Y-m-d H:i:s'), $order_id));
        }

        return $transaction;
    }

    public function createTransaction($post, $amount)
    {
        extract($_POST);

        $reference = $this->session->data['order_id'];
        $cancel = 1;
        $payment_method = $this->config->get('payment_rede_method');
        $capture = $payment_method === 'authorize_capture';
        $store = $this->store();

        $transaction = new \Rede\Transaction($amount, $reference + time());

        $transaction->creditCard($card_number, $card_cvv, $card_expiration_month, $card_expiration_year, $holder_name);
        $transaction->capture($capture);

        $gateway = $this->config->get('payment_rede_gateway');
        $module = $this->config->get('payment_rede_module');
        $softDescriptor = $this->config->get('payment_rede_soft_descriptor');

        if (!empty($gateway) && !empty($module)) {
            $transaction->additional($gateway, $module);
        }

        if (!empty($softDescriptor)) {
            $transaction->setSoftDescriptor($softDescriptor);
        }

        $card_installments = (int)$card_installments;
        $card_installments = $card_installments < 1 || $card_installments > $this->config->get('payment_rede_max_installments') ? 1 : $card_installments;

        if ($card_installments > 1) {
            $transaction->setInstallments($card_installments);
        }

        $exception = null;

        try {
            $transaction = (new \Rede\eRede($store, $this->logger()))->create($transaction);
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "erede`(
          `id_order`,
          `amount`,
          `transaction_id`,
          `refund_id`,
          `authorization_code`,
          `nsu`,
          `bin`,
          `last4`,
          `installments`,
          `can_capture`,
          `can_cancel`,
          `payment_method`,
          `return_code_authorization`,
          `return_message_authorization`,
          `authorization_datetime`
          ) VALUES (" .
            '"' . $reference . '",' .
            $amount . ',' .
            '"' . $transaction->getTid() . '",' .
            '"' . $transaction->getRefundId() . '",' .
            '"' . $transaction->getAuthorizationCode() . '",' .
            '"' . $transaction->getNsu() . '",' .
            '"' . $transaction->getCardBin() . '",' .
            '"' . $transaction->getLast4() . '",' .
            '"' . $card_installments . '",' .
            '"' . !$capture . '",' .
            '"' . $cancel . '",' .
            '"' . $payment_method . '",' .
            '"' . $transaction->getReturnCode() . '",' .
            '"' . $transaction->getReturnMessage() . '",' .
            '"' . date( 'Y-m-d H:i:s') . '"' .
        ")");

        if ($exception !== null) {
            throw $exception;
        }

        return $transaction;
    }

    private function logger()
    {
        $logger = null;

        if (class_exists('\Monolog\Logger')) {
            $logger = new \Monolog\Logger('rede');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(DIR_LOGS . 'rede.log', \Monolog\Logger::DEBUG));
            $logger->info('Log Rede');
        }

        return $logger;
    }

    private function environment()
    {
        $environment = \Rede\Environment::production();

        if ($this->config->get('payment_rede_environment') == 'tests') {
            $environment = \Rede\Environment::sandbox();
        }

        return $environment;
    }

    private function store()
    {
        $environment = $this->environment();
        $store = new \Rede\Store($this->config->get('payment_rede_pv'), $this->config->get('payment_rede_token'), $environment);

        return $store;

    }
}
