<?php

/**
 * sfDoctrineMasterSlaveConnectionManager tests.
 */
include dirname(__FILE__).'/../../../../../test/bootstrap/unit.php';

$t = new lime_test(19);

$manager = Doctrine_Manager::getInstance();
$pdo = new PDO('sqlite://'.sfConfig::get('sf_cache_dir').'/master_slave_test.sqlite');

$conn1 = $manager->openConnection($pdo, 'doctrine');
$conn2 = $manager->openConnection($pdo, 'slave');
$conn3 = $manager->openConnection($pdo, 'master');

class sfDoctrineMasterSlaveConnectionManagerTest extends sfDoctrineMasterSlaveConnectionManager
{
  public function getConnections()
  {
    return $this->connections;
  }

  public function setConnections(array $connections)
  {
    $this->connections = $connections;
  }
}

$connectionManager = new sfDoctrineMasterSlaveConnectionManagerTest($configuration->getEventDispatcher());

// ->register()
$t->diag('->register()');

$connectionManager->register($conn1);
$connections = $connectionManager->getConnections();
$t->is($connections['default']['master'], 'doctrine', '->register() marks the first connection as master');
$t->is(Doctrine_Manager::connection()->getName(), 'doctrine', '->register() sets the master as the current connection');

$connectionManager->register($conn2);
$connections = $connectionManager->getConnections();
$t->is_deeply($connections['default']['slaves'], array('slave'), '->register() saves additional connections as slaves');
$t->is(Doctrine_Manager::connection()->getName(), 'doctrine', '->register() sets the master as the current connection');

$connectionManager->register($conn3, null, true);
$connections = $connectionManager->getConnections();
$t->is($connections['default']['master'], 'master', '->register() sets a connection as master');
$t->is_deeply($connections['default']['slaves'], array('slave', 'doctrine'), '->register() fixes master assumptions');
$t->is(Doctrine_Manager::connection()->getName(), 'master', '->register() sets the master as the current connection');

// ->getMasterConnection()
$t->diag('->getMasterConnection()');

$connectionManager->setConnections(array(
  'default' => array(
    'master' => 'master',
    'slaves' => array('slave', 'doctrine'),
)));
$t->is($connectionManager->getMasterConnection()->getName(), 'master', '->getMasterConnection() returns the master connection');
$t->is($connectionManager->getMasterConnection('default')->getName(), 'master', '->getMasterConnection() accepts a group name');
$t->is($connectionManager->getMasterConnection($conn3)->getName(), 'master', '->getMasterConnection() accepts a master connection');
$t->is($connectionManager->getMasterConnection($conn2)->getName(), 'master', '->getMasterConnection() accepts a slave connection');

$connectionManager->setConnections(array());
try
{
  $connectionManager->getMasterConnection('default');
  $t->fail('->getMasterConnection() throws an exception if there is not master connection');
}
catch (Exception $e)
{
  $t->pass('->getMasterConnection() throws an exception if there is not master connection');
}

$connectionManager->setConnections(array(
  'foo' => array('master' => 'master'),
  'bar' => array('master' => 'doctrine'),
));
$t->is($connectionManager->getMasterConnection()->getName(), 'master', '->getMasterConnection() defaults to using the first group registered');

// ->getSlaveConnection()
$t->diag('->getSlaveConnection()');

$connectionManager->setConnections(array(
  'default' => array(
    'master' => 'master',
    'slaves' => array('slave', 'doctrine'),
)));
$slave = $connectionManager->getSlaveConnection('default');
$connections = $connectionManager->getConnections();
$t->is($slave->getName(), $connections['default']['current_slave'], '->getSlaveConnection() returns a slave');
$t->ok(in_array($connections['default']['current_slave'], $connections['default']['slaves']), '->getSlaveConnection() returns a slave');

$slave = $connectionManager->getSlaveConnection();
$connections = $connectionManager->getConnections();
$t->is($slave->getName(), $connections['default']['current_slave'], '->getSlaveConnection() defaults to the default group');

$conn3->beginTransaction();
$t->is($connectionManager->getSlaveConnection('default')->getName(), 'master', '->getSlaveConnection() returns the master if a transaction is open');
$conn3->rollback();
$t->is($connectionManager->getSlaveConnection('default')->getName(), $slave->getName(), '->getSlaveConnection() returns the slave once a transaction is closed');

$connectionManager->setConnections(array('default' => array(
  'master' => 'master',
  'slaves' => array(),
)));
$t->is($connectionManager->getSlaveConnection('default')->getName(), 'master', '->getSlaveConnection() returns the master if no slaves are registered');
