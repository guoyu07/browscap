<?php
/**
 * This file is part of the browscap package.
 *
 * Copyright (c) 1998-2017, Browser Capabilities Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapTest\Generator;

use Browscap\Data\DataCollection;
use Browscap\Data\Division;
use Browscap\Generator\BuildCustomFileGenerator;
use Browscap\Helper\CollectionCreator;
use Browscap\Writer\WriterCollection;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

/**
 * Class BuildGeneratorTest
 *
 * @category   BrowscapTest
 *
 * @author     James Titcumb <james@asgrim.com>
 */
class BuildCustomFileGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array
     */
    private $messages = [];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public function setUp() : void
    {
        $this->logger   = new Logger('browscapTest', [new NullHandler()]);
        $this->messages = [];
    }

    /**
     * tests failing the build if the build dir does not exist
     *
     * @group generator
     * @group sourcetest
     */
    public function testConstructFailsIfTheDirDoesNotExsist() : void
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessage('The directory "/dar" does not exist, or we cannot access it');

        new BuildCustomFileGenerator('/dar', '');
    }

    /**
     * tests failing the build if no build dir is a file
     *
     * @group generator
     * @group sourcetest
     */
    public function testConstructFailsIfTheDirIsNotAnDirectory() : void
    {
        $this->expectException('\Exception');
        $this->expectExceptionMessage('The path "' . __FILE__ . '" did not resolve to a directory');

        new BuildCustomFileGenerator(__FILE__, null);
    }

    /**
     * tests setting and getting a logger
     *
     * @group generator
     * @group sourcetest
     */
    public function testSetLogger() : void
    {
        $logger = $this->createMock(\Monolog\Logger::class);

        $resourceFolder = __DIR__ . '/../../../resources/';
        $buildFolder    = __DIR__ . '/../../../build/browscap-ua-test-' . time();

        @mkdir($buildFolder, 0777, true);

        $generator = new BuildCustomFileGenerator($resourceFolder, $buildFolder);
        self::assertSame($generator, $generator->setLogger($logger));
        self::assertSame($logger, $generator->getLogger());
    }

    /**
     * tests running a build
     *
     * @group generator
     * @group sourcetest
     */
    public function testBuild() : void
    {
        $division = $this->getMockBuilder(Division::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUserAgents', 'getVersions'])
            ->getMock();

        $division
            ->expects(self::exactly(4))
            ->method('getUserAgents')
            ->will(
                self::returnValue(
                    [
                        0 => [
                            'properties' => [
                                'Parent' => 'DefaultProperties',
                                'Browser' => 'xyz',
                                'Version' => '1.0',
                                'MajorBer' => '1',
                            ],
                            'userAgent' => 'abc',
                        ],
                    ]
                )
            );
        $division
            ->expects(self::once())
            ->method('getVersions')
            ->will(self::returnValue(['2']));

        $collection = $this->getMockBuilder(DataCollection::class)
            ->disableOriginalConstructor()
            ->setMethods(['getGenerationDate', 'getDefaultProperties', 'getDefaultBrowser', 'getDivisions', 'checkProperty'])
            ->getMock();

        $collection
            ->expects(self::once())
            ->method('getGenerationDate')
            ->will(self::returnValue(new \DateTime()));
        $collection
            ->expects(self::exactly(2))
            ->method('getDefaultProperties')
            ->will(self::returnValue($division));
        $collection
            ->expects(self::once())
            ->method('getDefaultBrowser')
            ->will(self::returnValue($division));
        $collection
            ->expects(self::once())
            ->method('getDivisions')
            ->will(self::returnValue([$division]));
        $collection
            ->expects(self::once())
            ->method('checkProperty')
            ->will(self::returnValue(true));

        $mockCreator = $this->getMockBuilder(CollectionCreator::class)
            ->disableOriginalConstructor()
            ->setMethods(['createDataCollection'])
            ->getMock();

        $mockCreator
            ->expects(self::any())
            ->method('createDataCollection')
            ->will(self::returnValue($collection));

        $writerCollection = $this->getMockBuilder(WriterCollection::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'fileStart',
                'renderHeader',
                'renderAllDivisionsHeader',
                'renderSectionHeader',
                'renderSectionBody',
                'fileEnd',
            ])
            ->getMock();

        $writerCollection
            ->expects(self::once())
            ->method('fileStart')
            ->will(self::returnSelf());
        $writerCollection
            ->expects(self::once())
            ->method('renderHeader')
            ->will(self::returnSelf());
        $writerCollection
            ->expects(self::once())
            ->method('renderAllDivisionsHeader')
            ->will(self::returnSelf());
        $writerCollection
            ->expects(self::exactly(3))
            ->method('renderSectionHeader')
            ->will(self::returnSelf());
        $writerCollection
            ->expects(self::exactly(3))
            ->method('renderSectionBody')
            ->will(self::returnSelf());
        $writerCollection
            ->expects(self::once())
            ->method('fileEnd')
            ->will(self::returnSelf());

        // First, generate the INI files
        $resourceFolder = __DIR__ . '/../../../resources/';
        $buildFolder    = __DIR__ . '/../../../build/browscap-ua-test-' . time();

        @mkdir($buildFolder, 0777, true);

        $generator = new BuildCustomFileGenerator($resourceFolder, $buildFolder);
        self::assertSame($generator, $generator->setLogger($this->logger));
        self::assertSame($generator, $generator->setCollectionCreator($mockCreator));
        self::assertSame($generator, $generator->setWriterCollection($writerCollection));

        $generator->run('test');
    }
}
