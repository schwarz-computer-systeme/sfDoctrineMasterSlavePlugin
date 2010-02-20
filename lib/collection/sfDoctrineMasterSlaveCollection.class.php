<?php

/**
 * Overrides certain methods that require a master connection.
 * 
 * @package    sfDoctrineMasterSlavePlugin
 * @subpackage collection
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfDoctrineMasterSlaveCollection extends Doctrine_Collection
{
  /**
   * Saves the current collection of records.
   * 
   * Forces a master connection.
   * 
   * @see Doctrine_Collection
   */
  public function save(Doctrine_Connection $conn = null, $processDiff = true)
  {
    $conn = ProjectConfiguration::getActive()->getMasterConnection($conn ? $conn : $this->getTable()->getConnection());

    return parent::save($conn, $processDiff);
  }

  /**
   * Deletes the current collection of records.
   * 
   * Forces a master connection.
   * 
   * @see Doctrine_Collection
   */
  public function delete(Doctrine_Connection $conn = null, $clearColl = true)
  {
    $conn = ProjectConfiguration::getActive()->getMasterConnection($conn ? $conn : $this->getTable()->getConnection());

    return parent::delete($conn, $clearColl);
  }
}
