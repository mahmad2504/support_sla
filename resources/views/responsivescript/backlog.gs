// Configurations are picked from mentioned sheet with data 
// organsed like below , starting row=1 col=1
//--------------------------------------------------------
//| URL	  | https://responsivestudio.atlassian.net
//| Email	  | abc@email.com
//| Token	  | atlassian login token
//| Board	  | Board ID
//| Sprint  | Sprint Name or Sprint ID
//| Sheet	  | Sheet Name to dump data
//----------------------------------------------------------

// This implements reading configuration from mentioned sheet
// Configuration includes, user credentials , board and sprint id
function Configuration() 
{
  this.Load = function(settings_sheet_name='Settings')
  {
    var userProperties = PropertiesService.getUserProperties();
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(settings_sheet_name);  
    values = sheet.getRange(1,2,6,1).getValues();
    var  changelog = 'false';
    var fields_str = '';
    var del='';
    for(var field in fields_) {
      if(field == 'changelog')
      {
        changelog = 'true';
        continue;
      }
      if(field == 'statusCategory')
        if(fields_.status !== undefined)
          continue;
      fields_str += del+fields_[field];
      del=',';
    }
    userProperties.setProperty('FIELDS',fields_str);
    userProperties.setProperty('URL',String(values[0]));
    userProperties.setProperty('TOKEN',String(Utilities.base64Encode(values[1]+":"+values[2])));
    userProperties.setProperty('BOARDID',String(values[3]));
    userProperties.setProperty('SPRINT',String(values[4]));
    userProperties.setProperty('DATA_SHEET_NAME',String(values[5]));
    userProperties.setProperty('CHANGELOG',changelog);
  }
  this.Dump = function ()
  {
    msg = "Fields="+GetConfig("FIELDS")+"\n";
    msg += "URL="+GetConfig("URL")+"\n";
    msg += "TOKEN="+GetConfig("TOKEN")+"\n";
    msg += "BOARDID="+GetConfig("BOARDID")+"\n";
    msg += "SPRINT="+GetConfig("SPRINT")+"\n";
    msg += "DATA_SHEET_NAME="+GetConfig("DATA_SHEET_NAME")+"\n";
    msg += "CHANGELOG="+GetConfig("CHANGELOG")+"\n";
    Logger.log(msg);
  }
  this.Get = function (param)
  {
    var userProperties = PropertiesService.getUserProperties();
    return userProperties.getProperty(param);
  }
}
function GetConfig(param)
{
  var userProperties = PropertiesService.getUserProperties();
  return userProperties.getProperty(param);
}