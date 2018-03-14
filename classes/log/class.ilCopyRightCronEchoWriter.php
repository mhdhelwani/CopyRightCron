<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

ilCopyRightCronPlugin::getInstance()->includeClass('log/class.ilCopyRightCronBaseLogWriter.php');
/**
 * Class ilCopyRightCronEchoWriter
 */
class ilCopyRightCronEchoWriter extends ilCopyRightCronBaseLogWriter
{
	/**
	 * @var resource
	 */
	protected $stream;

	/**
	 * @var string
	 */
	protected $logSeparator = PHP_EOL;

	/**
	 * 
	 */
	public function __construct()
	{
		$this->stream = fopen('php://stdout', 'w', false);
		if(!$this->stream || !is_resource($this->stream))
		{
			throw new ilException(sprintf(
				'"%s" cannot be opened with mode "%s"',
				'php://stdout',
				'w'
			));
		}	
	}

	/**
	 * @param string $logSeparator
	 */
	public function setLogSeparator($logSeparator)
	{
		$this->logSeparator = $logSeparator;
	}

	/**
	 * @return string
	 */
	public function getLogSeparator()
	{
		return $this->logSeparator;
	}
	
	/**
	 * @param array $message
	 * @return void
	 */
	protected function doWrite(array $message)
	{
		$line = $this->format($message) . $this->getLogSeparator();
		fwrite($this->stream, $line);
	}

	/**
	 * @return void
	 */
	public function shutdown()
	{
		if(is_resource($this->stream))
		{
			fclose($this->stream);
		}
	}
}