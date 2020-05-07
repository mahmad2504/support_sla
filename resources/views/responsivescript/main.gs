function Main()
{
  var conf = new Configuration();
  conf.Load('Settings'); //Settings is sheet name from where configurations are picked
  //conf.Dump();
  var boardid=conf.Get("BOARDID");
  var data_sheet_name = conf.Get("DATA_SHEET_NAME");
  var sprint=conf.Get("SPRINT");
  
  var BackLog = new Backlog();
  BackLog.Sync(boardid);
  BackLog.DumpSummary(data_sheet_name);
  BackLog.DumpBugSummary(data_sheet_name);
 
  var SStories =  new SprintStories();
  SStories.Sync(boardid,sprint);
  SStories.DumpStorys(data_sheet_name);
  SStories.DumpSprintProgress(data_sheet_name);
  SpreadsheetApp.flush();
}