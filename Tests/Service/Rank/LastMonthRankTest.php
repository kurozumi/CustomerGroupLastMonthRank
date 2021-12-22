<?php

namespace Customize\Tests\Service\Rank;

use Eccube\Entity\Customer;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Tests\EccubeTestCase;
use Plugin\CustomerGroup\Tests\TestCaseTrait;
use Plugin\CustomerGroupRank\Service\Rank\Context;

class LastMonthRankTest extends EccubeTestCase
{
    use TestCaseTrait;

    /**
     * @var Context
     */
    protected $context;

    public function setUp()
    {
        parent::setUp();

        $this->context = self::$container->get(Context::class);
    }

    /**
     * @param $buy_total
     * @param $price
     * @param $quantity
     * @param $expected
     *
     * @dataProvider decideProvider
     */
    public function testDecide($buy_total, $price, $quantity, $datetime, $hour, $minute, $second, $expected)
    {
        $group = $this->createGroup();
        $group
            ->setBuyTimes(1)
            ->setBuyTotal($buy_total);

        $customer = $this->createCustomer();

        $shipping_date = (new \DateTimeImmutable($datetime))->setTime($hour, $minute, $second);

        $orderStatus = $this->entityManager->find(OrderStatus::class, OrderStatus::DELIVERED);

        for($i = 0; $i < 2; $i++) {
            $order = $this->createOrder($customer);
            foreach ($order->getOrderItems() as $orderItem) {
                if ($orderItem->isProduct()) {
                    $shipping = $orderItem->getShipping();
                    $shipping->setShippingDate($shipping_date);
                    $orderItem
                        ->setShipping($shipping)
                        ->setPrice($price)
                        ->setQuantity($quantity);

                    $order->setOrderStatus($orderStatus);
                    $this->entityManager->persist($order);
                }
            }
        }

        $this->entityManager->flush();

        $this->context->decide($customer);

        $groups = $this->entityManager->find(Customer::class, $customer->getId())->getGroups();

        self::assertCount($expected, $groups);
    }

    public function decideProvider()
    {
        return [
            [1000, 1000, 1, 'first day of previous month', 0, 0, 0, 1],
            [1000, 100, 1, 'first day of previous month', 0, 0, 0, 0],
            [1000, 1000, 1, 'last day of previous month', 23, 59, 59, 1],
            [1000, 100, 1, 'last day of previous month', 23, 59, 59, 0],
            [1000, 1000, 1, 'previous month', 0, 0, 0, 1],
            [1000, 500, 1, 'previous month', 0, 0, 0, 1],
            [1000, 499, 1, 'previous month', 0, 0, 0, 0],
        ];
    }
}
