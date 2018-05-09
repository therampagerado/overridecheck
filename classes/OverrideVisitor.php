<?php
/**
 * 2017-2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace OverrideCheckModule;

use ThirtyBeesOverrideCheck\PhpParser\Node\Stmt\ClassMethod;
use ThirtyBeesOverrideCheck\PhpParser\NodeTraverser;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class OverrideVisitor
 *
 * @package OverrideCheckModule
 */
class OverrideVisitor implements \ThirtyBeesOverrideCheck\PhpParser\NodeVisitor
{
    /** @var string $methodToRemove */
    protected $methodToRemove;

    /**
     * OverrideVisitor constructor.
     */
    public function __construct($methodToRemove)
    {
        $this->methodToRemove = $methodToRemove;
    }

    /**
     * @param $methodToRemove
     *
     * @return OverrideVisitor
     */
    public function setMethodToRemove($methodToRemove)
    {
        $this->methodToRemove = $methodToRemove;

        return $this;
    }

    /**
     * Called once before traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param \ThirtyBeesOverrideCheck\PhpParser\Node $nodes Array of nodes
     *
     * @return null|\ThirtyBeesOverrideCheck\PhpParser\Node Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        return $nodes;
    }

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null
     *        => $node stays as-is
     *  * NodeTraverser::DONT_TRAVERSE_CHILDREN
     *        => Children of $node are not traversed. $node stays as-is
     *  * NodeTraverser::STOP_TRAVERSAL
     *        => Traversal is aborted. $node stays as-is
     *  * otherwise
     *        => $node is set to the return value
     *
     * @param \ThirtyBeesOverrideCheck\PhpParser\Node $node Node
     *
     * @return null|int|\ThirtyBeesOverrideCheck\PhpParser\Node Node
     */
    public function enterNode(\ThirtyBeesOverrideCheck\PhpParser\Node $node)
    {
        return $node;
    }

    /**
     * Called when leaving a node.
     *
     * Return value semantics:
     *  * null
     *        => $node stays as-is
     *  * NodeTraverser::REMOVE_NODE
     *        => $node is removed from the parent array
     *  * NodeTraverser::STOP_TRAVERSAL
     *        => Traversal is aborted. $node stays as-is
     *  * array (of Nodes)
     *        => The return value is merged into the parent array (at the position of the $node)
     *  * otherwise
     *        => $node is set to the return value
     *
     * @param \ThirtyBeesOverrideCheck\PhpParser\Node $node Node
     *
     * @return null|false|int|\ThirtyBeesOverrideCheck\PhpParser\Node|\ThirtyBeesOverrideCheck\PhpParser\Node Node
     */
    public function leaveNode(\ThirtyBeesOverrideCheck\PhpParser\Node $node)
    {
        if ($node instanceof ClassMethod && $node->name === $this->methodToRemove) {
            return NodeTraverser::REMOVE_NODE;
        }

        return $node;
    }

    /**
     * Called once after traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param \ThirtyBeesOverrideCheck\PhpParser\Node $nodes Array of nodes
     *
     * @return null|\ThirtyBeesOverrideCheck\PhpParser\Node Array of nodes
     */
    public function afterTraverse(array $nodes)
    {
        return $nodes;
    }
}
