## 2.0.0-dev
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu June 22 15:04:32 CEST 2017

Next major release v2, includes various new features and core changes. The API is still v1 and compatible with all current implementations.

* CORE: [CHANGE] php ext apc is now optional (cache configuration)
* CORE: [CHANGE] php ext imagick is now optional (if not installed image previews will fail)
* CORE: [CHANGE] php ext ldap is now optional (if not installed ldap authentication or ldap sync core app will not be available)
* CORE: [!BREAKER] ldap auth configuration host got changed to uri (and removed configuration port)
* CORE: [!BREAKER] Migrated core classes to \Micro framework (Certain adapters are required to be changed to \Micro, see upgrade guide) #19
* CORE: [!BREAKER] \Micro provides an OpenID-Connect authentication adapter, the current oauth2 auth adapter \Balloon\Auth\Adapter\Oauth2 gets removed with this release (see upgrade guide) #8
* CORE: [CHANGE] changed hook preAuthentication() first param to Auth $auth instead auth adapters
* CORE: [CHANGE] Moved various namespaces: \Balloon\Rest => \Balloon\Api, \Balloon\Plugin => \Balloon\Hook #55, \Balloon\Queue => \Balloon\Async
* CORE: [CHANGE] PHP set_error_handler now throws ErrorException instead \Balloon\Exception\Coding
* CORE: [CHANGE] Added new \Balloon\Server which is the new point of entry, also moved \Balloon\User to \Ballon\Server\User and made various code improvements to it
* CORE: [CHANGE] Moved \Balloon\Filesystem\node\INode to \Balloon\Filesystem\node\NodeInterface and \Balloon\Filesystem\Node\Node to \Balloon\Filesystem\Node\AbstractNode #6
* CORE: [CHANGE] Elasticsearch is now an app and not part of the core anymore #10
* CORE: [CHANGE] changed exception codes from hex to integer
* CORE: [CHANGE] Converted integration tests to unit tests and implemented mock classes for the whole server #36
* CORE: [FEATURE] console can now be executed with command parameters
* CORE: [FEATURE] console can now be executed as a daemon, meaning queue jobs can be asynchonosuly executed non-stop #56
* CORE: [CHANGE] Converted all core plugins from v1.0.x into hooks which are now part of new core apps #20
* CORE: [CHANGE] Moved converter classes from preview into global \Balloon\Converter space, \Balloon\Converted is now useable to converty anything to anything
* CORE: [CHANGE] config.xml is now completely optional, an example configuration for possible configurations is available at config/config.dist.xml #59
* CORE: [CHANGE] No more BSONDocument, all cursor get mapped to arrays
* CORE: [CHANGE] Sharlink is now an entirely removed from the core and operates as an own app Balloon.App.Sharelink
* CORE: [CHANGE] Preview is now an entirely removed from the core and operates as an own app Balloon.App.Preview
* CORE: [CHANGE] Changed generating access token to random_bytes() for creating sharelink tokens
* CORE: [FEATURE] added a couple of new methods to NodeAbstract to set/receive/unset app based attributes for invidual nodes
* CORE: [CHANGE] added AbstractNode::getAttributes(array $attributes=[]) besides AbstractNode::getAttribute()
* CORE: [FIX] fixed application/octet-stream mime type for office files (issue since 1.x)
* CORE: [CHANGE] Extracted Mime detection to \Balloon\Mime
* CORE: [FEATURE] New converter app Balloon.App.Convert to convert files into other formats and supporting file shadows
* CORE: [CHANGE] changed use \Psr\Log\LoggerInterface as Logger to use \Psr\Log\LoggerInterface
* CORE: [CHANGE] apps are now shipped without ui parts (ui componets got moved to balloon-client-web as separate apps)
* CORE: [CHANGE] apps are now automatically loaded once they are placed in the app directory
* CORE: [CHANGE] Implemented new \Balloon\Filesystem\Storage mechanism which allows to store file blobs via an interface everywhere if an adapter exists
* CORE: [CHANGE] Various code cleanup and refactoring within \Balloon\Filesystem
* CORE: [FEATURE] New module based cli implementation
* CORE: [FEATURE] Database init and delta migration support #78
* CORE: [FEATURE] Added various db delta upgrade scripts from v1 => v2
* CORE: [CHANGE] Implemented \Micro\Container (dependency injection container) which results in various simpler dependencies of some classes
* API: [CHANGE] removed GET /api/v1/about
* API: [CHANGE] removed GET /api/v1/version
* API: [CHANGE] added 'name' to output of GET /api and GET /api/v1 #46
* API: [FIX] fixed GET /node/last-cursor cursor now returns a cursor which point to the beginning of the delta feed even if there are no delta entries (for the account requested)
* API: [FIX] GET /node/delta now includes entries which are triggered in the exact same microsecond
* API: [CHANGE] Removed server_timestamp and server_timezone from GET /api/v1 since all timestamps are in UTC anyway #61
* API: [FEATURE] GET /api and GET /api/v1 are now public readable #46
* API: [CHANGE] Removed attribute history from GET /file/attributes
* API: [FEATURE] param $attributes can now be called to filter specific attributes for file or collection like 'file.size' which can be used for all endopoints which understand a param $attributes
* API: [FEATURE] New api endpoints provided by Balloon.App.Convert
* UI: [CHANGE] Moved web ui from the main server repo into https://github.com/gyselroth/balloon-client-desktop


--
1.0.16 - Raffael Sahli <sahli@gyselroth.com>
Tue Sept 28 14:35:32 CEST 2017
--
API: [FIX] POST /node/move can not move a node into a shared mailbox collection which holds a node with the same name #75 
API: [FIX] PUT /file returns Exception\Conflict with code 19 instead Exception\Forbidden code 40 if a file gets uploaded into a shared mailbox collection and the collection already holds a node with the same name #75
API: [FIX] PUT /file and POST /collection now create a node within a writeonly collection without a Exception\Forbidden response, his feature (writeonly) is deprecated now and will get removed in v2, replacement is the newly (v1.x) introduced permission mailbox)
API: [FIX] PUT /file does not throw an error anymore if an application/zip file with an unknown mimetype gets uploaded


## 1.0.15
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon July 31 12:55:02 CEST 2017

* API: [FEATURE] /node/attributes does now accept multiple id #47
* API: [FIX] fixed XSS via X-Client header


## 1.0.14
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon July 30 11:13:00 CEST 2017

* CORE: [FIX] fixed ldap auto share sync plugin #45
* API: [FIX] missing delta entry if node gets moved and a new node is created at the old place #44


## 1.0.13
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Fri June 30 16:04:32 CEST 2017

* CORE: [FIX] added missing node name in Exception\NotFound during requesting child by name
* CORE: [FIX] fixed #40 webdav adding new file return error 500
* API: [FIX] fixed #41 Error (Undefined class constant 'DESTINATION_NOT_FOUND') during POST /node/move request


## 1.0.12 GA
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu June 22 10:04:32 CEST 2017

