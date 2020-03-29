<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use App\JiraTicket;
use App\Google;
use App\Database;

class SyncJira extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
	// sla in business days
	private $sla= ['critical'=>[2,1],
				 'high'=>[10,5],
				 'medium'=>[20,10],
				 'low'=>[40,20],
				 ''=>[40,20]];
	private $hours_day=8;
	private $customfields=[];
    protected $signature = 'sync:jira';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync with Jira';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
		date_default_timezone_set('Asia/Karachi');
		parent::__construct();
    }
	function __get($prop)
	{
		if(isset($this->customfields[$prop]))
			return $this->customfields[$prop];
	}
	public function UpdateDb($ticket)
	{
		echo $ticket->key."\n";
		$this->db->SaveTicket($ticket);
	}
	public function UpdateTicketInJira($ticket)
	{
		$update=false;
		if($ticket->_status == 'RESOLVED')  // set gross minutes only when issue is closed
		{
			if(isset($ticket->_gross_minutes_to_resolution))
			{
				$ticket->issueField->addCustomField($this->gross_minutes_to_resolution,$ticket->_gross_minutes_to_resolution);
				$update=true;
			}
			if(isset($ticket->gross_time_to_resolution))
			{
				$ticket->issueField->addCustomField($this->gross_time_to_resolution,$ticket->gross_time_to_resolution);
				$update=true;
			}
			if(isset($ticket->net_time_to_resolution))
			{
				$ticket->issueField->addCustomField($this->net_time_to_resolution,$ticket->net_time_to_resolution);
				$update=true;
			}	
			/*if($ticket->net_minutes_to_resolution > $ticket->minutes_quota)
			{
				if($ticket->violation_time_to_resolution != true)
				{
					$ticket->issueField->addCustomField($this->violation_time_to_resolution,['value' => 'True']);
					$update=true;
				}
			}*/
		}
		else
		{
			if($ticket->gross_minutes_to_resolution  != null)
			{
				$ticket->issueField->addCustomField($this->gross_minutes_to_resolution,0);
				$ticket->issueField->addCustomField($this->gross_time_to_resolution,'');
				$ticket->issueField->addCustomField($this->net_time_to_resolution,'');	
				$update=true;
			}
			if($ticket->violation_time_to_resolution == true)
			{
				$ticket->issueField->addCustomField($this->violation_time_to_resolution,['value' => 'False']);
				$update=true;
			}
		}
		
		if($update)
		{
			$editParams = [
			'notifyUsers' => false,
			];
			$issueService = new IssueService();
			echo "Updating ".$ticket->key."\n";
			//$ret = $issueService->update($ticket->key, $ticket->issueField,$editParams);
		}
	}
	public function UpdateTimeToResolution($ticket)
	{
		if(($ticket->resolutiondate != null)&&($ticket->first_contact_date != null))
		{
			//$ticket->first_contact_date =  new \DateTime('2018-01-01');
			echo "Firct Cnta Date=".$ticket->first_contact_date->format('Y-m-d\TH:i:s.u')."\n";
			echo "Resolution Date=".$ticket->resolutiondate->format('Y-m-d\TH:i:s.u')."\n";
			
			$ticket->net_minutes_to_resolution = $this->get_working_minutes($ticket->first_contact_date->format('Y-m-d\TH:i:s.u'),$ticket->resolutiondate->format('Y-m-d\TH:i:s.u'));
			//echo "Net minutes to res=$ticket->net_minutes_to_resolution \n";
			$difference = $ticket->first_contact_date->diff($ticket->resolutiondate);
		}
		else if(($ticket->resolutiondate == null)&&($ticket->first_contact_date != null))
		{
			//$ticket->first_contact_date =  new \DateTime('2020-03-25T08:57:00');
			//$ticket->first_contact_date->setTimezone(new \DateTimeZone('Asia/Karachi'));
			
			echo "Firct Cnta Date=".$ticket->first_contact_date->format('Y-m-d\TH:i:s.u')."\n";
			$now =  $ticket->GetCurrentDateTime();
			echo "Now =".$now->format('Y-m-d\TH:i:s.u')."\n";
			$difference = $ticket->first_contact_date->diff($now);
			$ticket->net_minutes_to_resolution = $this->get_working_minutes($ticket->first_contact_date->format('Y-m-d\TH:i:s.u'),$now->format('Y-m-d\TH:i:s.u'));
			
		}
		echo "Net minutes=$ticket->net_minutes_to_resolution\n";
		echo "waitminutes=$ticket->waitminutes\n";
		$ticket->net_minutes_to_resolution = $ticket->net_minutes_to_resolution - $ticket->waitminutes ;
		echo "Net minutes=$ticket->net_minutes_to_resolution\n";
		
		$ticket->net_time_to_resolution  = $this->seconds2human($ticket->net_minutes_to_resolution*60);	
		//echo "Net time=$ticket->net_time_to_resolution\n";

		
		$ticket->_gross_minutes_to_resolution = $difference->days * 24 * 60;
		$ticket->_gross_minutes_to_resolution += $difference->h * 60;
		$ticket->_gross_minutes_to_resolution += $difference->i;
		
		$ticket->gross_time_to_resolution=$this->seconds2human($ticket->_gross_minutes_to_resolution*60);
		$ticket->gross_time_to_resolution=$difference->days." days,".$difference->h." hours,".$difference->i." minutes";


		echo "Gross minutes=$ticket->gross_minutes_to_resolution\n";
		//echo "days = ". $difference->days."\n";
		//echo "hours = ". $difference->h."\n";
		//echo "minutes = ". $difference->i."\n";
		
		//if($ticket->gross_minutes_to_resolution < $ticket->net_minutes_to_resolution)
		//	$ticket->gross_minutes_to_resolution = $ticket->net_minutes_to_resolution;
		
		
		
		$ticket->percent_time_consumed = 100;
		if($ticket->net_minutes_to_resolution <= $ticket->minutes_quota)
			$ticket->percent_time_consumed = $ticket->net_minutes_to_resolution/$ticket->minutes_quota*100;


		/*echo "ticket->gross_minutes_to_resolution=$ticket->gross_time_to_resolution\n";
		echo "ticket->net_minutes_to_resolution=$ticket->net_time_to_resolution\n";
		
		echo "ticket->gross_time_to_resolution=$ticket->gross_time_to_resolution\n";
		echo "ticket->net_time_to_resolution=$ticket->net_time_to_resolution\n";*/
			
	}
	public function SendEmail($ticket)
	{
		
		return 0;//-1
	}
	public function SendNotification($ticket)
	{
		echo $ticket->percent_time_consumed."%\n";
		
		if($ticket->percent_time_consumed >= 50)
		{
			if($ticket->alert=0)
			{
				if($this->SendEmail($ticket)==0)
					$ticket->alert=1;
			}
		}
		if($ticket->percent_time_consumed >= 75)
		{
			if($ticket->alert<2)
			{
				if($this->SendEmail($ticket)==0)
					$ticket->alert=2;
			}
		}
		if($ticket->percent_time_consumed >= 100)
		{
			if($ticket->alert<3)
			{
				if($this->SendEmail($ticket)==0)
					$ticket->alert=3;
			}
		}
		//$ttime_cconsumed = $ticket->net_time_to_resolution/
		//$ticket->net_time_to_resolution
		//$ticket->minutes_quota
		
	}
	public function ComputeWaitingTime($ticket)
	{
		$interval = null;
		$intervals = [];
		//dd($ticket->transitions);
		
		foreach($ticket->transitions as $transition)
		{
			//dump($transition);
			
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
					
					$interval->waiting_minutes = $this->get_working_minutes($interval->start->format('Y-m-d\TH:i:s.u'),$interval->end->format('Y-m-d\TH:i:s.u'));
					continue;
				}
			}
			else if(($transition->fromString == "Waiting Customer Feedback")||($transition->fromString == "Queued"))
			{
				if($interval != null)
				{
					$interval->end = $transition->created;
					$interval->waiting_minutes = $this->get_working_minutes($interval->start->format('Y-m-d\TH:i:s.u'),$interval->end->format('Y-m-d\TH:i:s.u'));
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
				$interval->waiting_minutes = $this->get_working_minutes($interval->start->format('Y-m-d\TH:i:s.u'),$interval->end->format('Y-m-d\TH:i:s.u'));
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
	public function ProcessActiveTickets($issue)
	{
		$ticket = new JiraTicket($issue,$this->customfields);
		$ticket->issueField = new IssueField(true);
		echo "--------------------------------------------"."\n";
		echo "Key=$ticket->key\n";
		echo $ticket->status."\n";
		$ticket->waitminutes=$this->ComputeWaitingTime($ticket);
		echo "Status=$ticket->status\n";
		echo "_Status=$ticket->_status\n";
		echo "Wait Minutes = $ticket->waitminutes\n";
		
		//echo $ticket->priority."\n";
		//echo "service_level=$ticket->service_level\n";
		$ticket->sla=$this->sla[$ticket->priority][$ticket->service_level];
		$ticket->minutes_quota=$ticket->sla*$this->hours_day*60;
		echo "violation_time_to_resolution=".$ticket->violation_time_to_resolution."\n";
		//echo "system_state_variables=$ticket->system_state_variables\n";
		//$this->state=$ticket->system_state_variables;
		
		
		//echo $ticket->system_state_variables->name."\n";
		echo "Quota=$ticket->minutes_quota minutes\n";
		
		//echo $ticket->summary."\n";
		//echo $ticket->_status."\n";
		//echo $ticket->status."\n";
		//echo $ticket->first_contact_date->format('Y-m-d\TH:i:s.u')."\n";
		//echo "Updated=".$ticket->updated->format('Y-m-d\TH:i:s.u')."\n";
		//$this->ComputeCWFTime($ticket);	
		$this->UpdateTimeToResolution($ticket);	
		$this->SendNotification($ticket);		
		$this->UpdateTicketInJira($ticket);
		$this->UpdateDb($ticket);
		
	}
	public function PullActiveTickets()
	{
		$issueService = new IssueService();

		$last_updated = $this->db->Get('last_updated');
		
		//$last_updated = getenv("LAST_UPDATED");
		$jql = 'project=SIEJIR_TEST  and  "First Contact Date" is not null  and (cf['.explode("_",$this->gross_minutes_to_resolution)[1].'] is EMPTY or cf['.explode("_",$this->gross_minutes_to_resolution)[1].'] =0 or  statusCategory  != Done)';
		//$jql = 'project=SIEJIR_TEST  and  "First Contact Date" is not null  and  statusCategory  != Done)';
		//$jql = 'project=SIEJIR_TEST';
		if(($last_updated !='')&&($last_updated !=null))
			$jql = 'project=SIEJIR_TEST and updated > "'.$last_updated.'"';
	
		echo "Query for active tickets \n".$jql."\n";
		$last_updated=new \DateTime();
		$last_updated = $last_updated->format('Y-m-d H:i');
		$expand = ['changelog'];
		$fields = ['priority','key','summary','updated','statuscategorychangedate','status','resolutiondate',$this->premium_support,$this->first_contact_date,$this->violation_time_to_resolution,$this->gross_minutes_to_resolution];
		$data = $issueService->search($jql,0, 500,$fields,$expand);
		foreach($data->issues as $issue)
		{
			$this->ProcessActiveTickets($issue);
		}
		$this->db->Save(compact('last_updated'));
		
		//echo "--------------->".$this->db->Get('last_updated')."\n";
		
		//putenv("LAST_UPDATED=$last_updated\n");
		//echo getenv("LAST_UPDATED");
		echo "System updated till ".$last_updated."\n"; 
		
	}
	function CheckWhenToUpdate()
	{
		$last_updated = $this->db->Get('last_updated');
		$force_update = $this->db->Get('force_update');
		if($force_update == 1)
		{
			$force_update=0;
			$this->db->Save(compact('force_update'));
			return true;
		}
		
		if(($last_updated !='')&&($last_updated !=null))
		{
			$dt1 =new \DateTime($last_updated);
			$now =  new \DateTime();
			$difference = $now->diff($dt1);
			$minutes = $difference->days * 24 * 60;
			$minutes += $difference->h * 60;
			$minutes += $difference->i;
			if($minutes>30)
				return true;
			return false;
		}
		return true;
	}
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$this->db = new Database();
		if(!$this->CheckWhenToUpdate())
		{
			echo "Its not time to update";
			return;
		}
		
		
		if(!file_exists("customefields.json"))
		{
			echo "Custom Field Mapping Not Found\n";
			echo "Run command php artisan fetch:customfields\n";
			exit();
		}
		
		$google = new Google();
		$google->LoadSheet('1AWOu7CWyMNwuImZas4YmA6fKMVgq80HVdQnjh4hpogc','servers');
		
		$customfields = json_decode(file_get_contents("customefields.json"));
		foreach($customfields as $variable_name=>$customfield)
		{
			$this->customfields[$variable_name]=$customfield->id;
		}
		
		$now = new \DateTime();
		$google->Cell(12,'B',[[$now->format('m/d/Y H:i:s')]]);
		$google->SaveSheet();
		
		$this->PullActiveTickets();
		return;
		//\Log::info("Cron is working fine!");
    }
	function secondsToTime($seconds) 
	{
		$dtF = new \DateTime('@0');
		$dtT = new \DateTime("@$seconds");
		//echo $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
		return $dtF->diff($dtT)->format('%a:%h:%i');
	}
	function seconds2human($ss) 
	{
		$s = $ss%60;
		$m = floor(($ss%3600)/60);
		$h = floor(($ss)/3600);
		
		$d = floor($h/8);
		$h = $h%8;
		
		//return "$d days, $h hours, $m minutes, $s seconds";
		return "$d days,$h hours,$m minutes";
	}
	function get_working_minutes($ini_str,$end_str){
		
		//config
		$ini_time = [10,0]; //hr, min
		$end_time = [18,0]; //hr, min
		//date objects
		$ini = date_create($ini_str);
		$ini_wk = date_time_set(date_create($ini_str),$ini_time[0],$ini_time[1]);
		$end = date_create($end_str);
		$end_wk = date_time_set(date_create($end_str),$end_time[0],$end_time[1]);
		//days
		$workdays_arr = $this->get_workdays($ini,$end);
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

	function get_workdays($ini,$end){
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
}
