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
	function SaveTicket($ticket)
	{
		$query=['key'=>$ticket->key];
		$options=['upsert'=>true];
		
		$obj=[];
		$obj['key'] = $ticket->key;
		$obj['summary'] = $ticket->summary;
		$obj['status'] = $ticket->status;
		$obj['_status'] = $ticket->_status;
		if($ticket->first_contact_date != null)
			$obj['first_contact'] = $ticket->first_contact_date->format('Y-m-d H:i');
		else
		{		
			$obj['first_contact'] = '';
			$obj['_status'] = 'WAITING';
		}
		
		
		if($ticket->resolutiondate != null)
			$obj['resolvedon'] = $ticket->resolutiondate->format('Y-m-d H:i');
		else
			$obj['resolvedon'] = '';
		$obj['net_minutes_consumed'] =   $ticket->net_minutes_to_resolution;
		$obj['gross_minutes_consumed'] =   $ticket->_gross_minutes_to_resolution;
		$obj['updated'] = $ticket->updated->format('Y-m-d H:i');
		$obj['minutes_quota'] = $ticket->minutes_quota;
		$obj['percent_time_consumed'] = round($ticket->percent_time_consumed,1);
		$obj['net_time_consumed'] = $ticket->net_time_to_resolution;
		$obj['gross_time_consumed'] = $ticket->gross_time_to_resolution;
		$obj['priority'] = $ticket->priority;
		$obj['sla'] = $ticket->sla;
		$obj['service_level'] =  $ticket->service_level;
		//$obj['url'] = $ticket->url;
		$this->collection->updateOne($query,['$set'=>$obj],$options);
	}
	function LoadActiveTickets()
	{
		$query = ['_status' => ['$ne' =>'RESOLVED']];
		$cursor = $this->collection->find($query);
		$tickets = $cursor->toArray();
		return $tickets;
		//dd($tickets);
		
	}
	function LoadClosedTickets()
	{
		$query = ['_status' => 'RESOLVED'];
		$options = ['sort' => ['updated' => -1],
				    'limit' => 50 ];

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
				    'limit' => 100 ];

		$cursor = $this->collection->find($query,$options);
		$tickets = $cursor->toArray();
		//$tickets[0]->key='SIEJTEST-30';
		//$tickets[0]->summary = "dsdsadsdsdasdsdsadsadsadsdsdasdsadsdsdasdsdsdasdsdasdsadsadsadsadsaddsadsadsdsd";
		return $tickets;
		//dd($tickets);
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
