# RFC-1 End-to-end Content Encryption

**ID**: RFC-1 \
**Author**: Raffael Sahli <sahli@gyselroth.net> \
**Copyright**: gyselroth GmbH 2018

## Description
This document describes an implementation of a secure client based End-To-End encryption for balloon.
This document only describes the theory how a possible implementation CAN work and does not specify what exact technology SHALL be used.

## Public/Private Keys
The actual encryption is implemented in balloon-client-desktop. The server acts as a keyserver and only provides public keys and encrypted symetric keys for users. Meaning the servers only job is to provide and store keys.
Each balloon-client-desktop installation generates a public/private keypair. The public key automatically gets uploaded to the balloon server via the balloon HTTP API. Therefore a user can easily own multiple public/private keypairs.
The private key MUST never leave the orginal system where it has been created. The generation for a public/private keypair only happens once for each device instance. The keypair is stored in the local .balloon folder instance. Theoretically there could be multiple keypairs for each device but only one keypair CAN exists for each instance.

## Symetric Keys
### User cloud
The implementation is based on a combination of both asymetric and symetric encryption. Like described above there is a pub/private keypar for each device. But instead encrypting the content using public keys, a symetric key is genereated.
The content itself gets encrypted using this very symetric key. The file gets uploaded encrypted by a symetric key.
The symetric user key MUST be encrypted using each available public key of the user owned devices.
Each encrypted user symetric key for each user device gets stored invidually. It MUST NOT use a system for multi party public key encryption like pgp since share symetric keys must be stored by each user (Described bellow).
A symetric key MUST only be genereated once for each user. The first balloon instance using encryption will create a symetric key, encrypts it with its public key and will upload the symetric key to the balloon server.
The enctypted user symetric key CAN be cached by each client instance. It should only be retrieved once from the server. It should only be decrypted using the private key if actually data must be decrypted or encrypted.

### Shares
There will be two kinds of symetric keys. Each user account CAN only have one symetric key. However, there is a seccond kind of symetric key used for shares. For each share created, a symetric key CAN be created. Like user owned symetric keys, a share symetric key gets encrypted by public keys. But instead using only the creaters device public keys all available public keys of each share member and its devices MUST be used to encrypt the share symetric key.
The share symetric key MUST be created during adding a new share. During share creation the share owner will receive all public keys of the share members devices
The share symetric key MUST be encrypted for each device invidually and the result of it MUST be uploaded individually. Meaning the encrypted symetric keys gets uploaded for each available public key.
Only share member with the privilege "m" (Manage) have the permission to invidually call api endpoints to actually adding encrypted symetric keys for a share for each share member. A share member with lower share privileges CAN only upload encrypted symetric keys for himself but not for other share member.
All encrypted symetric share keys CAN be cached by each client instance. It should only retrieved once from the server. It should only be decrypted using the private key if actually data must be decrypted or encrypted.

#### User Groups
A problem in this concept are user groups. The origin of those problems are in the dynamic of the nature how a group works.
A group can be dynamic, new members get added and removed. If a group is member of a share and a new member gets added later the members device public keys MUST somehow be retrived by the group creator and the symetric key MUST be encrypted with thowse public keys and uploaded. This can only be done when the share owner starts a client connected to its account. 

One possible option would be to not support groups at all or new group members can not use the share as long as there is no encrypted share symetric key for their devices.

For share group members (and even normal share user members) which get removed from a share member group there is now guarantee anyway that the content on their devices gets deleted.

## Connecting the first client
Connecting the first balloon-client-desktop by a user works as descibed above. This is a detailed summary:
1. Client A Create new balloon instance
2. Client A MUST create a public/private keypair
3. Client A MUST upload the new public key
4. Client A MUST genereate strong new symetric key (Since there are now existing devices owned by the user), encrypts it with its private key and uploads it to the balloon server.
5. Data CAN now be encrypted using the symetric key.

