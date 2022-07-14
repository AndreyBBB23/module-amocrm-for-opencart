<?php

class AmoCrmModel extends Model
{

    protected $_moduleSysName = "amocrm_";
    protected $_modulePostfix = "";
    protected $_logFile = "error.txt";
    protected $debug = false;
    public $params = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_logFile = $this->config->get("config_error_filename");
        if (isset($this->request->get["route"])) {
            $route = explode('/', $this->request->get["route"]);
            if ($route[0] == 'extension' && $route[1] == 'extension' && isset($route[2])) {
                // Это установка\удаление, например extension/extension/feed/install
                $this->_route = $route[2];
            } else if ($route[0] == 'extension' && $route[1] != 'extension') {
                // Это настройки модуля, например extension/feed/amocrm_product_feed
                $this->_route = $route[1];
            } else {
                // Это к модулю вообще относится боком, например catalog/product_feed
                $this->_route = $route[0];
            }
        } else {
            $this->_route = 'module';
        }
    }

    public function _moduleSysName()
    {
        return $this->_moduleSysName . $this->_modulePostfix;
    }

    public function getParams()
    {
        return $this->params;
    }

    protected function debug($message)
    {
        $this->log($message);
    }

    protected function log($message)
    {
        if (!$this->debug)
            return;

        if (file_exists(DIR_LOGS . $this->_logFile) && filesize(DIR_LOGS . $this->_logFile) >= 100 * 1024 * 1024) {
            unlink(DIR_LOGS . $this->_logFile);
        }

        file_put_contents(DIR_LOGS . $this->_logFile, date("Y-m-d H:i:s - ") . $message . "\r\n", FILE_APPEND);
    }

    protected function initParamsDefaults($items)
    {
        $params = array();
        foreach ($items as $name => $value) {
            $params[$this->_moduleSysName() . "_" . $name] = $value;
        }

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting($this->_moduleSysName(), $params);
    }

    protected function initParams($items)
    {
        // Функция вызывается при установке\обновлении модуля
        $this->load->model('setting/setting');
        $params = $this->model_setting_setting->getSetting($this->_moduleSysName());
        if (!$params) {
            $params = array();
        }

        foreach ($items as $name => $value) {
            if (isset($params[$this->_moduleSysName() . "_" . $name])) {
                continue;
            }
            $params[$this->_moduleSysName() . "_" . $name] = $value;
        }

        $this->model_setting_setting->editSetting($this->_moduleSysName(), $params);
    }

    /**
     * возвращает заданный параметр как массив, учитывая кол-во магазинов в системе
     *
     * @param mixed $param
     * @return array array(0 => $param, 1 => $param, ...)
     */
    public function getParamForAllStores($param)
    {
        $stores = $this->getStores();

        return array_fill(0, count($stores), $param);
    }

    /**
     * возвращает существующие магазины
     *
     * @return array (store_id => array(SELECT * FROM oc_store WHERE store_id=<store_id>))
     */
    public function getStores()
    {
        $this->load->model('setting/store');

        $stores[0] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name'),
            'url' => $this->config->get('config_secure') ? HTTPS_CATALOG : HTTP_CATALOG,
        );

        foreach ($this->model_setting_store->getStores() as $store) {
            $stores[$store['store_id']] = $store;
        }

        return $stores;
    }

    public function addPermission($user_group_id, $type, $route)
    {
        $user_group_query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "user_group WHERE user_group_id = '" . (int)$user_group_id . "'");

        if ($user_group_query->num_rows) {
            $data = json_decode($user_group_query->row['permission'], true);
            if (isset($data[$type])) {
                foreach ($data[$type] as $item) {
                    //Если запись уже есть в БД не нужно добавлять или обновлять права.
                    if ($item == $route)
                        return FALSE;
                }
                $data[$type][] = $route;
                $this->db->query("UPDATE " . DB_PREFIX . "user_group SET permission = '" . $this->db->escape(json_encode($data)) . "' WHERE user_group_id = '" . (int)$user_group_id . "'");
            }
        }

        return TRUE;
    }
}