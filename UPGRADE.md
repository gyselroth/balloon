## 2.x -> 2.6.x

### Upgrade

Upgrading to v2.6 requires an execution of: 
```
ballooncli upgrade -vvvv
```


## 2.x -> 2.5.x

### Elasticsearch
balloon v2.5 requires elasticsearch 6.x and the newer elasticsearch ingest-attachment instead of mapper-attachment.
This requires a full reindex of elasticsearch:

>**Note** The reindex process makes use of the async functionality of balloon, the more worker you have the faster it will index.
This may take while to fully index all documents.

```
ballooncli elasticsearch reindex -vvv
```

>**Note** v2.5 creates new elasticsearch indices (`blobs` and `nodes`). You may drop the previously indices (default name: balloon).

### Libreoffice
balloon v2.5 requires loolwsd for office previews. Previously libreoffice (soffice.bin) has been shipped with balloon or has been declared as optional requirement.
Such previews are now generated via loolwsd. loolwsd may either be deployed via docker image or as debian packages. See the docs.
You may need to configure the new env variable `BALLOON_LIBREOFFICE_URL`.

### ConfigMap

If you had a custom config mapped in the docker image this was previously mapped in `/usr/share/balloon/config`.
Using v2.5 you need to map the config to `/etc/balloon`.

### Upgrade

Upgrading to v2.5 requires an execution of: 
```
ballooncli upgrade -vvvv
```
(Mostly because of [#285](https://github.com/gyselroth/balloon/issues/285))


## 2.x -> 2.4.x

balloon v2.4.x comes with the stable version v3.0.0 of [\TaskScheduler](https://github.com/gyselroth/mongodb-php-task-scheduler) which requires the sysvmsg dependency
built into php. Note: This only affects you if you built php from scratch. The balloon docker image comes already with this dependency and if you are using the balloon deb package, most 
linux flavors already ship php with this dependency.

A one-time flush of the process queue is required:
```sh
ballooncli jobs -f -vvvv
```

### Configuration changes

ballon v2.4.x comes with micro PSR-11 container v.2.1.0 in which any selects statements are required to be replaced with calls statements. 
(selects statements just get ignored, upgrade to calls for required settings.)

For example, the MongoDB database name may be changed from:

>**Note** This only requried if you have another database name than `balloon`.

```
selects:
- method: selectDatabase
  arguments:
    databaseName: 'balloon2'
```

to:

```
 calls:
 - method: selectDatabase
   select: true
   arguments:
    databaseName: 'balloon2'
```

## 2.x -> 2.3.x
### Upgrade
Run upgrade is required to upgrade all nodes to 2.3.x:

```sh
ballooncli upgrade -vvvv
```


## 2.x -> 2.1.x

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


### Dependencies
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
