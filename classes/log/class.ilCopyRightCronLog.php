<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Calendar/classes/class.ilDateTime.php';
ilCopyRightCronPlugin::getInstance()->includeClass('../interfaces/interface.ilCopyRightCronLogger.php');
ilCopyRightCronPlugin::getInstance()->includeClass('log/class.ilCopyRightCronEchoWriter.php');
ilCopyRightCronPlugin::getInstance()->includeClass('log/class.ilCopyRightCronLogFileWriter.php');
/**
 * Class ilCopyRightCronLog
 */
class ilCopyRightCronLog implements ilCopyRightCronLogger
{
	/**
	 * @var self
	 */
	protected static $instance;

	/**
	 * @var ilCopyRightCronEchoWriter[]
	 */
	protected $writer = array();

	/**
	 *
	 */
	private function __construct()
	{
		$this->addWriter(new ilCopyRightCronEchoWriter());
		$this->addWriter(new ilCopyRightCronLogFileWriter());
	}

	/**
	 * Get singleton instance
	 * @return self
	 */
	public static function getInstance()
	{
		if(null !== self::$instance)
		{
			return self::$instance;
		}

		return (self::$instance = new self());
	}

	public function __destruct()
	{
		foreach($this->writer as $writer)
		{
			$writer->shutdown();
		}
	}
	/**
	 * @return array
	 */
	public static function getPriorities()
	{
		return array(
			self::EMERG  => 'EMERG',
			self::ALERT  => 'ALERT',
			self::CRIT   => 'CRIT',
			self::ERR    => 'ERR',
			self::WARN   => 'WARN',
			self::NOTICE => 'NOTICE',
			self::INFO   => 'INFO',
			self::DEBUG  => 'DEBUG',
		);
	}

	/**
	 * @param ilCopyRightCronBaseLogWriter $writer
	 * @param int $priority
	 */
	public function addWriter(ilCopyRightCronBaseLogWriter $writer, $priority = 1)
	{
		$this->writer[] = $writer;
	}

	/**
	 * @param ilCopyRightCronBaseLogWriter $writer
	 */
	public function removeWriter(ilCopyRightCronBaseLogWriter $writer)
	{
		$key = array_search($writer, $this->writer);
		if($key !== false)
		{
			unset($this->writer[$key]);
		}
	}

	/**
	 * @param int $priority
	 * @param mixed $message
	 * @param array $extra
	 * @throws ilException
	 */
	public function log($priority, $message, $extra = array())
	{
		if(!is_int($priority) || ($priority < 0) || ($priority >= count(self::getPriorities())))
		{
			throw new ilException(
				sprintf('$priority must be an integer > 0 and < %d; received %s',
					count(self::getPriorities()),
					var_export($priority, 1)
				)
			);
		}

		if(is_object($message) && !method_exists($message, '__toString'))
		{
			throw new ilException('$message must implement magic __toString() method');
		}

		if(is_array($message))
		{
			$message = var_export($message, true);
		}

		$timestamp = new ilDateTime(time(), IL_CAL_UNIX);

		$priorities = self::getPriorities();
		foreach($this->writer as $writer)
		{
			$writer->write(array(
				'timestamp'    => $timestamp,
				'priority'     => (int)$priority,
				'priorityName' => $priorities[$priority],
				'message'      => (string)$message,
				'extra'        => $extra
			));
		}
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function emerg($message, $extra = array())
	{
		$this->log(self::EMERG, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function alert($message, $extra = array())
	{
		$this->log(self::ALERT, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function crit($message, $extra = array())
	{
		$this->log(self::CRIT, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function err($message, $extra = array())
	{
		$this->log(self::ERR, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param        array
	 * @return void
	 */
	public function info($message, $extra = array())
	{
		$this->log(self::INFO, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function warn($message, $extra = array())
	{
		$this->log(self::WARN, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function notice($message, $extra = array())
	{
		$this->log(self::NOTICE, $message, $extra);
	}

	/**
	 * @param string $message
	 * @param array  $extra
	 * @return void
	 */
	public function debug($message, $extra = array())
	{
		$this->log(self::DEBUG, $message, $extra);
	}
}