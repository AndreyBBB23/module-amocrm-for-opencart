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

    public function getAmmoData($data)
    {
        $subdomain = $this->config->get($this->_moduleSysName() . "_amo_subdomain");
        $link = "https://" . $subdomain . ".amocrm.ru/api/v2/" . $data;
        $headers = array("Authorization: Bearer " . $this->getAmmoTokens());
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, "amoCRM-API-client/1.0");
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
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
        $current_codes = $this->db->query("SELECT * FROM `" . DB_PREFIX . "amocrm_tokens` WHERE  DATE_ADD(`get_time` ,interval expires_in SECOND) > now() AND  access_token <> ''");
        if ($current_codes->num_rows) {
            return $current_codes->row["access_token"];
        }
        $subdomain = $this->config->get($this->_moduleSysName() . "_amo_subdomain");
        $link = "https://" . $subdomain . ".amocrm.ru/oauth2/access_token";
        $webhook = HTTPS_CATALOG . 'index.php?route=tool/' . $this->_moduleSysName . '/webhook';
        $data = array("client_id" => $this->config->get($this->_moduleSysName() . "_integration_id"), "client_secret" => $this->config->get($this->_moduleSysName() . "_integration_secret"), "grant_type" => "authorization_code", "redirect_uri" => $webhook);
        $current_codes = $this->db->query("SELECT * FROM `" . DB_PREFIX . "amocrm_tokens` where  access_token <> ''");
        if ($current_codes->num_rows) {
            $data["grant_type"] = "refresh_token";
            $data["refresh_token"] = $current_codes->row["refresh_token"];
        } else {
            $data["code"] = $this->config->get($this->_moduleSysName() . "_auth_code");
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
            $this->db->query("TRUNCATE `" . DB_PREFIX . "amocrm_tokens`");
            $this->db->query("INSERT INTO `" . DB_PREFIX . "amocrm_tokens` (`token_type`, `expires_in`, `access_token`, `refresh_token`, `get_time`) \n\t\tVALUES ('" . $this->db->escape($response["token_type"]) . "', '" . $this->db->escape($response["expires_in"]) . "', '" . $this->db->escape($response["access_token"]) . "', '" . $this->db->escape($response["refresh_token"]) . "', now())");
            return $response["access_token"];
        }
    }

    public function getAmmoHandbooks()
    {
        $type = "account?with=pipelines,groups,note_types,task_types,custom_fields,users";
        $data = $this->getAmmoData($type);

        $processed_field = array();
        foreach ($data["_embedded"]["users"] as $amo_user) {
            $processed_field[] = $amo_user["id"];
            $query = "INSERT INTO `" . DB_PREFIX . "amocrm_users` (`amo_id`, `amo_name`) VALUES ('" . $this->db->escape($amo_user["id"]) . "', '" . $this->db->escape($amo_user["name"] . " " . $amo_user["last_name"] . " (" . $amo_user["login"] . ")") . "') ON DUPLICATE KEY UPDATE `amo_name` = '" . $this->db->escape($amo_user["name"] . " " . $amo_user["last_name"] . " (" . $amo_user["login"] . ")") . "'";
            $this->db->query($query);
        }
        if ($processed_field) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "amocrm_users` WHERE amo_id not in (" . $this->db->escape(implode(",", $processed_field)) . ")");
        }
        $processed_field = array();
        foreach ($data["_embedded"]["custom_fields"]["contacts"] as $_obfuscated_616D6F5FD18166_) {
            $processed_field[] = $_obfuscated_616D6F5FD18166_["id"];
            $query = "INSERT INTO `" . DB_PREFIX . "amocrm_fields` (`amo_id`, `oc_id`, `amo_name`) VALUES ('" . $this->db->escape($_obfuscated_616D6F5FD18166_["id"]) . "', '0', '" . $this->db->escape($_obfuscated_616D6F5FD18166_["name"]) . "') ON DUPLICATE KEY UPDATE `amo_name` = '" . $this->db->escape($_obfuscated_616D6F5FD18166_["name"]) . "'";
            $this->db->query($query);
        }
        if ($processed_field) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "amocrm_fields` WHERE amo_id not in (" . $this->db->escape(implode(",", $processed_field)) . ")");
        }
        $processed_pl = array();
        $processed_st = array();
        foreach ($data["_embedded"]["pipelines"] as $amo_pl) {
            $processed_pl[] = $amo_pl["id"];
            foreach ($amo_pl["statuses"] as $amo_st) {
                $processed_st[] = $amo_st["id"];
                $query = "INSERT INTO `" . DB_PREFIX . "amocrm_pipelines` (`amo_id`, `amo_status_id`, `oc_status`, `amo_name`) VALUES ('" . $this->db->escape($amo_pl["id"]) . "', '" . $this->db->escape($amo_st["id"]) . "', 0 ,'" . $this->db->escape($amo_pl["name"] . " >> " . $amo_st["name"]) . "')  ON DUPLICATE KEY UPDATE `amo_name` = '" . $this->db->escape($amo_pl["name"] . " >> " . $amo_st["name"]) . "'";
                $this->db->query($query);
            }
        }
        if ($processed_pl && $processed_st) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "amocrm_pipelines` WHERE amo_id not in (" . $this->db->escape(implode(",", $processed_pl)) . ") OR amo_status_id  not in (" . $this->db->escape(implode(",", $processed_st)) . ") ");
        }
    }

    public function getHandbookStatus()
    {
        $result = array("users" => 0, "fields" => 0, "pipelines" => 0);
        $total = $this->db->query("SELECT count(*) as total FROM `" . DB_PREFIX . "amocrm_users`");
        $result["users"] = $total->row["total"];
        $total = $this->db->query("SELECT count(*) as total FROM `" . DB_PREFIX . "amocrm_fields`");
        $result["fields"] = $total->row["total"];
        $total = $this->db->query("SELECT count(*) as total FROM `" . DB_PREFIX . "amocrm_pipelines`");
        $result["pipelines"] = $total->row["total"];
        return $result;
    }

    public function getAmoFields()
    {
        $fields = array($this->language->get("text_select"));
        $db_fields = $this->db->query("SELECT * FROM  `" . DB_PREFIX . "amocrm_fields`");
        foreach ($db_fields->rows as $row) {
            $fields[$row["amo_id"]] = $row["amo_name"];
        }
        return $fields;
    }

    public function getAmoPipelines()
    {
        $fields = array($this->language->get("text_select"));
        $db_fields = $this->db->query("SELECT * FROM  `" . DB_PREFIX . "amocrm_pipelines`");
        foreach ($db_fields->rows as $row) {
            $fields[$row["amo_status_id"] . "_" . $row["amo_id"]] = $row["amo_name"];
        }
        return $fields;
    }

    public function getAmoUsers()
    {
        $fields = array($this->language->get("text_select"));
        $db_fields = $this->db->query("SELECT * FROM  `" . DB_PREFIX . "amocrm_users`");
        foreach ($db_fields->rows as $row) {
            $fields[$row["amo_id"]] = $row["amo_name"];
        }
        return $fields;
    }
}