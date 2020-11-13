<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use App\CustomFields;
use App\Database;
use App\JiraTicket;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
protected $signature = 'sync:database {--rebuild=0} {--email=1} {--beat=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync From Jira and update Database';

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
	public function SearchJira($last_updated)
	{
		$debug = null;//'SIEJIR-5790';
		$max = 500;
		$start = 0;
		$issueService = new IssueService();
		$jql = 'project=SIEJIR_TEST   and (cf['.explode("_",$this->cf->gross_minutes_to_resolution)[1].'] is EMPTY or cf['.explode("_",$this->cf->gross_minutes_to_resolution)[1].'] =0 or  statusCategory  != Done)';
		$jql = 'project = Siebel_JIRA AND status != Closed AND "Product Name" !~ Vista AND "Product Name" !~ A2B AND "Product Name" !~ XSe';
		//$jql = 'project=Siebel_JIRA AND "Product Name" !~ Vista AND "Product Name" !~ A2B AND "Product Name" !~ XSe and updated >= startOfDay() ';
		
		$minutes = $this->option('beat');
		if($minutes == 0)
			$last_updated = '2020-01-01';
		
		if(($last_updated !='')&&($last_updated !=null))
			$jql = 'project=Siebel_JIRA AND "Product Name" !~ Vista AND "Product Name" !~ A2B AND "Product Name" !~ XSe  and updated >= "'.$last_updated.'"';
	
		if($debug != null)
			$jql = 'key in ('.$debug.')';
		//$jql = 'issue in (SIEJTEST-13)';
		echo "Query for active tickets \n".$jql."\n";
		
		$expand = ['changelog'];
		$fields = ['issuelinks','resolution','issuetype','assignee','priority','key','summary','updated','statuscategorychangedate','status','resolutiondate','created',$this->cf->violation_firstcontact,$this->cf->premium_support,$this->cf->first_contact_date,$this->cf->violation_time_to_resolution,$this->cf->gross_minutes_to_resolution,
		$this->cf->solution_provided_date,$this->cf->test_case_provided_date,$this->cf->account,$this->cf->product_name,$this->cf->component];
		$issues = [];
		while(1)
		{
			$data = $issueService->search($jql,$start, $max,$fields,$expand);
			if(count($data->issues) < $max)
			{
				foreach($data->issues as $issue)
				{
					$ticket = new JiraTicket($issue,$this->cf);
					if($ticket->key == $debug)
						$ticket->debug=1;
					$issues[] = $ticket ;
				}
				echo count($issues)." Found"."\n";
				return $issues;
			}
			foreach($data->issues as $issue)
			{
				$ticket = new JiraTicket($issue,$this->cf);
				if($ticket->key == $debug)
					$ticket->debug=1;
				$issues[] = $ticket ;	
			}
			$start = $start + count($data->issues);
		}
	}
	public function SaveTickets($tickets,$fromdb=0)
	{
		foreach($tickets as $ticket)
		{
			$this->db->SaveTicket($ticket,$fromdb);
		}
	}
	function TimeToUpdate($min)
	{
		$fu = $this->db->Get('force_update');
		$H=date("H");
		$i=date("i");
		if($fu != 0)
		{
			$force_update=0;
			$this->db->Save(compact('force_update'));
			//echo date("m")."\n";
			//echo date("d")."\n";
			//echo date("Y")."\n";
			//echo date("H")."\n";
			//echo date("i")."\n";
			
			if($fu == 1)
				return date("Y-m-d", mktime(0,0,0, date("m") , date("d")-1,date("Y")))." ".$H.":".$i;
			return $fu;
		}
		if(($min % 30)==0)	
			return date("Y-m-d", mktime(0, 0, 0, date("m") , date("d")-1,date("Y")))." ".$H.":".$i;
		
			return false;
		}

    public function handle()
    {
		//echo "2020-04-17 08:00','2020-04-17 09:00\n";
		//echo JiraTicket::get_working_minutes('2020-04-17 17:52','2020-04-21 00:28' );
		//echo JiraTicket::get_working_minutes('2020-04-17 08:00','2020-04-18 09:00');
		//return ;
		
        //
		$minutes = $this->option('beat');
		if($minutes % 10 == 0)// Every 10 minutes
		    file_get_contents("https://script.google.com/macros/s/AKfycbwCNrLh0BxlYtR3I9iW2Z-4RQK88Hryd4DEC03lIYLoLCce80A/exec?func=alive&device=support_sla");
				
			
		$rebuild = $this->option('rebuild');
		
		//dd($rebuild);
		$this->db = new Database();
		
		$email = $this->option('email');
		
		if($email==1)
			$this->db->email=1;
		else
			$this->db->email=0;


		//file_get_contents("https://script.google.com/macros/s/AKfycbwCNrLh0BxlYtR3I9iW2Z-4RQK88Hryd4DEC03lIYLoLCce80A/exec?func=alive&device=support_sla");
		
		//dd($this->db->email);
		if($rebuild == 0)
		{
			$rebuild = $this->TimeToUpdate($minutes);
			if($rebuild==false)
			{
			//echo "Its not time to update";
				return;
			}
		}
		
		
		//echo "ddd";
		$this->cf = new CustomFields();
		$new_updated=new \DateTime();
		$last_updated = $this->db->Get('last_updated');
		

		$tickets = $this->SearchJira($rebuild);
		$this->SaveTickets($tickets);
		
		$tickets = $this->db->LoadActiveTickets();
		echo "Active tickets=".count($tickets)."\n";
		$this->SaveTickets($tickets,1);
		
		$last_updated = $new_updated->format('Y-m-d H:i');
		$this->db->Save(compact('last_updated'));
		
		//$tickets = $this->db->ReadActiveTickets();
		//$this->Process($tickets);
		
    }
}
