var settings_sheet_name='Settings';
var fields_={
  'key':'key',
  'status':'status',
  'statusCategory':'status',
  'summary':'summary',
  'priority':'priority',
  'assignee':'assignee',
  'qa':'customfield_11500',
  'storypoint':'customfield_10005',
  'subtasks':'subtasks',
  'issuetype':'issuetype',
  'changelog':'changelog',
  'issuelinks':'issuelinks',
  'labels':'labels',
  
  /*'statusCategory':'status',
  'resolutiondate':'resolutiondate',
  'statuscategorychangedate':'statuscategorychangedate',
  'timeoriginalestimate':'timeoriginalestimate',
  'issuetype':'issuetype',
  'assignee':'assignee',
  'description':'description',
  'summary':'summary',
  'storypoint':'customfield_10004'*/
};

function MapIssueType_(type)
{
   return type;
}

function compare_item(a, b)
{
  // a should come before b in the sorted order
  //Logger.log(a.create+" "+b.create);
 // Logger.log(a.create < b.create);
 
  if(a.created < b.created){
    return -1;
    // a should come after b in the sorted order
  }else if(a.created > b.created){
    return 1;
    // and and b are the same
  }else{
    return 0;
  }
}
function ParseResponse(httpResponse)
{
  var issues = [];
  if (httpResponse) 
  {  
    var rspns = httpResponse.getResponseCode();
    switch(rspns){
      case 200:          
        var data = JSON.parse(httpResponse.getContentText());
        if(data["issues"] == undefined)
        {
          for(i=0;i<data["values"].length;i++)
          {
            if(data["values"][i].completeDate != null)
            {
              data["values"][i].completeDate=new Date( String(data["values"][i].completeDate)); 
              data["values"][i].completeDate = changeTimezone(data["values"][i].completeDate);
            }
            if(data["values"][i].startDate != null)
            {
              data["values"][i].startDate=new Date( String(data["values"][i].startDate))
              data["values"][i].startDate = changeTimezone(data["values"][i].startDate);
            }
            if(data["values"][i].endDate != null)
            {
              data["values"][i].endDate=new Date( String(data["values"][i].endDate))
              data["values"][i].endDate = changeTimezone(data["values"][i].endDate);
            } 
          }
          return data["values"];
        }
        for(i=0;i<data["issues"].length;i++)
        {
          issue = {};
          for(var field in fields_) 
          {
            
            switch(field)
            {
              case 'key': 
                 issue.key = data["issues"][i].key;
                 break;
              case 'status':
                issue.status = data["issues"][i].fields.status.name;
                break;
              case 'statusCategory':
                categoryid = data["issues"][i].fields.status.statusCategory.id;
                if(categoryid == 3)
                  issue.statusCategory = 'RESOLVED';
                else if(categoryid == 4)
                  issue.statusCategory = 'INPROGRESS';
				else
                  issue.statusCategory ='OPEN';
                break;
              case 'resolutiondate':
                if(data["issues"][i].fields.resolutiondate == null)
                  issue.resolutiondate=null;
                else
                {
                  issue.resolutiondate = new Date( String(data["issues"][i].fields.resolutiondate));
                  issue.resolutiondate = changeTimezone(issue.resolutiondate);
                }
                break;
              case 'statuscategorychangedate':
                if(data["issues"][i].fields.statuscategorychangedate == null)
                    issue.statuscategorychangedate = null;
                else
                {
                    issue.statuscategorychangedate = new Date( String(data["issues"][i].fields.statuscategorychangedate));
                    issue.statuscategorychangedate = changeTimezone(issue.statuscategorychangedate);
                }
                break;
             /* case 'storypoint':
                issue.storypoint = data["issues"][i].fields[fields_[field]];
                break;*/
              case 'timeoriginalestimate':
                issue.timeoriginalestimate = data["issues"][i].fields.timeoriginalestimate;
                break;
              case 'issuetype':
                issue.issuetype =  MapIssueType_(data["issues"][i].fields.issuetype.name);
                break;
              case 'assignee':
                if(data["issues"][i].fields.assignee == null)
                  issue.assignee = 'Unassigned';
                else
                   issue.assignee = data["issues"][i].fields.assignee.displayName.split(" ");
                break;
              case 'description':
                issue.description = data["issues"][i].fields.description;
                break;
              case 'summary':
                issue.summary = data["issues"][i].fields.summary;
                break;
              case 'priority':
                if(data["issues"][i].fields.priority === undefined)
                  issue.priority = null;
                else
                  issue.priority = data["issues"][i].fields.priority.name;
                break;
              case 'subtasks':
                issue.subtasks = [];
                if(data["issues"][i].fields.subtasks == undefined)
                  break;
                for(var j=0;j<data["issues"][i].fields.subtasks.length;j++)
                {
                  issue.subtasks [j] = {};
                  issue.subtasks [j].key = data["issues"][i].fields.subtasks[j].key;
                  issue.subtasks [j].type = data["issues"][i].fields.subtasks[j].fields.issuetype.name;
                  issue.subtasks [j].status = data["issues"][i].fields.subtasks[j].fields.status.name;
                }
                break;
              case  'changelog':
                //Logger.log('changelog');
                issue.transitions = [];
                if( data["issues"][i].changelog != null)
                {
                  for(var j=0;j<data["issues"][i].changelog.histories.length;j++)
                  {
                    history = data["issues"][i].changelog.histories[j];
                    for(var k=0;k<history.items.length;k++)
                    {
                      item=history.items[k];
                      if(item.field == "status")
                      {
                        item.created = new Date( String(history.created));
                        item.created = changeTimezone(item.created);
                        issue.transitions.push(item);
                      }
                    }
                  }
                }                        
                break;
              case 'issuelinks':
                issue.linkedtasks = [];
                if(data["issues"][i].fields.issuelinks === undefined)
                  break;
                for(j=0;j<data["issues"][i].fields.issuelinks.length;j++)
                {
                  link = data["issues"][i].fields.issuelinks[j];
                  lissue = {};
                  if(link.outwardIssue !== undefined)
                  {
                    lissue.key = link.outwardIssue.key;
                    lissue.type = link.outwardIssue.fields.issuetype.name;
                    lissue.status = link.outwardIssue.fields.status.name;
                    categoryid = link.outwardIssue.fields.status.statusCategory.id;
                    if(categoryid == 3)
                      lissue.statusCategory = 'RESOLVED';
                    else if(categoryid == 4)
                      lissue.statusCategory = 'INPROGRESS';
                    else
                      lissue.statusCategory ='OPEN'; 
                    issue.linkedtasks.push(lissue); 
                  }   
                }
                //Logger.log(issue.linkedtasks);
                break;
              case 'labels':
                if(data["issues"][i].fields.labels == null)
                  issue.labels=[];
                else
                  issue.labels = data["issues"][i].fields.labels;
                //Logger.log(issue.labels);
                break;
              case field:
                if(data["issues"][i].fields[fields_[field]] == null)
                  issue[field] = null;
                else 
                  issue[field] =  data["issues"][i].fields[fields_[field]]; 
                break;
             
                
            }
          }
          issue.transitions.sort(compare_item);
          //Logger.log(issue.transitions);
          //for(var j=0;j<issue.transitions.length;j++)
          //{
          //  Logger.log(issue.key+"-"+issue.transitions[j].created+"-"+issue.transitions[j].fromString+"-"+issue.transitions[j].toString);
          //  
          //}
          //Logger.log(issue.transitions);
          issues.push(issue);
        }
        //Logger.log(issues);
        //var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet(); //select active spreadsheet
        //sheet.getRange(2, 1, issuessss.length, 3).setValues(issuessss); // write from cell A2
        break;
      case 404:
        Logger.log("Response error, No item found");
        break;
      default:
        var data = JSON.parse(httpResponse.getContentText());
        Logger.log("Error: " + data.errorMessages.join(",")); // returns all errors that occured
        break;
    }
  }
  else {
    Logger.log("Jira Error","Unable to make requests to Jira!");
  }
  return issues;
}

