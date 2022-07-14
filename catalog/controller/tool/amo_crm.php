<?php

require_once DIR_SYSTEM . "/engine/amocrm/amocrm_controller.php";

class ControllerToolAmoCrm extends AmoCrmController
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = "amo_crm";
        $this->_logFile = $this->_moduleSysName . ".log";
        $this->debug = $this->config->get($this->_moduleSysName . "_debug") == 1;
    }

    public function webhook()
    {
        $data = $this->request->post;
        $this->log("ПОлучены данные:" . print_r($data, true));
        if (!isset($data["account"]["subdomain"]) || $data["account"]["subdomain"] != $this->config->get($this->_moduleSysName() . "_amo_subdomain")) {
            $this->log("Получены данные по вебхуку, но он не наш" . print_r($data, true));
            $this->response->setOutput(json_encode(array("status" => "ok")));
        } else {
            $this->log("Вебхук наш. Обрабатываем.");
            $this->load->model("checkout/order");
            $order_statuses = $this->config->get($this->_moduleSysName() . "_orders_statuses_to_amo");
            $leads_data = array();
            if (isset($data["leads"]["update"])) {
                $leads_data = $data["leads"]["update"];
                $this->log("Use update");
            } else {
                if (isset($data["leads"]["status"])) {
                    $leads_data = $data["leads"]["status"];
                    $this->log("Use status");
                }
            }
            if (0 < count($leads_data)) {
                foreach ($leads_data as $lead) {
                    $order_id = $this->db->query("SELECT order_id, \torder_status_id  FROM " . DB_PREFIX . "order where amo_id = " . (int)$lead["id"]);
                    if (!$order_id->num_rows) {
                        $this->log("Получены данные по вебхуку, но не найдена сделка!" . print_r($lead, true));
                        continue;
                    }
                    $new_status = $lead["status_id"] . "_" . $lead["pipeline_id"];
                    if (in_array($new_status, $order_statuses)) {
                        foreach ($order_statuses as $osid => $asid) {
                            if ($asid == $new_status && $osid != $order_id->row["order_status_id"]) {
                                $this->session->data["amo_crm_webhook_in_work"] = true;
                                $this->model_checkout_order->addOrderHistory($order_id->row["order_id"], $osid);
                                unset($this->session->data["amo_crm_webhook_in_work"]);
                                $this->log("Получены данные по вебхуку, Заказу №" . $order_id->row["order_id"] . " установоен статус #" . $osid);
                            }
                        }
                    } else {
                        $this->log("Получены данные по вебхуку, но не нужный статсус заказа");
                    }
                }
            } else {
                $this->log("Вебхук: Нет данных для обновления заказов");
            }
            if (isset($data["leads"]["delete"])) {
                foreach ($data["leads"]["delete"] as $lead) {
                    $order_id = $this->db->query("SELECT order_id, \torder_status_id  FROM " . DB_PREFIX . "order where amo_id = " . (int)$lead["id"]);
                    if (!$order_id->num_rows) {
                        $this->log("Получены данные на удаление по вебхуку, но не найдена сделка!" . print_r($lead, true));
                        continue;
                    }
                    $this->session->data["amo_crm_webhook_in_work"] = true;
                    $this->model_checkout_order->addOrderHistory($order_id->row["order_id"], $this->config->get($this->_moduleSysName() . "_deleted_status"));
                    unset($this->session->data["amo_crm_webhook_in_work"]);
                    $this->log("Получены данные по вебхуку, Заказу №" . $order_id->row["order_id"] . " установоен статус уладенного из Амо.Црм.");
                    $this->db->query("UPDATE " . DB_PREFIX . "order SET amo_id = '0' WHERE order_id = " . (int)$order_id->row["order_id"]);
                }
            } else {
                $this->log("Вебхук: Нет данных для удаления заказов");
            }
            $this->response->setOutput(json_encode(array("status" => "ok")));
        }
    }
}