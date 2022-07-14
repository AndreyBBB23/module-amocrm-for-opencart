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
}