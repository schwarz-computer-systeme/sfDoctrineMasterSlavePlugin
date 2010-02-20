<?php

/**
 * Manages Doctrine master and slave connections.
 * 
 * @package    sfDoctrineMasterSlavePlugin
 * @subpackage connection
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfDoctrineMasterSlaveConnectionManager
{
  const
    DEFAULT_GROUP = 'default';

  protected
    $dispatcher  = null,
    $connections = array();

  /**
   * Constructor.
   * 
   * @param sfEventDispatcher $dispatcher The event dispatcher
   */
  public function __construct(sfEventDispatcher $dispatcher)
  {
    $this->dispatcher = $dispatcher;
  }

  /**
   * Registers a connection with the current manager.
   * 
   * @param Doctrine_Connection $conn     A Doctrine connection object
   * @param string              $group    A connection group name
   * @param boolean             $isMaster Whether this connection is a master
   */
  public function register(Doctrine_Connection $conn, $group = null, $isMaster = null)
  {
    if (null === $group)
    {
      $group = $this->getDefaultGroup();
    }

    if (!isset($this->connections[$group]))
    {
      // initialize group
      $this->connections[$group] = array(
        'master' => null,
        'slaves' => array(),
      );
    }

    if (null === $isMaster)
    {
      // assume this is the master if another hasn't been setup yet or if the name includes "master"
      $isMaster = !isset($this->connections[$group]['master']) || false !== strpos($conn->getName(), 'master');
    }

    if ($isMaster)
    {
      // don't unregister any assumed master connections
      if (isset($this->connections[$group]['master']))
      {
        $this->connections[$group]['slaves'][] = $this->connections[$group]['master'];
      }

      $this->connections[$group]['master'] = $conn->getName();
    }
    else
    {
      $this->connections[$group]['slaves'][] = $conn->getName();
    }

    $this->resetCurrentConnection();
  }

  /**
   * Returns the master connection for a certain group.
   * 
   * @param string|Doctrine_Connection $group A Doctrine connection or connection group name
   * 
   * @return Doctrine_Connection A Doctrine connection object
   * 
   * @throws InvalidArgumentException If the group doesn't exists or doesn't have a master connection
   */
  public function getMasterConnection($group = null)
  {
    if (null === $group)
    {
      $group = $this->getDefaultGroup();
    }

    if ($group instanceof Doctrine_Connection)
    {
      $group = $this->getConnectionGroup($group);
    }

    if (!isset($this->connections[$group]['master']))
    {
      throw new InvalidArgumentException(sprintf('There is no master connection for the "%s" group', $group));
    }

    return $this->getDoctrineManager()->getConnection($this->connections[$group]['master']);
  }

  /**
   * Returns a slave connection for a certain group.
   * 
   * @param string $group A connection group name
   * 
   * @return Doctrine_Connection A Doctrine connection object
   * 
   * @throws InvalidArgumentException If the group doesn't exists or doesn't have a slave connection
   */
  public function getSlaveConnection($group = null)
  {
    if (null === $group)
    {
      $group = $this->getDefaultGroup();
    }

    // use the master connection if we're in a transaction
    $master = $this->getMasterConnection($group);
    if ($master->getTransactionLevel())
    {
      return $master;
    }

    if (!isset($this->connections[$group]['current_slave']))
    {
      // select one slave
      $event = $this->dispatcher->notifyUntil(new sfEvent($this, 'doctrine.select_slave', array(
        'group'  => $group,
        'master' => $this->connections[$group]['master'],
        'slaves' => $this->connections[$group]['slaves'],
      )));

      if ($event->isProcessed())
      {
        $this->connections[$group]['current_slave'] = $event->getReturnValue();
      }
      else if ($slaves = $this->connections[$group]['slaves'])
      {
        $this->connections[$group]['current_slave'] = $slaves[array_rand($slaves)];
      }
      else
      {
        $this->connections[$group]['current_slave'] = $master->getName();
      }
    }

    return $this->getDoctrineManager()->getConnection($this->connections[$group]['current_slave']);
  }

  // protected

  /**
   * Returns the name of the current default group.
   * 
   * @return string A connection group name
   */
  protected function getDefaultGroup()
  {
    if (isset($this->connections[self::DEFAULT_GROUP]) || !$this->connections)
    {
      return self::DEFAULT_GROUP;
    }
    else
    {
      return key($this->connections);
    }
  }

  /**
   * Resets Doctrine's current connection to the default group's master connection.
   */
  protected function resetCurrentConnection()
  {
    if (isset($this->connections[$this->getDefaultGroup()]['master']))
    {
      $this->getDoctrineManager()->setCurrentConnection($this->connections[$this->getDefaultGroup()]['master']);
    }
  }

  /**
   * Returns a group name based on connection name.
   * 
   * @param Doctrine_Connection $conn A Doctrine connection
   * 
   * @return string A connection group name
   * 
   * @throws InvalidArgumentException If the group name could not be determined
   */
  protected function getConnectionGroup(Doctrine_Connection $conn)
  {
    $name = $conn->getName();

    foreach ($this->connections as $group => $connections)
    {
      if (
        (isset($connections['master']) && $name == $connections['master'])
        ||
        (isset($connections['slaves']) && in_array($name, $connections['slaves']))
      )
      {
        return $group;
      }
    }

    throw new InvalidArgumentException(sprintf('Could not determine a group for the "%s" connection'));
  }

  /**
   * A convenience method for returning the current Doctrine manager.
   * 
   * @return Doctrine_Manager The current Doctrine manager
   */
  protected function getDoctrineManager()
  {
    return Doctrine_Manager::getInstance();
  }
}
