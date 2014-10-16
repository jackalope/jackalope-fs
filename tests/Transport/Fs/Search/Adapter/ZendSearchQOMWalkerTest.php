<?php

namespace Transport\Fs\Search\Adapter;

use Jackalope\Transport\Fs\Search\QOMWalker\ZendSearchQOMWalker;
use Prophecy\PhpUnit\ProphecyTestCase;
use Jackalope\Query\QOM\QueryObjectModelFactory;
use Jackalope\Factory;
use PHPCR\Util\QOM\QueryBuilder;
use Jackalope\Transport\Fs\Search\Adapter\ZendSearchAdapter;

class ZendSearchQOMWalkerTest extends ProphecyTestCase
{
    public function setUp()
    {
        $this->qomf = new QueryObjectModelFactory(new Factory());
        $this->qomWalker = new ZendSearchQOMWalker();
        $this->qom = $this->prophesize('PHPCR\Query\QOM\QueryObjectModelInterface');
    }

    public function provideWalkQOMQuery()
    {
        return array(

            // FROM Foobar
            array(
                'FROM foobar',
                function ($qb, $qomf) {
                    $qb->from($qomf->selector('a', 'foobar'));
                },
                '(jcr\:primaryType:foobar)'
            ),

            // FROM [nt:foobar]
            array(
                'FROM [nt:foobar]',
                function ($qb, $qomf) {
                    $qb->from($qomf->selector('a', 'nt:foobar'));
                },
                '(jcr\:primaryType:nt\:foobar)'
            ),

            array(
                'FROM foobar WHERE a.foo = "hello"',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.equal.to', $qomf->literal('hello'))); },
                '(jcr\:primaryType:Foobar) AND (foo:"hello")'
            ),
            array(
                'FROM foobar WHERE a.foo LIKE "hello%"',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.like', $qomf->literal('hello%'))); },
                '(jcr\:primaryType:Foobar) AND (foo:"hello*")'
            ),
            array(
                'FROM foobar WHERE a.foo != "hello%"',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.not.equal.to', $qomf->literal('hello'))); },
                '(jcr\:primaryType:Foobar) AND (NOT foo:"hello")'
            ),
            array(
                'FROM foobar WHERE a.foo < 5',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.less.than', $qomf->literal(5))); },
                '(jcr\:primaryType:Foobar) AND (foo:{-' . PHP_INT_MAX . ' TO 5})'
            ),

            array(
                'FROM foobar WHERE a.foo <= 5',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.less.than.or.equal.to', $qomf->literal(5))); },
                '(jcr\:primaryType:Foobar) AND (foo:[-' . PHP_INT_MAX . ' TO 5])'
            ),

            array(
                'FROM foobar WHERE a.foo > 5',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.greater.than', $qomf->literal(5))); },
                '(jcr\:primaryType:Foobar) AND (foo:{5 TO ' . PHP_INT_MAX . '})'
            ),

            array(
                'FROM foobar WHERE a.foo >= 5',
                function ($qb, $qomf) { $qb->where($qomf->comparison($qomf->propertyValue('a', 'foo'),
                'jcr.operator.greater.than.or.equal.to', $qomf->literal(5))); },
                '(jcr\:primaryType:Foobar) AND (foo:[5 TO ' . PHP_INT_MAX . '])'
            ),

            array(
                '// FROM foobar WHERE a.foo = "helland a.bar = "foo"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->andConstraint(
                            $qomf->comparison(
                                $qomf->propertyValue('a', 'foo'),
                                'jcr.operator.equal.to',
                                $qomf->literal('hello')
                            ),
                            $qomf->comparison(
                                $qomf->propertyValue('a', 'bar'),
                                'jcr.operator.equal.to',
                                $qomf->literal('foo')
                            )
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (foo:"hello" AND bar:"foo")'
            ),

            array(
                '// FRfoobar WHERE a.foo = "hello" OR a.bar = "foo"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->orConstraint(
                            $qomf->comparison(
                                $qomf->propertyValue('a', 'foo'),
                                'jcr.operator.equal.to',
                                $qomf->literal('hello')
                            ),
                            $qomf->comparison(
                                $qomf->propertyValue('a', 'bar'),
                                'jcr.operator.equal.to',
                                $qomf->literal('foo')
                            )
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (foo:"hello" OR bar:"foo")'
            ),

            array(
                'FROM foobar WHERE NOT a.foo = "hello"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->notConstraint(
                            $qomf->comparison(
                                $qomf->propertyValue('a', 'foo'),
                                'jcr.operator.equal.to',
                                $qomf->literal('hello')
                            )
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (NOT foo:"hello")'
            ),

            array(
                'FROM foobar WHERE CONTAINS(a.foo, "foobar")',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->fullTextSearch('a', 'foo', 'foobar')
                    );
                },
                '(jcr\:primaryType:Foobar) AND (foo:foobar)'
            ),