* CORE: [FIX] fixed destroy node via cli plugin if node is a sub node of a share but destroy timestamp was set by share member


## 1.0.11 RC5
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Tue June 20 09:30:00 CEST 2017

* CORE: [FIX] fixed decoding ldap filter from xml configuration &amp; => &
* API: [FIX] fixed GET node/attributes if parent is a share reference and attribute parent is requested the id is now the id of the reference instead the one of the share
* UI: [CHANGE] client hostname in event log is now more Unknown, if not available then there will be no client hostname


## 1.0.11 RC4
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Tue June 13 09:30:00 CEST 2017

* CORE: [FIX] fixed Balloon\Exception\Coding Undefined index: acl after unshare share and access request from a member
* CORE: [FIX] Balloon\Plugin\Delta now works in cli mode as well which is essential for some other plugins (there are now delta entries for destroy,cleantrash,...)
* CORE: [CHANGE] added system client log to system events within Balloon\Plugin\Delta
* CORE: [FIX] fixed invalid editFile delta entry during forceDeleteCollection event
* CORE: [FIX] fixed Balloon\Exception\Coding Undefined variable: $exists after restoring file content to a version with a 0byte file
* CORE: [CHANGE] .. and . are now invalid node names, Exception\InvalidArgument gets thrown
* CORE: [FIX] there is now a delta entry if event unshareCollection was triggered and endpoint GET node/delta is requested
* CORE: [FIX] fixed delta entries for share children after a refernece was added but the cursor was already more further
* CORE: [FIX] fixed parent collection /[COLLECTION_NAME] was not found instead conflict exception
* DOC: [CHANGE] extended /user docs with some information regarding admin privilege
* UI: [FIX] fixed multiple german locale translations (event log)
* UI: [CHANGE] changed coordinate filed from select to simple text input
* UI: [CHANGE] node names are now highlighted in the event log viewer
* UI: [FIX] deleted nodes are now markes correctly as such in all event messages
* UI: [FIX] hidden tree filter checkbox is now unchecked as default
* UI: [FIX] fixed balloon.nodeExists() to be case insensitive
* UI: [FIX] fixed byte size calculation, 1024Kb is now displayed as 1MB, (<1.0.10RC4 files > 1024kB got calculated as MB)
* UI: [CHANGE] previews have now a white background


## 1.0.9 RC3
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Tue June 7 16:00:00 CEST 2017

* CORE: [FIX] fixed major errors in delta, paths are now dynamically generated, fixed delta for share members
* CORE: [FIX] fixed delta entries for a newly created share with existing sub nodes
* CORE: [FIX] editFile delta entry now stores correctly the previous file version
* CORE: [FIX] share references are now synchronized during calling getDelta()
* CORE: [FIX] temporary file gets now removed if checksums are equal
* CORE: [CHANGE] used quota does now not include files with the deleted flag set
* API: [FIX] new shares now get added if delta gets called
* API: [CHANGE] GET /node/delta the delta gets now generated recursively from the given node via param id or p
* API: [FIX] fixed Balloon\Exception (parent collection was not found) if requested via param p and file is readonly
* API: [FIX] fixed application/xml response
* API: [FIX] xml response does now include Content-Type: application/xml; charset=UTF8 header
* API: [FIX] GET /collection/children does now return all child objects with their default attributes (removed json serializable interface)
* DOC: [CHANGE] Added some new mentions regarding MongoDB indexes (better performance)
* DOC: [FIX] Small typo fixes
* UI: [FIX] fixed event log icon and undo action for renaming share reference
* UI: [FIX] fixed window close button (color & hover)
* UI: [CHANGE] autosearch gets executed with at least 3 characters
* UI: [FIX] fixed clear search result after folder up or closing popup window
* UI: [FIX] fixed folderup/breadcrumb navigation if search mode is active
* UI: [CHANGE] different icon for event folder/file add for event log
* UI: [FIX] fixed undo event edit file
* UI: [FIX] fixed folderup node is now hidden after browser reload within a sub directory and returning to root
* UI: [FIX] fixed build script minify (multiple locale build.build files)


## 1.0.8 RC2
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Tue May 2 11:02:00 CEST 2017

* CORE: [CHANGE] node names are now case insensitive, meaning A and a can not exist under the same parent collection
* CORE: [FIX] fixed parent node empty string instead null in raw delta (db)
* CORE: [FIX] some minor fixes in Balloon\Log
* CORE: [FIX] some minor fixes in Balloon\Config
* UI: [FIX] fixed tree keybindings with focused textarea,input,select areas
* UI: [FIX] fixed undefined in undo move event prompt message
* UI: [FIX] smaller next/previous arrows in viewer
* UI: [FIX] fixed move icon
* UI: [CHANGE] reverted responsive on/off switcher (will come back with v1.1), disabled filter in mobile view since checkboxes are not responsive
* UI: [FIX] fix hover color on search button
* UI: [FIX] paste action is now only enabled if cut or copy are active
* UI: [FIX] removed merge prompt during exception with code 21
AppOffice-Webinterface: [FIX] changed de translation after closing an edit window


## 1.0.7 RC1
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu Apr 13 16:20:00 CEST 2017

* CORE: [FIX] fixed setOptions in Auth\Adapter\Preauth (removed unset)
* CORE: [FIX] fixed disabling apps in config
* CORE: [CHANGE] changed error code numbers
* CORE: [FIX] fixed delta for current state (shares do now include children)
* CORE: [CHANGE] upgraded mongodb-php-lib to 1.2.x-alpha (needed to seek gridfs streams)
* CORE: [CHANGE] build script now merges locale files from apps into the global locales
* CORE: [FIX] fixed double share references for new linked shares
* API: [FIX] chunked upload PUT /file/chunk now throws Exception\Conflict if chunks got lost during uploads
* API: [FIX] fixed multi upload as zip stream (1.0.6 returned an error 500)
* API: [FEATURE] Added params offset and length to GET /node to request a specific range of bytes
* API: [FIX] GET /node now sets response header Content-Disposition: inline; with file name if param download is not true
* API: [FIX] PUT /file/chunk now throws an Exception\InvalidArgument if param index is greater than param chunks
* UI: [CHANGE] Error window displays exception code
* UI: [CHANGE] swaped trash restore icon
* UI: [CHANGE] changed some file type icons
* UI: [CHANGE] renamed writeonly+ permission to mailbox
* UI: [FIX] drag&drop files from desktop now highlight the entire upload area and highlight the current dragover folder with a different color
webinterface: [FIX] drop file from desktop onto ".." folder uploads now into the parent folder
* UI: [FIX] disabled new file in mobile view
* UI: [FIX] fixed filename download if display does not work
* UI: [FIX] office viewer is now disabled in mobile view
* UI: [FIX] fixed search reset in mobile view
* UI: [CHANGE] replaced native browser radiobuttons with on/off switches since the native ones are not responsive
* UI: [CHANGE] filer select window is now similar designed to create new file menu in app office
* UI: [FIX] increased action buttin sizes in mobile view
* UI: [FIX] fixed fullscreen popup in mobile view webkit rendering
* UI: [FIX] fixed z-index position for new file/filter select box if root is empty
* UI: [CHANGE] rename can now be aborted via ESC
* UI: [FIX] fixed default focus in hint window and closing with ESC if focus is active
* UI: [CHANGE] Added startup prompt in office app and added de and en translations
* UI: [CHANGE] editor now asks if the file should be saved if changed made or gets closed immediately if no changes made
* UI: [FIX] tree actions with keyboard are no longer possible if a popup is active
* UI: [FIX] changed text in close prompt for office window
* UI: [FIX] fixed locale detection, added none country specific de, en locales since a lot of browsers do have the languange as primary locale and not a country based locale
* UI: [FIX] fixed clear inpurt in share collection after a role was added
* UI: [CHANGE] shortened breadcrumb from max. 4 nodes to 3 nodes
* UI: [FIX] fixed node links after reload browser (id parent node input via query string)
* UI: [FIX] fixed strange icon change animation in properties for share node attribute icon
* UI: [FIX] various minor layout fixes including modified line-heights and paddings
* UI: [FIX] upload manager now catches invalid access exception as asks if the file should be re-uploaded with a different name
AppOffice-Core: [FEATURE] Allowence of multple edit sessions
AppOffice-API: [FEATURE] added DELETE /app/office/session
AppOffice-API: [FEATURE] added POST /app/office/session to create a new session (besides join)
AppOffice-Webinterface: [FIX] session now gets removed after closing the window therfore no hidden auto saves anymore
AppOffice-Webinterface: [FEATURE] Prompt for session management, can handle one or multiple session and their joinable now
AppOffice-Webinterface: [FIX] code cleanup


