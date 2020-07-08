<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>
		<link rel="stylesheet" href="{{ asset('tabulator/css/tabulator.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('attention/attention.css') }}" />
    <style>
		.tabulator [tabulator-field="summary"]{
				max-width:200px;
		}

		.flex-container {
			height: 100%;
			padding: 0;
			margin: 0;
			display: -webkit-box;
			display: -moz-box;
			display: -ms-flexbox;
			display: -webkit-flex;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.row {
			width: auto;
			
		}
		.flex-item {
			text-align: center;
		}

    </style>
    </head>
    <body>
	<div class="flex-container">
	
	
		<div class="row"> 
		    
			<div class="flex-item"> 
				<div style="float:right"><a id="download" href="#">Download</a> </div>
				<a href="{{route('active')}}">Active </a>&nbsp&nbsp
				<a href="{{route('closed')}}">Closed </a>&nbsp&nbsp
				<a href="{{route('updated')}}">Recent updated</a>
			</div>
			<div class="flex-item"> 
				<br>
			</div>
			<div class="flex-item">
				<div style="box-shadow: 3px 3px #888888;" id="table"></div>
			</div>
			
			<div class="flex-item">
				<small> Last Updated {{ $last_updated}} CST <a id="update" href="#">Click to update</a> </small>
			</div>
		</div>
	</div>
    </body>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
	<script type="text/javascript" src="https://oss.sheetjs.com/sheetjs/xlsx.full.min.js"></script>
	<script src="{{ asset('tabulator/js/tabulator.min.js') }}" ></script>
	<script src="{{ asset('attention/attention.js') }}" ></script>
	<script>
	//define data
	var tabledata = @json($tickets);
	var jira_url = "{{$jira_url}}";
	var thistoday="{{date("Y-m-d H:i:s")}}";
	var dummy = [
		{id:1, name:"Oli Bob", location:"United Kingdom", gender:"male", rating:1, col:"red", dob:"14/04/1984"},
		{id:2, name:"Mary May", location:"Germany", gender:"female", rating:2, col:"blue", dob:"14/05/1982"},
		{id:3, name:"Christine Lobowski", location:"France", gender:"female", rating:0, col:"green", dob:"22/05/1982"},
		{id:4, name:"Brendon Philips", location:"USA", gender:"male", rating:1, col:"orange", dob:"01/08/1980"},
		{id:5, name:"Margret Marmajuke", location:"Canada", gender:"female", rating:5, col:"yellow", dob:"31/01/1999"},
		{id:6, name:"Frank Harbours", location:"Russia", gender:"male", rating:4, col:"red", dob:"12/05/1966"},
		{id:7, name:"Jamie Newhart", location:"India", gender:"male", rating:3, col:"green", dob:"14/05/1985"},
		{id:8, name:"Gemma Jane", location:"China", gender:"female", rating:0, col:"red", dob:"22/05/1982"},
		{id:9, name:"Emily Sykes", location:"South Korea", gender:"female", rating:1, col:"maroon", dob:"11/11/1970"},
		{id:10, name:"James Newman", location:"Japan", gender:"male", rating:5, col:"red", dob:"22/03/1998"},
	];
	
	var columns=[
        {title:"Key", field:"key", sorter:"string",align:"left",
			sorter:function(a, b, aRow, bRow, column, dir, sorterParams){
				va = a.split("-")[1];
				vb = b.split("-")[1]
				return va - vb; //you must return the difference between the two values
			},
			formatter:function(cell, formatterParams, onRendered)
			{
				url = days = cell.getRow().getData().url;
				return '<a href="'+jira_url+cell.getValue()+'">'+cell.getValue()+'</a>';
			}
		},
        {title:"Summary", field:"summary", sorter:"string", align:"left"},
		{title:"Account", field:"account", sorter:"string", align:"left"},
		{title:"E", field:"service_level", sorter:"number", align:"center"},
		{title:"SLA", field:"sla", sorter:"number", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue()+' Days';
			}
		},
		{title:"First Contact", field:"first_contact_date", sorter:"string", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{	
				violation_firstcontact = cell.getRow().getData().violation_firstcontact;
				//if(cell.getRow().getData().created < cell.getValue())
				if((violation_firstcontact==1)&&(cell.getValue() == ''))
					$(cell.getElement()).css({"color":"red"});
				//if(cell.getValue() == '')
					return cell.getRow().getData().net_time_to_firstcontact;
				//else
				//	return cell.getValue();
			}
		},
		{title:"Net minutes first_contact", field:"net_minutes_to_firstcontact", sorter:"number", align:"center",visible:false},
		
		{title:"Created", field:"created", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue().substring(0,10);
			}
		},
		{title:"Priority", field:"priority", sorter:"string", align:"center"},
		{title:"Net Time spent", field:"net_time_to_resolution", sorter:"string", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{
				value = cell.getValue();
				values = value.split(',');
				if(values.length ==1)
					return;
				if(values.length != 3)
					return 'Invalid';
				days = values[0].split(' ');
				hours = values[1].split(' ');
				min = values[2].split(' ');
				if((days[0] == 0)&&(hours[0]==0))
					return '';
				if((days[0] == 0)&&(hours[0]!=0))
					return hours[0]+' Hours';
				else
					return days[0]+'.'+hours[0]+' Days';
			},
			sorter:function(a, b, aRow, bRow, column, dir, sorterParams){
				va = aRow.getData().net_minutes_to_resolution;
				vb = bRow.getData().net_minutes_to_resolution;
				return va - vb; //you must return the difference between the two values
			}
		},
		{title:"Net minutes consumed", field:"net_minutes_to_resolution", sorter:"number", align:"center",visible:false},
		{title:"Gross Time spent", field:"gross_time_to_resolution", sorter:"string", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{
				value = cell.getValue();
				
				values = value.split(',');
				if(values.length ==1)
					return;
				if(values.length != 3)
					return 'Invalid';
				days = values[0].split(' ');
				hours = values[1].split(' ');
				if((days[0] == 0)&&(hours[0]==0))
					return '';
				if((days[0] == 0)&&(hours[0]!=0))
					return hours[0]+' Hours';
				else
					return days[0]+'.'+hours[0]+' Days';
			},
			sorter:function(a, b, aRow, bRow, column, dir, sorterParams){
				va = aRow.getData().gross_minutes_to_resolution;
				vb = bRow.getData().gross_minutes_to_resolution;
				return va - vb; //you must return the difference between the two values
			}
		},
		{title:"Gross minutes consumed", field:"gross_minutes_to_resolution", sorter:"number", align:"center",visible:false},
		//{title:"minutes first contact", field:"net_minutes_to_firstcontacte", sorter:"number", align:"center",visible:true},
		
		
		{title:"Quota(min)", field:"minutes_quota", sorter:"number", align:"center",visible:false},
		
		{title:"Resolved On", field:"resolutiondate", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue().substring(0,10);
			}
		},
		
		{title:"Time Consumed", field:"percent_time_consumed", sorter:"number", align:"left",visible:true,
			formatter:function(cell, formatterParams, onRendered)
			{
				_status = cell.getRow().getData()._status;
				time_consumed = cell.getValue();
				if(_status == 'RESOLVED')
				{
					return  '<span style="text-align: center;display: inline-block;width:'+'100'+'%;color:white;background-color:grey;"><small>'+time_consumed+'%</small></span>';
				}
				if(time_consumed <50)
				{
					bcolor='ForestGreen';
					fcolor='white';
				}
				else if(time_consumed <75)
				{
					bcolor='Gold';
					fcolor='black';
				}
				else if(time_consumed <100)
				{
					bcolor='orange';
					fcolor='black';
				}
				else
				{
					bcolor='red';
					fcolor='white';
				}
				
				return  '<span style="text-align: center;display: inline-block;width:'+time_consumed+'%;color:'+fcolor+';background-color:'+bcolor+';"><small>'+time_consumed+'%</small></span>';
			}
		},
		{title:"Status", field:"status", sorter:"string", align:"center",visible:true,
			formatter:function(cell, formatterParams, onRendered)
			{
				first_contact_date = cell.getRow().getData().first_contact_date;
				_status = cell.getRow().getData()._status;
				$(cell.getElement()).css({"background":"white"});
				$(cell.getElement()).css({"color":"black"});
				$(cell.getElement()).css({"border":"1px solid white"});
				
				if(_status == 'RESOLVED')
				{
					$(cell.getElement()).css({"background":"grey"});
					$(cell.getElement()).css({"color":"white"});
					return  cell.getValue();
				}
					
				if(first_contact_date == '')
				{
					$(cell.getElement()).css({"background":"orange"});
					return 'Waiting First Contact';
				}
				
				if( (cell.getValue() == 'Waiting Customer Feedback')||(cell.getValue() == 'Queued'))
				{
					$(cell.getElement()).css({"background":"yellow"});
					return  cell.getValue();
				}
				if(cell.getValue() == 'Pending Enhancements')
				{
					$(cell.getElement()).css({"background":"MediumTurquoise"});
					return  cell.getValue();
				}
				if(cell.getValue() == 'Pending Defect')
				{
					$(cell.getElement()).css({"background":"DarkKhaki"});
					return  cell.getValue();
				}
	
				$(cell.getElement()).css({"color":"white"});
				$(cell.getElement()).css({"background":"green"});
				return  cell.getValue();
			}
		},
		{title:"State", field:"_status", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				percent_time_consumed = cell.getRow().getData().percent_time_consumed;
				$(cell.getElement()).css({"background":"green"});
				$(cell.getElement()).css({"color":"white"});
				$(cell.getElement()).css({"border":"1px solid white"});
				
				if(percent_time_consumed > 50)
				{
					$(cell.getElement()).css({"background":"yellow"});
					$(cell.getElement()).css({"color":"black"});
				}
				if(percent_time_consumed > 75)
					$(cell.getElement()).css({"background":"orange"});
				if(percent_time_consumed == 100)
				{
					$(cell.getElement()).css({"background":"red"});
					$(cell.getElement()).css({"color":"white"});
				}
				if(cell.getValue() == 'RESOLVED')
				{
					$(cell.getElement()).css({"background":"grey"});
					$(cell.getElement()).css({"color":"white"});
				}
				return cell.getValue();
			}
		},
		{title:"Updated", field:"updated", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				ms = Math.floor(( Date.parse(thistoday) - Date.parse(cell.getValue()) ));
				t = millisToDaysHoursMinutes(ms);
				if(t.d > 0)
					return t.d+" days";
				else if(t.h > 0)
				{
					return t.h+" hours";
				}
				else if(t.m > 0)
				{
					return t.m+" min";
				}
				else if(t.s > 0)
				{
					return t.s+" sec";
				}
				else
					return "5 sec";
				
				return cell.getValue().substring(0,10);
			}
		},
		
        /*{title:"Gender", field:"gender", sorter:"string", cellClick:function(e, cell){console.log("cell click")},},
        {title:"Height", field:"height", formatter:"star", align:"center", width:100},
        {title:"Favourite Color", field:"col", sorter:"string"},
        {title:"Date Of Birth", field:"dob", sorter:"date", align:"center"},
        {title:"Cheese Preference", field:"cheese", sorter:"boolean", align:"center", formatter:"tickCross"},*/
    ];
	function millisToDaysHoursMinutes(miliseconds) 
	{
	  var days, hours, minutes, seconds, total_hours, total_minutes, total_seconds;
	  
	  total_seconds = parseInt(Math.floor(miliseconds / 1000));
	  total_minutes = parseInt(Math.floor(total_seconds / 60));
	  total_hours = parseInt(Math.floor(total_minutes / 60));
	  days = parseInt(Math.floor(total_hours / 24));

	  seconds = parseInt(total_seconds % 60);
	  minutes = parseInt(total_minutes % 60);
	  hours = parseInt(total_hours % 24);
	  
	  return { d: days, h: hours, m: minutes, s: seconds };

	};
	
	
	
	$(document).ready(function()
	{
		//define table
		$('#download').on('click',function()
		{
			table.showColumn("gross_minutes_to_resolution");
			table.showColumn("net_minutes_to_resolution");
			table.showColumn("net_minutes_to_firstcontact");
			table.download("xlsx", "support.xlsx", {sheetName:"tickets"});
			table.hideColumn("gross_minutes_to_resolution");
			table.hideColumn("net_minutes_to_resolution");
			table.hideColumn("net_minutes_to_firstcontact");
		});
		$('#update').on('click',function()
		{
			$.ajax({
				type:"GET",
				url:'{{route("sync")}}',
				cache: false,
				data:null,
				success: function(response){
					
					new Attention.Alert({
					title: 'Alert',
					content: 'A background update is requested',
					afterClose: () => {
						
					}
					});
				
				},
				error: function(response){
					new Attention.Alert({
					title: 'Alert',
					content: 'Failed',
					afterClose: () => {
						
					}
					});
				}
			});	
		});
		
		
		var table = new Tabulator("#table", {
			data:tabledata,
			columns:columns,
			tooltips:true,
			//autoColumns:true,
		});
		
	});
	
	</script>
</html>
