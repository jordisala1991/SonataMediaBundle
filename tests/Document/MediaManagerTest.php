<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Tests\Document;

use PHPUnit\Framework\TestCase;
use Sonata\MediaBundle\Document\MediaManager;

/**
 * @group document
 * @group mongo
 */
class MediaManagerTest extends TestCase
{
    /** @var MediaManager */
    private $manager;

    protected function setUp(): void
    {
        $this->manager = new MediaManager('Sonata\MediaBundle\Model\MediaInterface', $this->createRegistryMock());
    }

    public function testSave(): void
    {
        $media = new Media();
        $this->manager->save($media, 'default', 'media.test');

        $this->assertSame('default', $media->getContext());
        $this->assertSame('media.test', $media->getProviderName());

        $media = new Media();
        $this->manager->save($media, true);

        $this->assertNull($media->getContext());
        $this->assertNull($media->getProviderName());
    }

    public function testSaveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->save(null);
    }

    public function testDeleteException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->delete(null);
    }

    /**
     * Returns mock of doctrine document manager.
     *
     * @return \Sonata\DoctrinePHPCRAdminBundle\Model\ModelManager
     */
    protected function createRegistryMock()
    {
        $dm = $this->getMockBuilder('Doctrine\ODM\MongoDB\DocumentManager')
            ->setMethods(['persist', 'flush'])
            ->disableOriginalConstructor()
            ->getMock();
        $registry = $this->createMock('Doctrine\Common\Persistence\ManagerRegistry');

        $dm->expects($this->any())->method('persist');
        $dm->expects($this->any())->method('flush');
        $registry->expects($this->any())->method('getManagerForClass')->will($this->returnValue($dm));

        return $registry;
    }
}