## 1.0.6 Beta
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu Mar 9 16:20:00 CET 2017

* CORE: [FEATURE] Plugin\Delta now stores information about the client used (webinterface/api, version, app)
* CORE: [CHANGE] Log format variables are now encapsled with {} instead %%
* CORE: [CHANGE] Plugin\MailNotification variables are now encapsled with {} instead %%
* CORE: [CHANGE] format variables can now be any possible node attribute in Plugin\MailNotification
* CORE: [CHANGE] renamed configuration myaccount_filter for auth adapters to account_filter
* CORE: [FIX] fixed write only share privilege
* CORE: [FIX] references get now deleted after the source share gets deleted
* CORE: [CHANGE] changed use Balloon\Logger to use \Psr\Log\LoggerInterface as Logger;
* CORE: [FIX] preview converter do now return false in match() if the file is empty
* CORE: [CHANGE] preview manager does now throw an exception if no converter matched
* CORE: [FIX] fixed preview creation for text files with no file extension
* CORE: [CHANGE] added error codes for Balloon\Exception\{Forbidden,NotFound,Conflict}
* CORE: [FIX] reversed recursive removal fix from 1.0.5
* CORE: [FIX] fixed binary user attribute (avatar especially)
* CORE: [FIX] fixed destroyer plugin (wrong timestamp comparsion)
* CORE: [CHANGE] filesystem multi node getter do now yield nodes instead returning them
* DOC: [FIX] removed version comapre options (will probably come in a furture version again)
* DOC: [FEATURE] documented error codes
* DOC: [CHANGE] removed REST wording from documentation since its not real REST
* UI: [CHANGE] extended search pannel gets inserted after node properties
* UI: [FEATURE] event log shows with what client changed were made
* UI: [CHANGE] advanced settings get now saved by hitting the newly added save button
* UI: [FIX] fixed reseting destroy timestamp
* UI: [FIX] fixed "did you now" icons
* UI: [FIX] only the file name parts get selected (excluding file extension) during a rename activity


## 1.0.5 Beta
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Fri Mar 3 17:00:00 CET 2017

* CORE: [FIX] fixed preauth adapter (auth was not possible in 1.0.4)
* CORE: [FIX] fixed Balloon\Exception\Forbidden: not allowed to access node in initial delta request (triggered if a reference points to a deleted share)
* CORE: [FIX] fixed Balloon\Exception\Forbidden: not allowed to acces snode in delta request (triggered if a share or collection got deleted/undeleted)
* CORE: [FIX] fixed isAllowed() read permission for collection master shares, its now forbidden to request a master share instead the reference (expect the share owner)
* CORE: [CHANGE] simplified node initialization and filesystem node loading
* CORE: [FEATURE] All exception do now have a specific error code
* CORE: [FIX] share references gets now completely removed if the master share gets removed
* CORE: [FIX] restore from history does now reload the correct contents checksum
* CORE: [FIX] fixed restore from trash into root with nodes already exists there (added $conflict to undelete())
* CORE: [FIX] fixed restoring collections with data if collection already exists at the destination
* CORE: [FIX] fixed recursive removal
* CORE: [FIX] fixed restoring share reference
* API: [CHANGE] all api calls automatically load the share reference if the master share is requested (and the other way around), this also includes the feature that url's can be shared and loaded correctly with an other user account (this was only partially possible since 1.0.0)
* UI: [FIX] disabled delete by keydown delete if query view (parent node) is active
* UI: [FIX] window prompt has a fixed width now
* UI: [CHANGE] replaced confirm/abort with yes/no
* UI: [FIX] fixed reload node after restore from history
* UI: [FIX] fixed cached view of node if contents changed (added checksum to url param)
* UI: [FIX] gui asks now if dest node should be overriden after restore from trash if a node with the same name already exists in roo
* UI: [FIX] enabled refresh and delete action in all menus
* UI: [CHANGE] replaced restore from trash icon with a new one


## 1.0.4 Beta
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon Feb 27 11:00:00 CET 2017

* CORE: [CHANGE] changed all plugins to parse iterable config via setOptions() and include default values
* CORE: [CHANGE] changed queue job MailNotification to simple Zend\Mail\Message sender named Mail, creation of notifcation messages is now handled by the plugin MailNotification itself
* CORE: [CHANGE] transformed MailNotification plugin to handle various different notifications
* CORE: [FIX] fixed Balloon\Exception\Conflict move within a share but move only share members
* CORE: [FEATURE] integreaded \Balloon\Auth\Adapter\Basic\Db to authenticate local users (its possible since this release to operate without an ldap server)
* CORE: [CHANGE] changed some configurations entries (@see dist/config.xml)
* CORE: [CHANGE] removal of share search caching (new shares will automatically get discovered with every request)
* CORE: [CHANGE] new plugin \Balloon\Plugin\AutoCreateUser which creates an account if it does not exists
* CORE: [CHANGE] admin is now handled by a simple boolean on the user record itself
* CORE: [FIX] fixed stream_copy_to_stream() expects parameter 1 to be resource, null given with move/copy&merge and 0bytes files
* CORE: [CHANGE] removed plugin hook instanceUser, preCreateUser, postCreateUser and added new hooks preInstanceUser and postInstanceUser
* API: [CHANGE] param $q from GET /acl/v1/resource/acl-roles is now required
* API: [FIX] fixed POST /api/v1/user/quota
* API: [CHANGE] removing share-link no more possible via POST /api/v1/node/share-link, use DELETE /api/v1/share-link instead
* API: [CHANGE] removed response field data.url from GET /api/v1/share-link
* API: [CHANGE] POST /api/v1/node/share-link now returns only 204 instead 201
* API: [FIX] fixed encode=base64 GET /api/v1/node base64 is now chunked as well
* UI: [FIX] fixed empty share acl resource request (exception)
* UI: [FIX] fixed load icon and margin of autload acl share resources div
* UI: [FIX] smaller navigation arrows in viewer
* UI: [FIX] fixed mime type icon during rename request
* UI: [FIX] fixed quota check before file uploading
* UI: [FIX] fixed drag&drop under custom query folders
* UI: [CHANGE] new txt files get now the prefix .txt instead no prefix
* UI: [FIX] breadcrumbs with long text and whitespaces do not break anymore
* UI: [FÎX] catch close edit window with ESC
* UI: [FIX] prompt window can now be close with ESC if a submit button is active
* UI: [FIX] translation cache gets wiped if a new translation gets deployed


