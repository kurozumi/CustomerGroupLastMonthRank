<?php

namespace Customize\Service\Rank;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Customer;
use Eccube\Entity\Master\OrderItemType;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Entity\Shipping;
use Plugin\CustomerGroup\Entity\Group;
use Plugin\CustomerGroupRank\Service\Rank\RankInterface;

class LastMonthRank implements RankInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * 優先度が最上位のグループを会員に設定する
     *
     * @param Customer $customer
     * @return void
     */
    public function decide(Customer $customer): void
    {
        // 会員グループをクリアする
        $customer->getGroups()->clear();

        // 対象の会員グループが見つかったら登録
        $groups = $this->getGroups($customer);
        if ($groups->count() > 0) {
            /** @var Group $group */
            $group = $groups->first();
            $customer->addGroup($group);
        }
    }

    protected function getGroups(Customer $customer): ArrayCollection
    {
        $last_month_first = (new \DateTimeImmutable('first day of previous month'))->setTime(0, 0, 0);
        $last_month_last = (new \DateTimeImmutable('last day of previous month'))->setTime(23, 59, 59);

        $orderStatus = $this->entityManager->find(OrderStatus::class, OrderStatus::DELIVERED);
        $orderItemType = $this->entityManager->find(OrderItemType::class, OrderItemType::PRODUCT);

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('SUM(oi.price * oi.quantity)')
            ->from(OrderItem::class, 'oi')
            ->innerJoin(Order::class, 'o', 'WITH', 'oi.Order = o.id')
            ->innerJoin(Shipping::class, 's', 'WITH', 'o.id = s.Order')
            ->where('oi.OrderItemType = :OrderItemType')
            ->andWhere('o.Customer = :Customer')
            ->andWhere('o.OrderStatus = :OrderStatus')
            ->andWhere('s.shipping_date BETWEEN :last_month_first AND :last_month_last')
            ->setParameter('Customer', $customer)
            ->setParameter('OrderStatus', $orderStatus)
            ->setParameter('OrderItemType', $orderItemType)
            ->setParameter('last_month_first', $last_month_first)
            ->setParameter('last_month_last', $last_month_last);

        $buyTotal = $qb->getQuery()->getSingleScalarResult();

        // 注文回数は条件に入れないので0指定
        $searchData = [
            'buyTimes' => 0,
            'buyTotal' => $buyTotal ?? 0
        ];

        $groups = $this->entityManager->getRepository(Group::class)->getQueryBuilderBySearchData($searchData)
            ->getQuery()
            ->getResult();

        return new ArrayCollection($groups);
    }
}