## Connecting a new client
During connecting a new client by a user a new pub/private keypair MUST be generated. If it does the public key must be uploaded as described above. But for decrypting the users symetric key the first client MUST be online.
The new client will upload its new public key and MUST notify an existing device owned by the user. This can be done in (soft) realtime using WAMP described in RFC-2. The user MUST accept this request on the existing device. The existing device can then decrypt the existing users symetric key, encrypt it with the public key of the new device and upload it. The existing device MUST notify the new device using WAMP described in RFC-2. The new device can receive its new encrypted symetric key and can decryp it with its private key.

1. Client B Create new balloon instance
2. Client B creates a public/private keypair
3. Client B uploads the new public key
4. Client B tells the user that there is an existing device Client A and it must be notified that a new device Client B wants to be added. The user must choose which device should it be if there are more than one.
5. Client A gets notified by Client B and the user MUST accept the request
6. Client A receives the new public key, decrypts the existing user symetric key, encrypts it with the public key of Cient B and uploads the encrypted user symetric key. It will then notify Client B.
7. Client B can now receive the encrypted user symetric key and decrypt it with its private keys. It now can continue to fetch the cloud contents and decrypt encrypted files or upload new files encrypted with the user symetric key.

This can be repeated for each new device owned by the user.

## Client settings
Encryption should be optional within the client. A user MUST configure how the desktop client should encrypt. There SHOULD be at least two possibilities:

* Encrypt everything which can be encrypted (Share content can not be encrypted if not every share member has at least one public key)
* Encrypt only specific folders (Recursively). If a folder gets set up as encrypted storage a flag `encrypted` gets stored on the server. Furthermore the server will only accept new uploads which have the encrypted flag. All other uploades into that very folder get rejected.

## Encryption
Only file content gets encrypted. Meta data MUST NOT be encrypted. Meaning file name, timestamps, tags, advanced meta attributes are still clear text. Folders are not affected by the encryption since only file content gets encrypted.
If the client is configured to encrypt every possible file, a new file which gets uploaded MUST be encrypted locally using the users symetric key withing the tempory folder (.balloon) and the be uploaded. The client MUST tell the server that an encrypted file gets uploaded using an optional attribute `encrypted` which MUST be set to true.

## Decryption
Since the clients downloads a file first into a temporary folder within the .balloon folder, the client can determine if the content MUST be decrypted using the attribute `encrypted`. If encrypted is true the client MUST decrypt the file within the temporary folder and then move the file into the users data folder.

## A word to balloon-client-web
Theoretically it is possible to do crypto in web browsers. But a browser is not a safe environment and the risk is high for key hijacking through generel Web attacks using XSS, Phishing and others.
It COULD theoretically be implemented in a later step despite security risks.
The web ui MUST mark nodes which have encrypted content. The web ui CAN use the node attribute (bool) encrypted to determine if a node is encrypted or not.

If a new share gets created a user CAN configure it to encrypt its content. If this is done via the web client, the web client MUST inform via WAMP (RFC-2) a running device of the share owner to actually finish the share creation, creating symetric key, encrypt it with all share member public keys.

## General user warning using encryption
This document describes a real end-to-end encryption. There IS absolutely NOT any way to restore lost private keys by admins.
Each user is responsible to do one of:

* Connect multiple clients which actually acts as a backup since all data can be encrypted by each client.
* Creating a recovery key and store safely somewhere safe.

Once there are at least two setup devices the user still can access its data and connecting new clients. However it is recommended to have at least three devices or at least one device with a recovery key.

## Restrictions

### Server apps 
All server apps which work content based are not usable for encrypted content. Following apps are affected and not usable or only partially usable for encrypted files:
 
* Balloon.App.Preview \
The server is not able to generate previews for encrypted files

* Balloon.App.ClamAv \
Encrypted files can not be scanned for any malware/viruses

* Balloon.App.Elasticsearch \
Fulltext search is not possible due file encryption, however it is still possible to search for meta relevant information like tags, name, author.

* Balloon.App.Office \
Office formats like doc, docx, xls, xlsx, ppt, pptx are not editable nor viewable.

