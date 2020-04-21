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
	public function SendEmail($ticket)
	{
		
		return 0;//-1
	}
	public function SendNotification($ticket)
	{
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
		if(!isset($ticket->alert))
			$ticket->alert=0;
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
		else if(($ticket->resolutiondate == '')&&($ticket->first_contact_date != ''))
		{
			//$ticket->first_contact_date =  new \DateTime('2020-03-25T08:57:00');
			//$ticket->first_contact_date->setTimezone(new \DateTimeZone('Asia/Karachi'));
			$first_contact_date = new \DateTime($ticket->first_contact_date);
			//echo "Firct Cnta Date=".$ticket->first_contact_date->format('Y-m-d\TH:i:s.u')."\n";
			$now =  JiraTicket::GetCurrentDateTime();
			//echo "Now =".$now->format('Y-m-d\TH:i:s.u')."\n";
			$difference = $first_contact_date->diff($now);
			$ticket->net_minutes_to_resolution = JiraTicket::get_working_minutes($ticket->first_contact_date,$now->format('Y-m-d\TH:i:s.u'));
			
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
		
		
		
		$ticket->percent_time_consumed = 100;
		if($ticket->net_minutes_to_resolution <= $ticket->minutes_quota)
			$ticket->percent_time_consumed = round($ticket->net_minutes_to_resolution/$ticket->minutes_quota*100,1);

			
		/*echo "ticket->gross_minutes_to_resolution=$ticket->gross_time_to_resolution\n";
		echo "ticket->net_minutes_to_resolution=$ticket->net_time_to_resolution\n";
		
		echo "ticket->gross_time_to_resolution=$ticket->gross_time_to_resolution\n";
		echo "ticket->net_time_to_resolution=$ticket->net_time_to_resolution\n";*/
			
	}
	
	function SaveTicket($ticket)
	{
		//echo "Saving ".$ticket->key."\n";
		$query=['key'=>$ticket->key];
		$options=['upsert'=>true];
		
		$obj=new \StdClass();
		
		$obj->key = $ticket->key;
		$obj->summary = $ticket->summary;
		$obj->status = $ticket->status;
		$obj->_status = $ticket->_status;
		$obj->violation_firstcontact = $ticket->violation_firstcontact;
		
		$obj->firstcontact_minutes_quota = $ticket->firstcontact_minutes_quota;
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
				$obj->resolutiondate = $ticket->resolutiondate->format('Y-m-d H:i');
			else
				$obj->resolutiondate = $ticket->resolutiondate;
		}
		else
			$obj->resolutiondate = '';
		
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
		$this->UpdateTimeToResolution($obj);
		$this->UpdateFirstContactDelay($obj);
		$this->SendNotification($obj);
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
		$query = ['_status' => 'RESOLVED'];
		$options = ['sort' => ['updated' => -1],
				    'limit' => 50 ,
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