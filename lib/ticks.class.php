<?php
/**
	Ticks measurement.
*/
class cTicks
{
	private $dtStart;	//!< Start time set upon creation of this class
	private $dtEnd;		//!< End time set upon calling pf_getDurations
	private $arrTicks;	//!< Ticks array

	public function __construct()
	{
		$this->dtStart = $this->pf_getTickStamp();
	}

	/**
	 * Function gets tick stamp (microtime).
	 *
	 * @return float Time in seconds since Unix epoch with microseconds after coma.
	 */
	private function pf_getTickStamp()
	{
		$mtime = microtime();	// e.g. 0.65504100 1361641864
		$mtime = explode(" ",$mtime);
		$mtime = $mtime[1] + $mtime[0];
		return $mtime;
	}

	/**
	 * Insert a named tick
	 *
	 * @warning Always call insert and end tick in pairs!
	 */
	public function pf_insTick($strTickName)
	{
		$this->arrTicks[$strTickName] = $this->pf_getTickStamp();
	}
	
	/** End a named tick (calculate duration) */
	public function pf_endTick($strTickName)
	{
		if (isset($this->arrTicks[$strTickName]))
		{
			$this->arrTicks[$strTickName] = $this->pf_getTickStamp() - $this->arrTicks[$strTickName];
		}
	}

	/**
	 * Get durations array.
	 * 
	 * @param boolean $boolAddTotal
	 * @return array
	 */
	public function pf_getDurations($boolAddTotal=true)
	{
		$this->dtEnd = $this->pf_getTickStamp();
		$this->arrTicks['total'] = $this->dtEnd - $this->dtStart;
		return $this->arrTicks;
	}
}

?>