<?php
namespace App;
use \MongoDB\Client;
class Database
{
	function __construct()
	{
		$dbname = 'support_sla';
		$mongoClient=new Client("mongodb://127.0.0.1");
		$this->db = $mongoClient->$dbname;
		$collection = 'tickets';
		$this->collection = $this->db->$collection;
		$this->settings = $this->db->settings;
		
	}
	function Get($var)
	{
		$query=['id'=>'settings'];
		$obj = $this->settings->findOne($query);
		if($obj  == null)
			return null;
		if(isset($obj->$var))
				return $obj->$var;
		return null;
		
	}
	public function SendFirstContactEmail($ticket)
	{
		if($this->email)
		{
			$email = new Email();
		   $quota = JiraTicket::seconds2human($ticket->firstcontact_minutes_quota*60);
		   $email->SendFirstContactEmail($ticket,$quota);
		}
		else
			echo "No email sent as configured\r\n";
	}
	public function SendResolutionTimeEmail($ticket)
	{
		if($this->email)
		{
			$email = new Email();
			$quota = JiraTicket::seconds2human($ticket->minutes_quota*60);
			$email->SendResolutionTimeEmail($ticket,$quota);
		}
		else
			echo "No email sent as configured\r\n";
		return 0;
	}
	public function SendTimeToResolutionNotification($ticket)
	{
		if(!isset($ticket->time_to_resolution_alert))
			$ticket->time_to_resolution_alert=0;
		if($ticket->time_to_resolution_alert == '')
			$ticket->time_to_resolution_alert=0;
		
		if($ticket->percent_time_consumed >= 100)
		{
			if($ticket->time_to_resolution_alert<5)
			{
				$this->SendResolutionTimeEmail($ticket);
				$ticket->time_to_resolution_alert=5;
			}
		}
		else if($ticket->percent_time_consumed >= 90)
		{
			if($ticket->time_to_resolution_alert<4)
			{
				$this->SendResolutionTimeEmail($ticket);
				$ticket->time_to_resolution_alert=4;
			}
		}
		else if($ticket->percent_time_consumed >= 75)
		{
			if($ticket->time_to_resolution_alert<3)
			{
				$this->SendResolutionTimeEmail($ticket);
				$ticket->time_to_resolution_alert=3;
			}
		}
		else if($ticket->percent_time_consumed >= 50)
		{
			if($ticket->time_to_resolution_alert<2)
			{
				$this->SendResolutionTimeEmail($ticket);
				$ticket->time_to_resolution_alert=2;
			}
	}
		else if($ticket->percent_time_consumed >= 25)
	{
			if($ticket->time_to_resolution_alert<1)
		{
				$this->SendResolutionTimeEmail($ticket);
				$ticket->time_to_resolution_alert=1;
			}
		}
	}
	public function SendFirstContactNotification($ticket)
	{
		if(!isset($ticket->first_contact_alert))
			$ticket->first_contact_alert=0;
		if($ticket->first_contact_alert == '')
			$ticket->first_contact_alert=0;
		
		if(($ticket->first_contact_date == '')||($ticket->first_contact_date == null))
		{
			$ticket->percent_first_contact_time_consumed = 100	;
			if($ticket->net_minutes_to_firstcontact <= $ticket->firstcontact_minutes_quota)
				$ticket->percent_first_contact_time_consumed = round($ticket->net_minutes_to_firstcontact/$ticket->firstcontact_minutes_quota*100,1);
		}
		else
			return ;
		
		//echo $ticket->key."\n";
		//echo $ticket->percent_first_contact_time_consumed."\n";
		//echo $ticket->first_contact_alert."\n";
		
		
		if($ticket->percent_first_contact_time_consumed >= 100)
		{
			if($ticket->first_contact_alert<5)
			{
				$this->SendFirstContactEmail($ticket);
				$ticket->first_contact_alert=5;
			}
		}
		else if($ticket->percent_first_contact_time_consumed >= 90)
			{
			if($ticket->first_contact_alert<4)
			{
				$this->SendFirstContactEmail($ticket);
				$ticket->first_contact_alert=4;
			}
		}
		else if($ticket->percent_first_contact_time_consumed >= 75)
		{
			if($ticket->first_contact_alert<3)
			{
				$this->SendFirstContactEmail($ticket);
				$ticket->first_contact_alert=3;
			}
			}
		else if($ticket->percent_first_contact_time_consumed >= 50)
		{
			if($ticket->first_contact_alert<2)
			{
				$this->SendFirstContactEmail($ticket);
				$ticket->first_contact_alert=2;
		}
		}
		else if($ticket->percent_first_contact_time_consumed >= 25)
		{
			if($ticket->first_contact_alert<1)
			{
				$this->SendFirstContactEmail($ticket);
				$ticket->first_contact_alert=1;
			}
		}
	}
	public function UpdateFirstContactDelay($ticket)
	{
		if($ticket->first_contact_date != '')
		{
			//echo $ticket->created." ".$ticket->first_contact_date."\n";
			$ticket->net_minutes_to_firstcontact = JiraTicket::get_working_minutes($ticket->created,$ticket->first_contact_date );
		}
		else
		{
			$now =  JiraTicket::GetCurrentDateTime();
			$ticket->net_minutes_to_firstcontact = JiraTicket::get_working_minutes($ticket->created,$now->format('Y-m-d H:i') );
			//if('SIEJTEST-13' == $ticket->key)
			//{
			//	echo $ticket->key."\n";
			//	echo $ticket->created." - ".$now->format('Y-m-d H:i')."\n";
			//	echo $ticket->net_minutes_to_firstcontact."\n";
			//2020-03-25 00:37 2020-03-25 03:57
			//}
		}
		$ticket->net_time_to_firstcontact = JiraTicket::seconds2human($ticket->net_minutes_to_firstcontact*60);	
		//echo $ticket->violation_firstcontact."\n";
	
		if($ticket->firstcontact_minutes_quota<$ticket->net_minutes_to_firstcontact)
		{
			if($ticket->violation_firstcontact == 0)
			{
				JiraTicket::UpdateCustomField($ticket->key,'violation_firstcontact',1);
			}
			$ticket->violation_firstcontact = 1;
		}
		else
		{
			if($ticket->violation_firstcontact == 1)
			{
				JiraTicket::UpdateCustomField($ticket->key,'violation_firstcontact',0);
			}
			$ticket->violation_firstcontact = 0;
		}
		
		//echo $ticket->net_minutes_to_firstresponse."\n";
		//dd($ticket->created)."\n";
		
	}
	public function UpdateNetTimeToResolution($ticket)
	{
		$debug_ticket = 'SIEJIR-5531'; 
			
		$ticket->net_minutes_to_resolution = 0;
		$ticket->net_time_to_resolution = '';
		if($ticket->test_case_provided_date != null)
		{
			if(($ticket->solution_provided_date != null)||($ticket->resolutiondate != ''))// Ticket net resoluton time closedir
			{
				if($ticket->solution_provided_date != null)
				 $finish = new \DateTime($ticket->solution_provided_date);
				else
				 $finish =  new \DateTime($ticket->resolutiondate);
			}
			else
				$finish = JiraTicket::GetCurrentDateTime();
			
			$test_case_provided_date = new \DateTime($ticket->test_case_provided_date);
			$ticket->net_minutes_to_resolution = JiraTicket::get_working_minutes($test_case_provided_date,$finish);
			//echo $ticket->key."\n";
			///echo $ticket->net_minutes_to_resolution."\n";
			//echo $ticket->waitminutes."\n";
			if(isset($ticket->debug))
			{
				echo "Solution provided date".$ticket->solution_provided_date."\r\n";
				echo "Computed net minutes ".$ticket->net_minutes_to_resolution."\r\n";
				echo "Waiting minutes ".$ticket->waitminutes."\r\n";
			}
			$ticket->net_minutes_to_resolution = $ticket->net_minutes_to_resolution - $ticket->waitminutes ;
			$ticket->net_time_to_resolution  = JiraTicket::seconds2human($ticket->net_minutes_to_resolution*60);	
		
		}
		//echo $ticket->net_minutes_to_resolution."\n";
		//echo $ticket->net_time_to_resolution."\n";
	}
	public function UpdateTimeToResolution($ticket)
	{
		if(($ticket->resolutiondate != '')&&($ticket->first_contact_date != ''))
		{
			//$ticket->first_contact_date =  new \DateTime('2018-01-01');
			//echo "Firct Cnta Date=".$ticket->first_contact_date->format('Y-m-d\TH:i:s.u')."\n";
			//echo "Resolution Date=".$ticket->resolutiondate->format('Y-m-d\TH:i:s.u')."\n";
			$resolutiondate = new \DateTime($ticket->resolutiondate);
			$first_contact_date = new \DateTime($ticket->first_contact_date);
			
			$ticket->net_minutes_to_resolution = JiraTicket::get_working_minutes($ticket->first_contact_date,$ticket->resolutiondate);
			//echo "Net minutes to res=$ticket->net_minutes_to_resolution \n";
			$difference = $first_contact_date->diff($resolutiondate);
		}
		else if(($ticket->resolutiondate == ''))//&&($ticket->first_contact_date != ''))
		{
			//$ticket->first_contact_date =  new \DateTime('2020-03-25T08:57:00');
			//$ticket->first_contact_date->setTimezone(new \DateTimeZone('Asia/Karachi'));
			$created = new \DateTime($ticket->created);
			//echo "Firct Cnta Date=".$ticket->first_contact_date->format('Y-m-d\TH:i:s.u')."\n";
			$now =  JiraTicket::GetCurrentDateTime();
			//echo "Now =".$now->format('Y-m-d\TH:i:s.u')."\n";
			$difference = $created->diff($now);
			$ticket->net_minutes_to_resolution = JiraTicket::get_working_minutes($ticket->created,$now->format('Y-m-d\TH:i:s.u'));
			
		}
		
			
		if(!isset($ticket->net_minutes_to_resolution))
			$ticket->net_minutes_to_resolution = 0;
		//echo "Net minutes=$ticket->net_minutes_to_resolution\n";
		//echo "waitminutes=$ticket->waitminutes\n";
		$ticket->net_minutes_to_resolution = $ticket->net_minutes_to_resolution - $ticket->waitminutes ;
		//echo "Net minutes=$ticket->net_minutes_to_resolution\n";
		
		$ticket->net_time_to_resolution  = JiraTicket::seconds2human($ticket->net_minutes_to_resolution*60);	
		//echo "Net time=$ticket->net_time_to_resolution\n";

		if(isset($difference))
		{
			$ticket->gross_minutes_to_resolution = $difference->days * 24 * 60;
			$ticket->gross_minutes_to_resolution += $difference->h * 60;
			$ticket->gross_minutes_to_resolution += $difference->i;
		
			$ticket->gross_time_to_resolution=JiraTicket::seconds2human($ticket->gross_minutes_to_resolution*60);
			$ticket->gross_time_to_resolution=$difference->days." days,".$difference->h." hours,".$difference->i." minutes";
		}
		else
		{
			$ticket->gross_minutes_to_resolution = 0;	
			$ticket->gross_time_to_resolution = '';
		}
			

		//echo "Gross minutes=$ticket->gross_minutes_to_resolution\n";
		//echo "days = ". $difference->days."\n";
		//echo "hours = ". $difference->h."\n";
		//echo "minutes = ". $difference->i."\n";
		
		//if($ticket->gross_minutes_to_resolution < $ticket->net_minutes_to_resolution)
		//	$ticket->gross_minutes_to_resolution = $ticket->net_minutes_to_resolution;
		
		$this->UpdateNetTimeToResolution($ticket);
		
		$ticket->percent_time_consumed = 100;
		if($ticket->net_minutes_to_resolution < $ticket->minutes_quota)
			$ticket->percent_time_consumed = round($ticket->net_minutes_to_resolution/$ticket->minutes_quota*100,1);
		
		if($ticket->percent_time_consumed>=100)
		{
			if($ticket->violation_time_to_resolution == 0)
			{
				JiraTicket::UpdateCustomField($ticket->key,'violation_time_to_resolution',1);
			}
			$ticket->violation_time_to_resolution = 1;
		}
		else
		{
			if($ticket->violation_time_to_resolution == 1)
			{
				JiraTicket::UpdateCustomField($ticket->key,'violation_time_to_resolution',0);
			}
			$ticket->violation_time_to_resolution = 0;
		}
			
			
		/*echo "ticket->gross_minutes_to_resolution=$ticket->gross_time_to_resolution\n";
		echo "ticket->net_minutes_to_resolution=$ticket->net_time_to_resolution\n";
		
		echo "ticket->gross_time_to_resolution=$ticket->gross_time_to_resolution\n";
		echo "ticket->net_time_to_resolution=$ticket->net_time_to_resolution\n";*/
			
	}
	
