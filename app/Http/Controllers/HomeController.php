<?php

namespace App\Http\Controllers;
use \MongoDB\Client;
use \MongoDB\BSON\UTCDateTime;
use App\Database;

use Auth;
use Illuminate\Http\Request;
use Response;
use App\JiraTicket;
use Artisan;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
		date_default_timezone_set('Asia/Karachi');
		$this->db = new Database();
		$this->last_updated = $this->db->Get('last_updated');
    }
	public function ActiveTicketData(Request $request)
	{
		dd($tickets = $this->db->LoadActiveTickets());
	}
	public function ClosedTicketData(Request $request)
	{
		dd($tickets = $this->db->LoadClosedTickets());
	}
	
	public function Active(Request $request)
	{
		
		$tickets = $this->db->LoadActiveTickets();
		
		$lu = new \DateTime($this->last_updated);
		JiraTicket::SetTimeZone($lu);
		$last_updated = $lu->format('Y-m-d H:i:s');
		
		$jira_url = env('JIRA_HOST','')."/browse/";
		return view('home',compact('tickets','last_updated','jira_url'));
	}
	public function Closed(Request $request)
	{
		$tickets = $this->db->LoadClosedTickets();
		
		$lu = new \DateTime($this->last_updated);
		JiraTicket::SetTimeZone($lu);
		$last_updated = $lu->format('Y-m-d H:i:s');
		
		$jira_url = env('JIRA_HOST','')."/browse/";
		return view('home',compact('tickets','last_updated','jira_url'));
	}
	public function AllUpdated(Request $request)
	{
		$tickets = $this->db->LoadAllTickets();
		$lu = new \DateTime($this->last_updated);
		JiraTicket::SetTimeZone($lu);
		$last_updated = $lu->format('Y-m-d H:i:s');
		
		$jira_url = env('JIRA_HOST','')."/browse/";
		return view('home',compact('tickets','last_updated','jira_url'));
	}
	function checkIsAValidDate($myDateString)
	{
		return (bool)strtotime($myDateString);
	}
	public function Sync(Request $request)
	{
		$force_update=1;
		if($request->rebuild != null)
		{
			if($this->checkIsAValidDate($request->rebuild))
				$force_update=$request->rebuild;
			else
				return ['message'=>"Invalid rebuild date"];
			
		}
		$this->db->Save(compact('force_update'));
		//return Response::json(['error' => 'Invalid Credentials'], 404); 
		if($request->rebuild !=null)
			return ['message'=>"Rebuild from ".$force_update." Initiated in background"];
		else
			return ['message'=>"Update initiated in background"];
	}
}