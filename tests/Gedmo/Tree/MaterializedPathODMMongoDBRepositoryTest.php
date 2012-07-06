<?php

namespace Gedmo\Tree;

use Doctrine\Common\EventManager;
use Tool\BaseTestCaseMongoODM;
use Tree\Fixture\RootCategory;

/**
 * These are tests for Tree behavior
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @package Gedmo.Tree
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class MaterializedPathODMMongoDBRepositoryTest extends BaseTestCaseMongoODM
{
    const CATEGORY = "Tree\\Fixture\\Document\\Category";

    protected function setUp()
    {
        parent::setUp();

        $evm = new EventManager;
        $evm->addEventSubscriber(new TreeListener);

        $this->getMockDocumentManager($evm);
        $this->populate();
    }

    /**
     * @test
     */
    function getRootNodes()
    {
        $repo = $this->dm->getRepository(self::CATEGORY);
        $result = $repo->getRootNodes('title');
        
        $this->assertEquals(3, $result->count());
        $this->assertEquals('Drinks', $result->getNext()->getTitle());
        $this->assertEquals('Food', $result->getNext()->getTitle());
        $this->assertEquals('Sports', $result->getNext()->getTitle());
    }

    /**
     * @test
     */
    function getChildren()
    {
        $repo = $this->dm->getRepository(self::CATEGORY);
        $root = $repo->findOneByTitle('Food');

        // Get all children from the root, including it
        $result = $repo->getChildren($root, false, 'title', 'asc', true);

        $this->assertEquals(5, count($result));
        $this->assertEquals('Carrots', $result->getNext()->getTitle());
        $this->assertEquals('Food', $result->getNext()->getTitle());
        $this->assertEquals('Fruits', $result->getNext()->getTitle());
        $this->assertEquals('Potatoes', $result->getNext()->getTitle());
        $this->assertEquals('Vegitables', $result->getNext()->getTitle());

        // Get all children from the root, NOT including it
        $result = $repo->getChildren($root, false, 'title', 'asc', false);

        $this->assertEquals(4, count($result));
        $this->assertEquals('Carrots', $result->getNext()->getTitle());
        $this->assertEquals('Fruits', $result->getNext()->getTitle());
        $this->assertEquals('Potatoes', $result->getNext()->getTitle());
        $this->assertEquals('Vegitables', $result->getNext()->getTitle());

        // Get direct children from the root, including it
        $result = $repo->getChildren($root, true, 'title', 'asc', true);

        $this->assertEquals(3, $result->count());
        $this->assertEquals('Food', $result->getNext()->getTitle());
        $this->assertEquals('Fruits', $result->getNext()->getTitle());
        $this->assertEquals('Vegitables', $result->getNext()->getTitle());

        // Get direct children from the root, NOT including it
        $result = $repo->getChildren($root, true, 'title', 'asc', false);

        $this->assertEquals(2, $result->count());
        $this->assertEquals('Fruits', $result->getNext()->getTitle());
        $this->assertEquals('Vegitables', $result->getNext()->getTitle());

        // Get ALL nodes
        $result = $repo->getChildren(null, false, 'title');

        $this->assertEquals(9, $result->count());
        $this->assertEquals('Best Whisky', $result->getNext()->getTitle());
        $this->assertEquals('Carrots', $result->getNext()->getTitle());
        $this->assertEquals('Drinks', $result->getNext()->getTitle());
        $this->assertEquals('Food', $result->getNext()->getTitle());
        $this->assertEquals('Fruits', $result->getNext()->getTitle());
        $this->assertEquals('Potatoes', $result->getNext()->getTitle());
        $this->assertEquals('Sports', $result->getNext()->getTitle());
        $this->assertEquals('Vegitables', $result->getNext()->getTitle());
        $this->assertEquals('Whisky', $result->getNext()->getTitle());

        // Get ALL root nodes
        $result = $repo->getChildren(null, true, 'title');

        $this->assertEquals(3, $result->count());
        $this->assertEquals('Drinks', $result->getNext()->getTitle());
        $this->assertEquals('Food', $result->getNext()->getTitle());
        $this->assertEquals('Sports', $result->getNext()->getTitle());
    }

    /**
     * @test
     */
    function getTree()
    {
        $repo = $this->dm->getRepository(self::CATEGORY);
        $tree = $repo->getTree();

        $this->assertEquals(9, $tree->count());
        $this->assertEquals('Drinks', $tree->getNext()->getTitle());
        $this->assertEquals('Whisky', $tree->getNext()->getTitle());
        $this->assertEquals('Best Whisky', $tree->getNext()->getTitle());
        $this->assertEquals('Food', $tree->getNext()->getTitle());
        $this->assertEquals('Fruits', $tree->getNext()->getTitle());
        $this->assertEquals('Vegitables', $tree->getNext()->getTitle());
        $this->assertEquals('Carrots', $tree->getNext()->getTitle());
        $this->assertEquals('Potatoes', $tree->getNext()->getTitle());
        $this->assertEquals('Sports', $tree->getNext()->getTitle());

        // Get a specific tree
        $roots = $repo->getRootNodes();
        $tree = $repo->getTree($roots->getNext());

        $this->assertEquals(3, $tree->count());
        $this->assertEquals('Drinks', $tree->getNext()->getTitle());
        $this->assertEquals('Whisky', $tree->getNext()->getTitle());
        $this->assertEquals('Best Whisky', $tree->getNext()->getTitle());
    }

    /**
     * @test
     */
    function childrenHierarchy()
    {
        /** @var $repo \Gedmo\Tree\Document\MongoDB\Repository\MaterializedPathRepository */
        $repo = $this->dm->getRepository(self::CATEGORY);
        $tree = $repo->childrenHierarchy();

        $this->assertEquals('Drinks', $tree[0]['title']);
        $this->assertEquals('Whisky', $tree[0]['__children'][0]['title']);
        $this->assertEquals('Best Whisky', $tree[0]['__children'][0]['__children'][0]['title']);
        $vegitablesChildren = $tree[1]['__children'][1]['__children'];
        $this->assertEquals('Food', $tree[1]['title']);
        $this->assertEquals('Fruits', $tree[1]['__children'][0]['title']);
        $this->assertEquals('Vegitables', $tree[1]['__children'][1]['title']);
        $this->assertEquals('Carrots', $vegitablesChildren[0]['title']);
        $this->assertEquals('Potatoes', $vegitablesChildren[1]['title']);
        $this->assertEquals('Sports', $tree[2]['title']);

        // Tree of one specific root
        $roots = $repo->getRootNodes();
        $drinks = $roots->getNext();
        $food = $roots->getNext();
        $tree = $repo->childrenHierarchy();

        $this->assertEquals('Drinks', $tree[0]['title']);
        $this->assertEquals('Whisky', $tree[0]['__children'][0]['title']);
        $this->assertEquals('Best Whisky', $tree[0]['__children'][0]['__children'][0]['title']);

        // Tree of one specific root, with the root node
        $tree = $repo->childrenHierarchy($drinks, false, array(), true);

        $this->assertEquals('Drinks', $tree[0]['title']);
        $this->assertEquals('Whisky', $tree[0]['__children'][0]['title']);
        $this->assertEquals('Best Whisky', $tree[0]['__children'][0]['__children'][0]['title']);

        // Tree of one specific root only with direct children, without the root node
        $roots = $repo->getRootNodes();
        $tree = $repo->childrenHierarchy($food, true);

        $this->assertEquals(2, count($tree));
        $this->assertEquals('Fruits', $tree[0]['title']);
        $this->assertEquals('Vegitables', $tree[1]['title']);

        // Tree of one specific root only with direct children, with the root node
        $tree = $repo->childrenHierarchy($food, true, array(), true);

        $this->assertEquals(1, count($tree));
        $this->assertEquals(2, count($tree[0]['__children']));
        $this->assertEquals('Food', $tree[0]['title']);
        $this->assertEquals('Fruits', $tree[0]['__children'][0]['title']);
        $this->assertEquals('Vegitables', $tree[0]['__children'][1]['title']);

        // HTML Tree of one specific root, without the root node
        $roots = $repo->getRootNodes();
        $tree = $repo->childrenHierarchy($drinks, false, array('decorate' => true), false);

        $this->assertEquals('<ul><li>Whisky<ul><li>Best Whisky</li></ul></li></ul>', $tree);


        // HTML Tree of one specific root, with the root node
        $roots = $repo->getRootNodes();
        $tree = $repo->childrenHierarchy($drinks, false, array('decorate' => true), true);

        $this->assertEquals('<ul><li>Drinks<ul><li>Whisky<ul><li>Best Whisky</li></ul></li></ul></li></ul>', $tree);
    }

    public function testChildCount()
    {
        /** @var $repo \Gedmo\Tree\Document\MongoDB\Repository\MaterializedPathRepository */
        $repo = $this->dm->getRepository(self::CATEGORY);

        // Count all
        $count = $repo->childCount();

        $this->assertEquals(9, $count);

        // Count all, but only direct ones
        $count = $repo->childCount(null, true);

        $this->assertEquals(3, $count);

        // Count food children
        $food = $repo->findOneByTitle('Food');
        $count = $repo->childCount($food);

        $this->assertEquals(4, $count);

        // Count food children, but only direct ones
        $count = $repo->childCount($food, true);

        $this->assertEquals(2, $count);
    }

    protected function getUsedEntityFixtures()
    {
        return array(
            self::CATEGORY
        );
    }

    public function createCategory()
    {
        $class = self::CATEGORY;
        return new $class;
    }

    private function populate()
    {
        $root = $this->createCategory();
        $root->setTitle("Food");

        $root2 = $this->createCategory();
        $root2->setTitle("Sports");

        $child = $this->createCategory();
        $child->setTitle("Fruits");
        $child->setParent($root);

        $child2 = $this->createCategory();
        $child2->setTitle("Vegitables");
        $child2->setParent($root);

        $childsChild = $this->createCategory();
        $childsChild->setTitle("Carrots");
        $childsChild->setParent($child2);

        $potatoes = $this->createCategory();
        $potatoes->setTitle("Potatoes");
        $potatoes->setParent($child2);

        $drinks = $this->createCategory();
        $drinks->setTitle('Drinks');

        $whisky = $this->createCategory();
        $whisky->setTitle('Whisky');
        $whisky->setParent($drinks);

        $bestWhisky = $this->createCategory();
        $bestWhisky->setTitle('Best Whisky');
        $bestWhisky->setParent($whisky);

        $this->dm->persist($root);
        $this->dm->persist($root2);
        $this->dm->persist($child);
        $this->dm->persist($child2);
        $this->dm->persist($childsChild);
        $this->dm->persist($potatoes);
        $this->dm->persist($drinks);
        $this->dm->persist($whisky);
        $this->dm->persist($bestWhisky);

        $this->dm->flush();
    }
}
