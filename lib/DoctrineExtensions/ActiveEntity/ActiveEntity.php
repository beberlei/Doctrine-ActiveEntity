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

/**
 * Abstract class to extend your entities from to give a layer which gives you
 * the functionality magically offered by Doctrine_Record in Doctrine 1. This
 * class is not usually recommended to use as it is adds another layer of overhead
 * and magic. It is meant as a layer for backwards compatability with Doctrine 1.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
abstract class ActiveEntity implements \ArrayAccess
{
    const STATE_UNLOCKED = 0;
    const STATE_LOCKED = 1;

    private $_state = self::STATE_UNLOCKED;

    /**
     * @var array
     */
    static private $_lockedObjects = array();

    /**
     * @var ActiveEntityManager
     */
    private static $entityManager;

    /**
     * @var array
     */
    private $_data = array();

    /**
     *
     * @param ActiveEntityManager $em
     */
    public static function setEntityManager(ActiveEntityManager $em)
    {
        self::$entityManager = $em;
    }

    /**
     * @return ActiveEntityManager
     */
    private static function getEntityManager()
    {
        if (!self::$entityManager === null) {
            throw ActiveEntityException::noEntityManager();
        }
        return self::$entityManager;
    }

    public function save()
    {
        self::getEntityManager()->persist($this);
    }

    public function delete()
    {
        self::getEntityManager()->remove($this);
    }

    final public function __get($key)
    {
        return $this->get($key);
    }

    final public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    final public function __isset($key)
    {
        return isset($this->_data[$key]);
    }

    public function __unset($name)
    {
        unset($this->_data[$name]);
    }

    final public function offsetExists($key)
    {
        return $this->__isset($key);
    }

    final public function offsetGet($key)
    {
        return $this->get($key);
    }

    final public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    final public function offsetUnset($key)
    {
        $this->__unset($key);
    }

    public function get($key)
    {
        $methodName = 'get' . ucfirst($key);

        return (method_exists($this, $methodName)) ? $this->$methodName() : $this->_get($key);
    }

    final protected function _get($key)
    {
        if (!isset($this->_data[$key])) {
            return null;
        }
        return $this->_data[$key];
    }

    public function set($key, $value)
    {
        $methodName = 'set' . ucfirst($key);

        if (\method_exists($this, $methodName)) {
            $this->$methodName($value);
        } else {
            $this->_set($key, $value);
        }
    }

    protected function _set($key, $value)
    {
        $this->_data[$key] = $value;
    }

    public function fromArray(array $array, $obj = null)
    {
        if ($obj === null) {
            $obj = $this;
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->fromArray($value, $obj->$key);
            } else {
                $obj->set($key, $value);
            }
        }
    }

    public function toArray($obj = null)
    {
        if ($obj === null) {
            $obj = $this;
        }

        $array = array();

        if ($obj instanceof \DoctrineExtensions\ActiveEntity\ActiveEntity) {
            if ($obj->_state === self::STATE_LOCKED) {
                return array();
            }

            $originalState = $obj->_state;

            foreach ($this->obtainMetadata()->reflFields as $name => $reflField) {
                $value = $this->$name;

                if ($value instanceof \DoctrineExtensions\ActiveEntity\ActiveEntity) {
                    $obj->_state = self::STATE_LOCKED;

                    if ($result = $value->toArray()) {
                        $array[$name] = $result;
                    }
                } else if ($value instanceof \Doctrine\Common\Collections\Collection) {
                    $obj->_state = self::STATE_LOCKED;

                    $array[$name] = $this->toArray($value);
                } else {
                    $array[$name] = $value;
                }
            }

            $obj->_state = $originalState;
        } else if ($obj instanceof \Doctrine\Common\Collections\Collection) {
            foreach ($obj as $key => $value) {
                if (in_array(spl_object_hash($obj), self::$_lockedObjects)) {
                    $array[$key] = $obj;
                    continue;
                }
                self::$_lockedObjects[] = spl_object_hash($obj);
                if ($result = $this->toArray($value)) {
                    $array[$key] = $result;
                }
            }
        }

        self::$_lockedObjects[] = array();
        return $array;
    }

    public function __toString()
    {
        return var_export($this->obtainIdentifier(), true);
    }

    public function obtainMetadata()
    {
        return self::getEntityManager()->getClassMetadata(get_class($this));
    }

    public function obtainIdentifier()
    {
        return self::getEntityManager()->getUnitOfWork()->getEntityIdentifier($this);
    }

    public function exists()
    {
        $id = $this->obtainIdentifier();

        return (self::getEntityManager()->contains($this) && !empty($id)) ? true : false;
    }

    public function __call($method, $arguments)
    {
        $func = substr($method, 0, 3);
        $fieldName = substr($method, 3, strlen($method));
        $fieldName = lcfirst($fieldName);

        if ($func == 'get') {
            return $this->$fieldName;
        } else if ($func == 'set') {
            $this->$fieldName = $arguments[0];
        } else if ($func == 'has') {
            return $this->__isset($fieldName);
        } else {
            throw new \BadMethodCallException('Method ' . $method . ' does not exist on ActiveEntity ' . get_class($this));
        }
    }

    public static function __callStatic($method, $arguments)
    {
        return call_user_func_array(
            array(self::getEntityManager()->getRepository(get_called_class()), $method),
            $arguments
        );
    }
}