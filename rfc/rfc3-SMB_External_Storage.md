# RFC-3 SMB External Storage

**ID**: RFC-3 \
**Author**: Raffael Sahli <sahli@gyselroth.net> \
**Copyright**: gyselroth GmbH 2018

The key words “MUST”, “MUST NOT”, “REQUIRED”, “SHALL”, “SHALL NOT”, “SHOULD”, “SHOULD NOT”, “RECOMMENDED”, “MAY”, and “OPTIONAL” in this document are to be interpreted as described in [RFC 2119](https://tools.ietf.org/html/rfc2119).

## Description
This document describes an implementation of SMB in balloon.
The balloon server supports multiple or custom storage backends besides the default GridFS since version 2.0.0-alpha1. Metadata is still in MongoDB but the stored blobs
can be stored on different adapters. 

## User based external storage providers
There are two kind of storage. One is the default storage adapter which must be configured on the server itself (Default GridFS) and is related to every user.
The other kind MUST be implemented that a user can add a collection on a specific external storage.
Therefore a user can manually set the storage provider for a given collection. This could be a dropbox account, google drive, webdav, or as this RFC describes an SMB share.

## SMB vs GridFS
The difference between GridFS and SMB is, that we want to use an actual folder structure on the SMB share. Meaning unlike GridFS the folders must also be created on the 
SMB storage in the same way the are added to the metadata. GriFS is just a container storage, we do not manually edit GridFS contents. However an SMB share is slightly different, an SMB
share is used by users and also gets modified.

## Full integration
An SMB share integrates completely into balloon. Meaning apps like Balloon.App.Preview, Balloon.App.Elasticsearch or Balloon.App.Convert will work like they do with another storage.
The only difference is that the file blobs are stored completely on the SMB share. The file blobs MUST NOT be stored in default file storage GridFS.
Also modifying metadata MUST work as it does with GridFS since the metadata MUST always be stored in the storage collection.
The delta will work as well for SMB shares meaning the balloon desktop client MUST sync those folders without any issues. 
It MAY also be possible to share an SMB connected folder since it is a normal collection anyways.
There is no active SMB connection required to traverse a balloon SMB collection since it is synced from the SMB server in near realtime.
The only time an SMB connection is required is to execute changes on the SMB share.

## SMB share authentication
The SMB URL including credentials MUST be submited during creation of a new collection or through a PATCH.
User MAY connect an SMB share with their known credentials or an anonymous SMB share. It MAY also be possible that one user connects an SMB share using his credentials and shares
the balloon collection to other users. But be aware that all actions issued via balloon are executed with the credentials given on the SMB server.

## Notification listener
What if a file gets changed via SMB? What if a folder gets removed via SMB or worse a whole directory structure?
Well, for each SMB share, the balloon server will start a listener for changes. The SMB protocol does support a `notify` command, see  
https://www.samba.org/samba/docs/current/man-html/smbclient.1.html. Thefore the server will migrate all changes made via SMB in near realtime on the balloon server.
The listener MAY be started as Taskscheduler\Async job with a blocking option (Meaning the scheduler SHALL fork and continue to work or start a thread for it) or
it MAY be started as a separate daemon.
Since this integrates near realtime, changes made via SMB do reflect in balloon accordingly. Meaning those changes also get replicated to connected desktop clients and what so ever.
This is a full integration of SMB.
There are smbclient wrappers for PHP available which also support the notify command.

### Sync state
If the listener ist not started or a new SMB share gets added, the balloon server MUST issue a initial sync job. This job syncs the SMB share with the balloon meta collection.
The job MAY be scheduled via Taskscheduler\Async.

## Events
Changes made via SMB will have no issuer user. It MAY not be possible to get the changing user to bind it to a balloon event. Meaning the event owner MAY be the balloon server or the use
r who connected the SMB share. Changes made via balloon do have an event owner like they have with the default storage adapter.

## Delete & Restore nodes
The SMB `hidden` flag MAY act as an invisual trash.
A delete command issued from balloon MUST set the `hidden` flag via SMB on folders and files if force is not given.
If a force delete command is issued via balloon the node gets deleted on the SMB share as well.
Accordingly a restore issued via balloon MUST unset a potentially set `hidden` flag.

## Readonly nodes
Since SMB also supports a `readonly` flag this can be used very well.
If a file is marked as readonly the balloon server MUST set the `readonly` flag via SMB as well. 

## Deduplication
There is no native support in balloon for SMB blob deduplication. Unlink GridFS where we can link files with the same hash to the same blob this is not 
possible on an SMB share since the files MUST be stored on all locations. 
However deduplication can still be achieved with other technolgies such as deduplication on the storage system.

## Files history
Files content history does not make sense since with files stored on an SMB share since there can only be one version stored at a certain location.
And a file which get changed via SMB gets overwritten anyway. Therefore balloon SHOULD always show only the latest version.

## Connect SMB share

The /api/v2/collections POST endpoint MUST support an attribute `storage` to define a custom external storage:

```
POST /api/v2/collections -d '{
    "name": "My_Smb_Share",
    "storage": "smb://user:password@192.168.1.1/MyShare/Mysubfolder"
}'
```
