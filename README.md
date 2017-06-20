README

balloon is a high performance virtual filesystem and document managaement system  written in PHP7.1.
It comes with a full featured API and WebDAV interface. 
Besides the server it also has a high performance html5 javascript webinterface.

Features:

* Higly configurable via XML configuration
* Multiple types of authentication, local (MongoDB), LDAP or OAUTH2. 
* API (JSON or XML) with a detailed API Documentation
* WebDAV
* Integration with high performance search engine (elasticsearch)
* Virtual Filesystem based on MongoDB which is highly scalable
* Multilangual webinterface
* Webinterface which is completely javascript (no php at all)
* Various DMS features like tagging, meta data, sharing, file history and more
* Event logging and possibiliy of undo an event
* Core is expandable with an inbuilt plugin/hook system
* Built in app system
* File previews
* Multiple log adapter and detailed log files
* Plugins like "LdapAutoShare" for automatic system shares based on (ldap) group membership or TrashCleaner
* Full featured webinterface with keyboard navigation, drag&drop, upload files via drag&drop, video&audio player and more
* LDAP intgreation for authentication, user sync, auto shares and more
* Fully responsive webinterface
* Mail notification

... and more

---------------
Requirements:
---------------
* GNU/Linux Server
* Nginx
  (balloon will work with Apache2 or others too, but it has only been tested very strictly with Nginx,
   and there is a sample configuration at install/nginx.site.conf)
* PHP 7.1.x, if you use nginx, you will need PHP7.1-FPM
* MongoDB
* Elasticsearch

Needed for previews:
* libreoffice

Execute ./build.sh --dep to check all required dependencies

---------------
Build:
---------------
If you do not have a release package (.tar.gz) you can build a package by yourself.
All you need are the following tools which have to be available through your $PATH variable:

* Bash
* Subversion
* Bower (http://bower.io/)
* Composer (https://getcomposer.org/)
* yui-compressor (http://yui.github.io/yuicompressor/)

Go into the same directory as your downloaded source and execute:
./build.sh --full-build

This will build balloon for you and will create a .tar.gz package.

---------------
Webserver:
---------------
* Create a new vhost with the example configuration at install/nginx.site.conf
* Unpack your balloon build .tar.gz at your desired location on your server (change all paths in nginx.site.conf)

---------------
CRON tasker:
---------------
Install the cron script for the user who is running your webserver:
* * * * * APPLICATION_ENV=production /usr/bin/php /path/to/balloon/src/cgi-bin/cli.php

---------------
Elasticsearch:
---------------
Elasticsearch plugins:
* elasticsearch/elasticsearch-mapper-attachments

Create es index for balloon:
curl -XPUT 'http://localhost:9200/balloon' -d @dist/es_index.json

---------------------------------------------
MongoDB:
---------------------------------------------
* Install MongodDB Server

Create indicies:
use balloon
db.fs.files.ensureIndex( { "md5": 1 }, { unique: true } )
db.storage.ensureIndex( { "share.token": 1 }, { unique: true, sparse: true } )
db.storage.ensureIndex( { "acl.group.group": 1 }, { sparse: true } )
db.storage.ensureIndex( { "acl.user.user": 1 }, { sparse: true } )
db.storage.ensureIndex( { "hash": 1, "thumbnail": 1 }, { sparse: true })
db.storage.ensureIndex( { "parent": 1 }, {"owner": 1 }, { sparse: true })
db.storage.ensureIndex({"reference": 1})
db.storage.ensureIndex({"shared": 1})
db.user.ensureIndex( { "username": 1 }, { unique: true } )
db.fs.files.dropIndex({"filename": 1})
db.fs.files.dropIndex({"filename": 1,"uploadDate":1})
db.delta.createIndex({"owner": 1})
db.delta.createIndex({"timestamp": 1})
db.delta.createIndex({"node": 1})