## 1.0.3 Alpha
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon Feb 20 10:30:00 CET 2017

* CORE: [FIX] fixed log level (logger always logged debug instead checking log level)
* CORE: [FIX] oauth2 adapter sends 401 again if access token is expired
* CORE: [FIX] Queue\MailNotification sends mails separeted instead all recipients in "to"
* CORE: [CHANGE] a couple of new debug messages added
* CORE: [FIX] fixed infinte loop during destroy node if auto destruction is active (moved recursive actions to the end)
* CORE: [CHANGE] separated functionalily Node::share() into Node::share() and Node::unshare()
* API: [CHANGE] changed POST /api/v1/collection/share, replaced option $options with $acl (unshare can not be done anymore via this enpoint, use DELETE /api/v1/collection/share instead)
* UI: [FEATURE] added navigation during file viewer is open
* UI: [FIX] fixed remove share (unshare)


## 1.0.2 Alpha
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Fri Feb 17 10:30:00 CET 2017

* CORE: [FIX] removed debug mail addr from Plugin\MailNotification
* CORE: [FIX] replaced findNewShares() after a user has been set to the Filesystem
* CORE: [FIX] removed removing share reference if share still exists but deleted (only remove reference if share has been deleted completely)
* API: [FIX] fixed Argument 1 passed to Balloon\Rest\v1\Node::delete() must be of the type string or null, array given (all multi node enpoints were affected)
* API: [FEATURE] added param $skip to GET /api/v1/node/events to skip/limit return pool (useful for paging), set default limit to 100 instead 250
* UI: [FIX] swapped icons restore from trash & restore from history
* UI: [FIX] fixed time/date format conversion to unix timestamp (destroy at & share expiration)
* UI: [FIX] added missing translations for events.undelete_collection_share and events.undelete_collection_reference
* UI: [FIX] fixed previous.parent null check in event log
* UI: [FIX] upload under search views is now possible (only if a collection is selected/active)
* UI: [FIX] events window gets now correctly refresh after an undo event has been triggered
* UI: [FIX] events undo button won't get displayed when node is null (node has been deleted completely)
* UI: [FIX] no Exception\InvalidArgument anymore after trigger an undo event from a version rollback
* UI: [FIX] fixed events window if opened while events view is active / fixed center position
* UI: [FIX] prompt is now centered if opened more than once
* UI: [FEATURE] display events can now handle infinite scrolling and virtually load all events in the background
* UI: [FIX] fixed default pannel sizes


## 1.0.1 Alpha
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu Feb 16 10:30:00 CET 2017

* CORE: [FIX] added type hints to all rest controllers
* CORE: [FIX] fixed Call to undefined method MongoDB\GridFS\Bucket::updateOne() after update share with files content
* CORE: [FIX] router only sends response if action returns an instance of Http\Response
* CORE: [FIX] dead share references get automatically deleted once detected
* CORE: [FIX] fixed Filesystem\Node\node::getParent(), returns now the reference instead the share owners node, related erros like wrong getPath() (thrown Exception\Forbidden) are fixed cause of this
* CORE: [FIX] fixed Config\Xml parser, values with "0" get now parsed correctly
* CORE: [FIX] finding new shares is now executed during authenticated user initialization instead requesting root children
* API: [FIX] fixed office PUT /api/v1/app/office/document with empty collection (root)
* UI: [FIX] language is now correctly pre-selected
* UI: [CHANGE] switched from lang configuration to locale configuration, moved availbale locales from config.js to translate.js
* UI: [FIX] fixed locale usage for dates/time, format does now use the correct locale format everywhere (included cultures files)
* UI: [FIX] fixed transformed parent heights collection share table
* UI: [FIX] drag&drop is now possible for devices with mouse and touch (disabled drag&drop if touch, enabled if mouse usage)
* UI: [FIX] fixed typos in de locale translations
* UI: [FEATURE] Closing an edit session will result in asking via prompt if the document has been saved yet


## 1.0.0 Alpha
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Tue Feb 11 11:44:05 CET 2017

