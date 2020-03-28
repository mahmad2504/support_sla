<?php
namespace App\Console\Commands;

use JiraRestApi\Field\Field;
use JiraRestApi\Field\FieldService;
use JiraRestApi\JiraException;


use Illuminate\Console\Command;

class CustomFields extends Command
{
	private $variable_customfields_map=[
				'premium_support'=>'Premium Support',
				'first_contact_date'=>'First Contact Date',
				'violation_time_to_resolution'=>'Violation Time to Resolution',
				'gross_time_to_resolution'=>'Gross Time to Resolution',   
				'gross_minutes_to_resolution'=>'gross_minutes_to_resolution',  
				'net_time_to_resolution'=>'Net Time to Resolution',
				'waiting_time'=>'Time in Status(WFC)',
			];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'configure:customfields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Map Jira customfields with state variable';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		
        //
		try 
		{
			$fieldService = new FieldService();
			// return custom field only. 
			$ret = $fieldService->getAllFields(Field::CUSTOM); 
			foreach($ret as $field)
			{
				foreach($this->variable_customfields_map as $variablename=>$fieldname)
				{
					if($fieldname == $field->name)
					{
						$this->variable_customfields_map[$variablename] = $field; 
						$this->variable_customfields_map[$variablename]->variablename = $variablename;
					}
				}
				//dd($field);
			}
			foreach($this->variable_customfields_map as $variablename=>$field)
			{
				if(!is_object($field))
				{
					echo "Field ".$field." not set\n";
					exit();
				}
			}
			file_put_contents("customefields.json",json_encode($this->variable_customfields_map));
			//dump($this->variable_customfields_map);
		} catch (JiraRestApi\JiraException $e) 
		{
			$this->assertTrue(false, 'testSearch Failed : '.$e->getMessage());
		}
		echo "Done";
    }
}
