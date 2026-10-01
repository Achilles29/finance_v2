function onOpen() {
  var spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = spreadsheet.getActiveSheet();
  var today = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd');
  var lastColumn = sheet.getLastColumn();
  if (lastColumn < 1) return;

  var headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  var column = headers.indexOf(today) + 1;
  if (column > 0) sheet.setActiveRange(sheet.getRange(1, column));
}
