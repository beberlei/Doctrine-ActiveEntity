<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace DoctrineExtensions\ActiveEntity;

use DoctrineExtensions\ActiveEntity\Mapping\ActiveClassMetadataFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\Common\EventManager;
use Doctrine\ORM\ORMException;

/**
 * Active EntityManager
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author      Jonathan Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class ActiveEntityManager extends \Doctrine\ORM\EntityManager
{
    /**
     * @param Connection $conn
     * @param Configuration $config
     * @param EventManager $eventManager
     */
    protected function __construct(Connection $conn, Configuration $config, EventManager $eventManager)
    {
        parent::__construct($conn, $config, $eventManager);

        $metadataFactory = new ActiveClassMetadataFactory($this);
        $metadataFactory->setCacheDriver($this->getConfiguration()->getMetadataCacheImpl());
        
        // now this is the only hack required to get it work:
        $reflProperty = new \ReflectionProperty('Doctrine\ORM\EntityManager', 'metadataFactory');
        $reflProperty->setAccessible(true);
        $reflProperty->setValue($this, $metadataFactory);
    }

    /**
     * Factory method to create EntityManager instances.
     *
     * @param mixed $conn An array with the connection parameters or an existing
     *      Connection instance.
     * @param Configuration $config The Configuration instance to use.
     * @param EventManager $eventManager The EventManager instance to use.
     * @return EntityManager The created EntityManager.
     */
    public static function create($conn, Configuration $config, EventManager $eventManager = null)
    {
        if (!$config->getMetadataDriverImpl()) {
            throw ORMException::missingMappingDriverImpl();
        }

        if (is_array($conn)) {
            $conn = \Doctrine\DBAL\DriverManager::getConnection($conn, $config, ($eventManager ?: new EventManager()));
        } else if ($conn instanceof Connection) {
            if ($eventManager !== null && $conn->getEventManager() !== $eventManager) {
                 throw ORMException::mismatchedEventManager();
            }
        } else {
            throw new \InvalidArgumentException("Invalid argument: " . $conn);
        }

        return new ActiveEntityManager($conn, $config, $conn->getEventManager());
    }
}