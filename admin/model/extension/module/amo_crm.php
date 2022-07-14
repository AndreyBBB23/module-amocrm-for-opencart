<?php

require_once(DIR_SYSTEM . "/engine/amocrm/amocrm_model.php");

class ModelExtensionModuleAmoCrm extends AmoCrmModel
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = 'amo_crm';
        $this->_modulePostfix = ""; // Постфикс для разных типов модуля, поэтому переходим на использование $this->_moduleSysName()()
        $this->_logFile = $this->_moduleSysName() . '.log';
        $this->debug = $this->config->get($this->_moduleSysName() . '_debug') == 1;
        $this->language->load('extension/module/' . $this->_moduleSysName);
        $this->params = array(
            'status' => 0,
            'debug' => 0,
            'integration_id' => '',
            'integration_secret' => '',
            'auth_code' => '',
            'amo_subdomain' => '',
            'contact_email_field' => 0,
            'contact_phone_field' => 0,
            'contact_responsible' => 0,
            'orders_statuses_to_amo' => array(),
            'create_task' => 0,
            'task_user' => 0,
            'task_title' => $this->language->get('text_task_title'),
            'lead_responsible' => 0,
            'lead_title' => $this->language->get('text_task_title'),
            'deleted_status' => 0,
            'form_statuses' => array(),
        );
    }

    public function install()
    {
        // Значения параметров по умолчанию
        $this->initParams($this->params);

        // Создаем новые и недостающие таблицы в актуальной структуре
        $this->installTables();

        return TRUE;
    }

    public function installTables()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS`" . DB_PREFIX . "amocrm_tokens` (
			`token_type` varchar(60) NOT NULL,
			`expires_in` int(11) NOT NULL,
			`access_token` text NOT NULL,
			`refresh_token` text NOT NULL,
			`get_time` datetime NOT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "amocrm_fields` (
			`amo_id` int(11) NOT NULL,
			`oc_id` int(11) NOT NULL,
			`amo_name` varchar(255) NOT NULL,
			PRIMARY KEY (`amo_id`),
			KEY `oc_id` (`oc_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "amocrm_pipelines` (
			`amo_id` int(11) NOT NULL,
			`amo_status_id` int(11) NOT NULL,
			`oc_status` int(11) NOT NULL,
			`amo_name` varchar(255) NOT NULL,
			PRIMARY KEY (`amo_id`,`amo_status_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "amocrm_users` (
			`amo_id` int(11) NOT NULL,
			`amo_name` varchar(255) NOT NULL,
			PRIMARY KEY (`amo_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $cols = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order` LIKE 'amo_id';");
        if (!$cols->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `amo_id` INT NOT NULL DEFAULT '0' AFTER `date_modified`; ");
        }

    }

    public function upgrade()
    {
        // Добавляем недостающие новые параметры
        $this->initParams($this->params);

        // Создаем недостающие таблицы в актуальной структуре
        $this->installTables();
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE " . DB_PREFIX . "amocrm_tokens");
        $this->db->query("DROP TABLE " . DB_PREFIX . "amocrm_fields");
        $this->db->query("DROP TABLE " . DB_PREFIX . "amocrm_pipelines");
        $this->db->query("DROP TABLE " . DB_PREFIX . "amocrm_users");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` DROP `amo_id`");

        return TRUE;
    }
}