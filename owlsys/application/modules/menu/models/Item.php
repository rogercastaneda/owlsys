<?php
/**
 * Copyright 2012 Roger Castañeda
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 * @package menu
 * @subpackage models
 * @author roger castañeda <rogercastanedag@gmail.com>
 * @version 1
 */
class menu_Model_Item extends Zend_Db_Table_Abstract
{
  protected $_name = 'menu_item';
  
  protected $_dependentTables = array( 'menu_Model_menu', 'System_Model_Widgetdetail' );
  
  protected $_referenceMap = array(
      'refMenu' => array(
          'columns' 			=> array('menu_id'),
          'refTableClass' 	=> 'menu_Model_Menu',
          'refColumns'		=> array('id'),
      ),
      'refParent' => array(
          'columns' 			=> array('parent_id'),
          'refTableClass' 	=> 'menu_Model_Item',
          'refColumns'		=> array('id'),
      ),
      'refResource' => array(
          'columns' 			=> array('resource_id'),
          'refTableClass' 	=> 'Acl_Model_Resource',
          'refColumns'		=> array('id'),
      ),
  );
  
  /**
   * renames the table by adding the prefix defined in the global configuration parameters
  */
  function __construct() {
    $this->_name = Zend_Registry::get('tablePrefix').$this->_name;
    parent::__construct();
  }
  
  /**
   * Returns a recordset filter by menu order by parent_id and ordering
   * @param Zend_Db_Table_Row_Abstract $menu
   * @return Zend_Db_Table_Rowset_Abstract
   */
  public function getListByMenu( $menu )
  {
    $prefix = Zend_Registry::get('tablePrefix');
    $items = array();
    /* @var $cache Zend_Cache_Core|Zend_Cache_Frontend */
    $cache = Zend_Registry::get('cache');
    $cacheId = 'menuItem_getListBymenu_'.$menu->id;
    if ( $cache->test($cacheId) ) {
        $items = $cache->load($cacheId);
    } else {
      $select = $this->select()
        ->setIntegrityCheck(false)
        ->from( array('it'=> $this->_name), array('id', 'ordering', 'icon', 'wtype', 'title', 'route', 'isPublished', 'description') ) # item
        ->joinLeft( array('itp' => $prefix.'menu_item'), 'it.parent_id = itp.id', array('title AS parent_title', 'id AS parent_id') ) # item parent
        ->joinInner( array('rs' => $prefix.'acl_resource'), 'rs.id = it.resource_id', array('module', 'controller', 'actioncontroller') ) # resource
        ->where("it.menu_id = ?", $menu->id, Zend_Db::INT_TYPE)
        ->order('it.parent_id')
        ->order('it.ordering ASC')
      ;
      $items = $this->fetchAll($select);
        $cache->save($items, $cacheId);
    }
    return $items;
  }
  
  /**
   * returns the last position of a contact list according to the category they belong in ascending order
   * @param Zend_Db_Table_Row_Abstract $menuItem
   * @return number
   */
  public function getLastPosition( $menuItem )
  {
    $select = $select = $this->select()
    ->from($this->_name, 'ordering');
    if ( $menuItem->parent_id > 0 ) {
      $select
        ->where( 'menu_id = ?', $menuItem->menu_id, Zend_Db::INT_TYPE )
        ->where( 'parent_id = ?', $menuItem->parent_id, Zend_Db::INT_TYPE )
        ->order( 'ordering DESC' );
    } else {
      $select
        ->where( 'menu_id = ?', $menuItem->menu_id, Zend_Db::INT_TYPE )
        ->order( 'ordering DESC' );
    }
    $row = $this->fetchRow( $select );
    if ( $row )
      return $row->ordering;
    return 0;
  }
  
  /**
   * Moves the record position one above
   * @param Zend_Db_Table_Row_Abstract $menuItem
   * @return boolean
   */
  function moveUp( $menuItem )
  {
    $select = $this->select()
    ->order('ordering DESC')
    ->where("ordering < ?", $menuItem->ordering, Zend_Db::INT_TYPE)
    ->where("menu_id = ?", $menuItem->menu_id, Zend_Db::INT_TYPE);
    $previousItem = $this->fetchRow($select);
    if ( $previousItem ) {
      $previousPosition = $previousItem->ordering;
      $previousItem->ordering = $menuItem->ordering;
      $previousItem->save();
      $menuItem->ordering = $previousPosition;
    }
  }
  
