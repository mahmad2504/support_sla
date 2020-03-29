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
	private $timezone='Asia/Karachi'; //'America/Chicago'
	//private $timezone='America/Chicago';
    function __construct($issue,$customfields) 
	{
		$this->customfields = $customfields;
		$this->issue = $issue;
		$this->alert=0;
	}
	function GetCurrentDateTime()
	{
		$now =  new \DateTime();
		$now->setTimezone(new \DateTimeZone($this->timezone));
		return $now;
	}
	function SetTimeZone($datetime)
	{
		$datetime->setTimezone(new \DateTimeZone($this->timezone));
	}
	
	function __set($prop,$value)
	{	
		switch($prop)
		{
			default:
				$this->$prop=$value;
		}
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
				return $this->$prop;
		}
	}
}
