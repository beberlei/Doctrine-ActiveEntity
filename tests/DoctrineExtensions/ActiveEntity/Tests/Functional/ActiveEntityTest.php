<?php

namespace DoctrineExtensions\ActiveEntity\Tests\Functional;

use DoctrineExtensions\ActiveEntity\Models\Cms\CmsUser;

class ActiveEntityTest extends \PHPUnit_Framework_TestCase
{
    private $aem = null;

    public function setUp()
    {
        $cacheImpl = new \Doctrine\Common\Cache\ArrayCache;
        $driverImpl = new \Doctrine\ORM\Mapping\Driver\XmlDriver(__DIR__ . "/../../Models/Cms/mappings");

        $config = new \Doctrine\ORM\Configuration();
        $config->setMetadataCacheImpl($cacheImpl);
        $config->setQueryCacheImpl($cacheImpl);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setProxyNamespace('DoctrineExtensions\ActiveEntity\Tests\Proxies');
        $config->setMetadataDriverImpl($driverImpl);

        $conn = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        $this->aem = \DoctrineExtensions\ActiveEntity\ActiveEntityManager::create($conn, $config);
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->aem);
        $schemaTool->createSchema($this->aem->getMetadataFactory()->getAllMetadata());

        \DoctrineExtensions\ActiveEntity\ActiveEntity::setEntityManager($this->aem);
    }

    public function testSave()
    {
        $user = new CmsUser();
        $user->username = "Foo";
        $user->save();

        $this->aem->flush();
    }

    public function testUpdate()
    {
        $user = new CmsUser();
        $user->username = "Foo";
        $user->save();

        $this->aem->flush();
        $this->aem->clear();

        $freshUser = CmsUser::find($user->get('id'));
        $this->assertEquals('Foo', $user->username);
        $user->username = 'Bar';

        $this->aem->flush();

        $freshUser = CmsUser::find($user->get('id'));
        $this->assertEquals('Bar', $user->username);
    }
}