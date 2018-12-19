## 2.x _> 2.3.x
### Upgrade
Run upgrade is required to upgrade all nodes to 2.3.x:

```sh
ballooncli upgrade -vvvv
```

## 2.x -> 2.1.x
### Upgrade
Run upgrade is required to upgrade all nodes to 2.1.x:

```sh
ballooncli upgrade -vvvv
```

## 1.x -> 2.x
### Deployment
balloon 2.0.0 can be easily deployed using debian packages or docker images. Consider to rethink they way you deploy new balloon versions.

### Configration
There is a completely new and rewritten configuration implemented in balloon 2.0.0! The best you can do to migrate is to actually reconfigure all your previous settings
from v1.0.x. There is now way to automatically migrate your configuration.

### Upgrade
balloon 2.0.0 features an in-built upgrade mechanism. All you need to do after migrating the configuration is run the upgrade:
```sh
ballooncli upgrade -vvvv start
```

### API
balloon 2.0.0 also brings the REST API v2. v1 is still fully supported but only receives bug fixes and no new features!
It is recommended that you upgrade your client to the new API v2.


## 0.x -> 1.x

### Config
* If available, rename src/task.xml to src/cli.xml
* Remove any underscore from plugin class names in src/config.xml, src/local.xml and src/cli.xml
* log format was changed, @see dist/config.xml to get the new format
* Add api, dav, share as modules to your src/config.xml, @see dist/config.xml


### Server
* PHP7.1 is now required, upgrade your server to php7.1
* run ./build.sh --dep afterwards to check if all necessary libraries are installed
* First entry file changed to index.php, please verify your webserver configuration, @see dist/nginx.conf
* (Optional: your should run ./build.sh --test to verify the new setup)


### Database
#### (MongoDB < 3.0)
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

#### (MongoDB > 3.0)
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