	function SaveTicket($ticket,$fromdb=0)
	{
		//echo "Saving ".$ticket->key."\n";
		$query=['key'=>$ticket->key];
		$options=['upsert'=>true];
		
		$obj=new \StdClass();
		
		$obj->key = $ticket->key;
		$obj->summary = $ticket->summary;
		if($fromdb == 1)
		{
			if(!isset($ticket->assignee))
				$ticket->assignee = '';
		}
		
		$p = explode(">",$ticket->product_name);
		if(count($p)==3)
		{
			$obj->product_name = explode("<",$p[1])[0];
		}
		else
			$obj->product_name = $ticket->product_name;
		
		$obj->product_name = str_replace("\n","",$obj->product_name);
		
		$obj->issuetype = $ticket->issuetype;
		$obj->component = '';
		if($ticket->component != null)
		{
			$obj->component = $ticket->component;
		}
		$obj->issuelinks = $ticket->issuelinks;
		$obj->resolution = $ticket->resolution;
		$obj->assignee = $ticket->assignee;
		$obj->status = $ticket->status;
		$obj->_status = $ticket->_status;
		$obj->violation_firstcontact = $ticket->violation_firstcontact;
		$obj->violation_time_to_resolution = $ticket->violation_time_to_resolution;
		
		$obj->firstcontact_minutes_quota = $ticket->firstcontact_minutes_quota;
		if($fromdb == 0)
		{
			$obj->account = $ticket->account;  
			
		}
		else
		{
			if(!isset($ticket->account))
				$obj->account = "";
		}
	
		if(($ticket->first_contact_date != null)||($ticket->first_contact_date != ''))
		{
			if($ticket->first_contact_date instanceof \DateTime)
				$obj->first_contact_date = $ticket->first_contact_date->format('Y-m-d H:i');
			else
				$obj->first_contact_date = $ticket->first_contact_date;
		}
		else		
			$obj->first_contact_date = '';
			
		if(($ticket->resolutiondate != null)||($ticket->resolutiondate != ''))
		{
			if($ticket->resolutiondate instanceof \DateTime)
			{
				$obj->resolutiondate = $ticket->resolutiondate->format('Y-m-d H:i');
				$obj->closedon  = $ticket->resolutiondate->getTimestamp();
			}
			else
			{
				$obj->resolutiondate = $ticket->resolutiondate;
				$obj->closedon = $ticket->closedon;
			}
		}
		else
		{
			$obj->resolutiondate = '';
			$obj->closedon = '';
		}
		
		
		if($ticket->created instanceof \DateTime)
			$obj->created = $ticket->created->format('Y-m-d H:i');
		else
			$obj->created = $ticket->created;
		
		
		$obj->waitminutes = $ticket->waitminutes;
		if($ticket->updated instanceof \DateTime)
			$obj->updated = $ticket->updated->format('Y-m-d H:i');
		else
			$obj->updated = $ticket->updated;
		$obj->priority = $ticket->priority;
		$obj->service_level = $ticket->service_level;
		$obj->sla = $ticket->sla;
		$obj->minutes_quota = $ticket->minutes_quota;
		
		//if(!isset($ticket->solution_provided_date))
		//	dd($ticket);
		if($ticket->solution_provided_date instanceof \DateTime)
			$obj->solution_provided_date = $ticket->solution_provided_date->format('Y-m-d H:i');
		else
			$obj->solution_provided_date = $ticket->solution_provided_date;
		
		if($ticket->test_case_provided_date instanceof \DateTime)
			$obj->test_case_provided_date = $ticket->test_case_provided_date->format('Y-m-d H:i');
		else
			$obj->test_case_provided_date = $ticket->test_case_provided_date;
		//echo $obj->key."<br>";
		//dump($obj->test_case_provided_date );
		if(isset($ticket->first_contact_alert))
			$obj->first_contact_alert = $ticket->first_contact_alert;
		
		if(isset($ticket->time_to_resolution_alert))
			$obj->time_to_resolution_alert = $ticket->time_to_resolution_alert;
		
		$this->UpdateTimeToResolution($obj);
		$this->UpdateFirstContactDelay($obj);
		if($fromdb)
		{
			$this->SendTimeToResolutionNotification($obj);
			$this->SendFirstContactNotification($obj);
		}
		$obj= json_decode(json_encode($obj));
		$this->collection->updateOne($query,['$set'=>$obj],$options);
	}

