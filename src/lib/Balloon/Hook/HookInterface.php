<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use \Balloon\Exception;
use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Filesystem;
use \Balloon\Server;
use \Balloon\Server\User;
use \Balloon\Filesystem\Node\Collection;
use \Balloon\Filesystem\Node\File;
use \Balloon\Filesystem\Node\NodeInterface;
use \Micro\Auth;
use \Micro\Auth\Identity;

interface HookInterface
{
    /**
     * Create hook
     *
     * @param  Logger $logger
     * @param  Iterable $config
     * @return void
     */
    public function __construct(Logger $logger, ?Iterable $config=null);


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return HookInterface
     */
    public function setOptions(?Iterable $config): HookInterface;


    /**
     * Run: preAuthentication
     *
     * Executed before authentication
     *
     * @param   Auth $auth
     * @return  void
     */
    public function preAuthentication(Auth $auth): void;
   

    /**
     * Run: preServerIdentity
     *
     * Executed after authentication but before the identity gets authenticated with the server
     *
     * @param   Server $server
     * @param   Identity $identity
     * @param   array $attributes
     * @return  void
     */
    public function preServerIdentity(Server $server, Identity $identity, ?array &$attributes): void;
    

    /**
     * Run: postCreateCollection
     *
     * Executed authenticated with the server
     *
     * @param   Server $server
     * @param   User $user
     * @return  void
     */
    public function postServerIdentity(Server $server, User $user): void;

 
    /**
     * Run: preRestoreFile
     *
     * Executed pre version rollback
     *
     * @param   File $node
     * @param   int $version
     * @return  void
     */
    public function preRestoreFile(File $node, int $version): void;


    /**
     * Run: postRestoreFile
     *
     * Executed post version rollback
     *
     * @param   File $node
     * @param   int $version
     * @return  void
     */
    public function postRestoreFile(File $node, int $version): void;


    /**
     * Run: prePutFile
     *
     * Executed pre a put file request
     *
     * @param   File $node
     * @param   string|resource $content
     * @param   bool $force
     * @param   array $attributes
     * @return  void
     */
    public function prePutFile(File $node, $content, bool $force, array $attributes): void;


    /**
     * Run: postPutFile
     *
     * Executed post a put file request
     *
     * @param   File $node
     * @param   string|resource $content
     * @param   bool $force
     * @param   array $attributes
     * @return  void
     */
    public function postPutFile(File $node, $content, bool $force, array $attributes): void;


    /**
     * Run: preCreateCollection
     *
     * Executed pre a directory will be created
     *
     * @param   Collection $parent
     * @param   string $name
     * @param   array $attributes
     * @param   bool $clone
     * @return  void
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void;
    

    /**
     * Run: postCreateCollection
     *
     * Executed post a directory was created
     *
     * @param   Collection $parent
     * @param   Collection $node
     * @param   bool $clone
     * @return  void
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void;


    /**
     * Run: preCreateFile
     *
     * Executed pre a create will be created
     *
     * @param   Collection $parent
     * @param   string $name
     * @param   array $attributes
     * @param   bool $clone
     * @return  void
     */
    public function preCreateFile(Collection $parent, string &$name, array &$attributes, bool $clone): void;


    /**
     * Run: postCreateFile
     *
     * Executed post a file was created
     *
     * @param   Collection $parent
     * @param   File $node
     * @param   bool $clone
     * @return  void
     */
    public function postCreateFile(Collection $parent, File $node, bool $clone): void;
    

    /**
     * Run: preCopyCollection
     *
     * Executed pre a directory will be cloned
     *
     * @param   Collection $node
     * @param   Collection $parent
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function preCopyCollection(Collection $node, Collection $parent,
        int $conflict, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: postCopyCollection
     *
     * Executed post a directory will be cloned
     *
     * @param   Collection $node
     * @param   Collection $parent
     * @param   Collection $new_node
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postCopyCollection(Collection $node, Collection $parent,
        Collection $new_node, int $conflict, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: preCopyFile
     *
     * Executed pre a file will be cloned
     *
     * @param   File $node
     * @param   Collection $parent
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function preCopyFile(File $node, Collection $parent,
       int $conflict, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: postCopyFile
     *
     * Executed post a file will be cloned
     *
     * @param   File $node
     * @param   Collection $parent
     * @param   File $new_node
     * @param   int $conflict
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postCopyFile(File $node, Collection $parent, File $new_node,
       int $conflict, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: preDeleteCollection
     *
     * Executed pre a directory will be deleted
     *
     * @param   Collection $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: postDeleteCollection
     *
     * Executed post a directory was deleted
     *
     * @param   Collection $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void;

    
    /**
     * Run: preDeleteFile
     *
     * Executed pre a file will be deleted
     *
     * @param   File $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: postDeleteFile
     *
     * Executed post a file was deleted
     *
     * @param   File $node
     * @param   bool $force
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function postDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void;


    /**
     * Run: preSaveNodeAttributes
     *
     * Executed pre node attributes will be saved to mongodb
     *
     * @param   NodeInterface $node
     * @param   array $save_attributes
     * @param   array $remove_attributes
     * @param   string $recursion
     * @param   bool $recursion_first
     * @return  void
     */
    public function preSaveNodeAttributes(NodeInterface $node, array &$save_attributes,
        array &$remove_attributes, ?string $recursion, bool $recursion_first): void;

    
    /**
     * Run: postSaveNodeAttributes
     *
     * Executed post node attributes were saved to mongodb
     *
     * @param  NodeInterface $node
     * @param  array $save_attributes
     * @param  array $remove_attributes
     * @param  string $recursion
     * @param  bool $recursion_first
     * @return void
     */
    public function postSaveNodeAttributes(NodeInterface $node, array $save_attributes,
        array $remove_attributes, ?string $recursion, bool $recursion_first): void;
}
