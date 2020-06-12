<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use App\CustomFields;
class JiraTicket
{
	private $issue;
	private $customfields = null;
	private $state = null;
	private static $timezone='America/Chicago'; //'America/Chicago'
	private static $hours_day=12;
	private $sla= ['critical'=>[2,1],
				 'high'=>[10,5],
				 'medium'=>[20,10],
				 'low'=>[40,20],
				 ''=>[40,20]];
	private $sla_firstcontact = [2,1];
			
	//private $timezone='America/Chicago';
    function __construct($issue,$cf) 
	{
		$this->customfields = $cf->customfields;
		$this->issue = $issue;
		$this->waitminutes = $this->ComputeWaitingTime();
		$this->sla=$this->sla[$this->priority][$this->service_level];
		$this->firstcontact_minutes_quota=$this->sla_firstcontact[$this->service_level]*self::$hours_day*60;
		$this->minutes_quota=$this->sla*self::$hours_day*60;
		//echo $this->waitminutes."\n";
	}
	static function  UpdateCustomField($key,$prop,$value)
	{
		$cf = new CustomFields();
		$issueField = new IssueField(true);
		$issueService = new IssueService();
		switch($prop)
		{
			case 'violation_time_to_resolution':
			case 'violation_firstcontact':
			if($value == 0)
				$issueField->addCustomField($cf->$prop,['value' => 'False']);
			else
				$issueField->addCustomField($cf->$prop,['value' => 'True']);
			
			$editParams = [
			'notifyUsers' => true,
			];
			$ret = $issueService->update($key, $issueField,$editParams);	
			echo "Updating ".$key."  ".$prop."<->".$cf->$prop."=".$value."\n";
			break;
			
		}
	}			
	static function  GetCurrentDateTime()
	{
		$now =  new \DateTime();
		$now->setTimezone(new \DateTimeZone(self::$timezone));
		return $now;
	}
	static function SetTimeZone($datetime)
	{
		$datetime->setTimezone(new \DateTimeZone(self::$timezone));
	}
	
	function __set($prop,$value)
	{	
		switch($prop)
		{
			default:
				$this->$prop=$value;
		}
	}
	public static function seconds2human($ss) 
	{
		$s = $ss%60;
		$m = floor(($ss%3600)/60);
		$h = floor(($ss)/3600);
		
		$d = floor($h/self::$hours_day);
		$h = $h%self::$hours_day;
		
		//return "$d days, $h hours, $m minutes, $s seconds";
		return "$d day,$h hour,$m min";
	}
	/**
	 * Check if the given DateTime object is a business day.
	 *
	 * @param DateTime $date
	 * @return bool
	 */
	public static function isBusinessDay(\DateTime $date)
	{
		if ($date->format('N') > 5) {
			return false;
		}

		//Hard coded public Holidays
		$holidays = [
			"Human Rights Day"      => new \DateTime(date('Y') . '-03-21'),
			"Good Friday"           => new \DateTime(date('Y') . '-03-30'),
			"Family Day"            => new \DateTime(date('Y') . '-04-02'),
			"Freedom Day"           => new \DateTime(date('Y') . '-04-27'),
			"Labour Day"            => new \DateTime(date('Y') . '-05-01'),
			"Youth Day"             => new \DateTime(date('Y') . '-06-16'),
			"National Women's Day"  => new \DateTime(date('Y') . '-08-09'),
			"Heritage Day"          => new \DateTime(date('Y') . '-09-24'),
			"Day of Reconciliation" => new \DateTime(date('Y') . '-12-16'),
		];
		$holidays = [];
		foreach ($holidays as $holiday) {
			if ($holiday->format('Y-m-d') === $date->format('Y-m-d')) {
				return false;
			}
		}

		//December company holidays
		if (new \DateTime(date('Y') . '-12-15') <= $date && $date <= new \DateTime((date('Y') + 1) . '-01-08')) {
			return false;
		}

		// Other checks can go here

		return true;
	}

