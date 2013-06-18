<?php
/**
 * Copyright 2012 Roger Castañeda
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 * @package acl
 * @subpackage models
 * @author roger castañeda <rogercastanedag@gmail.com>
 * @version 1
 */
class Acl_Model_DbTable_Role extends Zend_Db_Table_Abstract
{

    protected $_name = 'acl_role';
    protected $_dependentTables = array ( 'Acl_Model_DbTable_Permission', 'Acl_Model_DbTable_Account' );
    
    function __construct ()
    {
        $this->_name = Zend_Registry::get('tablePrefix') . $this->_name;
        parent::__construct();
    }
    
    /**
     * Returns a recordset
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function getList() {
        $prefix = Zend_Registry::get('tablePrefix');
        /* @var $cache Zend_Cache_Core|Zend_Cache_Frontend */
//         $cache = Zend_Registry::get('cache');
//         $cacheId = 'role_getList';
//         $rows = array();
//         if ( $cache->test($cacheId) ) {
//             $row = $cache->load($cacheId);
//         } else {
            $select = $this->select()
                ->setIntegrityCheck(false)
                ->from( array('r'=>$this->_name), array('id','name','layout_id') )
                ->joinInner( array('l'=>$prefix.'layout'), 'l.id=r.layout_id', 'name AS layout_name')
            ;
            //Zend_Debug::dump($select->__toString());
            $rows = $this->fetchAll($select);
//             $cache->save($rows, $cacheId); 
//         }
        return $rows;
    }
    
    public function find($id)
    {
        $row = null;
        /* @var $cache Zend_Cache_Core|Zend_Cache_Frontend */
        $cache = Zend_Registry::get('cache');
        $cacheId = 'role_find_'.$id;
        if ( $cache->test($cacheId) ) {
            $row = $cache->load($cacheId);
        } else {
            $row = parent::find($id)->current();
            $cache->save($row, $cacheId);
        }
        return $row;
    }

    public function remove(Acl_Model_Role $role)
    {
        $select = $this->select()
            ->where('id=?',$role->getId(), Zend_Db::INT_TYPE)
            ->limit(1)
        ;
        $row = $this->fetchRow($select);
        $row->delete();
    }
}
