<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\Data\OrderTrackingSearchResultsInterface;
use Artimus\Matomo\Api\Data\OrderTrackingSearchResultsInterfaceFactory;
use Artimus\Matomo\Model\OrderTracking;
use Artimus\Matomo\Model\OrderTrackingFactory;
use Artimus\Matomo\Model\OrderTrackingRepository;
use Artimus\Matomo\Model\ResourceModel\OrderTracking as OrderTrackingResource;
use Artimus\Matomo\Model\ResourceModel\OrderTracking\Collection;
use Artimus\Matomo\Model\ResourceModel\OrderTracking\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderTrackingRepositoryTest extends TestCase
{
    /**
     * @var OrderTrackingResource&MockObject
     */
    private $resource;

    /**
     * @var OrderTrackingFactory&MockObject
     */
    private $orderTrackingFactory;

    /**
     * @var CollectionFactory&MockObject
     */
    private $collectionFactory;

    /**
     * @var OrderTrackingSearchResultsInterfaceFactory&MockObject
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface&MockObject
     */
    private $collectionProcessor;

    /**
     * @var OrderTrackingRepository
     */
    private OrderTrackingRepository $repository;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(OrderTrackingResource::class);
        $this->orderTrackingFactory = $this->createMock(OrderTrackingFactory::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->searchResultsFactory = $this->createMock(OrderTrackingSearchResultsInterfaceFactory::class);
        $this->collectionProcessor = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new OrderTrackingRepository(
            $this->resource,
            $this->orderTrackingFactory,
            $this->collectionFactory,
            $this->searchResultsFactory,
            $this->collectionProcessor
        );
    }

    public function testItSavesAnOrderTrackingRecordAndReturnsItWithAnId(): void
    {
        $orderTracking = $this->createMock(OrderTracking::class);
        $orderTracking->method('getEntityId')->willReturn(5);

        $this->resource->expects($this->once())
            ->method('save')
            ->with($orderTracking);

        $result = $this->repository->save($orderTracking);

        $this->assertSame($orderTracking, $result);
        $this->assertSame(5, $result->getEntityId());
    }

    public function testItLoadsAnOrderTrackingRecordByOrderId(): void
    {
        $orderTracking = $this->createMock(OrderTracking::class);
        $orderTracking->method('getId')->willReturn(5);
        $orderTracking->method('getOrderId')->willReturn(42);

        $this->orderTrackingFactory->method('create')->willReturn($orderTracking);
        $this->resource->expects($this->once())
            ->method('load')
            ->with($orderTracking, 42, OrderTrackingInterface::ORDER_ID);

        $result = $this->repository->getByOrderId(42);

        $this->assertSame($orderTracking, $result);
        $this->assertSame(42, $result->getOrderId());
    }

    public function testItThrowsNoSuchEntityExceptionWhenNoRecordExistsForTheOrderId(): void
    {
        $orderTracking = $this->createMock(OrderTracking::class);
        $orderTracking->method('getId')->willReturn(null);

        $this->orderTrackingFactory->method('create')->willReturn($orderTracking);

        $this->expectException(NoSuchEntityException::class);

        $this->repository->getByOrderId(99);
    }

    public function testItThrowsCouldNotSaveExceptionWhenTheResourceModelFailsToSave(): void
    {
        $orderTracking = $this->createMock(OrderTracking::class);

        $this->resource->method('save')
            ->willThrowException(new \Exception('Duplicate entry for order_id'));

        $this->expectException(CouldNotSaveException::class);

        $this->repository->save($orderTracking);
    }

    public function testItReturnsOrderTrackingRecordsMatchingSearchCriteria(): void
    {
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $items = [$this->createMock(OrderTracking::class)];

        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getSize')->willReturn(1);

        $this->collectionFactory->method('create')->willReturn($collection);
        $this->collectionProcessor->expects($this->once())
            ->method('process')
            ->with($searchCriteria, $collection);

        $searchResults = $this->createMock(OrderTrackingSearchResultsInterface::class);
        $searchResults->expects($this->once())->method('setSearchCriteria')->with($searchCriteria);
        $searchResults->expects($this->once())->method('setItems')->with($items);
        $searchResults->expects($this->once())->method('setTotalCount')->with(1);

        $this->searchResultsFactory->method('create')->willReturn($searchResults);

        $result = $this->repository->getList($searchCriteria);

        $this->assertSame($searchResults, $result);
    }
}
