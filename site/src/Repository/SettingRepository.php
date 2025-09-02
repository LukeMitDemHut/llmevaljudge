<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * Find a setting by name
     */
    public function findByName(string $name): ?Setting
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Get setting value by name, with optional default
     */
    public function getSettingValue(string $name, ?string $default = null): ?string
    {
        $setting = $this->findByName($name);
        return $setting ? $setting->getValue() : $default;
    }

    /**
     * Set or update a setting value
     */
    public function setSetting(string $name, string $value): Setting
    {
        $setting = $this->findByName($name);

        if (!$setting) {
            $setting = new Setting($name, $value);
            $this->getEntityManager()->persist($setting);
        } else {
            $setting->setValue($value);
            $setting->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->getEntityManager()->flush();

        return $setting;
    }

    /**
     * Get all settings as key-value pairs
     */
    public function getAllAsKeyValue(): array
    {
        $settings = $this->findAll();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->getName()] = $setting->getValue();
        }

        return $result;
    }

    //    /**
    //     * @return Setting[] Returns an array of Setting objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Setting
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
