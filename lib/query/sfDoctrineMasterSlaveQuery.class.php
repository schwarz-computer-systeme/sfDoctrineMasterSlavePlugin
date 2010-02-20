<?php

/**
 * Smartly uses either the master of slaves database connection.
 * 
 * @package    sfDoctrineMasterSlavePlugin
 * @subpackage query
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfDoctrineMasterSlaveQuery extends Doctrine_Query
{
  /**
   * Pre-query hook.
   * 
   * Sets the current query's connection based on what type of query is being run.
   * 
   * @see Doctrine_Query_Abstract
   */
  public function preQuery()
  {
    $method = Doctrine_Query::SELECT == $this->getType() ? 'getSlaveConnection' : 'getMasterConnection';
    $this->setConnection(ProjectConfiguration::getActive()->$method($this->getConnection()));
  }
}