/*foreach($this->issue->changelog->histories as $history)
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
					
				}*/

function JiraFetch_(resource)
{
  var start=0;
  var max=100;
  var token = GetConfig("TOKEN");
  var args = {
    contentType: "application/json",
    headers: {"Authorization":"Basic "+token},
    muteHttpExceptions : true
  };
  allissues = [];
  //Logger.log("Fetching = "+resource);
  while(1)
  {
    //Logger.log("Fetching = "+resource+'&startAt='+start+'&maxResults='+max);
    var httpResponse = UrlFetchApp.fetch(resource+'&startAt='+start+'&maxResults='+max, args);
    
    issues = ParseResponse(httpResponse);
    allissues = allissues.concat(issues);
    if(issues.length == max)
      start = start + max;
    else
    {
      //Logger.log("Found = "+allissues.length);
      return allissues;
    }
  }
  return [];
}

function SearchIssues(jql,fields=null)
{
   fields = fields==null? GetConfig('FIELDS'):fields;
   var resource = GetConfig("URL")+"/rest/api/2/search?"+'jql='+jql+'&fields='+fields;
   if(GetConfig("CHANGELOG") == 'true')
     resource += '&expand=changelog';
   return JiraFetch_(resource);
}
function BacklogIssues(boardid,fields=null)
{
   var userProperties = PropertiesService.getUserProperties();
   fields = fields==null? GetConfig('FIELDS'):fields;
   var resource = GetConfig("URL")+"/rest/agile/latest/board/"+boardid+'/backlog?fields='+fields;
   if(GetConfig("CHANGELOG") == 'true')
     resource += '&expand=changelog';
   return JiraFetch_(resource);
}
function BoardSprints(boardid,fields=null)
{
  var userProperties = PropertiesService.getUserProperties();
  fields = fields==null? GetConfig('FIELDS'):fields;
  var resource = GetConfig("URL")+"/rest/agile/1.0/board/"+boardid+'/sprint?';
  return JiraFetch_(resource);
}
function GetSprintId(sprintname,boardid)
{
  var sprints = BoardSprints(boardid);
  sprintname = sprintname.toLowerCase();
  for(var i=0;i<sprints.length;i++)
  {
    if(sprints[i].name.toLowerCase() === sprintname)
    {
      return sprints[i].id;
    } 
  }
  
}
function SprintIssues(boardid,sprint,fields=null)
{
  var sprintid = parseInt(sprint);
  //throw new Error( "My own exit" );
  if(Number.isInteger(sprintid)===false)
    sprintid = GetSprintId(sprint,boardid);
  fields = fields==null? GetConfig('FIELDS'):fields;
  var resource = GetConfig("URL")+"/rest/agile/1.0/board/"+boardid+'/sprint/'+sprintid+'/issue?fields='+fields;
  if(GetConfig("CHANGELOG") == 'true')
     resource += '&expand=changelog';
  return JiraFetch_(resource);
}