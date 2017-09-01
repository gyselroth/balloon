README

---------------
Requirements:
---------------
* LibreOffice


* MongoDB
As the app_office_session collections gets increased you should create an index for the attribute node to reach best performance:
db.app_office_session.ensureIndex({"node":1})