	/**
	 * Get the available business time between two dates (in seconds).
	 *
	 * @param $start
	 * @param $end
	 * @return mixed
	 */
	public static function get_working_seconds($start, $end)
	{
		$start = $start instanceof \DateTime ? $start : new \DateTime($start);
		$end = $end instanceof \DateTime ? $end : new \DateTime($end);
		$dates = [];

		$date = clone $start;

		while ($date <= $end) {

			$datesEnd = (clone $date)->setTime(23, 59, 59);

			if (self::isBusinessDay($date)) {
				$dates[] = (object)[
					'start' => clone $date,
					'end'   => clone ($end < $datesEnd ? $end : $datesEnd),
				];
			}

			$date->modify('+1 day')->setTime(0, 0, 0);
		}

		return array_reduce($dates, function ($carry, $item) {

			$businessStart = (clone $item->start)->setTime(8, 000, 0);
			$businessEnd = (clone $item->start)->setTime(20, 00, 0);

			$start = $item->start < $businessStart ? $businessStart : $item->start;
			$end = $item->end > $businessEnd ? $businessEnd : $item->end;

			//Diff in seconds
			return $carry += max(0, $end->getTimestamp() - $start->getTimestamp());
		}, 0);
	}
	
	
	
	public static function get_working_minutes($ini_str,$end_str){
		
		return round(self::get_working_seconds($ini_str,$end_str)/60);
	}
	
	public function ComputeWaitingTime()
	{
		$ticket = $this;
		$interval = null;
		$intervals = [];
		//dd($ticket->transitions);
		//$ticket->test_case_provided_date
		$debug_ticket = 'SIEJIR-5531'; 
		if(isset($ticket->debug))
		{
			echo "Test Case Provided";
			dump($ticket->test_case_provided_date);
			echo "first_contact_date";
			dump($ticket->first_contact_date);
			dump($ticket->transitions);
		}
		
		if($ticket->test_case_provided_date == null)
			return 0;
		
		
		foreach($ticket->transitions as $transition)
		{

			if($ticket->test_case_provided_date->format('U') >  $transition->created->format('U') )
			{
				 $transition->created = $ticket->test_case_provided_date;//$ticket->first_contact_date;
				 //if($transition->created == null)
				///	  $transition->created  = $ticket->test_case_provided_date;
			}
			if($ticket->solution_provided_date != null)
			{
				if($ticket->solution_provided_date->format('U') < $transition->created->format('U'))
					$transition->created = $ticket->solution_provided_date;
			}
			
			if(($transition->toString == "Waiting Customer Feedback")||($transition->toString == "Queued"))
			{
				if(($transition->fromString == "Waiting Customer Feedback")||($transition->fromString == "Queued"))
				{
					
				}
				else
				{
					$interval = new \StdClass();
					$interval->start = $transition->created;
					$interval->end  = $ticket->GetCurrentDateTime();
					if($ticket->solution_provided_date != null)
					{
						$interval->end = $ticket->solution_provided_date;					}
					
					//if($ticket->key == 'SIEJTEST-16')
					//	dd($ticket->transitions);
					$interval->waiting_minutes = self::get_working_minutes($interval->start->format('Y-m-d\TH:i:s.u'),$interval->end->format('Y-m-d\TH:i:s.u'));
					continue;
				}
			}
			else if(($transition->fromString == "Waiting Customer Feedback")||($transition->fromString == "Queued"))
			{
				if($interval != null)
				{
					$interval->end = $transition->created;
					$interval->waiting_minutes = self::get_working_minutes($interval->start->format('Y-m-d\TH:i:s.u'),$interval->end->format('Y-m-d\TH:i:s.u'));
					$interval->type="customer wait";
					//if($interval->waiting_minutes>0)
					$intervals[] = $interval;
					
					$interval = null;
				}
			}
			if($transition->toString=="Resolved")
			{
				$interval = new \StdClass();
				$interval->start = $transition->created;
				$interval->waiting_minutes = 0;
				//echo "Interval Created\n";
				
			}
			
			if(($transition->fromString=="Resolved")  && ($transition->toString == "Reopened"))
			{
				$interval->end = $transition->created;
				$interval->waiting_minutes = self::get_working_minutes($interval->start->format('Y-m-d\TH:i:s.u'),$interval->end->format('Y-m-d\TH:i:s.u'));
				//if($interval->waiting_minutes>0)
				$interval->type="Reopen";
				$intervals[] = $interval;
				$interval = null;
			}
		}
		if($interval != null && $interval->waiting_minutes>0)
			$intervals[] = $interval;
		
		$waiting_minutes = 0;
		foreach($intervals as $interval)
		{
			if($interval->waiting_minutes <=0 )
				continue;
			if($ticket->key == $debug_ticket)
				dump($interval);
			
			$waiting_minutes  += $interval->waiting_minutes;
		}
		if($ticket->debug)
		{
			echo "waiting minutes=".$waiting_minutes."\r\n";
			//dd($ticket->transitions);
		}
		return $waiting_minutes;
	}
	
