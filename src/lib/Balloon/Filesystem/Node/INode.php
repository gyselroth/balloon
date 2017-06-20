<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

interface INode
{
    /**
     * Deleted node options
     */
    const DELETED_EXCLUDE = 0;
    const DELETED_ONLY    = 1;
    const DELETED_INCLUDE = 2;


    /**
     * Handle conflicts
     */
    const CONFLICT_NOACTION = 0;
    const CONFLICT_RENAME   = 1;
    const CONFLICT_MERGE    = 2;
  

    /**
     * Meta attributes
     */
    const META_DESCRIPTION  = 'description';
    const META_COLOR        = 'color';
    const META_AUTHOR       = 'author';
    const META_MAIL         = 'mail';
    const META_LICENSE      = 'license';
    const META_COPYRIGHT    = 'copyright';
    const META_TAGS         = 'tags';
    const META_COORDINATE   = 'coordinate';
}
