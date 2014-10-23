<?php

namespace Jackalope\Transport\Fs\Filesystem\Storage;

use PHPCR\Util\UUIDHelper;

/**
 * - Every node in S' is given a new and distinct identifier
 *   - or, if $srcWorkspace is given -
 *   Every referenceable node in S' is given a new and distinct identifier
 *   while every non-referenceable node in S' may be given a new and
 *   distinct identifier.
 * - The repository may automatically drop any mixin node type T present on
 *   any node M in S. Dropping a mixin node type in this context means that
 *   while M remains unchanged, its copy M' will lack the mixin T and any
 *   child nodes and properties defined by T that are present on M. For
 *   example, a node M that is mix:versionable may be copied such that the
 *   resulting node M' will be a copy of N except that M' will not be
 *   mix:versionable and will not have any of the properties defined by
 *   mix:versionable. In order for a mixin node type to be dropped it must
 *   be listed by name in the jcr:mixinTypes property of M. The resulting
 *   jcr:mixinTypes property of M' will reflect any change.
 * - If a node M in S is referenceable and its mix:referenceable mixin is
 *   not dropped on copy, then the resulting jcr:uuid property of M' will
 *   reflect the new identifier assigned to M'.
 * - Each REFERENCE or WEAKEREFERENCE property R in S is copied to its new
 *   location R' in S'. If R references a node M within S then the value of
 *   R' will be the identifier of M', the new copy of M, thus preserving the
 *   reference within the subgraph.
 */
class NodeCopier
{
    private $nodeReader;
    private $nodeWriter;

    private $uuidMap = array();

    public function __construct(
        NodeReader $nodeReader,
        NodeWriter $nodeWriter
    )
    {
        $this->nodeReader = $nodeReader;
        $this->nodeWriter = $nodeWriter;
    }

    /**
     * Write the given node data.
     *
     * @return array Node data
     */
    public function copyNode($srcWorkspace, $srcPath, $destWorkspace, $destPath)
    {
        $node = $this->nodeReader->readNode($srcWorkspace, $srcPath);
        $this->processNode($node);
        $this->nodeWriter->writeNode($destWorkspace, $destPath, $node);

        foreach ($node as $key => $value) {
            if ($value instanceof \stdClass) {
                $this->copyNode(
                    $srcWorkspace, $srcPath . '/' . $key,
                    $destWorkspace, $destPath . '/' . $key
                );
            }
        }
    }

    private function processNode(\stdClass $node)
    {
        $jcrUuid = UUIDHelper::generateUUID();
        if (!isset($node->{'jcr:uuid'})) {
            return;
        }
        $this->uuidMap[$node->{'jcr:uuid'}] = $jcrUuid;
        $node->{'jcr:uuid'} = $jcrUuid;
    }
}
