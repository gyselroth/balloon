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
There are two kind of storage. One is the default storage adapter which must be configured on the server itself (Default GridFS) and is the default storage adapter for all users.
The other kind MUST be implemented that a user can add a collection on a specific external storage.
Therefore a user can manually set the storage provider for a given collection. This could be a dropbox account, google drive, webdav, or as this RFC describes an SMB share.

## SMB vs GridFS
The difference between GridFS and SMB is, that we want to use an actual folder structure on the SMB share. Meaning unlike GridFS the folders must also be created on the 
SMB storage in the same way the are added to the metadata. GriFS is just a container storage, we do not manually edit GridFS contents. However an SMB share is slightly different, an SMB
share is used by users and also gets modified bidirectionally (from balloon and from smb clients).

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
For each mounted SMB share, the balloon server will start a listener for changes. The SMB protocol does support a `notify` command, see  
https://www.samba.org/samba/docs/current/man-html/smbclient.1.html. Thefore the server will mirror all changes made via SMB in near realtime to the balloon server.
The listener MAY be started as TaskScheduler job with a blocking option (Meaning the scheduler SHALL fork and continue to work or start a thread for it) or
it MAY be started as a separate daemon.
Since this integrates near realtime, changes made via SMB do reflect in balloon accordingly. Meaning those changes also get replicated to connected desktop clients and what so ever.
This is a full integration of SMB.
There are smbclient wrappers for PHP available which also support the notify command.

### Sync state
If the listener is not started or a new SMB share gets added, the balloon server MUST issue a initial sync job. This job syncs the SMB share with the balloon meta collection.
The job MAY be scheduled via TaskScheduler. The balloon server MUST also spool a full resync job in a given interval (24h by default).

## Events
Changes made via SMB will have no issuer user. Neither Windows (NTFS) nor most posix filesystems store the user alongside file metadata. It will not be possible to get the changing user to bind it to a balloon event. Meaning the event owner MAY be the balloon server or the use
r who connected the SMB share. Changes made via balloon do have an event owner like they have with the default storage adapter.

## .balloon system folder
There MAY be a .balloon folder in the share root which MUST have the DOS HIDDEN (https://www.samba.org/samba/docs/using_samba/ch08.html.) flag set. Deleted nodes and temporary nodes/blobs will reside in that directory.
This directory MUST not be indexed by balloon.

## Delete & Restore nodes
Files and collections removed via balloon but are part of the smb storage mount MUST be moved to a system folder called .balloon in the configured smb share root.
Deleted nodes must be moved to .balloon/trash/{id}. A restore MUST collect a node from that path and move it back to the path given or the previous path.

## Readonly nodes
Since Windows (NTFS) and most unix systems  also supports a `readonly` flag this can be used very well. 
If a file is marked as readonly the balloon server MAY set the `readonly` flag via SMB as well.

## Deduplication
There is no native support in balloon for SMB blob deduplication. Unlink GridFS where we can link files with the same hash to the same blob this is not 
possible on an SMB share since the files MUST be stored on all locations. 
However deduplication can still be achieved with other technolgies such as deduplication on the storage system.

## Files history
Files content history does not make sense since with files stored on an SMB share since there can only be one version stored at a certain location.
And a file which get changed via SMB gets overwritten anyway. Therefore balloon SHOULD always show only the latest version.

## Rename & move
A rename can be properly detected if the notification listener is running, therefore an existing node can be renamed. However if the listener is not running this is not possible.
The old node gets removed and the new location gets added as new node. The same applies to move events. There is no such event in SMB implemented therefore nodes moved via SMB will always replicated in balloon as delete & create.

## Content checksums
Most filesystems do not store a checksum alongside file metadata (expect ZFS or BTRFS) as so does Windows (NTFS). Neither is it possible to access those checkusms via SMB. A files checkum indexed by balloon MUST be calcualted.
It MUST also be calcualted if a file gets stored on an SMB share via balloon.

## Quota 
Nodes which are part of an external storage mount MUST not count to the users quota. This MUST always be the case since quota is handled seperately on external storage providers.

## Action issued by balloon
All action issued via balloon MUST reflect on an external storage mount immediatly. If the storage adapter throws an exception of any kind the action MUST not be executed.

## Connect SMB share

The /api/v2/collections POST endpoint MUST support an attribute `mount` to define a custom external storage:

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