  /**
   * Moves the record position one down
   * @param Zend_Db_Table_Row_Abstract $menuItem
   * @return boolean
   */
  function moveDown( $menuItem )
  {
    $select = $this->select()
      ->order('ordering ASC')
      ->where("ordering > ?", $menuItem->ordering, Zend_Db::INT_TYPE)
      ->where("menu_id = ?", $menuItem->menu_id, Zend_Db::INT_TYPE);
    $nextItem = $this->fetchRow($select);
    if ( $nextItem ) {
      $nextPosition = $nextItem->ordering;
      $nextItem->ordering = $menuItem->ordering;
      $nextItem->save();
      $menuItem->ordering = $nextPosition;
    }
  }
  
  /**
   * Retorna menu items hijos
   * @param Zend_Db_Table_Row_Abstract $menuItem
   * @return Zend_Db_Table_Rowset_Abstract
   */
  public function getChildren($menuItem)
  {
    /* @var $cache Zend_Cache_Core|Zend_Cache_Frontend */
    $cache = Zend_Registry::get('cache');
    $cacheId = 'menu_getChildren_'.$menuItem->id;
    if ( $cache->test($cacheId) ) {
      $rows = $cache->load($cacheId);
    } else {
      $prefix = Zend_Registry::get('tablePrefix');
      $select = $this->select()
        ->setIntegrityCheck(false)
        ->from( array('it'=>$this->_name) )
        ->joinInner( array('rs' => $prefix.'acl_resource'), 'rs.id = it.resource_id', array('module', 'controller', 'actioncontroller') ) # resource
        ->where('it.parent_id=?', $menuItem->id)
        ->where('it.isVisible=1')
      ;
      $rows = $this->fetchAll($select);
      $cache->save($rows, $cacheId);
    }
    return $rows;
  }
  
  /**
   * Retorna los menu items de un menu especifico. (el parent_id=0 limita a que se busque en menuitems que no son hijos)
   * @param Zend_Db_Table_Row_Abstract $menu
   * @return Zend_Db_Table_Rowset_Abstract
   */
  public function getAllByMenu($menu)
  {
    /* @var $cache Zend_Cache_Core|Zend_Cache_Frontend */
    $cache = Zend_Registry::get('cache');
    $cacheId = 'menu_getAllByMenu_'.$menu->id;
    if ( $cache->test($cacheId) ) {
      $rows = $cache->load($cacheId);
    } else {
      $prefix = Zend_Registry::get('tablePrefix');
      $select = $this->select()
        ->setIntegrityCheck(false)
        ->from( array('it'=>$this->_name) )
        ->joinInner( array('rs' => $prefix.'acl_resource'), 'rs.id = it.resource_id', array('module', 'controller', 'actioncontroller') ) # resource
        ->where('IFNULL(it.parent_id,0)=0')
        ->where('it.menu_id=?', $menu->id, Zend_Db::INT_TYPE)
      ;
      //Zend_Debug::dump($select->__toString());
      $rows = $this->fetchAll($select);
      $cache->save($rows, $cacheId);
    }
    return $rows;
  }
  
  /**
   *
   * @return Zend_Db_Table_Rowset_Abstract
   */
  public function getRegisteredRoutes()
  {
    /* @var $cache Zend_Cache_Core|Zend_Cache_Frontend */
    $cache = Zend_Registry::get('cache');
    $cacheId = 'menu_getRegisteredRoutes';
    if ( $cache->test($cacheId) ) {
      $rows = $cache->load($cacheId);
    } else {
      $prefix = Zend_Registry::get('tablePrefix');
      $select = $this->select()
        ->setIntegrityCheck(false)
        ->from( array('it'=>$this->_name), array('route') )
        ->joinInner( array('rs' => $prefix.'acl_resource'), 'rs.id = it.resource_id', array('module', 'controller', 'actioncontroller') ) # resource
        ->where('it.isPublished=1')
      ;
      //Zend_Debug::dump($select->__toString());
      $rows = $this->fetchAll($select);
      $cache->save($rows, $cacheId);
    }
    return $rows;
  }
  
  /**
   * Remove a menu item
   * @param Zend_Db_Table_Row_Abstract $menuItem
   */
  public function remove($menuItem)
  {
    $row = $this->find($menuItem->id)->current();
    $row->delete();
  }	
}

