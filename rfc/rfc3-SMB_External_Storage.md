# RFC-3 SMB External Storage

**ID**: RFC-3 \
**Author**: Raffael Sahli <sahli@gyselroth.net> \
**Copyright**: gyselroth GmbH 2018

The key words “MUST”, “MUST NOT”, “REQUIRED”, “SHALL”, “SHALL NOT”, “SHOULD”, “SHOULD NOT”, “RECOMMENDED”, “MAY”, and “OPTIONAL” in this document are to be interpreted as described in [RFC 2119](https://tools.ietf.org/html/rfc2119).

## Description
This document describes an implementation of SMB in balloon.
The balloon server supports multiple storage backends besides the default GridFS since version 2.0.0-alpha1. Metadata is still in the MongoDB but the file blobs
can be stored on any storage adapter.

## Pitfalls summary

* Tests with complex and deep shares showed that not all changes were reflected from the notify pusher (SMB server). The only way around this would be to start periodically scans besides the listener
or spin up multiple listener which listen on different folder depths (But this will end in conflicts). But it may be good not to connect huge shares (Or connect sub folders).

* It is not exactly clear how to handle the metadata for different balloon users and different smb credentials. There is no fine solution for different mounts with different credentials using the same meta scope. Meta data is lost anyways if nodes get renamed or moved.

* Rename/Move can not be detected properly (Unless the server provides the cifs unix extension which is not the case for a windows server [3]), meaning such operations will result in delete and add as new.

* No technical good way to get the files hash and mimetype after #92 has been implemented. Hash may be calculated through a pipe to get it at least as fast as possible.

* Events coming from SMB server do not have an event owner, this will reflect on the balloon event log that all actions are issued by the same user (mount owner or a system user)

* No files history (But a file MAY be reverted with other technologies such as windows previous versions)


## User based external storage providers
There are two kind of storage adapters. One is the default storage adapter which must be configured on the server itself (Default GridFS) and is related to every user.
This is the main storage provider and also the default one attached to all nodes.
The other kind MUST be implemented in a way that a user can add a collection on a specific external storage.
Therefore a user can manually set the storage provider for a given collection. This could be a dropbox account, google drive, webdav, or as this RFC describes a share via SMB/CIFS.

## SMB vs GridFS
The difference between GridFS and SMB is, that we want to use an actual folder structure on the SMB share. Meaning unlike GridFS the folders must also be created on the
SMB storage in the same way they are added to the metadata. GridFS is just a container storage, we do not manually edit GridFS content. It is just a blob storage. However an SMB share is slightly different, an SMB
share is used by users and also gets modified. Meaning not only is data modified via balloon but also directly on the storage provider.

## Full integration
The storaged used SHALL not reflect on file based features. All those features MUST work regardless what storage is in use or what external storage is mounted.

| App                       | Description                                                                       |
|---------------------------|-----------------------------------------------------------------------------------|
| Balloon.App.Api           | Nodes on a mounted storare looks like the same as normal ones                     |
| Balloon.App.ClamAv        | Will scan all files                                                               |
| Balloon.App.Cli           | Not related                                                                       |
| Balloon.App.Convert       | Converting/Shadows are supported                                                  |
| Balloon.App.DesktopClient | Will sync external mounts the same as normal nodes                                |
| Balloon.App.Elasticsearch | All nodes get indexed                                                             |
| Balloon.App.Notification  | Notification is supported, even subscriptions if changes are made on the storage  |
| Balloon.App.Office        | Open office files with libreoffice works                                          |
| Balloon.App.Preview       | Previews are generated                                                            |
| Balloon.App.Sharelink     | Sharelinks are possible                                                           |
| Balloon.App.Webdav        | Webdav will work as well                                                          |

## SMB share authentication
The SMB host and (optional) credentials SHALL be submited during creation of a new collection or through a PATCH.
Users MAY connect an SMB share with their known credentials or an anonymous SMB share. It MAY also be possible that one user mounts an SMB share using his credentials and shares
the balloon collection to other users. But users MUST be aware that all actions issued via balloon are executed with the credentials given on the SMB server.

### Credentials security
The smbclient integrations requires credentials in plain text. Theoretically other mechanisms such as KRB5 are supported but there is no good way to use this method with smbclient notify.
Credentials (password) MAY be stored encrypted in the external_storage mount informations. The password MUST be encrypted during a new external storage mount gets added.
The encryption key MAY be the same for all mountpoints and MUST be injected from the balloon configuration (Therefore it can be injected via env variables from a secure vault).

## Synchronization
If the listener is not started or a new SMB share gets added, the balloon server MUST issue a initial sync job. This job MUST synchronize the SMB share with balloon.
ctime and mtime of each node MUST be used from the SMB provider. Server with cifs unix extension MAY also use the uid to map the user to a balloon account.
After a new mount gets added to balloon both a recursive scan MUST be executed and a SMB notify listener MUST be started. This MAY happen asynchronously.

### Notification listener
The SMB protocol supports `notify` and the smbclient implementation does as well [1].
Balloon SHALL start a listener for each connected mount. All changes will reflect on the balloon server in (soft) realtime.
Since this is a blocking call, the notify command will block the main thread of the job scheduler engine [2], therefore it SHALL either fork it or spin up a thread.

### Performance
Indexing a newly added mount can take some time depending how deep and complex the share is. But since both the scanner and the listener tasks MUST be asynchronous operations the mount
can already be used and content will get added in the background. Performance MAY also increase after issue #92 [4] has been implemented. But this leads to two other problems if #92 gets implemented.
There would be no way to determine the files hash and neither the mimetype. GridFS supports calculation of a file but the is no way to get this via SMB. Either do not store the files hash or the whole file stream must be stored to a temporary file to calculate the hash (This is actually now the case but results in slower performance).

## Shared meta scope vs not
An SMB share can be connected with different credentials meaning each credential has a different point of view on the listed content. It is not yet clear how this should be reflected in balloon itself.
Either each mount has its separate tree in balloon which will result in [number of mounts * number of nodes] and all content based operations would be executed multiple times.
Another option would be to use the same tree for identical SMB shares. Each scanner and listener is executed with different credentials and they will manage node based acl for each nodes. Like this different point of views can be handled.
This would save cpu resources and will result in better user experience since the meta data is also the same but it MAY be more error-prone.

## Events
Changes made via SMB will have no user. It is not possible to get the owner of an SMB event to bind it to a balloon event. Meaning the event owner MAY be the balloon server or the user who mounted the SMB share. Changes made via balloon do have an event owner like they have with the default storage adapter. It will also not work with a server with the unix cifs extension enabled (samba) since linux does not store the last modifier user and neither will the uid be published within the notify push.

## Action issued by balloon
All action issued via balloon MUST reflect on an external storage mount immediatly. If the storage adapter throws an exception of any kind the action MUST not be executed.

## Recursive removal of nodes via balloon
Like share references a recursive removal request MUST NOT delete children of an external storage mount folder on the storage level. Meaning a delete request MUST delete all meta data (Or MAY set the deleted flag depenending if the force flag is given or not) but MUST NOT delete children on the storage itself. A recursive delete request on the storage level SHALL only be executed if the delete request is within the external storage mount itself.

# Move from and to external storage mounts
Moving nodes from a different storage adapter MUST be reflected as delete and add like move from a non shared collection to a shared one. This is required since the different storage adapters supports different features, also the content must be copied to the oder storage.

## .balloon system folder
There MAY be a .balloon folder in the share root which MUST have the DOS HIDDEN [5] flag set. Deleted nodes and temporary nodes/blobs will reside in that directory.
This directory MUST not be indexed by balloon.

## Delete & Restore nodes
Files and collections removed via balloon but are part of the smb storage mount MUST be moved to a system folder called .balloon in the configured smb share root.
Deleted nodes must be moved to .balloon/trash/{id}. A restore MUST collect a node from that path and move it back to the path given or the previous path.

## Readonly nodes
SMB also supports a `readonly` flag which MAY be used if a node is marked as readonly from balloon.

## Deduplication
There is no native support for SMB blob deduplication. Unlike GridFS where files with the same hash get grouped together is this not
possible on an SMB share.
However deduplication can still be achieved using other technolgies such as deduplication on the storage layer.

## File content revisioning
Unlike GridFS a new version of a file SHALL not be stored on the SMB storage. Since it is actively used and there SHALL not be any co existing files.
However file revisioning MAY be achieved with other technologies such as "Previous versions" on Windows. Balloon MAY still track a files content history but the storage reference SHALL always point to the same blob on the SMB storage.
Therefore it is not possible to restore files via balloon, users MAY use technologies provided by the SMB/CIFS server.

## Rename & move
A rename can be properly detected if the notification listener is running, therefore an existing node MUST get renamed. However if the listener is not running this is not possible.
The old node gets removed and the new location gets added as new node. The same applies to move events. There is no such event in SMB implemented therefore nodes moved via SMB will always replicated in balloon as delete & create.

### Using inodes
Using inodes is theoretically possible to match nodes from SMB to balloon, but the server needs the unix extension enabled. Windows does not support this [3] and neither is it possible to access the windows 64bit fileid (Which is the same as inodes on a posix system).

## Quota
Nodes which are part of an external storage mount MUST not count to the users quota. This MUST always be the case since quota is handled seperately on external storage providers.

## Mount SMB share via API
The /api/v2/collections POST endpoint MUST support an attribute `external_storage` to define a custom external storage:

```
POST /api/v2/collections -d '{
    "name": "My_Smb_Share",
    "attributes": {
        "mount": {
            "adapter": "smb",
            "host": "192.168.1.1",
            "workgroup": "foo",
            "username": "user",
            "password": "pass",
            "share": "test",
            "path": "foo/bar"
        }
    }
}'
```
## References
* [1] https://www.samba.org/samba/docs/current/man-html/smbclient.1.html.
* [2] https://github.com/gyselroth/mongodb-php-task-scheduler
* [3] https://msdn.microsoft.com/en-us/library/cc246806.aspx
* [4] https://github.com/gyselroth/balloon/issues/92
* [5] https://www.samba.org/samba/docs/using_samba/ch08.html
