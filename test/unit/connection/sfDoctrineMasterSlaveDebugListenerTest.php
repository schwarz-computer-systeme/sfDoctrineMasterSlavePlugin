<?php

/**
 * sfDoctrineMasterSlaveDebugListener tests.
 */
include dirname(__FILE__).'/../../../../../test/bootstrap/unit.php';

$t = new lime_test(11);

$manager = Doctrine_Manager::getInstance();
$pdo = new PDO('sqlite://'.sfConfig::get('sf_cache_dir').'/master_slave_test.sqlite');
$master = $manager->openConnection($pdo, 'master');
$slave = $manager->openConnection($pdo, 'slave');

// ->__construct()
$t->diag('->__construct()');

try
{
  $listener = new sfDoctrineMasterSlaveDebugListener(new sfCallable(null));
  $t->pass('->__construct() accepts a sfCallable');
}
catch (Exception $e)
{
  $t->fail('->__construct() accepts a sfCallable');
  $t->diag('    '.$e->getMessage());
}

// ->checkConnection()
$t->diag('->checkConnection()');

$listener = new sfDoctrineMasterSlaveDebugListener($master);
try
{
  $listener->checkConnection($master);
  $t->pass('->checkConnection() does not throw an exception when passed the master connection');
}
catch (Exception $e)
{
  $t->fail('->checkConnection() does not throw an exception when passed the master connection');
  $t->diag('    '.$e->getMessage());
}
try
{
  $listener->checkConnection($slave);
  $t->fail('->checkConnection() throws an exception when passed a slave connection');
}
catch (Exception $e)
{
  $t->pass('->checkConnection() throws an exception when passed a slave connection');
}

// ->preExec()
$t->diag('->preExec()');

try
{
  $listener->preExec(new Doctrine_Event($slave, Doctrine_Event::CONN_EXEC, 'SET NAMES ?', array('UTF-8')));
  $t->pass('->preExec() allows SET queries to a slave connection');
}
catch (Exception $e)
{
  $t->fail('->preExec() allows SET queries to slave connections');
  $t->diag('    '.$e->getMessage());
}
try
{
  $listener->preExec(new Doctrine_Event($slave, Doctrine_Event::CONN_EXEC, 'UPDATE foo SET bar=?', array('test')));
  $t->fail('->preExec() disallows UPDATE queries to slave connections');
}
catch (Exception $e)
{
  $t->pass('->preExec() disallows UPDATE queries to slave connections');
}

// ->prePrepare()
$t->diag('->prePrepare()');

try
{
  $listener->prePrepare(new Doctrine_Event($slave, Doctrine_Event::CONN_PREPARE, 'SELECT * FROM foo'));
  $t->pass('->prePrepare() allows SELECT queries to slave connections');
}
catch (Exception $e)
{
  $t->fail('->prePrepare() allows SELECT queries to slave connections');
  $t->diag('    '.$e->getMessage());
}
try
{
  $listener->prePrepare(new Doctrine_Event($slave, Doctrine_Event::CONN_PREPARE, 'SET NAMES ?', array('UTF-8')));
  $t->pass('->prePrepare() allows SET queries to slave connections');
}
catch (Exception $e)
{
  $t->fail('->prePrepare() allows SET queries to slave connections');
  $t->diag('    '.$e->getMessage());
}
try
{
  $listener->preExec(new Doctrine_Event($slave, Doctrine_Event::CONN_PREPARE, 'UPDATE foo SET bar=?', array('test')));
  $t->fail('->prePrepare() disallows UPDATE queries to slave connections');
}
catch (Exception $e)
{
  $t->pass('->prePrepare() disallows UPDATE queries to slave connections');
}

// ->preTransactionBegin()
$t->diag('->preTransactionBegin()');

try
{
  $listener->preTransactionBegin(new Doctrine_Event(new Doctrine_Transaction($slave), Doctrine_Event::TX_BEGIN));
  $t->fail('->preTransactionBegin() disallows BEGIN TRANSACTION queries to slave connections');
}
catch (Exception $e)
{
  $t->pass('->preTransactionBegin() disallows BEGIN TRANSACTION queries to slave connections');
}

// ->preTransactionCommit()
$t->diag('->preTransactionCommit()');

try
{
  $listener->preTransactionCommit(new Doctrine_Event(new Doctrine_Transaction($slave), Doctrine_Event::TX_COMMIT));
  $t->fail('->preTransactionCommit() disallows COMMIT queries to slave connections');
}
catch (Exception $e)
{
  $t->pass('->preTransactionCommit() disallows COMMIT queries to slave connections');
}

// ->preTransactionRollback()
$t->diag('->preTransactionRollback()');

try
{
  $listener->preTransactionRollback(new Doctrine_Event(new Doctrine_Transaction($slave), Doctrine_Event::TX_ROLLBACK));
  $t->fail('->preTransactionRollback() disallows ROLLBACK queries to slave connections');
}
catch (Exception $e)
{
  $t->pass('->preTransactionRollback() disallows ROLLBACK queries to slave connections');
}
