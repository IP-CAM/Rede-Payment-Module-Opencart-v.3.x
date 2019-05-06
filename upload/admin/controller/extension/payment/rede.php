<?php

class ControllerExtensionPaymentRede extends Controller
{
    const VERSION = '0.1';

    private $error = array();

    private $settings = array();

    public function index()
    {
        $this->document->setTitle('Rede');

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_rede', $this->request->post);
            $this->session->data['success'] = 'Sucesso';
            $this->response->redirect($this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            ));
        }

        $data['payment_rede_status'] = isset($this->request->post['payment_rede_status']) ? $this->request->post['payment_rede_status'] : $this->config->get('payment_rede_status');
        $data['payment_rede_environment'] = isset($this->request->post['payment_rede_environment']) ? $this->request->post['payment_rede_environment'] : $this->config->get('payment_rede_environment');
        $data['payment_rede_pv'] = isset($this->request->post['payment_rede_pv']) ? $this->request->post['payment_rede_pv'] : $this->config->get('payment_rede_pv');
        $data['payment_rede_token'] = isset($this->request->post['payment_rede_token']) ? $this->request->post['payment_rede_token'] : $this->config->get('payment_rede_token');

        $data['payment_rede_method'] = isset($this->request->post['payment_rede_method']) ? $this->request->post['payment_rede_method'] : $this->config->get('payment_rede_method');

        $data['payment_rede_soft_descriptor'] = isset($this->request->post['payment_rede_soft_descriptor']) ? $this->request->post['payment_rede_soft_descriptor'] : $this->config->get('payment_rede_soft_descriptor');

        $data['payment_rede_max_installments'] = isset($this->request->post['payment_rede_max_installments']) ? $this->request->post['payment_rede_max_installments'] : $this->config->get('payment_rede_max_installments');
        $data['payment_rede_installments_min_value'] = isset($this->request->post['payment_rede_installments_min_value']) ? $this->request->post['payment_rede_installments_min_value'] : $this->config->get('payment_rede_installments_min_value');

        $data['payment_rede_gateway'] = isset($this->request->post['payment_rede_gateway']) ? $this->request->post['payment_rede_gateway'] : $this->config->get('payment_rede_gateway');
        $data['payment_rede_module'] = isset($this->request->post['payment_rede_module']) ? $this->request->post['payment_rede_module'] : $this->config->get('payment_rede_module');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            )
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Rede',
            'href' => $this->url->link(
                'extension/payment/rede',
                'user_token=' . $this->session->data['user_token'],
                true
            )
        );

        $data['action'] = $this->url->link(
            'extension/payment/rede',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true
        );

        $data['api_token'] = $this->session->getId();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/rede', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/rede')) {
            $this->error['warning'] = 'Você não tem permissões suficientes para isso';
        } else {
            if (!$this->request->post['payment_rede_pv']) {
                $this->error['error_account_id'] = 'Você precisa informar o PV';
            }

            if (!$this->request->post['payment_rede_token']) {
                $this->error['error_merchant_id'] = 'Você precisa informar o token da loja';
            }
        }

        return !$this->error;
    }

    public function order()
    {
        $order_id = $this->request->get['order_id'];

        $query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'erede` WHERE `id_order`="' . $order_id . '";');

        $data = is_array($query->row) ? $query->row : [];
        $data['user_token'] = $this->session->data['user_token'];
        $data['order_id'] = $order_id;

        if (isset($data['payment_method'])) {
            $data['payment_method_text'] = $data['payment_method'] == 'authorize_capture' ? 'Autorização com captura automática' : 'Somente autorização';
        }

        return $this->load->view('extension/payment/rede_order', $data);
    }

    public function install()
    {
        $this->load->model('extension/payment/rede');
        $this->load->model('setting/setting');
        $this->load->model('setting/event');

        $this->settings = array(
            'payment_rede_status' => '1',
            'payment_rede_environment' => 'tests',
            'payment_rede_pv' => '',
            'payment_rede_token' => '',
            'payment_rede_method' => 'authorize_capture',
            'payment_rede_soft_descriptor' => '',
            'payment_rede_max_installments' => 12,
            'payment_rede_installments_min_value' => 0,
            'payment_rede_gateway' => '',
            'payment_rede_module' => ''
        );

        $this->model_setting_setting->editSetting('payment_rede', $this->settings);
        $this->model_extension_payment_rede->createDatabaseTables();

        $this->model_setting_event->addEvent('rede_status', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/payment/rede/addOrderHistory');
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/rede');
        $this->load->model('setting/setting');
        $this->load->model('setting/event');

        $this->model_setting_setting->deleteSetting('payment_rede');
        $this->model_setting_event->deleteEvent('rede_status');
        $this->model_extension_payment_rede->dropDatabaseTables();
    }
}