* CORE: [CHANGE] Repository is now available under GPLv3
* CORE: [CHANGE] Changed all file headers
* CORE: [FEATURE] Rewritten http router, it is now possible to configure regex routes besides static ones
* CORE: [!BREAKER] Combined api.php, webdav.php and share.php into index.php, added custom routes for each (Implemented new bootstap classes for each), @see upgrade guide
* CORE: [FEATURE] Integrated possibility to add 3rd party applications. They can add features or change functionalitye how the core works. It's also possible to extend the webinterface with 3rd party apps.
* CORE: [FIX] removed logging of bin attribute "avatar" during user initialization
* CORE: [!BREAKER] upgraded codebase to php7.1, new required php version is at least php 7.1, @see upgrade guide
* CORE: [FEATURE] Migrated to mongo user level api mongo-php-library https://github.com/mongodb/mongo-php-library since there is no support for the legacy driver with php7.1
* CORE: [CHANGE] Migrated all array() declarations to the short version [] (which is available since php5.4)
* CORE: [FEATURE] Added scalar type declarations and return types to all methods
* CORE: [FEATURE] Added text as possible output format for \Balloon\Http\Response
* CORE: [FIX] If a globally shared node could not be opened a 404 will be sent instead a 200
* CORE: [FEATURE] Added various api PHPUnit tests including detailed desting of the delta endpoing
* CORE: [CHANGE] Upgraded Sabre library
* CORE: [FEATURE] Completely new build.sh script with various options
* CORE: [CHANGE] PSR-1/PSR-2 compatible code syle (executed php-cs-fixer and added to composer)
* CORE: [CHANGE] TNode::setName() throws \Balloon\Exception\InvalidArgument instead \Balloon\Exception if in invalid name was provided
* CORE: [FIX] Fix some attributes related to share in TNode::getAttributes() and added date converters for created/changed
* CORE: [FIX] Fixed various bugs within the delta, ordering is now correct, timestamp is now used to filter and order instead _id, fixed missing entries
* CORE: [FIX] Get nodes via path will now return only nodes which have not been deleted
* CORE: [FEATURE] Implemented new PSR-3 Logger, removed old log formats and moved to {message}, {context.category}, ... added new confg settting date_format
* CORE: [CHANGE] Removed all Registry, Singletons and static classes, converted them to dependency injection pattern
* CORE: [CHANGE] Since there is no registry anymore all rest controllers do now inherit from a new \Balloon\Controller class
* CORE: [CHANGE] All classes decalare now all used classes with use right after the namespace declaration
* CORE: [CHANGE] Big changes due the whole core, new bootstrap implementation which then setups router, db, ..., implemented Api, Webdav and global shares as Apps (@see new "feature 3rd party applications")
* CORE: [FIX] File uploads bigger than 500MB can now be achieved, before the server reached a timeout if php max_execution_time was configured for the default 30s
* CORE: [FEATURE] New Plugin\MailNotification which sends mails after new shares have been created
* CORE: [CHANGE] Removed old unused plugin Plugin\JobQueue
* CORE: [!BREAKER] Renamed all Plugins to a valid PSR classname (no underscores), @see upgrade guid
* CORE: [CHANGE] Moved job queue from Memcached to MongoDB since its persistent and Memcached implementation on php7.1 is shitty, @see feature apc, apc is now required and used if any cache needed
* CORE: [FEATURE] Added \Balloon\Resource which now includes lookup methods for user/groups (moved those methods from \Balloon\User)
* CORE: [CHANGE] Implemented more interfaces and renamed existing ones to NameInterface and abstract classes to AbstractName
* CORE: [CHANGE] Removed Balloon\Task and moved the functionality to Balloon\Bootstrap\Cli
* CORE: [FEATURE] Implemented new traversable/array \Balloon\Config class and moved xml stuff to \Balloon\Config\Xml, all other classes do now require Config instead \SimpleXMLElement
* CORE: [FEATURE] xml configuration is now stored in the apc cache
* CORE: [CHANGE] the log category is now get_class($this) instead a static string
* CORE: [CHANGE] All php files gets now parsed within strict type which is available since php7.0
* CORE: [CHANGE] Added type declarations to all parameters and return types which both is available since php7.0
* CORE: [CHANGE] Some cli minor updates, renamed task.php to cli.php
* CORE: [CHANGE] If a collection gets deleted, change timestamp wont get modified anymore
* CORE: [!BREAKER] If a node gets deleted, deleted now turns into a timestamp, none deleted nodes are just set deleted=NULL, database needs to get upgraded, @see upgrade guide
* CORE: [FEATURE] New plugin \Balloon\Plugin\CleanTrash which is able to completely remove trashed nodes older than x
* CORE: [FEATURE] Added package.json with some new meta data
* CORE: [FEATURE] Added possibility to set self-destruction timestamp (destroy attribute), added plugin \Balloon\Plugin\Destroyer which does the cleanup job
* CORE: [CHANGE] Upgraded elasticsearch api to 5.x
* CORE: [FIX] Fixed search bugs related with shares
* CORE: [FIX] Fixed isAllowed(r) position which is now placed at the constructor of a collection and file
* CORE: [FIX] Fixed GET application/json as content-type; only curl does support sending json within a GET body, the router can now parse json from the first query string entry
* CORE: [FEATURE] New acl permission w+ which allows a share member to modify/see only owned nodes
* CORE: [FIX] Fixed delta/event-log for nodes within a share, the real share id instead the reference is now stores within the delta
* CORE: [FIX] All pre* hooks are now called with parameters as reference
* CORE: [FEATURE] Fixed and reimplemented preview system, added \Balloon\Plugin\Preview and a new Preview namespace \Balloon\Preview with multiple converters
* CORE: [FIX] Fixed preview creation for MS Office documents
* CORE: [FEATURE] Added new plugin hooks prePutFile() and postPutFile()
* CORE: [CHANGE] Image previews are not png instead jpeg
* CORE: [FIX] empty files are not linked to gridfs anymore, instead the file reference will be set to 0
* CORE: [CHANGE] action endpoints return now a http response instance instead sending it by themselfes
* CORE: [FIX] if name is longer than 255 characters an InvalidArgument exception will be thrown
* CORE: [CHANGE] if a name is invalid an InvalidArgument exception instead Conflict exception will be thrown
* CORE: [CHANGE] router throws an Exception\InvalidArgument if required action param not available in request parameters
* CORE: [FIX] fixed delta feed after emptying the whole trash, Balloon\\Exception\\Coding Undefined index: path was thrown before
* CORE: [FIX] fixed Node::checkName() name will no be normalized to NFC (php-intl required)
* CORE: [CHANGE] moved config files from src/*.xml to config/
* API: [FIX] Fixed infinite loop during TNode::isSubNode(), called from POST /node/move a parent collection in a child collection of itself
* API: [CHANGE] If a readonly node will be modified an instance of \Balloon\Exception\Conflict will be thrown instead Forbidden
* API: [FEATURE] Integreated libre office online (CODE) as 3rd party app named office
* API: [FEATURE] node id can now be specified either within the path (like GET /node/121ee22323333322333/attributes) or as usual as id query string
* API: [FIX] Throws \Balloon\Exception\Conflict POST /node/clone if a collection would be copied into itself
* API: [CHANGE] POST /node/move and POST /node/undelete now return 200 with the new name if param $conflict was set to CONFLICT_RENAME
* API: [FEATURE] Added $limit parameter to GET /node/delta
* API: [CHANGE] Moved endpoint GET /user/acl-roles to /resource/acl-roles
* API: [FIX] Fixed invalid cursor (not always the latest) response from GET /node/last-cursor
* API: [CHANGE] Removed GET /user/hard-quota (Can be queried via /user/quota-usage)
* API: [CHANGE] Removed /admin/server controller (May be implemented in a future version)
* API: [FEATURE] Removed /admin/user implementation and replaced with a new implementation /admin/user which inherits the default /user. Its now possible to call every /user enpoint as admin and specify the user with $uid or $uname
* API: [FEATURE] Added HEAD /user to check if user exists
* API: [FEATURE] Added DELETE /user with option $force
* API: [FEATURE] Added POST /user/undelete to restore a disabled user account
* API: [FEATURE] Added POST /user/quota to modify users quota
* API: [FIX] Empty submited options like an empty id or an empty name gets now filtered out by the request router
* API: [FEATURE] Added option $at to DELETE /node which can be filled out with a unix timestamp, instead delete the node immediatly the node will destroy itself at the time given, set $at=0 to remove this setting
* API: [FEATURE] Added GET /node/query which allows to execute non-persistant custom filtered queries
* API: [CHANGE] Renamed GET /file/thumbnail to GET /file/preview
* API: [FEATURE] Added GET /node/trash to receive only the first deleted nodes from every subtree
* DOC: [FIX] added missing request param options.password to POST /node/share-link
* DOC: [FIX] Fixed invalid get/post example for /collection/share
* DOC: [FIX] Added missing GET /node/event-log documentation
* DOC: [FIX] fixed and extended various examples
* DOC: [FEATURE] Documentation features now an integrated api request tester
* DOC: [FEATURE] Added upgrade guide
* UI: [FIX] Prompt message is now markable
webinterface: [FIX] Global share link password input gets now reseted after open another node or removing the share
* UI: [FIX] IF a node is member of a share, the attribute "shareaccess" will always gets displayed and not only on the share itself
* UI: [FIX] Incoming shares get the correct icon under properties
* UI: [FIX] Incoming share owner is now correct
* UI: [FIX] Fixed center position of the error window
* UI: [FIX] Quota is now checked before uploading a file
* UI: [FEATURE] Globally shared links gets automatically selected and copied to the clipboard
* UI: [FIX] Fixed XSS vulnerability for globally shared html files, html will not be rendered anymore
* UI: [FIX] created and changed timestamps are visible again for a collection
* UI: [FEATURE] A deleted timestamp is visible under properties for a deleted node
* UI: [FEATURE] A new tab advanced has been added under which a self destroy date/time and readonly settings can be set
* UI: [FIX] file history restore button will only show up if there are at least two entries
* UI: [FIX] file history table will reload after a restore
* UI: [CHANGE] Fixed design of share acl and combined properties attributes shareowner,access and share
* UI: [FIX] prompt is not transparent anymore after opening a file
* UI: [FIX] unbind keyboard shortcuts after logout
* UI: [FIX] shows error if an empty file or folder will be uploaded
* UI: [FEATURE] Added file icon to file viewer/editor
* UI: [FIX] breadcrumb navigation gets cut off if there are more than 4 crumbs, added text width limit for crumbs
* UI: [FIX] deleted filter can now be turned off once activated
* UI: [FIX] fixed keyboard bindings for touch enabled computers with keyboard (keyboard gets always bound, touch only if touch detected)
* UI: [FIX] fixed keyboard bindings for search views
* UI: [FIX] trash shows now only the first deleted node from every subtree
* UI: [CHANGE] extended search quick filter are now a combination of and/or (every group is filtery by "or") and combined groups are "and"
* UI: [FIX] fixed emptying search results


## 0.4.3
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Tue Dec 20 14:57:05 CET 2016

* CORE: [FIX] TNode::getAttributes() attribute parent is now a normal string and not a converted MongoId
* UI: [FIX] Open file does not now show the popup top bar with webkit browser (close view was only possible with ESC)
* UI: [FIX] fixed height/width of images/pdfs viewer
* UI: [FIX] fixed minor webkit bugs
* API: [FIX] Copy node from sub collection to root collection works now
* DOC: [FIX] Various fixed regarded to attribut listing


## 0.4.2
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Wed Nov 23 13:44:05 CET 2016

STABLE RELEASE 0.4.x


## 0.4.1 RC
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Fri Nov 4 11:51:12 CET 2016

* CORE: [CHANGE] Replaced internal autoloader with composer PS-4 autoloader
* CORE: [FIX] Delta plugin will now store the absolute node path as string (needed for a working delta feed)
* API: [FIX] Fixed /node/last-cursor, will now return a valid response
* API: [FIX] Fixed /node/delta, will now return a valid response
* API: [FEATURE] Added param attributes to /node/delta to specify additional attributes
* API: [FEATURE] Request router now accepts JSON as input, you need to specify application/json as Content-Type header and JSON as body, it will automatically get mapped to the right action parameter
* API: [FEATURE] Added option deleted to HEAD /node and default is now to exclude deleted nodes, means it will return a 404 response
* API: [FIX] All api endpoint which accept p or id now operate different with deleted nodes, it actually does matter which parameter was given. If an id was given, per default deleted nodes are fully operateable. If p (path) was given all actions will only work with undeleted nodes, since deleted nodes can be placed with the same name under the same parent.
* UI: [FIX] doubleclick on file (file viewer) does not hide the file name within the right pannel anymore
* UI: [FIX] fixed event messages for share references (translation missing)
* UI: [FIX] doubleclick on a unselected node works again
* UI: [FIX] replaced icon library to 1.2 including colorful filetype icons and new icons for filtered folders/shared filtered folders
* UI: [FIX] Shows the share owner under properties again
* UI: [FIX] audio stops now when closing the audio or video player
* UI: [FIX] fixed audio player for ogg vorbis application/ogg in addition to audio/ogg
* UI: [FIX] search will not be executed if search field/or filters are empty
* UI: [FIX] search within tablet/mobile view works again
* UI: [FIX] Various fixes for webkit browser
* UI: [FIX] Stay in current menu if a node will be restored
* DOC: [FIX] now includes general documentation again, some documentation fixes


## 0.4 Beta
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Wed Nov 1 13:51:12 CET 2016

* CORE: [FIX] Restore a previous version now restores the changed (timestamp) field from the previous version as well.
* CORE: [FIX] Various coding revisions, added INode interface, renamed item trait to TNode
* CORE: [FIX] Calling INode::getParent() from a instance of a root collection now returns null instead the root collection itself again
* CORE: [FIX] All internal methods now use INode::save() to store their fields to mongodb
* CORE: [FEATURE] php internal debug, warnings and erros now throw a \Balloon\Exception\Coding
* CORE: [FEATURE] Added \Balloon\Plugin\Delta which adds an event for each modification
* CORE: [FEATURE] Added \Balloon\Exception classes for each Balloon namespace
* CORE: [FEATURE] User attributes can now be requested with User::getAttribute($attribute)
* CORE: [FIX] TNode::save() now also logs the changed attributes via Log::OPT_PARAMS
* CORE: [FIX] File::_put() does not try to create a new file anymore if something went wrong during modifying content of an existing file
* CORE: [FEATURE] It is now possible to protect a globally shared link with a password
* CORE: [FIX] TNode::setParent() now also checks if the node itself will be copied into any sub nodes of the node itself and not just one level, deeper requests were ended in a loop, now a Conflict exception will be thrown
* CORE: [FIX] If an exception is thrown during the response handling, the thrown exception will now be returned correctly under the data array element
* CORE: [FEATURE] Replaced all \Sabre\DAV\Exception with \Balloon\Exception
* CORE: [FEATURE] globally shared links do now support an additional request parameter download=1 which will enforce a download instead streaming or display
* CORE: [FIX] Replaced Http\Response::jsonFormat() with native json_encode() JSON_PRETTY_PRINT
* CORE: [CHANGE] Reconfigured user/group search filter to *%s* instead %s*
* CORE: [FEATURE] Added binary attribute mapping type for (ldap) user synchronisation
* CORE: [FEATURE] Added new user attribute: avatar which usually holds a profile picture
* CORE: [FIX] Fixed search interface and removed filteredSearch()
* CORE: [FIX] User::_getAttributeSummary() now excludes deleted nodes
* CORE: [CHANGE] Upgraded sabredav from 3.0.* to 3.2.*
* CORE: [FEATURE] createCollection is now capable of an additional attribute filter in param $attributes  and getChildren() actually can handle stored filter on the node itself. Its possible to create a stored filtered node with a dynamic range of children, not only parent=>this but also for example a folder which contains all pdf's.
* CORE: [CHANGE] Rewritten File::getAttribue(), Collection::getAttribute and added TNode::getAttribute()
* CORE: [FEATURE] Implemented readonly flag for nodes
* CORE: [FIX] Http\Response::send() only sends response without a body if response code is 204, if not the body would just be NULL
* CORE: [CHANGE] moved source code to ./src, added new build script ./build.sh
* CORE: [FIX] Node attribute share does not return the name of the share reference instead the name of the shared folder since that name could be different
* CORE: [CHANGE] Collection::createDirectory() and Collection::createFile() do now return an instance of either File or Collection instead just the id
* CORE: [FEATURE] Various new plugin hooks like preCopyCollection or postCopyFile
* API: [CHANGE] GET /node/children now returns HTTP 200 OK with an empty data array {data: []} if the are no children (previous version 0.3 returned: HTTP 204 No Content)
* API: [CHANGE] PUT /file and PUT /file/chunk now return HTTP 200 OK with the version number as data instead just a boolean if the specified file has been modified (returns still 201 if file has been created)
* API: [CHANGE] Removed param $meta_attributeds from GET /collection/children, meta attribute can now be filtered via $attributes like 'meta.license' or 'meta.comment'
* API: [FEATURE] Added GET /node/delta which returns a delta feed since a specific server state using a cursor
* API: [FEATURE] Added GET /node/last-cursor which returns the latest cursor, usable to check if the cursor has changed on the server
* API: [FEATURE] Added GET /user/event-log Receive a detailed event log of all modifications by the user or any share members
* API: [FIX] PUT /file now returns the node id as documented instead boolean true during a 201 Created (If a new file was created)
* API: [FEATURE] Added HEAD /collection/children to check if a collection has any children
* API: [FEATURE] POST /collection, PUT /file and PUT /file/chunk accept now an optional parameter $attributes to overwrite server generated attributes like changed and or created timestamps
* API: [FEATURE] POST /collection, PUT /file, PUT /file/chunk and /node/move accept now an optional parameter $conflict to tell the server how to manage a name confict.
* API: [FEATURE] Added POST /node/clone to copy/merge a node into another one
* API: [FEATURE] GET /collection/children now accepts an additional filter parameter to to filter child nodes ([mime => 'type/text'] for example)
* API: [FEATURE] Added GET /user/attributes with an optional $attributes filter
* API: [CHANGE] endpoints which accept multiple nodes as input (POST /node/undelete, POST /node/delete, POST /node/move) are now capable of single node errors and will return any occured error from each node under errors[] as HTTP 400 instead just log, ignore and return HTTP 204
* API: [CHANGE] Removed GET /node/meta-attributes since it is possible to request single meta attributes via /node/attributes
* API: [CHANGE] exception error/message (all non 2xx responses) are now under data:{}  => {"status": 500, data: {"error": "Exception", "message":"error"}}
* API: [FIX] fixed various documentation entries and added missing information
* API: [FIX] a node itself and the attribute meta is now correctly encoded as {} if empty and json is requested
* API: [FIX] sending PUT /file with neither p, id nor name will result in Exception\InvalidArgument
* API: [FIX] POST /collection and PUT /file does throw a collection if name and p was submitted at the same time
* API: [CHANGE] Removed parameters $filter_query and $meta_attributes from GET /node/search, everything can be searched via $query and meta attributes can be filtered via $attributes
* API: [FEATURE] Paramater $attribute in PUT /file and POST /collection also accepts now meta attributes.
* API: [FEATURE] POST /collection param $attribute now accepts an additional attribute filter in which stored filter can be stored.
* API: [FIX] Does now throw an exception if any request except GET /collection/children, HEAD /collection/children, POST /collection or PUT /file goes to the ROOT collection
* API: [FEATURE] Added POST /node/readonly to mark a node as readonly (or remove the readonly flags), it's also possible to set the readonly flag during creation of a new subnode via the $attributes parameter
* API: [CHANGE] collection attribute childcount GET /collection/attributes is replaced with size, the number of children is now stored under the attribue size and not childcount
* API: [FEATURE] Added move, destid, destp and conflict to POST /node/undelete, this will allow to undelte a node and restore it within another directory
* UI: [FEATURE] Added event-log viewer with undo possibilities (right user menu)
* UI: [FEATURE] The browser will ask now if the node at the destination already exists during move/copy and how to handle it
* UI: [FEATURE] Added copy&paste functionality
* UI: [FEATURE] Added cut&paste functionality with action buttons besides the existing drag&drop functionality
* UI: [FEATURE] Node properties include now the absolute path
* UI: [FEATURE] Implemented left side main menu to switch between different filesystem tree views
* UI: [FEATURE] Added left side menu entries [trash, search, my shared, shared for me, shared links, coordinate, my cloud]
* UI: [FEATURE] Added profile viewer (right user menu) including user avatar, detailed quota, user attributes
* UI: [FEATURE] Complete redesign of the default theme
* UI: [FEATURE] Added password input for global share option
* UI: [FEATURE] Possibilty to modify editable meta attributes like license, description or copyright
* UI: [FIX] list of share members and history is now scrollable and will be editable on smaller screens as well.
* UI: [FEATURE] The webinterface can now handle multi node erros, and will display them all
* UI: [FIX] popups will now be shown always in the center of the screen
* UI: [FIX] It is now possible again to search after attribues with a value xy, like: name:myfolder (This feature was partially broken with 0.3)
* UI: [CHANGE] Various fixes with the search design, smaller search input
* UI: [FEATURE] During deleting a node it will fade out slowly instead just hiding immediatley
* UI: [FEATURE] Possibility to edit files of type text/* via a new editor popup
* UI: [FEATURE] Create new empty file
* UI: [CHANGE] Removed unused css code, removed basic.css and reimplemented layout.css, css cleanup
* UI: [FEATURE] Implemented new icon library for the default theme
* UI: [FEATURE] Implemented new favicons for various devices
* UI: [FEATURE] Properties view does now include a checksum of the content of a file node
* UI: [FIX] File upload button does now work for Edge correctly
* UI: [FIX] Logout should now work with all major browser correctly
* UI: [CHANGE] Removed kendoui grid instances (history view) (kendoui core opensource does not contain kendo grid)
* UI: [FEATURE] Implemented hints which will show up during the interface initiialization
* UI: [FIX] If a node is selected and the the "folder up .." node will be clicked, no error will be thrown anymore
* UI: [CHANGE] Removed notification() code from v0.1 (such thing are handled with the new response success tick, error report or the new eventlog)
* UI: [FEATURE] It will show now a success tick besides the loader if any query of type POST, PUT or DELETE was successful
* UI: [FEATURE] Better in-built file viewer. Interface is able to show videos, play music, display pdfs and pictures
* UI: [CHANGE] upgraded i18next 1.11 to 3.x
* UI: [FIX] Fixed attribute translation for i18next-jquery (plugin upgrade), all attributes like title or placeholder are now translated correctly
* UI: [CHANGE] Upgraded jquery 2.x to 3.x
* UI: [FIX] If multiple nodes are restored and one or more of the are actually not deleted, the interface will notify you about that (like before), but it will not throw an error anymore after the action was permitted
* UI: [CHANGE] Upgraded kendo-ui-web 2014. to kendo-ui-core 2016., kendo ui is not fully opensource anymore, switched to the core version which is opensource (Apache License 2.0), linked treeview (which is a commercial widget now) against kendo-ui-web 2014. version licensed by GPLv3
* UI: [FIX] verified and corrected english locales by "Simon Frey" <frey@gyselroth.com>
* UI: [FIX] Drastically increased number of search results (Limit until 0.4 was just ~10)
* UI: [FiX] Fixed some layout bugs for responsive views
* UI: [FEATURE] Propper touch support, increased responsive (mobile view) usability, possibility to select multiple nodes via long-touch
* UI: [FEATURE] It will ask if you would like to restore a node to your root direcory if the desired destination does not exists or is deleted
* UI: [FIX] Autocomplete lists are now sorted alphabetically (tag list, share resources)
* UI: [FEATURE] Remember username during login, leave it when login failed and remeber it in localStorage if auth was successful
* UI: [FIX] Interface doesn't reload the righ pannel anymore if a resort of the tree happened
* UI: [FIX] Instead sending an invalid share request if no share roles were selected, the interface wont send the request and will focus on the resource search input
* UI: [FIX] Fixed bug to open node using [ENTER]
* UI: [FEATURE] Various new keyboard shortcuts to perform actions like cut, paste, copy, download, upload and add new file
* UI: [FIX] verified and corrected german locales by "Giuseppe Pizzolotto" <giuseppe.pizzolotto@kzn.ch>


## 0.3
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Fri Dec 11 10:18:15 CET 2015

* UI: [FIX] fixed upload manager colors (progress), increased animation time for quota progress
* UI: [FIX] upload progress bar has now a modern design and sticks at the bottom of the interface
* UI: [FEATURE] added username at the top right inclusive hover menu
* UI: [FEATURE] added logout possibility (user menu), basic and oauth2
* UI: [FIX] fixed enter key
* UI: [FIX] modified login style
* UI: [FEATURE] store OAUTH2 access_key in SessionStorage
* UI: [FEATURE] Moved from own oauth2 implementation to JSO (https://github.com/andreassolberg/jso)
* UI: [FEATURE] added user feedback if login was invalid (error class on input fields)
* UI: [FEATURE] Browsing trough history with forward and back browser button
* UI: [FEATURE] document.href now modified during browsing, it is now possible to copy'n'paste the current location
* UI: [FIX] The first node in the root folder will not be automatically selected anymore
* UI: [FIX] After submit a search request, the right view panel will be cleared
* UI: [FEATURE] Added .gz / .bz2 to icon list
* UI: [FIX] fixed sprite error
* UI: [FIX] removed old data like unused images from templates
* UI: [FEATURE] Download selected nodes as zip instead a single node only
* UI: [FIX] Shows now an error if a file which has to be uploaded is bigger then the max. allowed file size
* UI: [FEATURE] Moved to bower package manager (bower.io)
* UI: [FIX] collection and url share button is now called "Update" if a share would be modified instead created
* UI: [FEATURE] Multi lang is now correctly implemented and added a select box to select between languages
* UI: [FEATURE] Added german translation
* UI: [FIX] Moved from gettext to i18next (http://i18next.com)
* UI: [FIX] Language is now completely separted from code (html and js), translations are now json based in ui/locale/.json
* UI: [FIX] login handler will display an error instead a white page if the first api request against /auth results into an error 500
* UI: [FIX] upgrade to jQuery v2.1.3
* UI: [FEATURE] Added config.js for configuring webinterface
* UI: [FEATURE] Added build/build_ui.sh script for building (compress) ui
* UI: [FEATURE] Brought back error window from v0.1, redesign error window
* UI: [FIX] Moved loader gif to top bar
* UI: [FIX] top bar is not stretchable through all three kendoPannels
* UI: [FEATURE] Enhanced tagging with autocomplete and better editor feeling
* UI: [FIX] fixed method names underscored to camelcase in browser.js
* UI: [FEATURE] Implemented extended search with filtering and realtime queries
* UI: [FIX] Redesigned kendoWindow
* UI: [FIX] Code cleanup & increased performance in browser.js / added strict js mode
* UI: [FEATURE] Responsive mobile/tablet view including landscape & portrait mode
* UI: [FEATURE] Fixed file browser/viewer for iDevices. Safari mobile can't handle file downloads
* UI: [FIX] Disabled event notifications (they will come back in a later release)
* UI: [FIX] Rename node within the right pannel after *not* renamed it in the left pannel is now possible
* UI: [FIX] There is now an error message if no nodes were found
* UI: [FIX] sharing across namespaces is working again (If namespaces are in use)
* UI: [FIX] Microsoft Edge browser doesn't cache API responses anymore
* API: [FEATURE] modified GET v1 /node accecpts now multiple id's and can zip them together
* API: [FEATURE] added GET v1 /node/parent (Get parent node)
* API: [FEATURE] added GET v1 /node/parents (Get all parent nodes within a single array, beginning from /)
* API: [FEATURE] added GET v1 /user/node-attribute-summary
* API: [FIX] fixed xml response (contains now the same child attributes as the json response)
* API: [FIX] POST node/move throws now a custom exception message if the destination node was invalid
* CORE: [FIX] replaced marcj/php-rest-service with own implementation of a HTTP router (increased performance, api requests are now ~100ms faster)
* CORE: [FEATURE] It is now possible to reference anywhere to another configuration node within the xml configuration using the "inherits" attribute
* CORE: [FIX] Upgraded phpzip/phpzip to latest version 1.0.4
* CORE: [FIX] Upgraded to sabre/dav 3.0
* CORE: [FIX] Replaced development global namespace with \Balloon
* CORE: [FIX] Implemented the upcomming PHP5.6 function ldap_esacape to escape all ldap filters
* CORE: [FIX] Fixed trailing slash with zip files (windows explorer can't handle trailing slashes)
* CORE: [FIX] It is now allowed to call a node "0"
* CORE: [FIX] Username is now case insensitive
* CORE: [FEATURE] Added possibility to create task.xml config which will be merged with config.xml during cron task jobs
* DOC: [FIX] fixed some api doc errors
* DOC: [FIX] fixed some errors in README.md & INSTALL.md
* DOC: [FEATURE] added documentation and elasticsearch template for partial word matching


## 0.2
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Thu Apr 23 10:18:15 CEST 2015

BETA RELEASE


## 0.1
**Maintainer**: Raffael Sahli <sahli@gyselroth.com>\
**Date**: Mon Oct 27 10:18:15 CET 2014

ALPHA RELEASE
