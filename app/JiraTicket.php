<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
	//private $timezone='America/Chicago';
    function __construct($issue,$cf) 
	{
		$this->customfields = $cf->customfields;
		$this->issue = $issue;
		$this->alert=0;
		$this->waitminutes = $this->ComputeWaitingTime();
		$this->sla=$this->sla[$this->priority][$this->service_level];
		$this->minutes_quota=$this->sla*self::$hours_day*60;
		//echo $this->waitminutes."\n";
	}
	static function  GetCurrentDateTime()
	{
		$now =  new \DateTime();
		$now->setTimezone(new \DateTimeZone(self::$timezone));
		return $now;
	}
	function SetTimeZone($datetime)
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
		return "$d days,$h hours,$m minutes";
	}
	public static function get_working_minutes($ini_str,$end_str){
		
		//config
		$ini_time = [8,0]; //hr, min
		$end_time = [20,0]; //hr, min
		//date objects
		$ini = date_create($ini_str);
		$ini_wk = date_time_set(date_create($ini_str),$ini_time[0],$ini_time[1]);
		$end = date_create($end_str);
		$end_wk = date_time_set(date_create($end_str),$end_time[0],$end_time[1]);
		//days
		$workdays_arr = self::get_workdays($ini,$end);
		$workdays_count = count($workdays_arr);
		$workday_seconds = (($end_time[0] * 60 + $end_time[1]) - ($ini_time[0] * 60 + $ini_time[1])) * 60;
		//get time difference
		$ini_seconds = 0;
		$end_seconds = 0;
		if(in_array($ini->format('Y-m-d'),$workdays_arr)) $ini_seconds = $ini->format('U') - $ini_wk->format('U');
		if(in_array($end->format('Y-m-d'),$workdays_arr)) $end_seconds = $end_wk->format('U') - $end->format('U');
		$seconds_dif = $ini_seconds > 0 ? $ini_seconds : 0;
		if($end_seconds > 0) $seconds_dif += $end_seconds;
		//final calculations
		$working_seconds = ($workdays_count * $workday_seconds) - $seconds_dif;
		return round($working_seconds/60);
	}
	public static function get_workdays($ini,$end)
	{
		//config
		$skipdays = [6,0]; //saturday:6; sunday:0
		$skipdates = []; //eg: ['2016-10-10'];
		//vars
		$current = clone $ini;
		$current_disp = $current->format('Y-m-d');
		$end_disp = $end->format('Y-m-d');
		$days_arr = [];
		//days range
		while($current_disp <= $end_disp){
			if(!in_array($current->format('w'),$skipdays) && !in_array($current_disp,$skipdates)){
				$days_arr[] = $current_disp;
			}
			$current->add(new \DateInterval('P1D')); //adds one day
			$current_disp = $current->format('Y-m-d');
		}
		return $days_arr;
	}
	public function ComputeWaitingTime()
	{
		$ticket = $this;
		$interval = null;
		$intervals = [];
		//dd($ticket->transitions);
		if($ticket->first_contact_date == null)
			return 0;
		foreach($ticket->transitions as $transition)
		{
			if($ticket->first_contact_date->format('U') >  $transition->created->format('U') )
				 $transition->created = $ticket->first_contact_date;
			
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
			
			$waiting_minutes  += $interval->waiting_minutes;
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
				$this->SetTimeZone($this->issue->fields->updated);
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
			case 'resolutiondate':
				if($this->issue->fields->status->statuscategory->id != 3) // if not resolved
					return null;
				if(isset($this->issue->fields->resolutiondate))
				{
					$resolutiondate = new \DateTime($this->issue->fields->resolutiondate);
					$this->SetTimeZone($resolutiondate);
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
			case 'first_contact_date':
				$prop = $this->customfields['first_contact_date'];
				if(isset($this->issue->fields->customFields[$prop]))
				{
					$first_contact_date= new \DateTime($this->issue->fields->customFields[$prop]);
					$this->SetTimeZone($first_contact_date);
					return $first_contact_date;
				}
				return null;
			case 'violation_time_to_resolution':
				$prop = $this->customfields['violation_time_to_resolution'];
				
				if(isset($this->issue->fields->customFields[$prop]))
				{
					//echo $this->issue->fields->customFields[$prop]->value;
					if(strtolower($this->issue->fields->customFields[$prop]->value) == 'yes' || strtolower($this->issue->fields->customFields[$prop]->value) == 'true')
						return true;
					return false;
				}
				return null;
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
							$this->SetTimeZone($item->created);
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
