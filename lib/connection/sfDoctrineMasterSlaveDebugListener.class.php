<?php

/**
 * Emulates a read-only database connection by throwing exceptions.
 * 
 * @package    sfDoctrineMasterSlavePlugin
 * @subpackage connection
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfDoctrineMasterSlaveDebugListener extends Doctrine_EventListener
{
  protected
    $masterConnection = null;

  /**
   * Constructor.
   * 
   * @param Doctrine_Connection|sfCallable $masterConnection The master connection or an sfCallable that returns the master connection
   * 
   * @throws InvalidArgumentException If the argument is neither a connection nor sfCallable
   */
  public function __construct($masterConnection)
  {
    if (!$masterConnection instanceof Doctrine_Connection && !$masterConnection instanceof sfCallable)
    {
      throw new InvalidArgumentException('Argument must be either a connection object or sfCallable that returns a connection object.');
    }

    $this->masterConnection = $masterConnection;
  }

  /**
   * Checks that the supplied connection is the master connection.
   * 
   * @param Doctrine_Connection $conn A connection to check
   * 
   * @throws LogicException If the connection is not the master connection
   */
  public function checkConnection(Doctrine_Connection $conn, $query = null)
  {
    if ($this->masterConnection instanceof sfCallable)
    {
      $this->masterConnection = $this->masterConnection->call();
    }

    if ($this->masterConnection !== $conn)
    {
      throw new LogicException('Cannot run this query on a read-only connection: '.$query);
    }
  }

  public function preExec(Doctrine_Event $event)
  {
    if (0 !== stripos(trim($event->getQuery()), 'set'))
    {
      $this->checkConnection($event->getInvoker(), $event->getQuery());
    }
  }

  public function prePrepare(Doctrine_Event $event)
  {
    if (!preg_match('/^(select|set)/i', trim($event->getQuery())))
    {
      $this->checkConnection($event->getInvoker(), $event->getQuery());
    }
  }

  public function preTransactionBegin(Doctrine_Event $event)
  {
    $this->checkConnection($event->getInvoker()->getConnection(), 'BEGIN TRANSACTION');
  }

  public function preTransactionCommit(Doctrine_Event $event)
  {
    $this->checkConnection($event->getInvoker()->getConnection(), 'COMMIT');
  }

  public function preTransactionRollback(Doctrine_Event $event)
  {
    $this->checkConnection($event->getInvoker()->getConnection(), 'ROLLBACK');
  }
}
