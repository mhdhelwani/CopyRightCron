<?php

require_once './Services/Cron/classes/class.ilCronJob.php';
require_once('class.ilCopyRightCronPlugin.php');

/**
 * Class ilCopyRightCron
 *
 * @author Mohammed Helwani <mohammed.helwani@llz.uni-halle.de>
 */
class ilCopyRightCron extends ilCronJob
{
    /**
     * @var ilCopyRightCronLog
     */
    protected $log;

    const ID = 'copyright_cron';
    /**
     * @var  ilCopyRightCronPlugin
     */
    protected $pl;
    /**
     * @var  ilDB
     */
    protected $db;
    /**
     * @var  ilLog
     */
    protected $ilLog;


    public function __construct()
    {
        global $ilDB, $ilLog;
        $this->db = $ilDB;
        $this->pl = ilCopyRightCronPlugin::getInstance();
        $this->log = $ilLog;
    }


    /**
     * @return string
     */
    public function getId()
    {
        return self::ID;
    }


    /**
     * @return bool
     */
    public function hasAutoActivation()
    {
        return false;
    }


    /**
     * @return bool
     */
    public function hasFlexibleSchedule()
    {
        return true;
    }


    /**
     * @return int
     */
    public function getDefaultScheduleType()
    {
        return self::SCHEDULE_TYPE_DAILY;
    }


    /**
     * @return array|int
     */
    public function getDefaultScheduleValue()
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCustomSettings()
    {
        return false;
    }

    /**
     * @return ilCronJobResult
     */
    public function run()
    {
        global $ilDB, $lng, $tree, $ilPluginAdmin, $rbacreview, $ilUser;

        require_once "log/class.ilCopyRightCronLog.php";
        require_once "helper/class.copyrightCronHelper.php";

        $this->log = ilCopyRightCronLog::getInstance();
        $this->log->info('Starting CopyRight Cronjob...');
        $result = new ilCronJobResult();
        $result->setMessage('Finished CopyRightCron job task successfully');
        $result->setStatus(ilCronJobResult::STATUS_OK);

        copyrightCronHelper::_collectFilesInRepository($this->log);

        $this->log->info('...CopyRightCron job finished.');

        return $result;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return ilCopyRightCronPlugin::getInstance()->txt('copyright_cron_title');
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return ilCopyRightCronPlugin::getInstance()->txt('copyright_cron_desc');
    }
}