            array(
                'FROM foobar WHERE ISSAMENODE((a, "/foo/bar")',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->sameNode('a', '/foo/bar')
                    );
                },
                '(jcr\:primaryType:Foobar) AND (jcr\:path:/foo/bar)'
            ),

            array(
                'FROM foobar WHERE ISCHILDNODE(a, "/foo/bar")',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->childNode('a', '/foo/bar')
                    );
                },
                '(jcr\:primaryType:Foobar) AND (jcr\:parentpath:/foo/bar)'
            ),

            array(
                'FROM foobar WHERE ISDESCENDANTNODE(a, "/foo/bar")',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->descendantNode('a', '/foo/bar')
                    );
                },
                '(jcr\:primaryType:Foobar) AND (jcr\:path:/foo/bar/*)'
            ),

            // POST PROCESS
            array(
                'FROM foobar WHERE LENGTH("a", "bar") =  5',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->comparison(
                            $qomf->length(
                                $qomf->propertyValue('a', 'foo')
                            ),
                            'jcr.operator.equal.to',
                            $qomf->literal(5)
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (foo:"5")'
            ),

            array(
                'FROM foobar WHERE NAME(a) = "foobar"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->comparison(
                            $qomf->nodename('a'),
                            'jcr.operator.equal.to',
                            $qomf->literal('foo:foo')
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (' . ZendSearchAdapter::IDX_NODENAME . ':"foo\:foo")'
            ),

            array(
                'FROM foobar WHERE LOCALNAME(a) = "foobar"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->comparison(
                            $qomf->nodeLocalName('a'),
                            'jcr.operator.equal.to',
                            $qomf->literal('foo:foo')
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (' . ZendSearchAdapter::IDX_NODELOCALNAME. ':"foo\:foo")'
            ),

            // POST PROCESS
            array(
                'FROM foobar WHERE LOWERCASE(LOCALNAME(a)) = "foobar"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->comparison(
                            $qomf->lowercase(
                                $qomf->nodeLocalName('a')
                            ),
                            'jcr.operator.equal.to',
                            $qomf->literal('nt:Foo')
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (' . ZendSearchAdapter::IDX_NODELOCALNAME. ':"nt\:Foo")'
            ),

            // POST PROCESS
            array(
                'FROM foobar WHERE UPPERCASE(LOCALNAME(a)) = "foobar"',
                function ($qb, $qomf) {
                    $qb->where(
                        $qomf->comparison(
                            $qomf->upperCase(
                                $qomf->nodeLocalName('a')
                            ),
                            'jcr.operator.equal.to',
                            $qomf->literal('nt:Foo')
                        )
                    );
                },
                '(jcr\:primaryType:Foobar) AND (' . ZendSearchAdapter::IDX_NODELOCALNAME. ':"nt\:Foo")'
            ),
        );


    }

    /**
     * @dataProvider provideWalkQOMQuery
     */
    public function testWalkQOMQuery($description, $queryCallback, $expected)
    {
        $qb = new QueryBuilder($this->qomf);
        $qb->from($this->qomf->selector('a', 'Foobar'));
        $queryCallback($qb, $this->qomf);
        $res = $this->qomWalker->walkQOMQuery($qb->getQuery());
        $this->assertEquals($expected, $res, $description);
    }
}
