<?php

require_once DIR_SYSTEM . "/engine/amocrm/amocrm_model.php";

class ModelToolAmoCrm extends AmoCrmModel
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = "amo_crm";
        $this->_modulePostfix = "";
        $this->_logFile = $this->_moduleSysName() . ".log";
        $this->debug = $this->config->get($this->_moduleSysName() . "_debug") == 1;
    }

    public function getAmmoData($data, $values = array())
    {
        $subdomain = $this->config->get($this->_moduleSysName() . "_amo_subdomain");
        $link = "https://" . $subdomain . ".amocrm.ru/api/v2/" . $data;
        $headers = array("Authorization: Bearer " . $this->getAmmoTokens());

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, "amoCRM-API-client/1.0");
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        if (0 < count($values)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($values));
        }
        curl_setopt($curl, CURLOPT_COOKIEFILE, DIR_CACHE . "/cookie.txt");
        curl_setopt($curl, CURLOPT_COOKIEJAR, DIR_CACHE . "/cookie.txt");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $code = (int)$code;
        $errors = array(301 => "Moved permanently", 400 => "Bad request", 401 => "Unauthorized", 403 => "Forbidden", 404 => "Not found", 500 => "Internal server error", 502 => "Bad gateway", 503 => "Service unavailable");

        if ($code < 200 && 204 < $code) {
            $this->session->data["error_warning"] = "Сервер вернул ошибку: " . $errors[$code];
        } else {
            $response = json_decode($out, true);
            $this->log("Ответ от АММО [" . $code . "]: " . print_r($response, true));

            return $response;
        }
    }

    public function getAmmoTokens()
    {
        $current_codes = $this->db->query("SELECT * FROM `" . DB_PREFIX . "amocrm_tokens` WHERE  DATE_ADD(`get_time` ,interval expires_in SECOND) > now() AND  access_token<> ''");

        if ($current_codes->num_rows) {
            return $current_codes->row["access_token"];
        }

        $subdomain = $this->config->get($this->_moduleSysName() . "_amo_subdomain");
        $link = "https://" . $subdomain . ".amocrm.ru/oauth2/access_token";
        $webhook = HTTPS_CATALOG . 'index.php?route=tool/' . $this->_moduleSysName . '/webhook';
        $data = array("client_id" => $this->config->get($this->_moduleSysName() . "_integration_id"), "client_secret" => $this->config->get($this->_moduleSysName() . "_integration_secret"), "grant_type" => "authorization_code", "redirect_uri" => $webhook);
        $current_codes = $this->db->query("SELECT * FROM `" . DB_PREFIX . "amocrm_tokens` where  access_token<> ''");

        if ($current_codes->num_rows) {
            $data["grant_type"] = "refresh_token";
            $data["refresh_token"] = $current_codes->row["refresh_token"];
        } else {
            $data["code"] = $this->config->get($this->_moduleSysName() . "_auch_code");
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, "amoCRM-oAuth-client/1.0");
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type:application/json"));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $code = (int)$code;
        $errors = array(400 => "Bad request", 401 => "Unauthorized", 403 => "Forbidden", 404 => "Not found", 500 => "Internal server error", 502 => "Bad gateway", 503 => "Service unavailable");

        if ($code < 200 && 204 < $code) {
            $this->session->data["error_warning"] = "Сервер вернул ошибку: " . $errors[$code];
        } else {
            $response = json_decode($out, true);
            $this->log("Response for token: " . print_r($response, true));
            $this->db->query("TRUNCATE `" . DB_PREFIX . "amocrm_tokens`");
            $this->db->query("INSERT INTO `" . DB_PREFIX . "amocrm_tokens` (`token_type`, `expires_in`, `access_token`, `refresh_token`, `get_time`) VALUES ('" . $this->db->escape($response["token_type"]) . "', '" . $this->db->escape($response["expires_in"]) . "', '" . $this->db->escape($response["access_token"]) . "', '" . $this->db->escape($response["refresh_token"]) . "', now())");
            return $response["access_token"];
        }
    }

    public function newFormToAmo($title, $data = array())
    {
        $this->log("Функция newFormToAmo: Получили данные " . print_r($data, true));

        $form_status = $this->config->get($this->_moduleSysName() . "_form_statuses");
        $this->log("Получили pipeline: " . $form_status);
        $pipeline = explode("_", $form_status);
        list($form_status, $pipeline) = $pipeline;

        $link = "contacts/?query=" . $data["telephone"];
        $search = $this->getAmmoData($link);

        if (!$search) {
            $contact_data = array();
            $contact_data["add"] = array(array("name" => $data["name"], "first_name" => $data["name"], "last_name" => "", "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_contact_responsible"), "created_by" => $this->config->get($this->_moduleSysName() . "_contact_responsible"), "created_at" => time(), "tags" => "Покупатель,сайт", "custom_fields" => array(array("id" => $this->config->get($this->_moduleSysName() . "_" . "contact_phone_field"), "values" => array(array("value" => $data["telephone"], "enum" => "WORK"))), array("id" => $this->config->get($this->_moduleSysName() . "_" . "contact_email_field"), "values" => array(array("value" => $data["email"] ?: "", "enum" => "WORK"))))));
            $link = "contacts";
            $output = $this->getAmmoData($link, $contact_data);
            $contact_id = $output["_embedded"]["items"][0]["id"];
            $this->log("Функция newFormToAmo: Форма - " . $title . " Создан контакт " . $contact_id);
        } else {
            $contact_id = $search["_embedded"]["items"][0]["id"];
            $this->log("Функция newFormToAmo: Форма - " . $title . " Найден контакт " . $contact_id);
        }

        $leads["add"] = array(array("name" => $title, "created_at" => time(), "status_id" => $form_status, "pipeline_id" => $pipeline, "sale" => "", "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_lead_responsible"), "contacts_id" => $contact_id));
        $link = "leads";
        $this->log("Leads request: " . print_r($leads, true));
        $output = $this->getAmmoData($link, $leads);
        $lead_id = $output["_embedded"]["items"][0]["id"];
        $this->log("Функция newFormToAmo: Форма - " . $title . " Создана сделка " . $lead_id);

        $notes_data = array("add" => array(array("element_id" => $lead_id, "element_type" => "2", "text" => $data['comment'], "note_type" => "4", "created_at" => time(), "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_lead_responsible"), "created_by" => $this->config->get($this->_moduleSysName() . "_lead_responsible"))));
        $link = "notes";
        $this->getAmmoData($link, $notes_data);
        $this->log("Функция newFormToAmo: Форма - " . $title . " Создано примечание");
    }

    public function newFastOrderToAmo($data = array())
    {
        $this->newOrderToAmo($data['order_id'], $data['order_status_id']);
    }

    public function newOrderToAmo($order_id, $order_status_id)
    {
        $this->log("Функция newOrderToAmo: Получили данные " . $order_id . ", " . $order_status_id);
        $ammo_id = $this->db->query("SELECT amo_id FROM " . DB_PREFIX . "order WHERE order_id = " . (int)$order_id);

        if (0 < $ammo_id->row["amo_id"]) {
            $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " ужe обработан ранее. пропускаем");
        } else {
            $this->load->model("checkout/order");
            $data = $this->model_checkout_order->getOrder($order_id);
            $order_statuses = $this->config->get($this->_moduleSysName() . "_orders_statuses_to_amo");

            if (!isset($order_statuses[$order_status_id])) {
                $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " Статус заказа, который нас не интересует. Нужные статусы:" . print_r($order_statuses, true));
            } else {
                $this->log("Pipeline for order:" . $order_statuses[$order_status_id]);
                $pipeline = explode("_", $order_statuses[$order_status_id]);
                list($order_statuses[$order_status_id], $pipeline) = $pipeline;
                $link = "contacts/?query=" . $data["telephone"];
                $search = $this->getAmmoData($link);

                if (!$search) {
                    $contact_data = array();
                    $contact_data["add"] = array(array("name" => $data["firstname"] . " " . $data["lastname"], "first_name" => $data["firstname"], "last_name" => $data["lastname"], "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_contact_responsible"), "created_by" => $this->config->get($this->_moduleSysName() . "_contact_responsible"), "created_at" => time(), "tags" => "Покупатель,сайт", "custom_fields" => array(array("id" => $this->config->get($this->_moduleSysName() . "_" . "contact_phone_field"), "values" => array(array("value" => $data["telephone"], "enum" => "WORK"))), array("id" => $this->config->get($this->_moduleSysName() . "_" . "contact_email_field"), "values" => array(array("value" => $data["email"], "enum" => "WORK"))))));
                    $link = "contacts";
                    $output = $this->getAmmoData($link, $contact_data);
                    $contact_id = $output["_embedded"]["items"][0]["id"];
                    $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " Создан контакт " . $contact_id);
                } else {
                    $contact_id = $search["_embedded"]["items"][0]["id"];
                    $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " Найден контакт " . $contact_id);
                }

                $leads["add"] = array(array("name" => str_replace("{order_id}", $data["order_id"], $this->config->get($this->_moduleSysName() . "_" . "lead_title")), "created_at" => time(), "status_id" => $order_statuses[$order_status_id], "pipeline_id" => $pipeline, "sale" => $data["total"], "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_lead_responsible"), "contacts_id" => $contact_id));
                $link = "leads";
                $this->log("Leads request: " . print_r($leads, true));
                $output = $this->getAmmoData($link, $leads);
                $lead_id = $output["_embedded"]["items"][0]["id"];
                $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " Создана сделка " . $lead_id);
                $this->db->query("UPDATE " . DB_PREFIX . "order SET amo_id = '" . (int)$lead_id . "' WHERE order_id = " . (int)$order_id);
                $text_p = "";
                $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                foreach ($order_product_query->rows as $order_product) {
                    $text_p .= $order_product["name"] . " x " . $order_product["quantity"] . " :\n " . $order_product["price"] . "x" . $order_product["quantity"] . " = " . $order_product["total"] . "\n";
                }

                $text_p .= "==========================\n";
                $order_total_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order ASC");

                foreach ($order_total_query->rows as $total) {
                    $text_p .= $total["title"] . " : " . $total["value"] . "\n";
                }

                if ($this->config->get("order_referrer_status") && file_exists(DIR_APPLICATION . "/model/tool/order_referrer.php")) {
                    $this->load->model("tool/order_referrer");
                    $text_p .= "Источник заказа:" . "\n";
                    $text_p .= $this->model_tool_order_referrer->decode($data["first_referrer"], "list") . "\n";
                    $text_p .= $this->model_tool_order_referrer->decode($data["last_referrer"], "list") . "\n";
                }

                $notes_data = array("add" => array(array("element_id" => $lead_id, "element_type" => "2", "text" => $text_p, "note_type" => "4", "created_at" => time(), "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_lead_responsible"), "created_by" => $this->config->get($this->_moduleSysName() . "_lead_responsible"))));
                $link = "notes";
                $output = $this->getAmmoData($link, $notes_data);
                $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " Создано примечание");

                if ($this->config->get($this->_moduleSysName() . "_create_task")) {
                    $tasks["add"] = array(array("element_id" => $lead_id, "element_type" => 2, "task_type" => 1, "text" => str_replace("{order_id}", $data["order_id"], $this->config->get($this->_moduleSysName() . "_" . "task_title")), "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_" . "task_user"), "complete_till_at" => time() + 60 * 60));
                    $link = "tasks";
                    $output = $this->getAmmoData($link, $tasks);
                    $this->log("Функция newOrderToAmo: Заказ - " . $order_id . " Создана задача.");
                }
            }
        }
    }

    public function updateOrderToAmo($order_id, $order_status_id)
    {
        $this->log("Функция updateOrderToAmo: Получили данные " . $order_id . ", " . $order_status_id);
        $ammo_id = $this->db->query("SELECT amo_id FROM " . DB_PREFIX . "order WHERE order_id = " . (int)$order_id);

        if (0 >= $ammo_id->row["amo_id"]) {
            $this->log("Функция updateOrderToAmo: Заказ - " . $order_id . "  еще не создан. пропускаем");
        } else {
            $ammo_id = $ammo_id->row["amo_id"];
            $order_statuses = $this->config->get($this->_moduleSysName() . "_orders_statuses_to_amo");

            if (!isset($order_statuses[$order_status_id])) {
                $this->log("Функция updateOrderToAmo: Заказ - " . $order_id . " Статус заказа, который нас не интересует. Нужные статусы:" . json_encode($order_statuses));
            } else {
                $pipeline = explode("_", $order_statuses[$order_status_id]);
                list($order_statuses[$order_status_id], $pipeline) = $pipeline;
                $leads["update"] = array(array("id" => $ammo_id, "status_id" => $order_statuses[$order_status_id], "pipeline_id" => $pipeline, "updated_at" => time()));
                $link = "leads";
                $output = $this->getAmmoData($link, $leads);
                $this->log("Функция updateOrderToAmo: Заказ - " . $order_id . "  обновлен. [" . $ammo_id . "," . $order_statuses[$order_status_id] . "," . $pipeline . "] Ответ:" . print_r($output, true));
                $notes_data = array("add" => array(array("element_id" => $ammo_id, "element_type" => "2", "text" => "Статус заказа изменен на сайте", "note_type" => "4", "created_at" => time(), "responsible_user_id" => $this->config->get($this->_moduleSysName() . "_lead_responsible"), "created_by" => $this->config->get($this->_moduleSysName() . "_lead_responsible"))));
                $link = "notes";
                $output = $this->getAmmoData($link, $notes_data);
                $this->log("Функция updateOrderToAmo: Заказ - " . $order_id . " Примечание создано");
            }
        }
    }
}