	function __get($prop)
	{
		switch($prop)
		{
			
			case 'key':
				return $this->issue->key;
				break;
			case 'summary':
				return $this->issue->fields->summary;
				break;
			case 'updated':
				self::SetTimeZone($this->issue->fields->updated);
				return $this->issue->fields->updated;
				break;
			case 'statuscategorychangedate':
				//return $this->issue->fields->statuscategorychangedate;
				return  $this->issue->fields->updated;
				break;
			case 'status':
				return  $this->issue->fields->status->name;
				break;
			case '_status':
				//echo $this->issue->fields->status->statuscategory->id."-".$this->issue->fields->status->statuscategory->name."\n";
				if($this->issue->fields->status->statuscategory->id == 2)
					return 'OPEN';
				else if($this->issue->fields->status->statuscategory->id == 3)
					return 'RESOLVED';
				else if($this->issue->fields->status->statuscategory->id == 4)
					return 'INPROGRESS';
				else
				{
					echo $this->issue->key." has unknown category";
					exit();
				}
				break;
			case 'created':
					self::SetTimeZone($this->issue->fields->created);
				return $this->issue->fields->created;
				break;
			case 'resolutiondate':
				if($this->issue->fields->status->statuscategory->id != 3) // if not resolved
					return null;
				if(isset($this->issue->fields->resolutiondate))
				{
					$resolutiondate = new \DateTime($this->issue->fields->resolutiondate);
					self::SetTimeZone($resolutiondate);
					return $resolutiondate;
				}
				else 
					return null;
			case 'priority':
				switch(strtolower($this->issue->fields->priority->name))
				{
					case 'critical':
						return 'critical';
					case 'high':
						return 'high';
					case 'medium':
						return 'medium';
					case 'low':
						return 'low';
					default:
						return '';
				}
				return $this->issue->fields->priority->name;
				break;
			case 'service_level':
				$prop = $this->customfields['premium_support'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					if(strtolower($this->issue->fields->customFields[$prop]->value) == 'yes' || strtolower($this->issue->fields->customFields[$prop]->value) == 'true')
						return 1;
					return 0;
				}
				return 0;
				break;
			case 'solution_provided_date':
				$prop = $this->customfields['solution_provided_date'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					$solution_provided_date= new \DateTime($this->issue->fields->customFields[$prop]);
					self::SetTimeZone($solution_provided_date);
					return $solution_provided_date;
				}
				return null;	
			case 'test_case_provided_date':
				$prop = $this->customfields['test_case_provided_date'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					$test_case_provided_date= new \DateTime($this->issue->fields->customFields[$prop]);
					self::SetTimeZone($test_case_provided_date);
					return $test_case_provided_date;
				}
				return null;
			case 'first_contact_date':
				$prop = $this->customfields['first_contact_date'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					$first_contact_date= new \DateTime($this->issue->fields->customFields[$prop]);
					self::SetTimeZone($first_contact_date);
					return $first_contact_date;
				}
				return null;
			case 'violation_firstcontact':
				$prop = $this->customfields['violation_firstcontact'];
				//dump($this->issue->key);
				if(isset($this->issue->fields->customFields[$prop]))
				{
					if(strtolower($this->issue->fields->customFields[$prop]->value) == 'yes' || strtolower($this->issue->fields->customFields[$prop]->value) == 'true')
						return 1;
					else
						return 0;
				}
				return 0;
				break;
			case 'violation_time_to_resolution':
				$prop = $this->customfields['violation_time_to_resolution'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					//echo $this->issue->fields->customFields[$prop]->value;
					if(strtolower($this->issue->fields->customFields[$prop]->value) == 'yes' || strtolower($this->issue->fields->customFields[$prop]->value) == 'true')
						return 1;
					return 0;
				}
				return 0;
			case 'gross_minutes_to_resolution':
				$prop = $this->customfields['gross_minutes_to_resolution'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					return $this->issue->fields->customFields[$prop];
				}
				return null;
			case 'transitions':
				$transitions = [];
				foreach($this->issue->changelog->histories as $history)
				{
					foreach($history->items as $item)
					{
						if($item->field == "status")
						{
							$item->created= new \DateTime($history->created);
							self::SetTimeZone($item->created);
							$transitions[] = $item;
						}
					}
					
				}
				return $transitions;
				break;
			case 'url':
				return $this->issue->self;
				break;
			default:
				if(isset($this->$prop))
					return $this->$prop;
				return '';
		}
	}
}
