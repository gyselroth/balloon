##1.x -> 2.x
###Package

###Configration
* If you have been using any additional log adapters, make sure to replace them with \Micro\Log\Adapter\* 
* If you have been using \Balloon\Auth\Adapter\Ldap, make sure to replace it with \Micro\Auth\Adapter\Ldap and replace ldap configuration <host> with <uri>, and remove <port>
* Plugins have been migrated to apps, if you have any special plugin configuration or third-party plugins enabled make sure you will move any existing configuration to <app> and delete <plugin>

###Database


##0.x -> 1.x

###Config
* If available, rename src/task.xml to src/cli.xml
* Remove any underscore from plugin class names in src/config.xml, src/local.xml and src/cli.xml
* log format was changed, @see dist/config.xml to get the new format
* Add api, dav, share as modules to your src/config.xml, @see dist/config.xml


###Server
* PHP7.1 is now required, upgrade your server to php7.1
* run ./build.sh --dep afterwards to check if all necessary libraries are installed
* First entry file changed to index.php, please verify your webserver configuration, @see dist/nginx.conf
* (Optional: your should run ./build.sh --test to verify the new setup)


###Database
####(MongoDB < 3.0)
```javascript
 db.storage.update({'deleted': true},{$set: {'deleted': new Date()}}, {multi: true})
 db.storage.update({'size': 0},{$set: {'file': null}}, {multi: true})
 db.user.update({},{$unset: {'share_attr_sync': ""}}, {multi: true}) 
 db.fs.files.remove({length: 0})
 db.storage.ensureIndex({"reference": 1})
 db.storage.ensureIndex({"shared": 1})
 db.fs.files.dropIndex({"filename": 1})
 db.fs.files.dropIndex({"filename": 1,"uploadDate":1})
 db.delta.createIndex({"owner": 1})
 db.delta.createIndex({"timestamp": 1})
 db.delta.createIndex({"node": 1})
```

####(MongoDB > 3.0)
```javascript
 db.storage.updateMany({'deleted': true},{$set: {'deleted': new Date()}})
 db.storage.updateMany({'size': 0},{$set: {'file': null}})
 db.user.updateMany({},{$unset: {'share_attr_sync': ""}})
 db.fs.files.remove({length: 0})
 db.storage.ensureIndex({"reference": 1})
 db.storage.ensureIndex({"shared": 1})
 db.fs.files.dropIndex({"filename": 1})
 db.fs.files.dropIndex({"filename": 1,"uploadDate":1})
 db.delta.createIndex({"owner": 1})
 db.delta.createIndex({"timestamp": 1})
 db.delta.createIndex({"node": 1})
```
