<?php
include_once("./Services/Cron/classes/class.ilCronHookPlugin.php");
require_once 'class.ilCopyRightCron.php';

/**
 * Class ilCopyRightCronPlugin
 *
 * @author Mohammed Helwani <mohammed.helwani@llz.uni-halle.de>
 */
class ilCopyRightCronPlugin extends ilCronHookPlugin
{

    /**
     * @var ilCopyRightCronPlugin
     */
    protected static $instance;


    /**
     * @return ilCopyRightCronPlugin
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    const PLUGIN_NAME = 'CopyRightCron';
    /**
     * @var  ilCopyRightCron
     */
    protected static $cron_job_instance;


    /**
     * @return ilCopyRightCron[]
     */
    public function getCronJobInstances()
    {
        $this->loadCronJobInstance();

        return array(self::$cron_job_instance);
    }


    /**
     * @param $a_job_id
     *
     * @return ilCopyRightCron
     */
    public function getCronJobInstance($a_job_id)
    {
        if ($a_job_id == ilCopyRightCron::ID) {
            $this->loadCronJobInstance();

            return self::$cron_job_instance;
        }

        return false;
    }


    /**
     * @return string
     */
    public function getPluginName()
    {
        return self::PLUGIN_NAME;
    }


    protected function loadCronJobInstance()
    {
        if (!isset(self::$cron_job_instance)) {
            self::$cron_job_instance = new ilCopyRightCron();
        }
    }
}