	function LoadActiveTickets()
	{
		$query = ['_status' => ['$ne' =>'RESOLVED']];
		$options = ['sort' => ['updated' => -1],
					'projection' => ['_id' => 0]];
		$cursor = $this->collection->find($query,$options);
		$tickets = $cursor->toArray();
		return $tickets;
		//dd($tickets);
		
	}
	function LoadClosedTickets()
	{
		$date = new \DateTime("-6 months");
		$query = ['_status' => 'RESOLVED','closedon'=>['$gte'=>$date->getTimestamp()]];
		$options = ['sort' => ['closedon' => -1],
				    //'limit' => 50 ,
					'projection' => ['_id' => 0]];

		$cursor = $this->collection->find($query,$options);
		$tickets = $cursor->toArray();
		return $tickets;
		//dd($tickets);
		
	}
	function LoadAllTickets()
	{
		$query = [];
		$options = [
					'projection' => ['_id' => 0],
					'sort' => ['updated' => -1],
				    'limit' => 100 ,
					'projection' => ['_id' => 0]];

		$cursor = $this->collection->find($query,$options);
		$tickets = $cursor->toArray();
		//$tickets[0]->key='SIEJTEST-30';
		//$tickets[0]->summary = "dsdsadsdsdasdsdsadsadsadsdsdasdsadsdsdasdsdsdasdsdasdsadsadsadsadsaddsadsadsdsd";
		return $tickets;
		//dd($tickets);
	}
	function Save($arr)
	{
		$query=['id'=>'settings'];
		$obj = $this->settings->findOne($query);
		if($obj  == null)
		{
			$obj = new \StdClass();
			$obj->id = 'settings';
		}
		foreach($arr as $key=>$val)
		{
			$obj->$key=$val;
		}
		$options=['upsert'=>true];
		$this->settings->updateOne($query,['$set'=>$obj],$options);
	}
}