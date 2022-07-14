<?php

require_once(DIR_SYSTEM . '/engine/amocrm/amocrm_controller.php');
require_once(DIR_SYSTEM . '/engine/amocrm/amocrm_view.php');

class ControllerExtensionModuleAmoCrm extends AmoCrmController
{
    private $error = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = "amo_crm";
        $this->_modulePostfix = ""; // Постфикс для разных типов модуля, поэтому переходим на испольлзование $this->_moduleSysName()
        $this->_logFile = $this->_moduleSysName() . ".log";
        $this->debug = $this->config->get($this->_moduleSysName() . "_debug") == 1;
    }

    public function index()
    {
        $this->upgrade();

        $data = $this->language->load('extension/module/' . $this->_moduleSysName);

        $this->document->setTitle($this->language->get('heading_title_raw'));

        $this->load->model('setting/setting');
        $this->load->model('tool/' . $this->_moduleSysName());

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->model_setting_setting->editSetting($this->_moduleSysName, $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            if (isset($this->request->get['close'])) {
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true));
            } else {
                $this->response->redirect($this->url->link('extension/module/' . $this->_moduleSysName, 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
            }
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else if (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = $this->session->data['error_warning'];
            unset($this->session->data['error_warning']);
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        }

        $data = $this->initBreadcrumbs(array(
            array("extension/extension", "text_module"),
            array("module/" . $this->_moduleSysName, "heading_title_raw")
        ), $data);

        $data = $this->initButtons($data);

        $this->load->model('extension/' . $this->_route . "/" . $this->_moduleSysName);
        $data = $this->initParamsListEx($this->{"model_extension_" . $this->_route . "_" . $this->_moduleSysName}->getParams(), $data);

        $data['user_token'] = $this->session->data['user_token'];
        $data['config_language_id'] = $this->config->get('config_language_id');
        $data['params'] = $data;

        $data[$this->_moduleSysName() . '_webhook'] = HTTPS_CATALOG . 'index.php?route=tool/' . $this->_moduleSysName . '/webhook';

        $data["logs"] = $this->getLogs();
        $data['amo_fields'] = $this->{'model_tool_' . $this->_moduleSysName()}->getAmoFields();
        $data['amo_pipelines'] = $this->{'model_tool_' . $this->_moduleSysName()}->getAmoPipelines();
        $data['amo_users'] = $this->{'model_tool_' . $this->_moduleSysName()}->getAmoUsers();

        $this->load->model('localisation/order_status');

        $order_statuses = $this->model_localisation_order_status->getOrderStatuses();
        $data['order_statuses'] = array();
        foreach ($order_statuses as $order_status) {
            $data['order_statuses'][$order_status['order_status_id']] = $order_status['name'];
        }
        $data['sysname'] = $this->_moduleSysName();

        $widgets = new AmoCrmWidgets($this->_moduleSysName() . '_', $data);
        $widgets->text_select_all = $this->language->get('text_select_all');
        $widgets->text_unselect_all = $this->language->get('text_unselect_all');
        $data['widgets'] = $widgets;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['handbooks'] = $this->{'model_tool_' . $this->_moduleSysName()}->getHandbookStatus();

        $counts = 0;
        foreach ($data['handbooks'] as $hb) {
            $counts += $hb;
        }
        if ($counts == 0) {
            $data['error_warning'] = $this->language->get('error_handbooks');
        }

        $data['update_hb_links'] = $this->url->link('extension/module/' . $this->_moduleSysName . '/updateHandbooks', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $this->response->setOutput($this->load->view('extension/module/' . $this->_moduleSysName, $data));
    }

    public function updateHandbooks()
    {
        $this->load->model('tool/' . $this->_moduleSysName());
        $this->{'model_tool_' . $this->_moduleSysName()}->getAmmoHandbooks();
        $this->language->load('extension/module/' . $this->_moduleSysName);
        if (!isset($this->session->data['error_warning'])) $this->session->data['success'] = $this->language->get('text_update_success');
        $this->response->redirect($this->url->link('extension/module/' . $this->_moduleSysName, 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/' . $this->_moduleSysName)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

}