function changeTimezone(date, ianatz="Africa/Johannesburg") 
{
  // suppose the date is 12:00 UTC
  var invdate = new Date(date.toLocaleString('en-US', {
    timeZone: ianatz
  }));
  return invdate;
}
function GetEndDataFromTransition(transition,end)
{
  if((transition.toString == 'Ready to Test')||(transition.toString == 'Done')||
        (transition.String == "Closed (Won't Do)")||(transition.toString == "Closed"))
  {
     return transition.created;
  }
  return end;
}     
function CheckInReview(transition)
{
  if((transition.toString == 'In Code Review')||(transition.toString == 'Done'))
      return 1;
  else
      return 0;
}
function SprintStories()
{
  var self = this;
  var remainingStoryPoints = 0;
  var completedStoryPoints = 0;
  Date.prototype.yyyymmdd = function() {         

    var yyyy = this.getFullYear().toString();                                    
    var mm = (this.getMonth()+1).toString(); // getMonth() is zero-based         
    var dd  = this.getDate().toString();             

    return yyyy + '-' + (mm[1]?mm:"0"+mm[0]) + '-' + (dd[1]?dd:"0"+dd[0]);
    };
  
  this.Sync = function(boardid,sprint)
  {
    issues = SprintIssues(boardid,sprint);
    issues = self.Filter(issues);
    this.issues = self.FetchSubIssues(issues);
    this.remainingStoryPoints = 0;
    this.completedStoryPoints = 0;
  }
  this.DumpSprintProgress = function(data_sheet_name,row=null,columns=null)
  {
   var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(data_sheet_name);
    var rowno=row==null?3:row;
    var c=columns==null?
      {
        'date':9,
        'progress':12,
      }:columns;
    
     for(var i=0;i<15;i++)
     {
       var tag = sheet.getRange(rowno+i,c.date-1).getValue();
       var date = sheet.getRange(rowno+i,c.date).getValue();
       if (!(date instanceof Date))
         break;
         
       var today = new Date();
       var today = changeTimezone(today);
       
       if(today.yyyymmdd() == date.yyyymmdd())
       {
           //Logger.log(tag+"-"+(rowno+i)+"-Today "+date.toISOString().split("T")[0]);
           sheet.getRange(rowno+i,c.progress).setValue(this.remainingStoryPoints);   
           //Logger.log("this.remainingStoryPoints="+this.remainingStoryPoints);
           break;
       }
     }  
  }
  this.DumpStorys = function(data_sheet_name,row=null,columns=null)
  {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(data_sheet_name);
    var rowno=row==null?65:row;
    var c=columns==null?
    {
    'issuetype':1,
    'key':2,
    'summary':3,
    'priority':4,
    'assignee':5,
    'qa':6,
    'storypoint':7,
    'status':8,
    'new':9, //This can remain manual unless there is a way to determine if the story was carried over when the last sprint was closed						
    'hasdeskchecktasks':10,
    'start':11,
    'estimatedprogress':12,//Manual field (estimate given by dev during standup)
    'due':13,
    'end':14,
    'actualduration':15,
    'unknownfield':16,  // This was left empty in original data sheet which was shared
    'estimatedaccuracy':17,
    'deskcheckscompleted':18,
    'deskcheckfailed':19,
    'label':
      {
         'styling':20,
         'development':21,
         'requirements':22,
         'design':23,
         'integration':24,
         'unclassified':25
       }
    }:columns;
    var records = {};
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(data_sheet_name);
    while(1)
    {
      var key = sheet.getRange(rowno,c.key).getValue();
      if(key == '')
      {
        break;
      }
      records[key]={};
      records[key].key = key;
      records[key].rowno = rowno;
      records[key].valid = 0;
      rowno++;
    }  
    for(var i=0;i<this.issues.length;i++)
    {
      issue = this.issues[i];
      //Logger.log("Issue "+issue.key);
      if( records[issue.key] === undefined)
      {
        issue.rowno = rowno;
        rowno++;
      }
      else
      {
        issue.rowno = records[issue.key].rowno;
      }
      records[issue.key] = issue;
      records[issue.key].valid = 1;
    }
    for (const key in records) 
    {
      issue = records[key];
      if(issue.valid == 0)
      {
        //Ignore tasks that are present in sheet but are no more in sprint
        sheet.getRange(issue.rowno,c.key).setBackground("red");
        continue;
      } 
      
      //Logger.log("Processing issue "+issue.key);
      self.UpdateFromTransitions(issue);
      
      if(issue.end != null)
      {
        this.completedStoryPoints += issue.storypoint; 
      }
      else
      {
        this.remainingStoryPoints += issue.storypoint; 
      }
      sheet.getRange(issue.rowno,c.key).setBackground("white");
      sheet.getRange(issue.rowno,c.issuetype).setValue(issue.issuetype); 
      sheet.getRange(issue.rowno,c.key).setValue(issue.key); 
      sheet.getRange(issue.rowno,c.summary).setValue(issue.summary);
      sheet.getRange(issue.rowno,c.priority).setValue(issue.priority);
      sheet.getRange(issue.rowno,c.assignee).setValue(issue.assignee);
      sheet.getRange(issue.rowno,c.qa).setValue(issue.qa);
      sheet.getRange(issue.rowno,c.storypoint).setValue(issue.storypoint);
      sheet.getRange(issue.rowno,c.status).setValue(issue.status);
      sheet.getRange(issue.rowno,c.hasdeskchecktasks).setValue(issue.deschchecktaskcount>0?'TRUE':'FALSE');
      sheet.getRange(issue.rowno,c.start).setValue(issue.start);
      sheet.getRange(issue.rowno,c.due).setValue(self.DueDate(issue));
      sheet.getRange(issue.rowno,c.end).setValue(issue.end);
      sheet.getRange(issue.rowno,c.actualduration).setValue(self.ActualDuration(issue));
      sheet.getRange(issue.rowno,c.estimatedaccuracy).setValue(self.EstimatedAccuracy(issue));
      sheet.getRange(issue.rowno,c.deskcheckscompleted).setValue(issue.deskcheckscompleted);
      sheet.getRange(issue.rowno,c.deskcheckfailed).setValue(self.DeskCheckFailures(issue));
      sheet.getRange(issue.rowno,c.label.styling).setValue(self.GetLabelCount(issue,'Styling'));
      sheet.getRange(issue.rowno,c.label.development).setValue(self.GetLabelCount(issue,'Development'));
      sheet.getRange(issue.rowno,c.label.requirements).setValue(self.GetLabelCount(issue,'Requirements'));
      sheet.getRange(issue.rowno,c.label.design).setValue(self.GetLabelCount(issue,'Design'));
      sheet.getRange(issue.rowno,c.label.integration).setValue(self.GetLabelCount(issue,'Integration'));
      sheet.getRange(issue.rowno,c.label.unclassified).setValue(self.GetLabelCount(issue,'Unclassified'));
     
    }
  }
  this.GetLabelCount = function(issue,label)
  {
    label=label.toLowerCase();
    if((issue.linkedlabels[label] === undefined)||((issue.linkedlabels[label] === 0)))
      return null;
    else
      return issue.linkedlabels[label];
  }
  this.DeskCheckFailures = function(issue)
  {
    if(issue.deskcheckfailed > issue.deschchecktaskcount)
      return issue.deskcheckfailed - issue.deschchecktaskcount;
    else
      return null;
  }
  this.EstimatedAccuracy = function(issue)
  {
    issue.estimatedaccuracy = null;
    if(issue.actualduration != null && issue.storypoint != null)
      issue.estimatedaccuracy = issue.actualduration - issue.storypoint;
    if(issue.estimatedaccuracy != null)
    {
      return issue.estimatedaccuracy;
      /*if(issue.estimatedaccuracy < 0)
        return 'Ahead Schedule';
      else if(issue.estimatedaccuracy > 0)
        return 'Behind Schedule';
      else
       return 'On Schedule';*/
    }
    return null;
  }
  this.DateDiff = function(date1,date2)
  {
    var one_day=1000*60*60*24;
    // Convert both dates to milliseconds
    var date1_ms = date1.getTime();
    var date2_ms = date2.getTime();
    // Calculate the difference in milliseconds
    var difference_ms = date2_ms - date1_ms;
    // Convert back to days and return
    return Math.round(difference_ms/one_day); 
  }
  this.ActualDuration = function(issue)
  {
    issue.actualduration = null;
    if((issue.start !=null)&&(issue.end != null))
       issue.actualduration = self.DateDiff(issue.start,issue.end);
    return issue.actualduration;
  }
  
  this.add_business_days =function(d,n) 
  {
    d = new Date(d.getTime());
    var day = d.getDay();
    d.setDate(d.getDate() + n + (day === 6 ? 2 : +!day) + (Math.floor((n - 1 + (day % 6 || 1)) / 5) * 2));
    return d;
  }
  this.DueDate = function(issue)
  {
    issue.due = null;
    if((issue.storypoint != null)&&(issue.start !=null))
    {
      issue.due = self.add_business_days(issue.start,issue.storypoint);
      //Logger.log(issue.start.toISOString().split("T")[0]+"-"+issue.storypoint+"="+issue.due.toISOString().split("T")[0]);
    }
    return issue.due;
  }  
  this.UpdateFromTransitions = function(issue)
  {
    issue.inreview=0;
    //if(issue.key == 'NF-1405')
    //   Logger.log(issue.transitions);
    for(var i=0;i<issue.transitions.length;i++)
    {
      issue.inreview += CheckInReview(issue.transitions[i]);
      //if((issue.transitions[i].toString == 'In Code Review')||(issue.transitions[i].toString == 'Done'))
      //  issue.inreview++;
      
      if((issue.transitions[i].toString == 'In Progress')&&(issue.start == null))
      {
         issue.start = issue.transitions[i].created;
      }
      issue.end = GetEndDataFromTransition(issue.transitions[i],issue.end);
      //if((issue.transitions[i].toString == 'Ready to Test')||(issue.transitions[i].toString == 'Done')||
      //  (issue.transitions[i].toString == "Closed (Won't Do)")||(issue.transitions[i].toString == "Closed"))
      //{
      //  issue.end = issue.transitions[i].created;
      //}
    }
    
  }
  this.Filter = function(issues)
  {
    filteredtasks = [];
    for(var i=0;i<issues.length;i++)
    {
      issue = issues[i];
      if(issue.issuetype == 'Sub-task' || issue.issuetype == 'Desk-Check')
      {
        //  IGNORE THESE TYPES AS SPRINT TASKS
      }
      else
        filteredtasks.push(issue);
    }
    return filteredtasks;
  }
  this.FetchSubIssues =  function(issues)
  {
     
    del='';
    subissues='';
    for(var i=0;i<issues.length;i++)
    {
      issue = issues[i];
      issue.linkedlabels={
        'unclassified':0,
        'styling':0,
        'development':0,
        'requirements':0,
        'design':0,
        'integration':0,
      };
      for(var j=0;j<issue.subtasks.length;j++)
      {
         if(issue.subtasks[j].type == 'Desk-Check')
         {
            subissues += del+issue.subtasks[j].key;
            del=',';
         }
      }
      for(var j=0;j<issue.linkedtasks.length;j++)
      {
        if(issue.linkedtasks[j].type == 'Bug')
        {
          subissues += del+issue.linkedtasks[j].key;
          del=',';
        }
      }
    }
    subissues =  SearchIssues('issue in ('+subissues+')');
   
    psubissues = {};
    for(var i=0;i<subissues.length;i++)
    {
        subissue = subissues[i];
        //Logger.log("Processing sub issue "+subissue.key);
        this.UpdateFromTransitions(subissue);
        psubissues[subissue.key]=subissue;
    }
    for(var i=0;i<issues.length;i++)
    {
      issue = issues[i];
      issue.deskcheckscompleted=1
      issue.deskcheckfailed = 0;
      issue.deschchecktaskcount = 0;
      for(var j=0;j<issue.subtasks.length;j++)
      {
         //Process Deskcheck tasks
         if(issue.subtasks[j].type == 'Desk-Check')
         {
           issue.deschchecktaskcount++;
           deskchecktask = psubissues[issue.subtasks[j].key];
           if(deskchecktask.end == null)
             issue.deskcheckscompleted=0;
           
           issue.deskcheckfailed += deskchecktask.inreview;
         }
      }
      for(var j=0;j<issue.linkedtasks.length;j++)
      {
        // Process linked defects
        if(issue.linkedtasks[j].type == 'Bug')
        {
          defect = psubissues[issue.linkedtasks[j].key];
          if(defect.labels.length == 0)
            issue.linkedlabels.unclassified++;
          else
          {
            for(k=0;k<defect.labels.length;k++)
            {
              label = defect.labels[k].toLowerCase()
              if(issue.linkedlabels[label] != undefined)
              {
                issue.linkedlabels[label]++;
                break;
              }
            }
            if(k == defect.labels.length)// no label found
              issue.linkedlabels.unclassified++;
          }
        }
      }
    }
    return issues;
  }
}

