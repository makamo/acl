<?php
/**
 * AclBehaviorTest file
 *
 * Test the Acl Behavior
 *
 * PHP 5
 *
 * CakePHP : Rapid Development Framework (http://cakephp.org)
 * Copyright 2006-2010, Cake Software Foundation, Inc.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2010, Cake Software Foundation, Inc.
 * @link          http://cakephp.org CakePHP Project
 * @package       cake.libs.tests.model.behaviors.acl
 * @since         CakePHP v 1.2.0.4487
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('AclBehavior', 'Model/Behavior');
App::uses('Aco', 'Model');
App::uses('Aro', 'Model');
App::uses('AclNode', 'Model');
App::uses('DbAcl', 'Model');


/**
* Test Person class - self joined model
*
* @package       cake
* @package       cake.tests.cases.libs.model.behaviors
*/
class AclPerson extends CakeTestModel {

/**
 * name property
 *
 * @var string
 * @access public
 */
	public $name = 'AclPerson';

/**
 * useTable property
 *
 * @var string
 * @access public
 */
	public $useTable = 'people';

/**
 * actsAs property
 *
 * @var array
 * @access public
 */
	public $actsAs = array('Acl' => 'requester');

/**
 * belongsTo property
 *
 * @var array
 * @access public
 */
	public $belongsTo = array(
		'Mother' => array(
			'className' => 'AclPerson',
			'foreignKey' => 'mother_id',
		)
	);

/**
 * hasMany property
 *
 * @var array
 * @access public
 */
	public $hasMany = array(
		'Child' => array(
			'className' => 'AclPerson',
			'foreignKey' => 'mother_id'
		)
	);

/**
 * parentNode method
 *
 * @return void
 */
	public function parentNode() {
		if (!$this->id && empty($this->data)) {
			return null;
		}
		if (isset($this->data['AclPerson']['mother_id'])) {
			$motherId = $this->data['AclPerson']['mother_id'];
		} else {
			$motherId = $this->field('mother_id');
		}
		if (!$motherId) {
			return null;
		} else {
			return array('AclPerson' => array('id' => $motherId));
		}
	}
}

/**
* AclUser class
*
* @package       cake
* @package       cake.tests.cases.libs.model.behaviors
*/
class AclUser extends CakeTestModel {

/**
 * name property
 *
 * @var string
 * @access public
 */
	public $name = 'User';

/**
 * useTable property
 *
 * @var string
 * @access public
 */
	public $useTable = 'users';

/**
 * actsAs property
 *
 * @var array
 * @access public
 */
	public $actsAs = array('Acl');

/**
 * parentNode
 *
 */
	public function parentNode() {
		return null;
	}
}

/**
* AclPost class
*
* @package       cake
* @package       cake.tests.cases.libs.model.behaviors
*/
class AclPost extends CakeTestModel {

/**
 * name property
 *
 * @var string
 * @access public
 */
	public $name = 'Post';

/**
 * useTable property
 *
 * @var string
 * @access public
 */
	public $useTable = 'posts';

/**
 * actsAs property
 *
 * @var array
 * @access public
 */
	var $actsAs = array('Acl' => 'Controlled');

/**
 * parentNode
 *
 */
	public function parentNode() {
		return null;
	}
}