* Balloon.App.Convert \
No documents can be converted anymore by the server automatically. This is true for any document.

* Balloon.App.Sharelink \
World wide accessable links theoretically still work for encrypted files, however since the content is encrypted and can only be decrypted by specific clients the contents provided by those links would be unsuable.

## Server

### Description
The balloon server acts as key server. A new app called Balloon.App.Keys is provided for that usage.

### API Endpoints
This very app provides following new API Endpoints:

#### Request registered devices

Receive all devices for a given user id. If no id given the servr MUST return all available devices for the current
authenticated user.
The server COULD also accept an array of user ID.

**Endpoint**: GET /api/v?/keys/devices?user=:user

Success response:
```
(number) status HTTP status code (200 for a successfuly response)
(array[]) data[] Array of device objects
(string) data.id Device id (server generates one)
(string) data.device_identifier (Defined by client)
(string) data.public_key (Public key uploaded by client)
```

Example request:
```
GET /api/v?/keys/devices
HTTP/1.1 200 OK
{
    "status": 200,
    "data": [
        {	
            "id": "807f191e810c19729de860ea",
            "device_identifier": "myhostname (Linux Mint 17)",
            "public_key": "base64 encoded public key"
        }
    ]
}
```

### Create new device
Create a new client device. Parameters client_identifier and public_key MUST be provided.

**Endpoint**: PUT /api/v?/keys?device_identitfier=:device_identifier&public_key=:public_key

Request parameters:
```
(string) device_identifier A device identifier (human readable)
(string) public_key Base64 encoded public key in pem format 
```

Success response:
```
(number) status HTTP status code (201 for a successfuly response)
(string) data The id generated by the server
```

Example request:
```
PUT /api/v?/keys/devices?device_identifier=Thinkpad%s204266%20Windows10&public_key=base64_encoded_pem_pubkey
HTTP/1.1 201 Created
{
    "status": 201,
    "data": "807f191e810c19729de860cc"
}
```

Possible error response:
* Device identifier already exists (400 Bad request)
* Publickey fingerprint already exists (400 Bad request)

### Delete device
Deletes an existing device by id. An id MUST be provided by the client to execute the request.
It COULD be possible that also the public key fingerprint is accepted.

**Endpoint**: DELETE /api/v?/keys/device?id=:id

Request parameters:
```
(string) device_identifier A device identifier (human readable)
(string) public_key Base64 encoded public key in pem format 
```

Example request:
```
DELETE /api/v?/key/devices?id=807f191e810c19729de860cc
HTTP/1.1 204 No Content
```

Possible error response:
* Device identifier does not exists (404 Not Found)


### Request user symetric key
This endpoint can be used to receive the encrypted user symetric keys. The device id MUST be given.
This COULD also work by a public fingerkey given.

**Endpoint**: GET /api/v?/keys/user?id=:id

Request parameters:
```
(string) device A device id
```
Success response:
```
(number) status HTTP status code (200 for a successfuly response)
(string) data The encrypted user symetric key
```

Example request:
```
GET /api/v?/keys/user?device=807f191e810c19729de860aa
HTTP/1.1 200 OK
{
    "status": 200,
    "data": "encrypted user symetric key"
}
```

Possible error response:
* Device identifier does not exists (404 Not Found)


### Request share symetric key
This endpoint can be used to receive the encrypted share symetric keys. The device id and the share id MUST be given.
This COULD also work by a public fingerkey given.
This will receive a specific device.

**Endpoint**: GET /api/v?/keys/share?id=:id

Request parameters:
```
(string) device A device id
(string) share A share id

```
Success response:
```
(number) status HTTP status code (200 for a successfuly response)
(string) data The encrypted share symetric key
```

Example request:
```
GET /api/v?/keys/share?id=807f191e810c19729de860aa
HTTP/1.1 200 OK
{
    "status": 200,
    "data": "encrypted share symetric key"
}
```

Possible error response:
* Device identifier does not exists (404 Not Found)
* Share does not exists (404 Not Found)
