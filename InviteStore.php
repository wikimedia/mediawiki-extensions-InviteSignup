<?php
/**
 * Storage abstraction for invites.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Niklas Laxström
 * @copyright Copyright © 2013 Lost in Translations Inc.
 */

/**
 * InviteStore which uses database as storage.
 */
class InviteStore {
	protected $db;
	protected $dbTable;

	public function __construct( DatabaseBase $db, $table ) {
		$this->db = $db;
		$this->dbTable = $table;
	}

	public function getInvites() {
		$fields = array( '*' );
		$conds = array();
		$res = $this->db->select( $this->dbTable, $fields, $conds, __METHOD__ );
		$invites = array();
		foreach ( $res as $row ) {
			$invites[] = $this->rowToArray( $row );
		}
		return $invites;
	}

	public function addInvite( User $inviter, $email, $groups ) {
		global $wgSecretKey;
		$hash = sha1( $inviter->getId() . $wgSecretKey . $email . wfTimestamp( TS_UNIX ) );

		$data = array(
			'is_inviter' => $inviter->getId(),
			'is_email' => $email,
			'is_when' => wfTimestamp( TS_UNIX ),
			'is_hash' => $hash,
			'is_groups' => serialize( $groups ),
		);

		$this->db->insert( $this->dbTable, $data, __METHOD__ );

		return $hash;
	}


	public function deleteInvite( $hash ) {
		$conds = array( 'is_hash' => $hash );
		$this->db->delete( $this->dbTable, $conds, __METHOD__ );
	}

	public function getInvite( $hash ) {
		$fields = array( '*' );
		$conds = array( 'is_hash' => $hash );
		$res = $this->db->selectRow( $this->dbTable, $fields, $conds, __METHOD__ );
		return $this->rowToArray( $res );
	}

	public function addSignupDate( User $user, $hash ) {
		$conds = array( 'is_hash' => $hash );
		$data = array(
			'is_used' => wfTimestamp( TS_UNIX ),
			'is_invitee' => $user->getId(),
		);
		$this->db->update( $this->dbTable, $data, $conds, __METHOD__ );
	}

	protected function rowToArray( $row ) {
		$array = array();
		if ( $row === false ) {
			return null;
		}

		foreach ( $row as $key => $value ) {
			if ( $key === 'is_groups' ) {
				$value = unserialize( $value );
			}
			$array[substr( $key, 3 )] = $value;
		}
		return $array;
	}
}