/**
* AclBehaviorTest class
*
* @package       cake
* @package       cake.tests.cases.libs.controller.components
*/
class AclBehaviorTest extends CakeTestCase {

/**
 * Aco property
 *
 * @var Aco
 * @access public
 */
	public $Aco;

/**
 * Aro property
 *
 * @var Aro
 * @access public
 */
	public $Aro;

/**
 * fixtures property
 *
 * @var array
 * @access public
 */
	public $fixtures = array('core.person', 'core.user', 'core.post', 'core.aco', 'core.aro', 'core.aros_aco');

/**
 * Set up the test
 *
 * @return void
 */
	public function setUp() {
		Configure::write('Acl.database', 'test');

		$this->Aco = new Aco();
		$this->Aro = new Aro();
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		ClassRegistry::flush();
		unset($this->Aro, $this->Aco);
	}

/**
 * Test Setup of AclBehavior
 *
 * @return void
 */
	public function testSetup() {
		$User = new AclUser();
		$this->assertTrue(isset($User->Behaviors->Acl->settings['User']));
		$this->assertEqual($User->Behaviors->Acl->settings['User']['type'], 'requester');
		$this->assertTrue(is_object($User->Aro));

		$Post = new AclPost();
		$this->assertTrue(isset($Post->Behaviors->Acl->settings['Post']));
		$this->assertEqual($Post->Behaviors->Acl->settings['Post']['type'], 'controlled');
		$this->assertTrue(is_object($Post->Aco));
	}

/**
 * test After Save
 *
 * @return void
 */
	public function testAfterSave() {
		$Post = new AclPost();
		$data = array(
			'Post' => array(
				'author_id' => 1,
				'title' => 'Acl Post',
				'body' => 'post body',
				'published' => 1
			),
		);
		$Post->save($data);
		$result = $this->Aco->find('first', array(
			'conditions' => array('Aco.model' => 'Post', 'Aco.foreign_key' => $Post->id)
		));
		$this->assertTrue(is_array($result));
		$this->assertEqual($result['Aco']['model'], 'Post');
		$this->assertEqual($result['Aco']['foreign_key'], $Post->id);

		$aroData = array(
			'Aro' => array(
				'model' => 'AclPerson',
				'foreign_key' => 2,
				'parent_id' => null
			)
		);
		$this->Aro->save($aroData);

		$Person = new AclPerson();
		$data = array(
			'AclPerson' => array(
				'name' => 'Trent',
				'mother_id' => 2,
				'father_id' => 3,
			),
		);
		$Person->save($data);
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => $Person->id)
		));
		$this->assertTrue(is_array($result));
		$this->assertEqual($result['Aro']['parent_id'], 5);

		$node = $Person->node(array('model' => 'AclPerson', 'foreign_key' => 8));
		$this->assertEqual(count($node), 2);
		$this->assertEqual($node[0]['Aro']['parent_id'], 5);
		$this->assertEqual($node[1]['Aro']['parent_id'], null);

		$aroData = array(
			'Aro' => array(
			'model' => 'AclPerson',
				'foreign_key' => 1,
				'parent_id' => null
			)
		);
		$this->Aro->create();
		$this->Aro->save($aroData);
 
		$Person->read(null, 8);
		$Person->set('mother_id', 1);
		$Person->save();
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => $Person->id)
		));
		 $this->assertTrue(is_array($result));
		 $this->assertEqual($result['Aro']['parent_id'], 7);
 
		$node = $Person->node(array('model' => 'AclPerson', 'foreign_key' => 8));
		$this->assertEqual(sizeof($node), 2);
		$this->assertEqual($node[0]['Aro']['parent_id'], 7);
		$this->assertEqual($node[1]['Aro']['parent_id'], null);
	}

/**
 * test that an afterSave on an update does not cause parent_id to become null.
 *
 * @return void
 */
	function testAfterSaveUpdateParentIdNotNull() {
		$aroData = array(
			'Aro' => array(
				'model' => 'AclPerson',
				'foreign_key' => 2,
				'parent_id' => null
			)
		);
		$this->Aro->save($aroData);

		$Person = new AclPerson();
		$data = array(
			'AclPerson' => array(
				'name' => 'Trent',
				'mother_id' => 2,
				'father_id' => 3,
			),
		);
		$Person->save($data);
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => $Person->id)
		));
		$this->assertTrue(is_array($result));
		$this->assertEqual($result['Aro']['parent_id'], 5);

		$Person->save(array('id' => $Person->id, 'name' => 'Bruce'));
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => $Person->id)
		));
		$this->assertEqual($result['Aro']['parent_id'], 5);
	}

/**
 * Test After Delete
 *
 * @return void
 */
	public function testAfterDelete() {
		$aroData = array(
			'Aro' => array(
				'model' => 'AclPerson',
				'foreign_key' => 2,
				'parent_id' => null
			)
		);
		$this->Aro->save($aroData);
		$Person = new AclPerson();
		$data = array(
			'AclPerson' => array(
				'name' => 'Trent',
				'mother_id' => 2,
				'father_id' => 3,
			),
		);
		$Person->save($data);
		$id = $Person->id;
		$node = $Person->node();
		$this->assertEqual(count($node), 2);
		$this->assertEqual($node[0]['Aro']['parent_id'], 5);
		$this->assertEqual($node[1]['Aro']['parent_id'], null);

		$Person->delete($id);
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => $id)
		));
		$this->assertTrue(empty($result));
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => 2)
		));
		$this->assertFalse(empty($result));

		$data = array(
			'AclPerson' => array(
				'name' => 'Trent',
				'mother_id' => 2,
				'father_id' => 3,
			),
		);
		$Person->save($data);
		$id = $Person->id;
		$Person->delete(2);
		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => $id)
		));
		$this->assertTrue(empty($result));

		$result = $this->Aro->find('first', array(
			'conditions' => array('Aro.model' => 'AclPerson', 'Aro.foreign_key' => 2)
		));
		$this->assertTrue(empty($result));
	}

/**
 * Test Node()
 *
 * @return void
 */
	public function testNode() {
		$Person = new AclPerson();
		$aroData = array(
			'Aro' => array(
				'model' => 'AclPerson',
				'foreign_key' => 2,
				'parent_id' => null
			)
		);
		$this->Aro->save($aroData);

		$Person->id = 2;
		$result = $Person->node();
		$this->assertTrue(is_array($result));
		$this->assertEqual(count($result), 1);
	}
}