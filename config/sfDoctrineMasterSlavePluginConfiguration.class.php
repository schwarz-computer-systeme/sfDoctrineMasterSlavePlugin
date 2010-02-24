<?php

/**
 * Plugin configuration.
 * 
 * Adds the following parameters to each Doctrine database:
 * 
 *  * is_master: Whether the database is a master connection
 *  * group:     Groups a connection with others
 * 
 * @package    sfDoctrineMasterSlavePlugin
 * @subpackage config
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfDoctrineMasterSlavePluginConfiguration extends sfPluginConfiguration
{
  protected
    $connectionManager = null;

  /**
   * @see sfPluginConfiguration
   */
  public function configure()
  {
    $a = array_search('sfDoctrineMasterSlavePlugin', $this->configuration->getPlugins());
    $b = array_search('sfDoctrinePlugin', $this->configuration->getPlugins());

    if ($a > $b)
    {
      throw new LogicException('The sfDoctrineMasterSlavePlugin plugin must be enabled before sfDoctrinePlugin');
    }
  }

  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    $this->connectionManager = new sfDoctrineMasterSlaveConnectionManager($this->dispatcher);

    $this->dispatcher->connect('configuration.method_not_found', array($this, 'listenForConfigurationMethodNotFound'));
    $this->dispatcher->connect('doctrine.configure', array($this, 'configureDoctrine'));
    $this->dispatcher->connect('doctrine.configure_connection', array($this, 'configureDoctrineConnection'));
    $this->dispatcher->connect('doctrine.filter_model_builder_options', array($this, 'filterBuilderOptions'));
  }

  /**
   * Returns the current connection manager.
   * 
   * @return sfDoctrineMasterSlaveConnectionManager
   */
  public function getConnectionManager()
  {
    return $this->connectionManager;
  }

  /**
   * Listens for the configuration.method_not_found event.
   * 
   * Adds accessors for master and slave connections to the configuration object.
   * 
   * @param sfEvent $event A symfony event
   * 
   * @return boolean Returns true if the event was processed
   */
  public function listenForConfigurationMethodNotFound(sfEvent $event)
  {
    switch ($event['method'])
    {
      case 'getMasterConnection':
      case 'getSlaveConnection':
        $event->setReturnValue(call_user_func_array(array($this->connectionManager, $event['method']), $event['arguments']));
        return true;
    }

    return false;
  }

  /**
   * Configures Doctrine.
   * 
   * Adds custom query and collection classes if none are setup already.
   * 
   * @param sfEvent $event A symfony event
   */
  public function configureDoctrine(sfEvent $event)
  {
    $manager = $event->getSubject();

    if ('Doctrine_Query' == $manager->getAttribute(Doctrine_Core::ATTR_QUERY_CLASS))
    {
      $manager->setAttribute(Doctrine_Core::ATTR_QUERY_CLASS, 'sfDoctrineMasterSlaveQuery');
    }

    if ('Doctrine_Collection' == $manager->getAttribute(Doctrine_Core::ATTR_COLLECTION_CLASS))
    {
      $manager->setAttribute(Doctrine_Core::ATTR_COLLECTION_CLASS, 'sfDoctrineMasterSlaveCollection');
    }
  }

  /**
   * Configures a Doctrine connection.
   * 
   * Registers each connection with the current master/slave connection manager.
   * 
   * @param sfEvent $event A symfony event
   */
  public function configureDoctrineConnection(sfEvent $event)
  {
    $database = $event['database'];
    $conn = $event['connection'];

    $this->connectionManager->register($conn, $database->getParameter('group'), $database->getParameter('is_master'));

    if (sfConfig::get('sf_debug') || sfConfig::get('sf_test'))
    {
      $callable = new sfCallable(array($this->connectionManager, 'getMasterConnection'));
      $conn->addListener(new sfDoctrineMasterSlaveDebugListener($callable), 'slave_emulator');
    }
  }

  /**
   * Filters Doctrine builder options.
   * 
   * @param sfEvent $event   A symfony event
   * @param array   $options An array of builder options
   * 
   * @return array The filtered array of builder options
   */
  public function filterBuilderOptions(sfEvent $event, $options)
  {
    if ('sfDoctrineRecord' == $options['baseClassName'])
    {
      $options['baseClassName'] = 'sfDoctrineMasterSlaveRecord';
    }

    return $options;
  }
}
